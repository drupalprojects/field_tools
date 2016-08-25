<?php

namespace Drupal\field_tools\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field_tools\FieldCloner;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for cloning a field.
 *
 * TODO: allow this to clone to other entity types.
 */
class FieldConfigCloneForm extends EntityForm {

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
  public function form(array $form, FormStateInterface $form_state) {
    $field_config = $this->getEntity();

    $field_config_target_entity_type_id = $field_config->getTargetEntityTypeId();
    $field_config_target_bundle = $field_config->getTargetBundle();

    $form['#title'] = t("Clone field %field", [
      '%field' => $field_config->getLabel(),
    ]);

    $current_entity_type_bundles = $this->entityTypeBundleInfo->getBundleInfo($field_config->getTargetEntityTypeId());

    $destination_bundle_options = [];
    foreach ($current_entity_type_bundles as $bundle_id => $bundle_info) {
      if ($bundle_id == $field_config_target_bundle) {
        continue;
      }

      $destination_bundle_options[$bundle_id] = $bundle_info['label'];
    }
    natcasesort($destination_bundle_options);

    $form['destination_bundles'] = [
      '#type' => 'checkboxes',
      '#title' => t("Facet source to clone this facet to"),
      '#options' => $destination_bundle_options,
    ];

    // Get all the fields with the same name on the same entity type, to mark
    // their checkboxes as disabled.
    $field_ids = $this->queryFactory->get('field_config')
      ->condition('entity_type', $field_config_target_entity_type_id)
      ->condition('field_name', $field_config->getName())
      ->execute();
    $other_bundle_fields = $this->entityTypeManager->getStorage('field_config')->loadMultiple($field_ids);

    $other_bundles = [];
    foreach ($other_bundle_fields as $field) {
      $form['destination_bundles'][$field->getTargetBundle()]['#disabled'] = TRUE;
      $form['destination_bundles'][$field->getTargetBundle()]['#description'] = t("The field is already on this bundle.");
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Clone field'),
    );
    return $actions;
  }

  /**
   * Form submission handler for the 'clone' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   A reference to a keyed array containing the current state of the form.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $destination_bundles = array_filter($form_state->getValue('destination_bundles'));

    foreach ($destination_bundles as $destination_bundle) {
      // TODO: $destination_entity_type doesn't do anything yet.
      $destination_entity_type = NULL;
      $this->fieldCloner->cloneField($this->entity, $destination_entity_type, $destination_bundle);
    }

    // TODO: confirmation message
    // TODO: redirect
  }

}
