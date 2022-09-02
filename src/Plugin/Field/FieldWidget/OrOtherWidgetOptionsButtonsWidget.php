<?php

namespace Drupal\or_other\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\CompositeFormElementTrait;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Plugin implementation of the 'string_textfield' widget.
 *
 * @FieldWidget(
 *   id = "or_other_options_buttons",
 *   label = @Translation("Or Other check boxes/radio buttons"),
 *   field_types = {
 *     "string"
 *   },
 *   multiple_values = TRUE
 * )
 */
class OrOtherWidgetOptionsButtonsWidget extends OrOtherWidget implements TrustedCallbackInterface {

  use CompositeFormElementTrait;

  /**
   * The delimiter.
   *
   * @var string
   */
  public static $delimiter = ': ';

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRenderCompositeFormElement'];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $this->required = $element['#required'];
    $this->multiple = $this->fieldDefinition->getFieldStorageDefinition()->isMultiple();
    $other_triggers = $this->getOtherTriggers();
    $selected = $this->getSelectedOptions($items);
    $options = $this->getOptionsWithOther();

    $element['#pre_render'][] = [static::class, 'preRenderCompositeFormElement'];

    if ($this->multiple) {
      // Add 'current value' if it does not exist in the options array. We assume
      // this was previously set as 'other'.
      // if ($value && !isset($options[$value])) {
      //   $options[$value] = $value;
      // }
      $element['values'] = [
        '#type' => 'container',
      ];
      foreach ($options as $tid => $label) {
        $path = $this->getElementPath($element, ['values', $tid, 'value']);
        $id = Html::getUniqueId('or-other-' . $path);
        $element['values'][$tid]['value'] = [
          '#type' => 'checkbox',
          '#id' => $id,
          '#title_lock' => TRUE,
          '#ux_wrapper_supported' => FALSE,
          '#title' => $label,
          '#term_name' => $label,
          '#default_value' => !empty($selected[$tid]['value']),
        ];
        if (!empty($other_triggers[$tid])) {
          $element['values'][$tid]['other'] = [
            '#type' => 'textfield',
            '#attributes' => ['class' => ['js-text-full', 'text-full']],
            '#placeholder' => str_replace('@value', $label, $this->getSetting('placeholder')),
            '#default_value' => $selected[$tid]['other'] ?? '',
            '#states' => [
              'visible' => [
                '#' . $id => ['checked' => TRUE],
              ],
              'required' => [
                '#' . $id => ['checked' => TRUE],
              ],
            ],
          ];
        }
      }
      // Add our custom validator.
      $element['#element_validate'][] = [
        get_class($this), 'validateMultipleElement',
      ];
    }
    else {
      $element['values'] = [
        '#type' => 'container',
      ];
      $path = $this->getElementPath($element, ['values']);
      $id = Html::getUniqueId('or-other-' . $path);
      foreach ($options as $tid => $label) {
        $element['values'][$tid]['value'] = [
          '#type' => 'radio',
          '#title_lock' => TRUE,
          '#ux_wrapper_supported' => FALSE,
          '#name' => $path,
          '#return_value' => $tid,
          '#title' => $label,
          '#term_name' => $label,
          '#default_value' => !empty($selected[$tid]['value']),
        ];
        if (!empty($other_triggers[$tid])) {
          $element['values'][$tid]['other'] = [
            '#type' => 'textfield',
            '#title_lock' => TRUE,
            '#attributes' => ['class' => ['js-text-full', 'text-full']],
            '#placeholder' => str_replace('@value', $label, $this->getSetting('placeholder')),
            '#default_value' => $selected[$tid]['other'] ?? '',
            '#states' => [
              'visible' => [
                '#' . $id => ['value' => $tid],
              ],
              'required' => [
                '#' . $id => ['value' => $tid],
              ],
            ],
          ];
        }
      }
      // Add our custom validator.
      $element['#element_validate'][] = [get_class($this), 'validateElement'];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function validateElement(array $element, FormStateInterface $form_state) {
    $items = [];
    $value = $element['value']['#value'];
    $other_value = $element['other']['#value'];
    if ($value === 'Other') {
      $value = $other_value;
    }
    $items[] = ['value' => $value];
    $form_state->setValueForElement($element, $items);
  }

  /**
   * {@inheritdoc}
   */
  public static function validateMultipleElement(array $element, FormStateInterface $form_state) {
    $values = [];
    foreach (Element::children($element['values']) as $key) {
      $child_element = $element['values'][$key];
      if ($child_element['value']['#value']) {
        $value = $child_element['value']['#term_name'];
        if (!empty($child_element['other']['#value'])) {
          $value .= self::$delimiter . $child_element['other']['#value'];
        }
        $values[] = $value;
      }
    }

    if ($element['#required'] && empty($value)) {
      $form_state->setError($element, t('@name field is required.', ['@name' => $element['#title']]));
    }

    // Transpose selections from field => delta to delta => field.
    $items = [];
    foreach ($values as $value) {
      $items[] = ['value' => $value];
    }
    $form_state->setValueForElement($element, $items);
  }

  /**
   * Determines selected options from the incoming field values.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field values.
   *
   * @return array
   *   The array of corresponding selected options.
   */
  protected function getSelectedOptions(FieldItemListInterface $items) {
    // We need to check against a flat list of options.
    $flat_options = OptGroup::flattenOptions($this->getOptionsWithOther());

    if ($this->multiple) {
      $selected_options = [];
      foreach ($items as $item) {
        $full_value = $item->value;
        foreach ($flat_options as $tid => $option_value) {
          $value = substr($full_value, 0, strlen($option_value));
          $other_value = substr($full_value, strlen($value . self::$delimiter));
          if ($full_value === $option_value || $value . self::$delimiter . $other_value === $full_value) {
            $selected_options[$tid]['value'] = $value;
            $selected_options[$tid]['other'] = $other_value;
          }
        }
      }
    }
    else {
      $value = $items[0]->value ?? '';
      $other = '';
      if (!empty($value) && !isset($flat_options[$value])) {
        $other = $value;
        $value = 'Other';
      }
      $selected_options['value'] = $value;
      $selected_options['other'] = $other;
    }

    return $selected_options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
    if ($this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
      return NULL;
    }
    return parent::getEmptyLabel();
  }

}
