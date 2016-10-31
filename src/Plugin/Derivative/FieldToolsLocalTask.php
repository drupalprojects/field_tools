<?php

namespace Drupal\field_tools\Plugin\Derivative;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class FieldToolsLocalTask extends DeriverBase implements ContainerDeriverInterface {
  use StringTranslationTrait;

  /**
   * The route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * The entity manager
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * Creates an FieldToolsLocalTask object.
   *
   * @param \Drupal\Core\Routing\RouteProviderInterface $route_provider
   *   The route provider.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(RouteProviderInterface $route_provider, EntityManagerInterface $entity_manager, TranslationInterface $string_translation) {
    $this->routeProvider = $route_provider;
    $this->entityManager = $entity_manager;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('router.route_provider'),
      $container->get('entity.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = array();

    foreach ($this->entityManager->getDefinitions() as $entity_type_id => $entity_type) {
      if ($entity_type->get('field_ui_base_route')) {
        // 'Bulk clone' tab.
        $this->derivatives["field_tools_bulk_clone_$entity_type_id"] = array(
          'route_name' => "field_tools.field_bulk_clone_$entity_type_id",
          'weight' => 4,
          'title' => $this->t('Clone fields'),
          'base_route' => $entity_type->get('field_ui_base_route'),
        );
      }
    }

    foreach ($this->derivatives as &$entry) {
      $entry += $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
