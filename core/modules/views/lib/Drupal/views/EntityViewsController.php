<?php

/**
 * @file
 * Contains \Drupal\views\EntityViewsController.
 */

namespace Drupal\views;

use Drupal\Core\Entity\EntityControllerInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TypedData\TypedDataManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides generic views integration for entities.
 */
class EntityViewsController implements EntityControllerInterface {

  /**
   * Entity type for this views controller instance.
   *
   * @var string
   */
  protected $entityType;

  /**
   * Array of information about the entity type.
   *
   * @var array
   *
   * @see \Drupal\Core\Entity\EntityManager::getDefinition()
   */
  protected $entityInfo;

  /**
   * The storage controller used for this entity type.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $storageController;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The translation manager.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The typed data manager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * Constructs an EntityViewsController object.
   *
   * @param string $entity_type
   *   The entity type to provide views integration for.
   * @param array $entity_info
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface $storage_controller
   *   The storage controller used for this entity type.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation_manager
   *   The translation manager.
   */
  function __construct($entity_type, array $entity_info, EntityStorageControllerInterface $storage_controller, EntityManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, TranslationInterface $translation_manager, TypedDataManager $typed_data_manager) {
    $this->entityType = $entity_type;
    $this->entityInfo = $entity_info;
    $this->entityManager = $entity_manager;
    $this->storageController = $storage_controller;
    $this->moduleHandler = $module_handler;
    $this->translationManager = $translation_manager;
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, $entity_type, array $entity_info) {
    return new static(
      $entity_type,
      $entity_info,
      $container->get('entity.manager')->getStorageController($entity_type),
      $container->get('entity.manager'),
      $container->get('module_handler'),
      $container->get('string_translation'),
      $container->get('typed_data')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewsData() {
    $data = array();

    // @todo In theory we should use the data table as base table, as this would
    //   save one pointless join (and one more for every relationship).
    $base_table = $this->entityInfo['base_table'];
    $base_field = $this->entityInfo['entity_keys']['id'];
    $data_table = isset($this->entityInfo['data_table']) ? $this->entityInfo['data_table']: NULL;
    $revision_table = isset($this->entityInfo['revision_table']) ? $this->entityInfo['revision_table'] : NULL;
    $revision_data_table = isset($this->entityInfo['revision_data_table']) ? $this->entityInfo['revision_data_table'] : NULL;
    $revision_field = isset($this->entityInfo['entity_keys']['revision']) ? $this->entityInfo['entity_keys']['revision'] : NULL;

    // Setup base information of the views data.
    $data[$base_table]['table']['entity_type'] = $this->entityType;
    $data[$base_table]['table']['group'] = $this->entityInfo['label'];
    $data[$base_table]['table']['base'] = array(
      'field' => $base_field,
      'title' => $this->entityInfo['label'],
    );

    // Setup relations to the revisions/property data.
    if ($data_table) {
      $data[$data_table]['table']['join'][$base_table] = array(
        'left_field' => $base_field,
        'field' => $base_field
      );

    }
    if ($revision_table) {
      $data[$revision_table]['table']['entity_type'] = $this->entityType;
      $data[$revision_table]['table']['group'] = $this->entityInfo['label'];
      $data[$revision_table]['table']['base'] = array(
        'field' => $revision_field,
        'title' => $this->t('@entity_type revisions', array('@entity_type' => $this->entityInfo['label'])),
      );
      // Join the revision table to the base table.
      $data[$revision_table]['table']['join'][$base_table] = array(
        'left_field' => $base_field,
        'field' => $base_field,
      );

      if ($revision_data_table) {
        $data[$revision_data_table]['table']['join'][$revision_table] = array(
          'left_field' => $revision_field,
          'field' => $revision_field,
        );
      }
    }

    // Load all typed data definitions of all fields. This should cover each of
    // the entity base, revision, data tables.
    $field_definitions = $this->entityManager->getFieldDefinitions($this->entityType);

    // Iterate over each table we have so far and collect field data for each.
    // Based on whether the field is in the field_definitions provided by the
    // entity manager.
    // @todo We should better just rely on information coming from the entity
    //   storage controller.
    foreach (array_keys($data) as $table) {
      foreach ($this->drupalSchemaFieldsSql($table) as $field_name) {
        if (isset($field_definitions[$field_name])) {
          $views_field = &$data[$table][$field_name];
          // @todo Is translating the right way to handle label/description?
          //   This is/should be translated in the UI when it's displayed?
          $views_field['title'] = $field_definitions[$field_name]['label'];
          $views_field['help'] = $field_definitions[$field_name]['description'];
          // @todo Find a proper find the mappings between typed data and views
          //   handlers. Maybe the data types should define it with fallback to
          //   standard or views should have the same naming scheme.
          $views_field = $this->mapTypedDataToHandlerId($field_definitions[$field_name], $views_field);


        }
      }
    }

    return $data;
  }

  /**
   * Provides a mapping from typed data plugin types to view plugin types.
   *
   * @param array $typed_data
   *   The typed data plugin definition
   * @param array $views_field
   *   The views field data definition.
   *
   * @return array
   *   The modified views data field definition.
   */
  protected function mapTypedDataToHandlerId($typed_data, $views_field) {

    $instance = $this->typedDataManager->createInstance($typed_data['type'], $typed_data);
    if ($typed_data['list']) {
      $instance = $instance[0];
    }
    $propertyDefinitions = $instance->getPropertyDefinitions();
    $value_type = $propertyDefinitions['value']['type'];

    if (empty($value_type)) {
      $value_type = 'entity_reference';
    }

    switch ($value_type) {
      case 'integer':
        $views_field['field']['id'] = 'numeric';
        $views_field['argument']['id'] = 'numeric';
        $views_field['filter']['id'] = 'numeric';
        $views_field['sort']['id'] = 'standard';
        break;
      case 'string':
        $views_field['field']['id'] = 'standard';
        $views_field['argument']['id'] = 'string';
        $views_field['filter']['id'] = 'string';
        $views_field['sort']['id'] = 'standard';
        break;
      case 'language':
        $views_field['field']['id'] = 'language';
        $views_field['argument']['id'] = 'language';
        $views_field['filter']['id'] = 'language';
        $views_field['sort']['id'] = 'standard';
        break;
      case 'boolean':
        $views_field['field']['id'] = 'boolean';
        $views_field['argument']['id'] = 'numeric';
        $views_field['filter']['id'] = 'boolean';
        $views_field['sort']['id'] = 'standard';
        break;
      case 'uuid':
        $views_field['field']['id'] = 'standard';
        $views_field['argument']['id'] = 'string';
        $views_field['filter']['id'] = 'string';
        $views_field['sort']['id'] = 'standard';
        break;
      case 'entity_reference':
        // @todo No idea to determine how to find out whether the field is a number/string ID.
        // @todo Should the actual field handler respect that this is just renders a number
        // @todo Create an optional entity field handler, that can render the
        //   entity.
        $views_field['field']['id'] = 'numeric';
        $views_field['argument']['id'] = 'numeric';
        $views_field['filter']['id'] = 'numeric';
        $views_field['sort']['id'] = 'standard';

        $entity_type = $typed_data['settings']['target_type'];
        $entity_info = $this->entityManager->getDefinition($entity_type);
        $views_field['relationship'] = array(
          'base' => $entity_info['base_table'],
          'base field' => $entity_info['entity_keys']['id'],
          'label' => $typed_data['label'],
          'id' => 'standard',
        );
        // @todo use the module handler to provide a way to hook in for other
        //   modules. Therefore the mapping has to be static defined.
    }

    return $views_field;
  }

  /**
   * Translates a string to the current language or to a given language.
   *
   * See the t() documentation for details.
   */
  protected function t($string, array $args = array(), array $options = array()) {
    return $this->translationManager->translate($string, $args, $options);
  }

  /**
   * Wraps drupal_schema_fields_sql().
   *
   * @return array
   */
  protected function drupalSchemaFieldsSql($table) {
    return drupal_schema_fields_sql($table);
  }

}
