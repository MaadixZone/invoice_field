<?php

namespace Drupal\invoice_field\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Plugin implementation of the 'invoice_number_default' widget.
 *
 * @FieldWidget(
 *   id = "invoice_number_default",
 *   label = @Translation("Invoice number widget"),
 *   field_types = {
 *     "invoice_number"
 *   }
 * )
 */
class InvoiceNumberDefaultWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'size' => 60,
      'placeholder' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = [];

    $elements['size'] = [
      '#type' => 'number',
      '#title' => t('Size of textfield'),
      '#default_value' => $this->getSetting('size'),
      '#required' => TRUE,
      '#min' => 1,
    ];
    $elements['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('Text that will be shown inside the field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];

    $summary[] = t('Textfield size: @size', ['@size' => $this->getSetting('size')]);
    if (!empty($this->getSetting('placeholder'))) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $this->getSetting('placeholder')]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $element['value'] = [
      '#title' => t('Number'),
      '#type' => 'number',
      '#default_value' => isset($items[$delta]->value) ? $items[$delta]->value : NULL,
      '#size' => $this->getSetting('size'),
      '#series_suffix' => $this->getFieldSetting('series_suffix'),
      '#states' => [
        'visible' => [
          ':input[name="' . $this->fieldDefinition->getName() . '[0][autofill]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $element['series_suffix'] = [
      '#type' => 'textfield',
      '#title' => t('Series suffix'),
      '#default_value' => isset($items[$delta]->series_suffix) ? $items[$delta]->series_suffix : NULL,
      '#description' => '',
      '#size' => $this->getSetting('size'),
      '#placeholder' => $this->getSetting('placeholder') . $this->getFieldSetting('series_suffix'),
      '#states' => [
        'visible' => [
          ':input[name="' . $this->fieldDefinition->getName() . '[0][autofill]"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $element['autofill'] = [
      '#type' => 'checkbox',
      '#title' => t('Automatic fill value'),
      '#default_value' => isset($items[$delta]->value) ? NULL : $items[$delta]->autofill,
      '#description' => t('Autofill the value increasing by one the last found value: <b>\'@last_number\'</b>. Of this series: \'<b>@series</b>\' ', [
        '@series' => date($this->getFieldSetting('series_suffix', REQUEST_TIME)),
        '@last_number' => $this->lastNumber($entity),
      ]),
    ];
    $element += [
      '#type' => 'fieldset',
    ];
    return $element;
  }

  /**
   * Just to show the last number in description, it doesn't affect the values.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity that this field is part of.
   *
   * @return int
   *   The last number of the current series.
   */
  protected function lastNumber(EntityInterface $entity) {
    $bundle = $entity->bundle();
    $entity_type_id = $entity->getEntityTypeId();
    $field_name = $this->fieldDefinition->getName();
    $query = \Drupal::entityQuery($entity_type_id);
    $entity_keys = $entity->getEntityType()->getKeys();
    $current_suffix = date($this->getFieldSetting('series_suffix', REQUEST_TIME));
    // Autofill do not fill a same entity twice.
    $query->condition($field_name . ".series_suffix", $current_suffix)
      ->condition($entity_keys['bundle'], $bundle)
      ->condition($entity_keys['id'], $entity->id(), "!=")
      ->sort($field_name . '.value', "DESC")
      ->range(0, 1);
    $ids = $query->execute();
    if (!empty($ids)) {
      $id = array_values($ids)[0];
      $last_series_entity = \Drupal::entityManager()->getStorage($entity_type_id)->load($id);
      $last_number = $last_series_entity->get($field_name)->first()->value;
      return $last_number ? $last_number + 1 : 1;
    }
    else {
      // No previous items in this series found.
      return 1;
    }
  }

}
