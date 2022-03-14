<?php

namespace Drupal\or_other\Plugin\Field\FieldWidget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Plugin implementation of the 'string_textfield' widget.
 *
 * @FieldWidget(
 *   id = "or_other_taxonomy",
 *   label = @Translation("Or Other Taxonomy"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class OrOtherTaxonomyWidget extends OrOtherWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager'),
      $container->get('entity.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'vid' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $vid = $this->getSetting('vid');
    $options = $this->getVocabularyOptions();

    $summary[] = t('Vocabulary: @vid', ['@vid' => isset($options[$vid]) ? $options[$vid] : '- None -']);

    $placeholder = $this->getSetting('placeholder');
    if (!empty($placeholder)) {
      $summary[] = t('Placeholder: @placeholder', ['@placeholder' => $placeholder]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = parent::settingsForm($form, $form_state);

    $element['vid'] = [
      '#type' => 'select',
      '#title' => $this->t('Vocabulary'),
      '#description' => $this->t('The vocabulary that will be used to generate the list of available options.'),
      '#options' => $this->getVocabularyOptions(),
      '#default_value' => $this->getSetting('vid'),
      '#required' => TRUE,
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getVocabularyOptions() {
    if (!isset($this->vocabularyOptions)) {
      $this->vocabularyOptions = [];
      $vocabulary_storage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      foreach ($vocabulary_storage->loadMultiple() as $vocabulary) {
        $this->vocabularyOptions[$vocabulary->id()] = $vocabulary->label();
      }
    }
    return $this->vocabularyOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOptions() {
    $options = $this->getTaxonomyOptions();
    $options = array_combine($options, $options);
    // Add an empty option if the widget needs one.
    if ($empty_label = $this->getEmptyLabel()) {
      $options = ['' => $empty_label] + $options;
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function getOtherTriggerOptions() {
    $options = $this->getTaxonomyOptions();
    return array_combine($options, $options);
  }

  /**
   * {@inheritdoc}
   */
  protected function getTaxonomyOptions() {
    if (!isset($this->taxonomyOptions) && $this->getSetting('vid')) {
      $vid = $this->getSetting('vid');
      $this->taxonomyOptions = [];
      $taxonomy_storage = $this->entityTypeManager->getStorage('taxonomy_term');
      foreach ($taxonomy_storage->loadTree($vid, 0, NULL, TRUE) as $term) {
        $this->taxonomyOptions[$term->id()] = $term->label();
      }

    }
    return $this->taxonomyOptions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyLabel() {
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
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return \Drupal::moduleHandler()->moduleExists('taxonomy');
  }

}
