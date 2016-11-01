<?php

namespace Drupal\field_tools;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Entity\EntityDisplayBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;

/**
 * Contains methods for cloning displays.
 */
class DisplayCloner {

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
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a new FieldCloner.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, QueryFactory $entity_query, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->queryFactory = $entity_query;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Clone or merge a form or view display to a new bundle.
   *
   * Display settings are copied to the new bundle.
   *  - Fields which are present on the source but not the target are ignored.
   * If the target already has this display:
   *  - Fields which are on the both source and target have their settings
   *    overwritten.
   *  - Fields which are on the target only but not the source have their
   *    settings left untouched.
   *
   * @param \Drupal\Core\Entity\EntityDisplayBase $source_entity_display
   *  The entity display (form or view) to clone.
   * @param string $destination_bundle
   *  The destination bundle.
   */
  public function cloneDisplay(EntityDisplayBase $source_entity_display, $destination_bundle) {
    $display_entity_type = $source_entity_display->getEntityTypeId();

    // Have to deduce the context from the entity type, as there's no accessor
    // for $displayContext: see https://www.drupal.org/node/2823807.
    if ($display_entity_type == 'entity_form_display') {
      $context = 'form';
    }
    else {
      $context = 'view';
    }

    $target_entity_type_id = $source_entity_display->getTargetEntityTypeId();
    $source_bundle = $source_entity_display->getTargetBundle();
    $mode_name = $source_entity_display->getMode();

    // Try to load the destination display.
    $destination_display = $this->entityTypeManager->getStorage($display_entity_type)->load($target_entity_type_id . '.' . $destination_bundle . '.' . $mode_name);

    if (empty($destination_display)) {
      // TODO: create it!
      throw new \Exception("The destination display doesn't exist yet -- this is not yet supported.");
    }

    // Get all fields on destination bundle
    $field_ids = $this->queryFactory->get('field_config')
      ->condition('entity_type', $target_entity_type_id)
      ->condition('bundle', $destination_bundle)
      ->execute();
    $destination_bundle_fields = $this->entityTypeManager->getStorage('field_config')->loadMultiple($field_ids);
    //dsm($destination_bundle_fields);
    $destination_bundle_field_names = [];
    foreach ($destination_bundle_fields as $field) {
      $destination_bundle_field_names[$field->getName()] = $field->id();
    }

    // Get the display components from the source display, and copy them to the
    // destination.
    // This only returns visible fields.
    $components = $source_entity_display->getComponents();
    foreach ($components as $field_name => $source_display_field_component) {
      // Skip fields that do not exist on the destination bundle.
      if (!isset($destination_bundle_field_names[$field_name])) {
        continue;
      }

      $destination_display->setComponent($field_name, $source_display_field_component);
    }

    // Copy field groups.
    if ($this->moduleHandler->moduleExists('field_group')){
      $field_group_settings = $source_entity_display->getThirdPartySettings('field_group');
      foreach ($field_group_settings as $group_id => $group_settings) {
        // Remove any fields in groups which do not exist on the destination
        // bundle.
        foreach ($group_settings['children'] as $index => $child_field) {
          if (!isset($destination_bundle_field_names[$child_field])) {
            unset($group_settings['children'][$index]);
          }
        }

        // Skip groups which don't have any fields left in them.
        if (empty($group_settings['children'])) {
          continue;
        }

        // TODO: rekey the numeric 'children' array?

        // Set the group in the destination display.
        // (There's no setThirdPartySetting*s*() method...)
        $destination_display->setThirdPartySetting('field_group', $group_id, $group_settings);
      }
    }

    // Save the display.
    $destination_display->save();
  }

}
