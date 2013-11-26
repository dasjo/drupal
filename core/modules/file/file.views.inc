<?php

/**
 * @file
 * Provide views data for file.module.
 */

use Drupal\Core\Entity\FieldableDatabaseStorageController;
use Drupal\field\FieldInterface;

/**
 * Implements hook_views_data().
 */
function file_views_data() {
  $data = array();
  return $data;
}

/**
 * Implements hook_field_views_data().
 *
 * Views integration for file fields. Adds a file relationship to the default
 * field data.
 *
 * @see field_views_field_default_views_data()
 */
function file_field_views_data(FieldInterface $field) {
  $data = field_views_field_default_views_data($field);
  foreach ($data as $table_name => $table_data) {
    // Add the relationship only on the fid field.
    $data[$table_name][$field->getFieldName() . '_target_id']['relationship'] = array(
      'id' => 'standard',
      'base' => 'file_managed',
      'entity type' => 'file',
      'base field' => 'target_id',
      'label' => t('file from !field_name', array('!field_name' => $field->getFieldName())),
    );
  }

  return $data;
}

/**
 * Implements hook_field_views_data_views_data_alter().
 *
 * Views integration to provide reverse relationships on file fields.
 */
function file_field_views_data_views_data_alter(array &$data, FieldInterface $field) {
  $entity_type = $field->entity_type;
  $entity_info = entity_get_info($entity_type);
  $field_name = $field->getFieldName();
  $pseudo_field_name = 'reverse_' . $field_name . '_' . $entity_type;

  list($label) = field_views_field_label($entity_type, $field_name);

  $data['file_managed'][$pseudo_field_name]['relationship'] = array(
    'title' => t('@entity using @field', array('@entity' => $entity_info['label'], '@field' => $label)),
    'help' => t('Relate each @entity with a @field set to the file.', array('@entity' => $entity_info['label'], '@field' => $label)),
    'id' => 'entity_reverse',
    'field_name' => $field_name,
    'entity_type' => $entity_type,
    'field table' => FieldableDatabaseStorageController::_fieldTableName($field),
    'field field' => $field_name . '_target_id',
    'base' => $entity_info['base_table'],
    'base field' => $entity_info['entity_keys']['id'],
    'label' => t('!field_name', array('!field_name' => $field_name)),
    'join_extra' => array(
      0 => array(
        'field' => 'deleted',
        'value' => 0,
        'numeric' => TRUE,
      ),
    ),
  );
}
