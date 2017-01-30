<?php

namespace Drupal\field_tools\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Delete form for field storage config entities.
 */
class FieldStorageConfigDeleteForm extends EntityDeleteForm {

  // TODO: add extra warnings about how this will delete MULTIPLE FIELDS!

}
