<?php

namespace Drupal\field_tools;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\field\FieldConfigInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Contains methods for cloning fields.
 */
class FieldCloner {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity query service.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * Constructs a new FieldCloner.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueryFactory $entity_query) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queryFactory = $entity_query;
  }

  /**
   * Clone a field to a new entity type and bundle.
   *
   * It is assumed that the caller has already checked this is possible!
   *
   * TODO: handle new entity type
   * TODO: add the field to form and display modes
   *
   * @param \Drupal\field\FieldConfigInterface $field_config
   *  The field config entity to clone.
   * @param string $destination_entity_type_id
   *  The entity type to clone the field to. TODO this parameter does not yet do
   *  anything!
   * @param string $destination_bundle
   *  The destination bundle.
   */
  public function cloneField(FieldConfigInterface $field_config, $destination_entity_type_id, $destination_bundle) {
    // Create and save the duplicate field.
    $new_field_config = $field_config->createDuplicate();
    $new_field_config->set('bundle', $destination_bundle);
    $new_field_config->save();

    // Get the entity type and bundle of the original field.
    $field_config_target_entity_type_id = $field_config->getTargetEntityTypeId();
    $field_config_target_bundle = $field_config->getTargetBundle();

    // Copy the field's display settings to the destination bundle's displays,
    // where possible.
    $this->copyDisplayComponents('entity_form_display', $field_config, $destination_entity_type_id, $destination_bundle);
    $this->copyDisplayComponents('entity_view_display', $field_config, $destination_entity_type_id, $destination_bundle);
  }

  /**
   * Copy the field's display settings to the destination bundle's displays.
   *
   * This finds displays with the same name and copies the original field's
   * settings to them. So for example, if the source bundle has a 'teaser' view
   * mode and so does the destination bundle, the settings will be copied from
   * one to the other.
   *
   * @param string $display_type
   *  The entity type ID of the display entities to copy: one of
   *  'entity_view_display' or entity_form_display'.
   * @param \Drupal\field\FieldConfigInterface $field_config
   *  The field config entity to clone.
   * @param string $destination_entity_type_id
   *  The entity type to clone the field to. TODO this parameter does not yet do
   *  anything!
   * @param string $destination_bundle
   *  The destination bundle.
   */
  protected function copyDisplayComponents($display_type, FieldConfigInterface $field_config, $destination_entity_type_id, $destination_bundle) {
    $field_name = $field_config->getName();
    $field_config_target_entity_type_id = $field_config->getTargetEntityTypeId();
    $field_config_target_bundle = $field_config->getTargetBundle();

    // Get the view displays on the source entity bundle.
    $display_ids = $this->queryFactory->get($display_type)
      ->condition('targetEntityType', $field_config_target_entity_type_id)
      ->condition('bundle', $field_config_target_bundle)
      ->execute();
    $original_field_bundle_displays = $this->entityTypeManager->getStorage($display_type)->loadMultiple($display_ids);

    // Get the views displays on the duplicate's target entity bundle.
    $display_ids = $this->queryFactory->get($display_type)
      ->condition('targetEntityType', $field_config_target_entity_type_id)
      ->condition('bundle', $destination_bundle)
      ->execute();
    $displays = $this->entityTypeManager->getStorage($display_type)->loadMultiple($display_ids);
    // Re-key this array by the mode name.
    $duplicate_field_bundle_displays = [];
    foreach ($displays as $display) {
      $duplicate_field_bundle_displays[$display->getMode()] = $display;
    }

    // Work over the original field's view displays.
    foreach ($original_field_bundle_displays as $display) {
      // If the destination bundle doesn't have a display of the same name,
      // skip this.
      if (!isset($duplicate_field_bundle_displays[$display->getMode()])) {
        continue;
      }

      $destination_display = $duplicate_field_bundle_displays[$display->getMode()];

      // Get the settings for the field in this display.
      $field_component = $display->getComponent($field_name);

      // Copy the settings to the duplicate field's view mode with the same
      // name.
      if (is_null($field_component)) {
        // Explicitly hide the field, so it's set in the display.
        $destination_display->removeComponent($field_name);
      }
      else {
        $destination_display->setComponent($field_name, $field_component);
      }

      $destination_display->save();
    }
  }

}
