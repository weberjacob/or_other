<?php

namespace Drupal\or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;

/**
 * Defines a base field widget for 'or other' field widgets.
 */
abstract class OrOtherWidgetBase extends WidgetBase {

  /**
   * Required flag.
   *
   * @var bool
   */
  protected $required;

  /**
   * Multiple flag.
   *
   * @var bool
   */
  protected $multiple;

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'placeholder' => 'Other',
      'no_other' => FALSE,
      'no_empty' => FALSE,
      'other_triggers' => [],
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($placeholder = $this->getSetting('placeholder')) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }
    if ($this->getSetting('no_other')) {
      $summary[] = t('Other option: Disabled');
    }
    if ($this->getSetting('no_empty')) {
      $summary[] = t('Empty option: Disabled');
    }
    if ($options = $this->getOtherTriggers()) {
      $summary[] = t('Triggers:');
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
    $this->multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    $element['placeholder'] = [
      '#type' => 'textfield',
      '#title' => t('Other Placeholder'),
      '#default_value' => $this->getSetting('placeholder'),
      '#description' => t('Text that will be shown inside the other field until a value is entered. This hint is usually a sample value or a brief description of the expected format.'),
    ];
    $element['no_other'] = [
      '#type' => 'checkbox',
      '#title' => t('Disable other option'),
      '#default_value' => $this->getSetting('no_other'),
    ];
    if (!$this->multiple && !$this->fieldDefinition->isRequired()) {
      $element['no_empty'] = [
        '#type' => 'checkbox',
        '#title' => t('Disable empty option'),
        '#default_value' => $this->getSetting('no_empty'),
      ];
    }
    if ($options = $this->getOtherTriggerOptions()) {
      $element['other_triggers'] = [
        '#type' => 'checkboxes',
        '#title' => t('Option Triggers'),
        '#default_value' => $this->getSetting('other_triggers'),
        '#options' => $options,
        '#element_validate' => [
          [get_class($this), 'otherTriggersValidate'],
        ],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function otherTriggersValidate(array $element, FormStateInterface $form_state) {
    // Clean up empty values.
    $form_state->setValueForElement($element, array_filter($element['#value']));
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->required = $element['#required'];
    $this->multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();

    $value = $items[$delta]->value ?? '';
    $options = $this->getOptions();
    // Add 'current value' if it does not exist in the options array. We assume
    // this was previously set as 'other'.
    if ($value && !isset($options[$value])) {
      $options[$value] = $value;
    }

    if (!$this->getSetting('no_other')) {
      // Add 'other' option.
      $options['_other'] = $this->t('Other');
    }

    $path = $this->getElementPath($element, [0, 'value']);
    $id = Html::getUniqueId('or-other-' . $path);
    $other_triggers = $this->getOtherTriggers();

    $element['#or_other_type'] = $this->getPluginId();
    $element['value'] = $element + [
      '#type' => 'select',
      '#id' => $id,
      '#default_value' => $value,
      '#options' => $options,
    ];

    $element['other'] = [
      '#type' => 'textfield',
      '#placeholder' => $this->getSetting('placeholder'),
      '#attributes' => ['class' => ['js-text-full', 'text-full']],
      '#element_validate' => [
        [get_class($this), 'validateOther'],
      ],
      '#or_other_options' => $other_triggers,
      '#access' => !empty($other_triggers),
    ];
    $states = [];
    foreach ($other_triggers as $key => $value) {
      $states['visible']['#' . $id][]['value'] = $key;
      $states['required']['#' . $id][]['value'] = $key;
    }
    $element['other']['#states'] = $states;

    return $element;
  }

  /**
   * Build out unique name for element.
   *
   * @param array $element
   *   The form element.
   * @param array $current_path
   *   The current path.
   */
  protected function getElementPath(array $element, array $current_path = []) {
    $name = '';
    foreach ($element['#field_parents'] as $parent) {
      if (empty($name)) {
        $name .= $parent;
      }
      else {
        $name .= '[' . $parent . ']';
      }
    }
    if (!empty($name)) {
      $name .= '[' . $this->fieldDefinition->getName() . ']';
    }
    else {
      $name .= $this->fieldDefinition->getName();
    }
    if (!empty($current_path)) {
      $name .= '[' . implode('][', $current_path) . ']';
    }
    return $name;
  }

  /**
   * Form validation handler for other elements.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateOther(array $element, FormStateInterface $form_state) {
    $other_value = $element['#value'];
    $input_parents = $element['#parents'];
    array_pop($input_parents);
    $input_parents[] = 'value';
    $input_value = $form_state->getValue($input_parents);
    if (isset($element['#or_other_options'][$input_value])) {
      if (!empty($other_value)) {
        $form_state->setValue($input_parents, $other_value);
      }
      else {
        $form_state->setError($element, 'When other is select an explanation is required.');
      }
    }
  }

  /**
   * Returns the array of options for the widget.
   *
   * @return array
   *   The array of options for the widget.
   */
  abstract protected function getOptions();

  /**
   * Returns the array of options for the widget.
   *
   * @return array
   *   The array of options for the widget.
   */
  protected function getOptionsWithOther() {
    $options = $this->getOptions();
    if (!$this->getSetting('no_other')) {
      $options['_other'] = $this->t('Other');
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if ($this->getSetting('no_empty')) {
      return NULL;
    }
    // Single select: add a 'none' option for non-required fields,
    // and a 'select a value' option for required fields that do not come
    // with a value selected.
    if (!$this->fieldDefinition->isRequired()) {
      return t('- None -');
    }
    return t('- Select -');
  }

  /**
   * {@inheritdoc}
   */
  protected function getOtherTriggerOptions() {
    $options = $this->getOptions();
    unset($options['']);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOtherTriggers() {
    $triggers = $this->getSetting('other_triggers') ?: [];
    $triggers = array_combine($triggers, $triggers);
    $triggers['_other'] = $this->t('Other');
    if ($this->getSetting('no_other')) {
      unset($triggers['_other']);
    }
    return $triggers;
  }

}
