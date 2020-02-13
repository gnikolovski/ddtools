<?php

namespace Drupal\ddtools\Form;

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EntityFieldsForm.
 */
class EntityFieldsForm extends FormBase {

  /**
   * The Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityTypeBundleInfo = $container->get('entity_type.bundle.info');
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_fields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $entity_bundle = $form_state->getValue('entity_bundle');

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getEntityTypes(),
      '#default_value' => $entity_type ? $entity_type : NULL,
      '#ajax' => [
        'callback' => [$this, 'getEntityBundles'],
        'event' => 'change',
        'wrapper' => 'entity-bundle-wrapper',
      ],
    ];

    $form['entity_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity bundle'),
      '#options' => $entity_type ? $this->getEntityTypeBundles($entity_type) : [],
      '#default_value' => $entity_bundle ? $entity_bundle : NULL,
      '#prefix' => '<div id="entity-bundle-wrapper">',
      '#suffix' => '</div>',
    ];

    $form['field_cardinality'] = [
      '#type' => 'select',
      '#title' => $this->t('Field cardinality'),
      '#options' => [
        '0' => $this->t('All'),
        '-1' => $this->t('Unlimited'),
        '1' => $this->t('Single'),
      ],
      '#default_value' => '0',
    ];

    $form['kint_max_levels'] = [
      '#type' => 'select',
      '#title' => $this->t('Kint max levels'),
      '#options' => [],
      '#default_value' => 3,
      '#required' => TRUE,
    ];
    for ($i = 1; $i < 11; $i++) {
      $form['kint_max_levels']['#options'][$i] = $i;
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Get fields'),
    ];

    return $form;
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The form element with options.
   */
  public function getEntityBundles(&$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $form['entity_bundle']['#options'] = $this->getEntityTypeBundles($entity_type);
    return $form['entity_bundle'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity_type = $form_state->getValue('entity_type');
    $entity_bundle = $form_state->getValue('entity_bundle');
    $field_cardinality = $form_state->getValue('field_cardinality');
    $kint_max_levels = $form_state->getValue('kint_max_levels');

    kint_require();
    \Kint::$maxLevels = $kint_max_levels;
    $field_definitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $entity_bundle);
    $filtered_field_definitions = array_filter($field_definitions, function($item) use($field_cardinality) {
      if ($field_cardinality == 0) {
        return TRUE;
      }
      return $item->getCardinality() == $field_cardinality;
    });
    dsm($filtered_field_definitions);

    $form_state->setRebuild();
    $this->messenger->addMessage($this->t('Entity type: @entity_type, bundle: @entity_bundle', [
      '@entity_type' => $entity_type,
      '@entity_bundle' => $entity_bundle,
    ]));
  }

  /**
   * Gets all entity types.
   *
   * @return array
   *   The list of all entity types.
   */
  protected function getEntityTypes() {
    $entity_types = ['_none' => $this->t('- Select -')];

    $entity_type_definitions = $this->entityTypeManager
      ->getDefinitions();

    foreach ($entity_type_definitions as $entity_type_name => $entity_type_definition) {
      if ($entity_type_definition instanceof ContentEntityType) {
        $entity_types[$entity_type_name] = $entity_type_definition->getLabel();
      }
    }

    return $entity_types;
  }

  /**
   * Gets all entity type bundles.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @return array
   *   The list of all entity type bundles.
   */
  protected function getEntityTypeBundles($entity_type) {
    $entity_type_bundles = [];

    $entity_type_bundle_info = $this->entityTypeBundleInfo
      ->getBundleInfo($entity_type);

    foreach ($entity_type_bundle_info as $entity_bundle_name => $entity_bundle_label) {
      $entity_type_bundles[$entity_bundle_name] = $entity_bundle_label['label'];
    }

    return $entity_type_bundles;
  }

}
