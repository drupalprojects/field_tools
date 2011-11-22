<?php
/**
 * @file
 * Contains admin callbacks for the Field tools module.
 */

/**
 * Form builder for the field clone page.
 */
function field_tools_field_clone_form($form, &$form_state, $instance) {
  //dsm($instance);
  $form['#instance'] = $instance;

  $field_name = $instance['field_name'];

  // TODO: is there a way to turn most of what follows into a form element?
  $field = field_info_field($field_name);
  $field_exists = isset($field);
  $field_type = field_info_field_types('taxonomy_term_reference');

  // Field settings fieldset.
  // @todo restore this when we add a field apply-type UI.
  /*
  $form['settings'] = array(
    '#type' => 'fieldset',
  );
  $form['settings']['multiple'] = array('#type' => 'checkbox',
    '#title' => t('Multiple select'),
    '#description' => t('Allows reference fields to hold more than one term from this vocabulary.'),
  );
  // Lock this if the field exists.
  if ($field_exists) {
    $form['settings']['multiple'] += array(
      '#disabled' => TRUE,
      '#default_value' => ($field['cardinality'] == 1 ? FALSE : TRUE),
    );
    $form['settings']['multiple']['#description'] .= ' ' . t('This setting may not be changed here because this field already has instances.');
  }

  $form['settings']['required'] = array('#type' => 'checkbox',
    '#title' => t('Required'),
    '#description' => t('At least one term in this vocabulary must be selected when submitting data with this field.'),
  );

  module_load_include('inc', 'field_ui', 'field_ui.admin');
  $widget_options = field_ui_widget_type_options($field['type']);
  $form['settings']['widget_type'] = array(
    '#type' => 'select',
    '#title' => t('Widget type'),
    '#required' => TRUE,
    '#options' => $widget_options,
    '#default_value' => $field_type['default_widget'],
    '#description' => t('The type of form element you would like to present to the user when creating this field in the types below.'),
  );
  */

  $options = array();
  foreach (entity_get_info() as $entity_type => $entity_info) {
    foreach ($entity_info['bundles'] as $bundle_type => $bundle_info) {
      $options[$entity_type . ':' . $bundle_type] = $entity_info['label'] . ': ' . $bundle_info['label'];
    }
  }
  //dsm($options);
  $default_bundles = array();
  if ($field_exists) {
    foreach ($field['bundles'] as $entity_type => $bundles) {
      foreach ($bundles as $bundle_type) {
        $default_bundles[] = $entity_type . ':' . $bundle_type;
      }
    }
  }
  $form['bundles'] = array(
    '#type' => 'checkboxes',
    '#options' => $options,
    '#default_value' => $default_bundles,
    '#description' => t("Select bundles on which to apply this vocabulary's term reference field."),
  );
  // Very neat but undocumented trick: see http://drupal.org/node/1349432
  foreach ($default_bundles as $option_key) {
    $form['bundles'][$option_key] = array(
      '#disabled' => TRUE,
    );
  }

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Add field instances'),
  );

  return $form;
}

/**
 * Submit handler for the field clone form.
 */
function field_tools_field_clone_form_submit($form, &$form_state) {
  $instance = $form['#instance'];
  $field_name = $instance['field_name'];
  $field = field_info_field($field_name);
  //dsm($field);
  
  foreach (array_filter($form_state['values']['bundles']) as $option_key) {
    list($entity_type, $bundle_type) = explode(':', $option_key);

    if (isset($field['bundles'][$entity_type]) && in_array($bundle_type, $field['bundles'][$entity_type])) {
      // Skip existing instances.
      continue;
    }

    // Copy the existing instance.
    $new_instance = array(
      'entity_type' => $entity_type,
      'bundle' => $bundle_type,
    ) + $instance;
    field_create_instance($new_instance);
  }
}
