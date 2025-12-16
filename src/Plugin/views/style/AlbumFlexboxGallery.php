<?php

namespace Drupal\lightgallery_views_flex_justified\Plugin\views\style;

use Drupal\file\FileInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\media\MediaInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\Core\Url;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


use Drupal\views\ViewExecutable;

use Drupal\image\Entity\ImageStyle;
use Drupal\lightgallery_settings_ui\Traits\LightGallerySettingsTrait as LightGallerySettingsTrait;

/**
 * Album Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_flexbox_gallery",
 *   title = @Translation("Album Flexbox Gallery"),
 *   help = @Translation("Displays albums with a Flexbox layout."),
 *   theme = "album_flexbox_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumFlexboxGallery extends StylePluginBase {
  use LightGallerySettingsTrait;

  /**
   * The file URL generator service.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected FileUrlGeneratorInterface $fileUrlGenerator;

  /**
   * {@inheritdoc}
   */
  protected $usesRowPlugin = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = TRUE;

  /**
   * Does the style plugin for itself support to add fields to its output.
   *
   * This option only makes sense on style plugins without row plugins, like
   * for example table.
   *
   * @var bool
   */
  protected $usesFields = FALSE;

  /**
   * Constructs an AlbumIsotopeGallery style plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $file_url_generator
   *   The file URL generator service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FileUrlGeneratorInterface $file_url_generator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->fileUrlGenerator = $file_url_generator;
  }

  /**
   * Creates an instance of the AlbumIsotopeGallery style plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
          $configuration,
          $plugin_id,
          $plugin_definition,
          $container->get('file_url_generator')
      );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['image'] = [
      'default' => [
        'image_field' => NULL,
        'title_field' => NULL,
        'author_field' => NULL,
        'description_field' => NULL,
        'url_field' => NULL,
        'image_thumbnail_style' => 'medium',
      ],
    ];
    $options['lightgallery'] = [
      'default' => [
        'closable' => TRUE,
        'closeOnTap' => FALSE,
        'controls' => TRUE,
      ],
    ];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    if (isset($form['grouping']) && is_array($form['grouping'])) {
      // Limit to two grouping levels.
      $form['grouping'] = array_slice($form['grouping'], 0, 2, TRUE);
    }

    [$fields_text, $fields_media, $fields_taxo] = $this->getTextAndMediaFields($this->view);

    // Field for the description.
    $image_styles = ImageStyle::loadMultiple();
    foreach ($image_styles as $style => $image_style) {
      $image_thumbnail_style[$image_style->id()] = $image_style->label();
    }
    $default_style = '';
    if (isset($this->options['image']['image_thumbnail_style']) && $this->options['image']['image_thumbnail_style']) {
      $default_style = $this->options['image']['image_thumbnail_style'];
    }
    elseif (isset($image_styles['image']['medium'])) {
      $default_style = 'medium';
    }
    elseif (isset($image_styles['image']['thumbnail'])) {
      $default_style = 'thumbnail';
    }
    elseif (!empty($image_styles)) {
      $default_style = array_key_first($image_styles);
    }
    $this->options['image']['image_thumbnail_style'] = $default_style;

    // Field for the image.
    $form['image'] = [
      '#type' => 'details',
      '#title' => $this->t('Image settings'),
      '#description' => $this->t('Configure the image settings for the album gallery.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];

    $form['image']['image_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Image field'),
      '#options' => $fields_media,
      '#default_value' => $this->options['image']['image_field'],
      '#required' => TRUE,
    ];

    $form['image']['image_thumbnail_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Thumbnail style'),
      '#options' => $image_thumbnail_style,
      '#default_value' => $this->options['image']['image_thumbnail_style'],
      '#description' => $this->t('Select an image style to apply to the thumbnails.'),
    ];

    $form['image']['captions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display captions'),
      '#default_value' => $this->options['image']['captions'] ?? TRUE,
      '#description' => $this->t('Display captions for images.'),
    ];

    // Field for the title.
    $form['image']['title_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Title field'),
      '#options' => ['' => $this->t('- None -')] + $fields_text,
      '#default_value' => $this->options['image']['title_field'],
      '#required' => FALSE,
    ];

    $form['image']['description_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Description field'),
      '#options' => ['' => $this->t('- None -')] + $fields_text,
      '#default_value' => $this->options['image']['description_field'],
      '#required' => FALSE,
    ];
    // Field for the author.
    $form['image']['author_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Author field'),
      '#options' => ['' => $this->t('- None -')] + $fields_taxo,
      '#default_value' => $this->options['image']['author_field'],
    ];

    // Use album lightgallery settings form.
    // // Field for the LightGallery.
    // // get Core settings only.
    // $definitions = self::getLightGalleryPluginDefinitions();
    // $config = self::getGeneralSettings([]);
    // $form['lightgallery'] = self::buildCoreSettingsForm(
    //   $definitions,
    //   $config ?? [],
    //   [],
    // )['params'];
    // // Rename module settings title.
    // $form['lightgallery']['#title'] = $this->t('LightGallery');
    // foreach ($form['lightgallery'] as $key => $value) {
    //   if (is_array($value)) {
    //     // Remove the config target to avoid overwriting global settings.
    //     unset($form['lightgallery'][$key]['#config_target']);
    //     // Set default value from style options.
    //     $form['lightgallery'][$key]['#default_value'] = $this->options['lightgallery'][$key];
    //   }
    // }.
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $id = rand();

    // Get image style dimensions.
    $max_width = NULL;
    $max_height = NULL;
    if (!empty($this->options['image']['image_thumbnail_style'])) {
      $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
      if ($image_style) {
        $effects = $image_style->getEffects();
        foreach ($effects as $effect) {
          $conf = $effect->getConfiguration();
          if ($conf['id'] === 'image_scale' || $conf['id'] === 'image_scale_and_crop') {
            $max_width = $conf['data']['width'] ?? NULL;
            $max_height = $conf['data']['height'] ?? NULL;
            break;
          }
        }
      }
    }

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => [],
      '#attributes' => [
        'class' => ['album-flexbox-gallery', 'lightgallery-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery_views_flex_justified/flexbox',
          'lightgallery/lightgallery',
        ],
        'drupalSettings' => [
          'settings' => [
            'lightgallery' => [
              'inline' => FALSE,
              'galleryId' => $id,
              'thumbnail_width' => $max_width,
              'thumbnail_height' => $max_height,
              'albums_settings' => [],
            ],
          ],
        ],
      ],
    ];

    // Get grouped results from Views.
    $grouped_rows = $this->renderGrouping(
    $this->view->result,
    $this->options['grouping'],
    TRUE
    );

    // Recursively process the grouped structure.
    $build['#groups'] = $this->processGroupRecursive($grouped_rows,
        $build,
        $build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']);

    foreach ($build['#attached']['drupalSettings']['settings']['lightgallery']['albums_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
      $build['#attached']['library'][] = $plugin;
    }

    $build['#options'] += [
      'captions' => $this->options['image']['captions'] ?? TRUE,
    ];

    unset($this->view->row_index);
    return $build;
  }

  /**
   * Recursively process the grouping structure from Views.
   *
   * @param array $groups
   *   The grouping structure returned by renderGrouping().
   * @param array &$build
   *   The build array (passed by reference to add settings).
   * @param array &$lightgallery_settings
   *   The lightgallery settings (passed by reference to collect).
   * @param int $depth
   *   Current depth (for debug/styling).
   *
   * @return array
   *   Normalized structure for Twig.
   */
  private function processGroupRecursive(array $groups, array &$build, array &$lightgallery_settings, int $depth = 0) {
    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $group_item = [
        'title' => $group_data['group'] ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
        'groupid' => 'album-group-' . rand(),
      ];

      // Check if this group contains rows (final results)
      if (isset($group_data['rows']) && is_array($group_data['rows'])) {

        // DDetermine if the "rows" are actually other groups or real rows.
        $first_row = reset($group_data['rows']);

        if (is_array($first_row) && isset($first_row['group']) && isset($first_row['level'])) {
          // These are subgroups, process recursively.
          $group_item['subgroups'] = $this->processGroupRecursive(
          $group_data['rows'],
          $build,
          $lightgallery_settings,
          $depth + 1,
          );
        }
        else {
          // These are real rows (ResultRow), process albums.
          foreach ($group_data['rows'] as $index => $row) {
            $album_data = $this->buildAlbumData($row, $index, $lightgallery_settings);
            if ($album_data) {
              $group_item['albums'][] = $album_data;
            }
          }
        }
      }
      $processed[] = $group_item;
    }

    return $processed;
  }

  /**
   * Build album data from a row.
   *
   * @param object $row
   *   The view row (ResultRow).
   * @param int $index
   *   The row index.
   * @param array $lightgallery_album_settings
   *   The lightgallery settings (passed by reference to collect).
   *
   * @return array|null
   *   The album data or NULL on error.
   */
  private function buildAlbumData($row, $index, array &$lightgallery_album_settings) {
    $this->view->row_index = $index;

    // Get first media as presentation image.
    $image_url = $this->getMediaImageUrl($row, $this->options['image']['image_field']);

    // Text fields.
    $title = !empty($this->options['image']['title_field'])
    ? $this->getFieldValue($index, $this->options['image']['title_field'])
    : '';
    $author = !empty($this->options['image']['author_field'])
    ? $this->getFieldValue($index, $this->options['image']['author_field'])
    : '';
    $description = !empty($this->options['image']['description_field'])
    ? $this->getFieldValue($index, $this->options['image']['description_field'])
    : '';
    $url = Url::fromRoute('entity.node.canonical', ['node' => $row->nid])->toString();

    // Get all media items associated with this album.
    $medias = [];
    if (isset($row->_entity)
      && $row->_entity instanceof EntityInterface
      && $row->_entity->hasField($this->options['image']['image_field'])
    ) {
      foreach ($row->_entity->get($this->options['image']['image_field']) as $media_item) {
        $media = $media_item->entity;

        if (!$media instanceof MediaInterface) {
          continue;
        }

        $source_field = $this->getSourceField($media);
        if (!$source_field) {
          continue;
        }

        switch ($media->getSource()->getPluginId()) {
          case 'image':
            $file = $media->get($source_field)->entity;
            if ($file instanceof FileInterface) {
              $original_url = $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
              $thumbnail_url = $original_url;

              if (!empty($this->options['image']['image_thumbnail_style'])) {
                try {
                  $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                  if ($image_style) {
                    $thumbnail_url = $image_style->buildUrl($file->getFileUri());
                  }
                }
                catch (\Exception $e) {
                  \Drupal::logger('album_gallery')->error('Error loading image style: @error',
                  ['@error' => $e->getMessage()]);
                }
              }

              $medias[] = [
                'url' => $original_url,
                'mime_type' => $file->getMimeType(),
                'alt' => $media->get($source_field)->first()->get('alt')->getValue() ?? '',
                'title' => $media->get($source_field)->first()->get('title')->getValue() ?? '',
                'thumbnail' => $thumbnail_url,
              ];
            }
            break;

          case 'video_file':
            $file = $media->get($source_field)->entity;
            $thumbnail = $media->get('thumbnail')->entity;
            if ($file instanceof FileInterface) {
              $thumbnail_url = '';
              if ($thumbnail) {
                $thumbnail_url = $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri());

                if (!empty($this->options['image']['image_thumbnail_style'])) {
                  try {
                    $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                    if ($image_style) {
                      $thumbnail_url = $image_style->buildUrl($thumbnail->getFileUri());
                    }
                  }
                  catch (\Exception $e) {
                    \Drupal::logger('album_gallery')->error('Error loading image style for video thumbnail: @error',
                    ['@error' => $e->getMessage()]);
                  }
                }
              }

              $medias[] = [
                'url' => $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri()),
                'mime_type' => $file->getMimeType(),
                'thumbnail' => $thumbnail_url,
                'title' => $media->get($source_field)->first()->get('description')->getValue() ?? '',
              ];
            }
            break;
        }
      }
    }

    // Get lightgallery settings.
    $renderer = $this->view->display_handler->getOption('fields')[$this->options['image']['image_field']]['settings']['view_mode'] ?? 'default';
    $display = \Drupal::service('entity_display.repository')->getViewDisplay('node', $row->_entity->bundle(), $renderer);

    $node_settings = [];
    if ($display && $display->getComponent($this->options['image']['image_field'])) {
      $node_settings = $display->getComponent($this->options['image']['image_field'])['settings'];
    }

    // Build the settings for the album.
    // Same settings for all albums, as they use the same formatter/renderer,
    // but Views can return different content types.
    $lightgallery_settings = self::getGeneralSettings($node_settings);
    // Build plugins list and attach libraries.
    foreach ($lightgallery_settings['plugins'] ?? [] as $plugin_name => $plugin) {
      $lightgallery_album_settings['plugins'][$plugin_name] = 'lightgallery/lightgallery-' . $plugin_name ?? $plugin_name;
    }

    $album_id = 'album-item-' . rand();
    $lightgallery_album_settings[$album_id] = $lightgallery_settings;
    return [
      'id' => $album_id,
      'image_url' => $image_url,
      'title' => $title,
      'author' => $author,
      'description' => $description,
      'url' => $url,
      'medias' => $medias,
    ];
  }

  /**
   * Retrieves the source field configuration from the media entity's source plugin.
   *
   * Logs a warning and skips processing if the source_field
   * configuration is missing.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity being processed.
   *
   * @return string|null
   *   The source field name or NULL if not found.
   */
  private function getSourceField($media) {
    try {
      if (!$media instanceof MediaInterface) {
        \Drupal::logger('album_gallery')->warning('Invalid media entity provided.');
        return NULL;
      }
      $source = $media->getSource();
      if (!$source) {
        \Drupal::logger('album_gallery')->warning('Media entity @id has no source plugin.', ['@id' => $media->id()]);
        return NULL;
      }
      $source_config = $media->getSource()->getConfiguration();
      $source_field = $source_config['source_field'] ?? NULL;
      if (!$source_field) {
        \Drupal::logger('album_gallery')->warning('Media entity @id is missing a source_field configuration.', ['@id' => $media->id()]);
      }
      return $source_field;
    }
    catch (\Exception $e) {
      \Drupal::logger('album_gallery')->error('Error retrieving source field for media @id: @error', ['@id' => $media->id(), '@error' => $e->getMessage()]);
      return NULL;
    }
  }

  /**
   * Get media image URL.
   */
  protected function getMediaImageUrl($row, $field_name) {
    if (empty($field_name)) {
      return '';
    }

    if (!isset($row->_entity) || !$row->_entity instanceof EntityInterface) {
      return '';
    }

    $source_field = $this->getSourceField($row->_entity->get($field_name)->entity);
    if (!$source_field) {
      \Drupal::logger('album_gallery')->warning('Media @media does not have a valid source field.', ['@media' => $row->_entity->id()]);
      return '';
    }

    try {
      $media_item = $row->_entity->get($field_name)->entity ?? NULL;
      if ($media_item instanceof MediaInterface) {
        switch ($media_item->getSource()->getPluginId()) {
          case 'image':
            if ($media_item->hasField($source_field) && !$media_item->get($source_field)->isEmpty()) {
              $file = $media_item->get($source_field)->entity;
              if ($file) {
                // Vérifier si un style d'image est défini dans les options.
                if (!empty($this->options['image']['image_thumbnail_style'])) {
                  $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                  if ($image_style) {
                    // Générer l'URL avec le style d'image.
                    return $image_style->buildUrl($file->getFileUri());
                  }
                }
                // Fallback: URL originale si aucun style n'est défini.
                return $this->fileUrlGenerator->generateAbsoluteString($file->getFileUri());
              }
              else {
                return $this->getDefaultImageUrl('Document_error.png');
              }
            }
            break;

          case 'video_file':
            if ($media_item->hasField($source_field) && !$media_item->get($source_field)->isEmpty()) {
              $thumbnail = $media_item->get('thumbnail')->entity;
              if ($thumbnail && $thumbnail instanceof FileInterface) {
                // Appliquer aussi le style aux miniatures vidéo si besoin.
                if (!empty($this->options['image']['image_thumbnail_style'])) {
                  $image_style = ImageStyle::load($this->options['image']['image_thumbnail_style']);
                  if ($image_style) {
                    return $image_style->buildUrl($thumbnail->getFileUri());
                  }
                }
                return $this->fileUrlGenerator->generateAbsoluteString($thumbnail->getFileUri());
              }
              else {
                return $this->getDefaultImageUrl('Video_error.png');
              }
            }
            else {
              return $this->getDefaultImageUrl('Video.png');
            }
            break;
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('album_gallery')->error('Media field error: @error', ['@error' => $e->getMessage()]);
    }

    return '';
  }

  /**
   * Helper method to get default image URLs.
   */
  protected function getDefaultImageUrl($filename) {
    return \Drupal::request()->getSchemeAndHttpHost() . '/' .
            \Drupal::service('extension.list.module')->getPath('lightgallery') .
            '/images/' . $filename;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $image_field = $form_state->getValue(['style_options', 'image', 'image_field']);
    $handlers = $this->displayHandler->getHandlers('field');

    if (empty($image_field) || !isset($handlers[$image_field])) {
      $form_state->setErrorByName('image_field', $this->t('You must select a valid image/media field.'));
      return;
    }

    // Retrieve the entity type and bundle from the view.
    // e.g., 'node'.
    $entity_type = $this->view->storage->get('base_table');
    if ($entity_type === 'node_field_data') {
      $entity_type = 'node';
    }
    elseif ($entity_type === 'media_field_data') {
      $entity_type = 'media';
    }
    // e.g., 'article'.
    $bundle = $this->view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      // Take the first bundle if it's an array.
      $bundle = reset($bundle);
    }

    // Load the field definition.
    if ($entity_type && $bundle) {
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type, $bundle);
      if (isset($field_definitions[$image_field])) {
        $field_def = $field_definitions[$image_field];
        $type = $field_def->getType();
        if (
              $type !== 'image' &&
              !($type === 'entity_reference' && $field_def->getSetting('target_type') === 'media')
          ) {
          $form_state->setErrorByName('image_field', $this->t('The selected field must be an image or a media reference.'));
        }
      }
    }
  }

  /**
   * Get text and media fields from the view.
   * 1. Determine entity type and bundle from the view.
   * 2. Load field definitions for the entity and bundle.
   * 3. Browse fields in the view and match with definitions.
   * 4. Test field type.
   */
  protected function getTextAndMediaFields(ViewExecutable $view) {
    $text_fields = [];
    $media_fields = [];
    $taxo_fields = [];

    // 1. Determine entity type and bundle from the view.
    $base_table = $view->storage->get('base_table');
    $entity_type_id = NULL;
    $table_to_entity = [
      'node_field_data' => 'node',
      'media_field_data' => 'media',
      'user_field_data' => 'user',
      'taxonomy_term_field_data' => 'taxonomy_term',
      // Add other mappings as needed.
    ];
    if (isset($table_to_entity[$base_table])) {
      $entity_type_id = $table_to_entity[$base_table];
    }
    else {
      // Fallback: search in entity type definitions.
      foreach (\Drupal::entityTypeManager()->getDefinitions() as $id => $definition) {
        if ($definition->getBaseTable() === $base_table) {
          $entity_type_id = $id;
          break;
        }
      }
    }
    if (!$entity_type_id) {
      return [$text_fields, $media_fields, $taxo_fields];
    }

    // Retreive bundle from view filters options.
    $bundle = $view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      $bundle = reset($bundle);
    }
    if (!$bundle) {
      return [$text_fields, $media_fields, $taxo_fields];
    }

    // 2. load field definitions for the entity and bundle.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);

    // 3. Browse fields in the view and match with definitions.
    foreach ($view->display_handler->getHandlers('field') as $field_id => $handler) {
      $field_name = $handler->field ?? NULL;
      if ($field_name && isset($field_definitions[$field_name])) {
        $field_def = $field_definitions[$field_name];
        $type = $field_def->getType();

        // 4. test field type.
        if (in_array($type, ['string', 'text', 'text_long', 'text_with_summary'])) {
          $text_fields[$field_name] = (string) $field_def->getLabel();
        }
        elseif (
              $type === 'entity_reference' &&
              $field_def->getSetting('target_type') === 'media'
          ) {
          $media_fields[$field_name] = (string) $field_def->getLabel();
        }
        elseif (
              $type === 'entity_reference' &&
              $field_def->getSetting('target_type') === 'taxonomy_term'
          ) {
          $taxo_fields[$field_name] = (string) $field_def->getLabel();
        }
      }
    }

    return [$text_fields, $media_fields, $taxo_fields];
  }

  /**
   * Retrieves the value of a field for a specific row in the view.
   *
   * @param int $index
   *   The row index.
   * @param string $field
   *   The field name.
   *
   * @return mixed
   *   The field value.
   */
  public function getFieldValue($index, $field) {
    $filters = $this->view->display_handler->getOption('filters');

    $bundle = NULL;
    foreach (['type', 'bundle', 'media_bundle'] as $key) {
      if (!empty($filters[$key]['value'])) {
        $bundle = $filters[$key]['value'];
        if (is_array($bundle)) {
          $bundle = reset($bundle);
        }
        break;
      }
    }

    // 1. Retrieve base table.
    $base_table = $this->view->storage->get('base_table');

    // 2. table to entity type mapping.
    $table_to_entity = [
      'node_field_data' => 'node',
      'media_field_data' => 'media',
      'user_field_data' => 'user',
      'taxonomy_term_field_data' => 'taxonomy_term',
      // Add other mappings as needed.
    ];
    $entity_type_id = $table_to_entity[$base_table] ?? $base_table;

    // 3. Get the row entity directly.
    $row_entity = $this->view->result[$index]->_entity ?? NULL;
    if (!$row_entity || !$row_entity->hasField($field)) {
      return '';
    }

    // 4. Get field definition.
    $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity_type_id, $bundle);
    $field_definition = $field_definitions[$field] ?? NULL;

    if (!$field_definition) {
      return '';
    }

    $type = $field_definition->getType();

    // Text field - render via field handler if exists, otherwise get raw value.
    if (in_array($type, ['string', 'text', 'text_long', 'text_with_summary'])) {
      // Check if field is in view's field handlers.
      if (isset($this->view->field[$field])) {
        $this->view->row_index = $index;
        $value = $this->view->field[$field]->getValue($this->view->result[$index]);
        unset($this->view->row_index);
        return $value;
      }
      // Fallback: get raw value from entity.
      else {
        $field_value = $row_entity->get($field);
        if (!$field_value->isEmpty()) {
          return $field_value->first()->getValue()['value'] ?? '';
        }
      }
    }
    // Taxonomy term reference field.
    elseif ($type === 'entity_reference' && $field_definition->getSetting('target_type') === 'taxonomy_term') {
      $labels = [];
      foreach ($row_entity->get($field) as $item) {
        if ($item->entity) {
          $labels[] = $item->entity->label();
        }
      }
      // Array of labels to comma-separated string.
      return implode(', ', $labels);
    }

    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);

    // Debug: Log what we're trying to save.
    $values = $form_state->getValues();
    \Drupal::logger('album_flexbox_gallery')->info('Form values: @values', [
      '@values' => json_encode($values['style_options']['grouping'] ?? []),
    ]);
  }

  /**
   * {@inheritdoc}
   * Override to ensure the value used for grouping is a
   * simple string, thus avoiding the 'TypeError' caused by using a
   * Markup object as an array key.
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {

    // 1. Force the use of the raw field value for grouping.
    $cleaned_grouping = $groupings;
    if (!empty($cleaned_grouping)) {
      foreach ($cleaned_grouping as $key => $grouping_info) {
        if (is_array($grouping_info) && isset($grouping_info['rendered'])) {
          // This line is crucial: it tells the parent method not to use the HTML rendering.
          $cleaned_grouping[$key]['rendered'] = FALSE;
        }
      }
    }

    // 2. Avoid executing the backward compatibility block (where the error occurs).
    // This forces $group_rendered to a non-NULL value, disabling the buggy loop.
    $group_rendered_fixed = $group_rendered ?? FALSE;

    // Call the parent method with the corrected parameters.
    return parent::renderGrouping($records, $cleaned_grouping, $group_rendered_fixed);
  }

}
