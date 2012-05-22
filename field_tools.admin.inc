<?php
/**
 * @file
 * Contains admin callbacks for the Field tools module.
 */

/**
 * Form builder for the mass clone page.
 *
 * @param $entity_type
 *  The machine name of the entity.
 * @param $bundle_name
 *  The machine name of the bundle, or a bundle object if the particular
 *  entity type has a menu loader for bundles.
 */
function field_tools_bundle_fields_clone_form($form, &$form_state, $entity_type, $bundle_name) {
  // Get the bundle name if the bundle name is really a bundle object.
  $bundle_name = field_extract_bundle($entity_type, $bundle_name);

  $field_instances = field_info_instances($entity_type, $bundle_name);
  //dsm($field_instances);

  $options_fields = array();
  foreach ($field_instances as $field_name => $field) {
    $options_fields[$field_name] = $field['label'];
  }

  $form['fields'] = array(
    '#title' => t('Fields to clone'),
    '#type' => 'checkboxes',
    '#options' => $options_fields,
    '#description' => t("Select fields to clone onto one or more bundles."),
  );

  $form['bundles'] = array(
    '#title' => t('Bundle(s) to clone onto'),
    '#type' => 'checkboxes',
    '#options' => field_tools_options_entity_bundle(),
    //'#default_value' => $default_bundles,
    '#description' => t("Select bundles on which to apply the selected fields."),
  );

  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Add field instances'),
  );

  return $form;
}

/**
 * Submit handler for the mass clone form.
 */
function field_tools_bundle_fields_clone_form_submit($form, &$form_state) {
  // Get details from the original form builder parameters.
  list($source_entity_type, $source_bundle_name) = $form_state['build_info']['args'];

  // Get names of fields to clone.
  $field_names = array_filter($form_state['values']['fields']);

  foreach ($field_names as $field_name) {
    $field = field_info_field($field_name);
    $instance = field_info_instance($source_entity_type, $field_name, $source_bundle_name);

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
}

/**
 * Form builder for the field clone page.
 *
 * @param $instance
 *  A FieldAPI field instance definition array.
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

/**
 * Helper to get FormAPI options for entity bundles.
 *
 * @return
 *  An array for FormAPI '#options' properties, with:
 *    - keys of the form ENTITY:BUNDLE, using machine names.
 *    - values of the form ENTITY: BUNDLE, using labels.
 */
function field_tools_options_entity_bundle() {
  $options = array();
  foreach (entity_get_info() as $entity_type => $entity_info) {
    foreach ($entity_info['bundles'] as $bundle_type => $bundle_info) {
      $options[$entity_type . ':' . $bundle_type] = $entity_info['label'] . ': ' . $bundle_info['label'];
    }
  }
  return $options;
}

