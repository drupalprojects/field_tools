<?php

namespace Drupal\field_tools\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_tools\FieldCloner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to clone multiple fields from an entity bundle.
 */
class FieldToolsBulkCloneForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The query factory to create entity queries.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * The field cloner.
   *
   * @var \Drupal\field_tools\FieldCloner
   */
  protected $fieldCloner;

  /**
   * Creates a Clone instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The entity query factory.
   * @param \Drupal\field_tools\FieldCloner $field_cloner
   *   The field cloner.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info, QueryFactory $query_factory, FieldCloner $field_cloner) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->queryFactory = $query_factory;
    $this->fieldCloner = $field_cloner;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity.query'),
      $container->get('field_tools.field_cloner')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_tools_field_clone_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $bundle = NULL) {
    $field_ids = $this->queryFactory->get('field_config')
      ->condition('entity_type', $entity_type_id)
      ->condition('bundle', $bundle)
      ->execute();
    $current_bundle_fields = $this->entityTypeManager->getStorage('field_config')->loadMultiple($field_ids);

    $field_options = array();
    foreach ($current_bundle_fields as $field_id => $field) {
      $field_options[$field_id] = t("@field-label (machine name: @field-name)", array(
        '@field-label' => $field->getLabel(),
        '@field-name' => $field->getName(),
      ));
    }
    asort($field_options);

    $form['fields'] = array(
      '#title' => t('Fields to clone'),
      '#type' => 'checkboxes',
      '#options' => $field_options,
      '#description' => t("Select fields to clone onto one or more bundles."),
    );

    // TODO: currently only support cloning to the current entity type.
    $current_entity_type_bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);

    $destination_bundle_options = [];
    foreach ($current_entity_type_bundles as $bundle_id => $bundle_info) {
      if ($bundle_id == $bundle) {
        continue;
      }

      $destination_bundle_options[$bundle_id] = $bundle_info['label'];
    }
    natcasesort($destination_bundle_options);

    $form['destination_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => t("Bundles to clone the fields to"),
      '#options' => $destination_bundle_options,
    ];

    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Clone fields'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the original parameters given to buildForm().
    // TODO: is this the right way to do this?
    $build_info = $form_state->getBuildInfo();
    list($entity_type_id, $bundle) = $build_info['args'];

    $destination_bundles = array_filter($form_state->getValue('destination_bundles'));
    $fields_to_clone = array_filter($form_state->getValue('fields'));

    foreach ($fields_to_clone as $field_id) {
      foreach ($destination_bundles as $destination_bundle) {
        $field_config = $this->entityTypeManager->getStorage('field_config')->load($field_id);

        // Check the field is not already on the bundle.
        $field_ids = $this->queryFactory->get('field_config')
          ->condition('entity_type', $entity_type_id)
          ->condition('bundle', $destination_bundle)
          ->condition('field_name', $field_config->getName())
          ->execute();

        if ($field_ids) {
          drupal_set_message(t("Field @name is already on bundle @bundle, skipping.", [
            '@name' => $field_config->getName(),
            // TODO: use label!
            '@bundle' => $destination_bundle,
          ]));

          continue;
        }

        $this->fieldCloner->cloneField($field_config, $entity_type_id, $destination_bundle);
      }
    }

    drupal_set_message(t("The fields have been cloned."));
  }

}
