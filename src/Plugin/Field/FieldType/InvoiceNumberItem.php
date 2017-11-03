<?php

namespace Drupal\invoice_field\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the 'invoice_number' field type.
 *
 * @FieldType(
 *   id = "invoice_number",
 *   label = @Translation("Invoice number"),
 *   description = @Translation("Field that increases by one the last item found in the same series"),
 *   default_widget = "invoice_number_default",
 *   default_formatter = "invoice_number_series",
 *   cardinality = 1
 * )
 */
class InvoiceNumberItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'series_suffix' => '-Y-\s\t\o\r\e',
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    // Prevent early t() calls by using the TranslatableMarkup.
    $properties['value'] = DataDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Invoice number'));
    $properties['autofill'] = DataDefinition::create('boolean')
      ->setLabel(new TranslatableMarkup('Automatic fill'));
    $properties['series_suffix'] = DataDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Serie suffix'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    $schema = [
      'columns' => [
        'value' => [
          'type' => 'int',
          'not null' => FALSE,
        ],
        'autofill' => [
          'type' => 'int',
          'default' => 0,
          'size' => 'tiny',
        ],
        'series_suffix' => [
          'type' => 'text',
          'size' => 'tiny',
        ],
      ],
    ];

    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];
    $elements['series_suffix'] = [
      '#type' => 'textfield',
      '#title' => t('Serie suffix'),
      '#required' => FALSE,
      '#description' => t('The series suffix lookup and constructor as date format. See the <a href="http://php.net/manual/function.date.php">PHP manual</a> for available options.'),
      '#default_value' => $this->getSetting('series_suffix'),
      '#attributes' => [
        'data-drupal-date-formatter' => 'source',
      ],
      '#field_suffix' => ' <small class="js-hide" data-drupal-date-formatter="preview">' . $this->t('Displayed as %date_format', ['%date_format' => '']) . '</small>',
    ];
    // @todo add javascript as Add date format configuration do in
    // core/modules/system/src/Form/DateFormatFormBase.php
    $form['#attached']['library'][] = 'system/drupal.system.date';

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $value = $this->get('value')->getValue();
    $autofill = $this->get('autofill')->getValue();
    // Check autofill also to be empty to trigger the autofill if marked.
    return $value === NULL || $value === '' && ($autofill === NULL || $autofill === 0);
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values['value'] = mt_rand(0, 1000);
    return $values;
  }

  /**
   * Discover the value for the series in settings.
   */
  public function generateValueSeries() {
    $bundle = $this->getEntity()->bundle();
    $entity_type_id = $this->getEntity()->getEntityTypeId();
    $field_name = $this->getFieldDefinition()->getName();
    $query = \Drupal::entityQuery($entity_type_id);
    $entity_keys = $this->getEntity()->getEntityType()->getKeys();
    $current_suffix = date($this->getSetting('series_suffix', REQUEST_TIME));
    // Autofill do not fill a same entity twice.
    $query->condition($field_name . ".series_suffix", $current_suffix)
      ->condition($entity_keys['bundle'], $bundle)
      ->condition($entity_keys['id'], $this->getEntity()->id(), "!=")
      ->sort($field_name . ".value", "DESC")
      ->range(0, 1);
    $ids = $query->execute();
    if (!empty($ids)) {
      $id = array_values($ids)[0];
      $last_series_entity = \Drupal::entityManager()->getStorage($entity_type_id)->load($id);
      // Want to check if order is payed? Not here, implement in
      // payment state event_subscriber to update this field whenever
      // is payed instead.
      $last_number = $last_series_entity->get($field_name)->first()->value;
      $this->value = $last_number ? $last_number + 1 : 1;
    }
    else {
      // No previous items in this series found.
      $this->value = 1;
    }
    $this->series_suffix = $current_suffix;
    // Now that logic is done mark autofill to zero to prevent increasing this value again;
    $this->autofill = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    parent::preSave();
    // @todo test when:
    //   - cardinality != 1
    //   - autofill = 1 && suffix = ""
    //   - autofill = 0
    //   - autofill = 1 && suffix is changed with data stored
    //   - autofill = 0 && suffix is changed with data stored
    //   - do changes without form.
    if ($this->autofill) {
      $this->generateValueSeries();
    }
  }

}
