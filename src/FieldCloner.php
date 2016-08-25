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
  protected $entityQuery;

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
    $this->entityQuery = $entity_query;
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
    $new_field_config = $field_config->createDuplicate();
    $new_field_config->set('bundle', $destination_bundle);
    $new_field_config->save();
  }

}
