<?php

namespace Drupal\or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Render\Markup;

/**
 * Plugin implementation of the 'string_textfield' widget.
 *
 * @FieldWidget(
 *   id = "or_other",
 *   label = @Translation("Or Other"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class OrOtherWidget extends OrOtherWidgetBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'options' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $options = $this->getOptions();
    unset($options['']);
    if (!empty($options)) {
      $summary[] = t('Options:');
      foreach ($options as $option) {
        $summary[] = Markup::create('<small>- ' . $option . '</small>');
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);
    $element['options'] = [
      '#type' => 'textarea',
      '#title' => t('Options'),
      '#description' => $this->allowedValuesDescription(),
      '#default_value' => $this->getSetting('options'),
      '#required' => TRUE,
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions() {
    $options = $this->extractAllowedValues($this->getSetting('options')) ?: [];
    // Add an empty option if the widget needs one.
    if ($empty_label = $this->getEmptyLabel()) {
      $options = ['' => $empty_label] + $options;
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function allowedValuesDescription() {
    $description = '<p>' . t('The possible values this field can contain. Enter one value per line, in the format key|label.');
    $description .= '<br/>' . t('The key is the stored value. The label will be used in displayed values and edit forms.');
    $description .= '<br/>' . t('The label is optional: if a line contains a single string, it will be used as key and label.');
    $description .= '</p>';
    $description .= '<p>' . t('Allowed HTML tags in labels: @tags', [
      '@tags' => implode(', ', FieldFilteredMarkup::allowedTags()),
    ]) . '</p>';
    return $description;
  }

  /**
   * {@inheritdoc}
   */
  protected static function validateAllowedValue($option) {
    if (mb_strlen($option) > 255) {
      return t('Allowed values list: each key must be a string at most 255 characters long.');
    }
  }

  /**
   * Extracts the allowed values array from the allowed_values element.
   *
   * @param array|string $value
   *   The raw string to extract values from.
   *
   * @return array|null
   *   The array of extracted key/value pairs, or NULL if the string is invalid.
   *
   * @see \Drupal\options\Plugin\Field\FieldType\ListItemBase::allowedValuesString()
   */
  protected static function extractAllowedValues($value) {
    $values = [];
    $generated_keys = $explicit_keys = FALSE;
    $list = [];
    if (is_array($value)) {
      $list = $value;
    }
    if (is_string($value)) {
      $list = explode("\n", $value);
    }
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');
    if (empty($list)) {
      return;
    }

    foreach ($list as $position => $text) {
      // Check for an explicit key.
      $matches = [];
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
        $explicit_keys = TRUE;
      }
      // Otherwise see if we can use the value as the key.
      elseif (!static::validateAllowedValue($text)) {
        $key = $value = $text;
        $explicit_keys = TRUE;
      }
      else {
        return;
      }

      $values[$key] = $value;
    }

    // We generate keys only if the list contains no explicit key at all.
    if ($explicit_keys && $generated_keys) {
      return;
    }

    return $values;
  }

}
