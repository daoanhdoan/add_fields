<?php

namespace Drupal\add_fields\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\FieldStorageConfigInterface;
use Drupal\field_ui\FieldUI;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the "field storage" add page.
 *
 * @internal
 */
class AddFieldsFieldStorageAddForm extends FormBase
{

  /**
   * The name of the entity type.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The entity bundle.
   *
   * @var string
   */
  protected $bundle;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The field type plugin manager.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $fieldTypePluginManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new FieldStorageAddForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $field_type_plugin_manager
   *   The field type plugin manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface|null $entity_field_manager
   *   (optional) The entity field manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   (optional) The entity display repository.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FieldTypePluginManagerInterface $field_type_plugin_manager, ConfigFactoryInterface $config_factory, EntityFieldManagerInterface $entity_field_manager = NULL, EntityDisplayRepositoryInterface $entity_display_repository = NULL)
  {
    $this->entityTypeManager = $entity_type_manager;
    $this->fieldTypePluginManager = $field_type_plugin_manager;
    $this->configFactory = $config_factory;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'add_fields_field_storage_add_form';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.field.field_type'),
      $container->get('config.factory'),
      $container->get('entity_field.manager'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $entity_type_id = NULL, $bundle = NULL)
  {
    if (!$form_state->get('entity_type_id')) {
      $form_state->set('entity_type_id', $entity_type_id);
    }
    if (!$form_state->get('bundle')) {
      $form_state->set('bundle', $bundle);
    }

    $this->entityTypeId = $form_state->get('entity_type_id');
    $this->bundle = $form_state->get('bundle');

    // Gather valid field types.
    $field_type_options = [];
    foreach ($this->fieldTypePluginManager->getGroupedDefinitions($this->fieldTypePluginManager->getUiDefinitions()) as $category => $field_types) {
      foreach ($field_types as $name => $field_type) {
        $field_type_options[$category][$name] = $field_type['label'];
      }
    }
    $existing_field_storage_options = $this->getExistingFieldStorageOptions();
    if ($existing_field_storage_options) {
      $form['#attached']['drupalSettings']['existingFieldLabels'] = $this->getExistingFieldLabels(array_keys($existing_field_storage_options));
    }

    $form['add'] = [
      '#type' => 'table',
      '#header' => [
        'type' => t('Add a new field'),
        'existing_storage_name' => t('Re-use an existing field'),
        'label' => t('Label'),
        //'settings' => t('Setting'),
        'weight' => t('Weight')
      ],
      '#tree' => TRUE,
      '#attributes' => ['class' => ['form--inline', 'clearfix']],
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'add-fields-weight',
        ]
      ]
    ];

    for ($i = 0; $i <= 10; $i++) {
      $form['add'][$i]['new_storage_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Add a new field'),
        '#title_display' => 'invisible',
        '#options' => $field_type_options,
        '#empty_option' => $this->t('- Select a field type -'),

      ];
      if ($existing_field_storage_options) {
        $form['add'][$i]['existing_storage_name'] = [
          '#type' => 'select',
          '#title' => $this->t('Re-use an existing field'),
          '#title_display' => 'invisible',
          '#options' => $existing_field_storage_options,
          '#empty_option' => $this->t('- Select an existing field -'),
          '#states' => [
            '!disabled' => [
              ':input[name="add[' . $i . '][new_storage_type]"]' => ['value' => '']
            ],
          ]
        ];
      } else {
        $form['add'][$i]['existing_storage_name'] = [
          '#type' => 'value',
          '#value' => FALSE
        ];
      }
      $form['add'][$i]['storage_settings'] = [
        '#type' => 'container',
      ];
      $form['add'][$i]['storage_settings']['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#title_display' => 'invisible',
        '#size' => 15,
        '#states' => [
          '!visible' => [
            ':input[name="add[' . $i . '][new_storage_type]"]' => ['value' => '']
          ],
        ]
      ];
      if ($existing_field_storage_options) {
        $form['add'][$i]['new_storage_type']['#states']['!disabled'][':input[name="add[' . $i . '][existing_storage_name]"]'] = ['value' => ''];
        $form['add'][$i]['storage_settings']['existing_storage_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Label'),
          '#title_display' => 'invisible',
          '#size' => 15,
          '#states' => [
            '!visible' => [
              ':input[name="add[' . $i . '][existing_storage_name]"]' => ['value' => '']
            ]
          ]
        ];
      }

      $field_prefix = $this->config('field_ui.settings')->get('field_prefix');
      $form['add'][$i]['storage_settings']['field_name'] = [
        '#type' => 'machine_name',
        '#field_prefix' => $field_prefix,
        '#size' => 15,
        '#description' => $this->t('A unique machine-readable name containing letters, numbers, and underscores.'),
        '#maxlength' => FieldStorageConfig::NAME_MAX_LENGTH - strlen($field_prefix),
        '#machine_name' => [
          'source' => ['add', $i, 'storage_settings', 'label'],
          'exists' => [$this, 'fieldNameExists'],
        ],
        '#required' => FALSE,
      ];

      $form['add'][$i]['weight'] = array(
        '#type' => 'weight',
        '#title' => t('Weight'),
        '#title_display' => 'invisible',
        '#default_value' => $i,
        '#attributes' => ['class' => ['add-fields-weight']],
      );
      $form['add'][$i]['#attributes']['class'][] = 'draggable';
      $form['add'][$i]['#weight'] = $i;
    }

    // Place the 'translatable' property as an explicit value so that contrib
    // modules can form_alter() the value for newly created fields. By default
    // we create field storage as translatable so it will be possible to enable
    // translation at field level.
    $form['translatable'] = [
      '#type' => 'value',
      '#value' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#button_type' => 'primary',
    ];

    $form['#attached']['library'][] = 'field_ui/drupal.field_ui';

    return $form;
  }

  /**
   * Update the dependent field options.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return mixed
   *   The updated field.
   */
  public static function ajaxUpdate(array $form, FormStateInterface &$form_state)
  {
    $form_state->setRebuild();
    $trigger = $form_state->getTriggeringElement();
    $index = $trigger['#index'];
    return $form['add'][$index]['storage_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    foreach ($form_state->getValue('add') as $i => &$item) {
      if ($form_state->getValue(['add', $i, 'new_storage_type'])) {
        if (!$form_state->getValue(['add', $i, 'storage_settings', 'label'])) {
          $form_state->setErrorByName('add][' . $i . '][storage_settings][label', $this->t('Add new field: you need to provide a label.'));
        }
        if (!$form_state->getValue(['add', $i, 'storage_settings', 'field_name'])) {
          $form_state->setErrorByName('add][' . $i . '][storage_settings][label', $this->t('Add new field: you need to provide a machine name for the field.'));
        }
      } elseif ($form_state->getValue(['add', $i, 'existing_storage_name'])) {
        if (!$form_state->getValue(['add', $i, 'storage_settings', 'existing_storage_label'])) {
          $form_state->setErrorByName('add][' . $i . '][storage_settings][existing_storage_label', $this->t('Re-use existing field: you need to provide a label.'));
        }
      } elseif ($form_state->getValue(['add', $i, 'storage_settings', 'label'])) {
        $form_state->setErrorByName('add][' . $i . '][new_storage_type', $this->t('You need to select a field type or an existing field.'));
      }
      if ($form_state->getValue(['add', $i, 'existing_storage_name']) && $form_state->getValue(['add', $i, 'new_storage_type'])) {
        $form_state->setErrorByName('add][' . $i . '][new_storage_type', $this->t('Adding a new field and re-using an existing field at the same time is not allowed.'));
        $form_state->setErrorByName('add][' . $i . '][existing_storage_name', $this->t('Adding a new field and re-using an existing field at the same time is not allowed.'));
      }
      if ($field_name = $form_state->getValue(['add', $i, 'storage_settings', 'field_name'])) {        // Add the field prefix.
        $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $field_name;
        $form_state->setValueForElement($form['add'][$i]['storage_settings']['field_name'], $field_name);
      }
      $item += $item['storage_settings'];
    }
  }

  public function createNewField($values, &$form_state)
  {
    $destinations = [];
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);
    $field_storage_values = [
      'field_name' => $values['field_name'],
      'entity_type' => $this->entityTypeId,
      'type' => $values['new_storage_type'],
      'translatable' => $values['translatable'],
    ];
    $field_values = [
      'field_name' => $values['field_name'],
      'entity_type' => $this->entityTypeId,
      'bundle' => $this->bundle,
      'label' => $values['label'],
      // Field translatability should be explicitly enabled by the users.
      'translatable' => FALSE,
    ];
    $widget_id = $formatter_id = NULL;
    $widget_settings = $formatter_settings = [];

    // Check if we're dealing with a preconfigured field.
    if (strpos($field_storage_values['type'], 'field_ui:') !== FALSE) {
      list(, $field_type, $option_key) = explode(':', $field_storage_values['type'], 3);
      $field_storage_values['type'] = $field_type;

      $field_definition = $this->fieldTypePluginManager->getDefinition($field_type);
      $options = $this->fieldTypePluginManager->getPreconfiguredOptions($field_definition['id']);
      $field_options = $options[$option_key];

      // Merge in preconfigured field storage options.
      if (isset($field_options['field_storage_config'])) {
        foreach (['cardinality', 'settings'] as $key) {
          if (isset($field_options['field_storage_config'][$key])) {
            $field_storage_values[$key] = $field_options['field_storage_config'][$key];
          }
        }
      }

      // Merge in preconfigured field options.
      if (isset($field_options['field_config'])) {
        foreach (['required', 'settings'] as $key) {
          if (isset($field_options['field_config'][$key])) {
            $field_values[$key] = $field_options['field_config'][$key];
          }
        }
      }

      $widget_id = isset($field_options['entity_form_display']['type']) ? $field_options['entity_form_display']['type'] : NULL;
      $widget_settings = isset($field_options['entity_form_display']['settings']) ? $field_options['entity_form_display']['settings'] : [];
      $formatter_id = isset($field_options['entity_view_display']['type']) ? $field_options['entity_view_display']['type'] : NULL;
      $formatter_settings = isset($field_options['entity_view_display']['settings']) ? $field_options['entity_view_display']['settings'] : [];
    }

    // Create the field storage and field.
    try {
      $this->entityTypeManager->getStorage('field_storage_config')->create($field_storage_values)->save();
      $field = $this->entityTypeManager->getStorage('field_config')->create($field_values);
      $field->save();

      $this->configureEntityFormDisplay($values['field_name'], $widget_id, $widget_settings);
      $this->configureEntityViewDisplay($values['field_name'], $formatter_id, $formatter_settings);

      // Always show the field settings step, as the cardinality needs to be
      // configured for new fields.
      $route_parameters = [
          'field_config' => $field->id(),
        ] + FieldUI::getRouteBundleParameter($entity_type, $this->bundle);
      $destinations[] = ['route_name' => "entity.field_config.{$this->entityTypeId}_storage_edit_form", 'route_parameters' => $route_parameters];
      $destinations[] = ['route_name' => "entity.field_config.{$this->entityTypeId}_field_edit_form", 'route_parameters' => $route_parameters];

      // Store new field information for any additional submit handlers.
      $form_state->set(['fields_added', '_add_new_field'], $values['field_name']);
    } catch (\Exception $e) {
      $error = TRUE;
      $this->messenger()->addError($this->t('There was a problem creating field %label: @message', ['%label' => $values['label'], '@message' => $e->getMessage()]));
    }
    return $destinations;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $form_state->cleanValues();
    $error = FALSE;
    $values = $form_state->getValues();
    $destinations = [];
    $entity_type = $this->entityTypeManager->getDefinition($this->entityTypeId);

    // Create new field.
    foreach ($values['add'] as $item) {
      if (!empty($item['new_storage_type'])) {
        $destinations = array_merge($destinations, $this->createNewField($item, $form_state));
      }
      // Re-use existing field.
      elseif (!empty($item['existing_storage_name'])) {
        $field_name = $item['existing_storage_name'];

        try {
          $field = $this->entityTypeManager->getStorage('field_config')->create([
            'field_name' => $field_name,
            'entity_type' => $this->entityTypeId,
            'bundle' => $this->bundle,
            'label' => $item['existing_storage_label'],
          ]);
          $field->save();

          $this->configureEntityFormDisplay($field_name);
          $this->configureEntityViewDisplay($field_name);

          $route_parameters = [
              'field_config' => $field->id(),
            ] + FieldUI::getRouteBundleParameter($entity_type, $this->bundle);
          $destinations[] = ['route_name' => "entity.field_config.{$this->entityTypeId}_field_edit_form", 'route_parameters' => $route_parameters];

          // Store new field information for any additional submit handlers.
          $form_state->set(['fields_added', '_add_existing_field'], $field_name);
        } catch (\Exception $e) {
          $error = TRUE;
          $this->messenger()->addError($this->t('There was a problem creating field %label: @message', ['%label' => $values['label'], '@message' => $e->getMessage()]));
        }
      }
    }

    $route_parameters = FieldUI::getRouteBundleParameter($entity_type, $this->bundle);
    $destinations[] = ['route_name' => "entity.{$this->entityTypeId}.field_ui_fields", 'route_parameters' => $route_parameters];

    if ($destinations) {
      $destination = $this->getDestinationArray();
      $destinations[] = $destination['destination'];
      $form_state->setRedirectUrl(FieldUI::getNextDestination($destinations, $form_state));
    } elseif (!$error) {
      $this->messenger()->addStatus($this->t('Your settings have been saved.'));
    }
  }

  /**
   * Configures the field for the default form mode.
   *
   * @param string $field_name
   *   The field name.
   * @param string|null $widget_id
   *   (optional) The plugin ID of the widget. Defaults to NULL.
   * @param array $widget_settings
   *   (optional) An array of widget settings. Defaults to an empty array.
   */
  protected function configureEntityFormDisplay($field_name, $widget_id = NULL, array $widget_settings = [])
  {
    $options = [];
    if ($widget_id) {
      $options['type'] = $widget_id;
      if (!empty($widget_settings)) {
        $options['settings'] = $widget_settings;
      }
    }
    // Make sure the field is displayed in the 'default' form mode (using
    // default widget and settings). It stays hidden for other form modes
    // until it is explicitly configured.
    $this->entityDisplayRepository->getFormDisplay($this->entityTypeId, $this->bundle, 'default')
      ->setComponent($field_name, $options)
      ->save();
  }

  /**
   * Configures the field for the default view mode.
   *
   * @param string $field_name
   *   The field name.
   * @param string|null $formatter_id
   *   (optional) The plugin ID of the formatter. Defaults to NULL.
   * @param array $formatter_settings
   *   (optional) An array of formatter settings. Defaults to an empty array.
   */
  protected function configureEntityViewDisplay($field_name, $formatter_id = NULL, array $formatter_settings = [])
  {
    $options = [];
    if ($formatter_id) {
      $options['type'] = $formatter_id;
      if (!empty($formatter_settings)) {
        $options['settings'] = $formatter_settings;
      }
    }
    // Make sure the field is displayed in the 'default' view mode (using
    // default formatter and settings). It stays hidden for other view
    // modes until it is explicitly configured.
    $this->entityDisplayRepository->getViewDisplay($this->entityTypeId, $this->bundle)
      ->setComponent($field_name, $options)
      ->save();
  }

  /**
   * Returns an array of existing field storages that can be added to a bundle.
   *
   * @return array
   *   An array of existing field storages keyed by name.
   */
  protected function getExistingFieldStorageOptions()
  {
    $options = [];
    // Load the field_storages and build the list of options.
    $field_types = $this->fieldTypePluginManager->getDefinitions();
    foreach ($this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId) as $field_name => $field_storage) {
      // Do not show:
      // - non-configurable field storages,
      // - locked field storages,
      // - field storages that should not be added via user interface,
      // - field storages that already have a field in the bundle.
      $field_type = $field_storage->getType();
      if ($field_storage instanceof FieldStorageConfigInterface
        && !$field_storage->isLocked()
        && empty($field_types[$field_type]['no_ui'])
        && !in_array($this->bundle, $field_storage->getBundles(), TRUE)) {
        $options[$field_name] = $this->t('@type: @field', [
          '@type' => $field_types[$field_type]['label'],
          '@field' => $field_name,
        ]);
      }
    }
    asort($options);

    return $options;
  }

  /**
   * Gets the human-readable labels for the given field storage names.
   *
   * Since not all field storages are required to have a field, we can only
   * provide the field labels on a best-effort basis (e.g. the label of a field
   * storage without any field attached to a bundle will be the field name).
   *
   * @param array $field_names
   *   An array of field names.
   *
   * @return array
   *   An array of field labels keyed by field name.
   */
  protected function getExistingFieldLabels(array $field_names)
  {
    // Get all the fields corresponding to the given field storage names and
    // this entity type.
    $field_ids = $this->entityTypeManager->getStorage('field_config')->getQuery()
      ->condition('entity_type', $this->entityTypeId)
      ->condition('field_name', $field_names)
      ->execute();
    $fields = $this->entityTypeManager->getStorage('field_config')->loadMultiple($field_ids);

    // Go through all the fields and use the label of the first encounter.
    $labels = [];
    foreach ($fields as $field) {
      if (!isset($labels[$field->getName()])) {
        $labels[$field->getName()] = $field->label();
      }
    }

    // For field storages without any fields attached to a bundle, the default
    // label is the field name.
    $labels += array_combine($field_names, $field_names);

    return $labels;
  }

  /**
   * Checks if a field machine name is taken.
   *
   * @param string $value
   *   The machine name, not prefixed.
   * @param array $element
   *   An array containing the structure of the 'field_name' element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return bool
   *   Whether or not the field machine name is taken.
   */
  public function fieldNameExists($value, $element, FormStateInterface $form_state)
  {
    // Don't validate the case when an existing field has been selected.
    if ($form_state->getValue('existing_storage_name')) {
      return FALSE;
    }

    // Add the field prefix.
    $field_name = $this->configFactory->get('field_ui.settings')->get('field_prefix') . $value;

    $field_storage_definitions = $this->entityFieldManager->getFieldStorageDefinitions($this->entityTypeId);
    return isset($field_storage_definitions[$field_name]);
  }

}
