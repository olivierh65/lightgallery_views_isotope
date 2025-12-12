<?php

namespace Drupal\lightgallery_views_isotope\Plugin\views\style;

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

/**
 * Album Gallery style plugin.
 *
 * @ViewsStyle(
 *   id = "album_isotope_gallery",
 *   title = @Translation("Album Isotope Gallery"),
 *   help = @Translation("Displays albums with an Isotope layout."),
 *   theme = "album_isotope_gallery",
 *   display_types = {"normal"}
 * )
 */
class AlbumIsotopeGallery extends StylePluginBase {
  use \Drupal\lightgallery_settings_ui\Traits\LightGallerySettingsTrait;

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
    // Tolerance for thumbnail size in percentage.
        'thumbnail_size_tolerance' => 10,
        'layout' => 'packery',
        'captions' => TRUE,
      ],
    ];
    $options['gallery'] = [
      'default' => [
        'width' => '30%',
        'columnWidth' => '',
        'rowHeight' => '',
        'gutter' => 5,
        'horizontal' => FALSE,
        'horizontalOrder' => TRUE,
        'fitWidth' => FALSE,
        'border' => TRUE,
      ],
    ];

    // don't add grouping options as they are already defined in the parent class
    // when $this->usesGrouping = TRUE;.
    /* $options['grouping'] = [
    'default' => [],
    'contains' => [
    0 => [
    'default' => ['field' => '', 'rendered' => FALSE, 'rendered_strip' => FALSE],
    'contains' => [
    'field' => ['default' => ''],
    'rendered' => ['default' => FALSE],
    'rendered_strip' => ['default' => FALSE],
    ],
    ],
    1 => [
    'default' => ['field' => '', 'rendered' => FALSE, 'rendered_strip' => FALSE],
    'contains' => [
    'field' => ['default' => ''],
    'rendered' => ['default' => FALSE],
    'rendered_strip' => ['default' => FALSE],
    ],
    ],
    ],
    ]; */

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

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

    $form['image']['thumbnail_size_tolerance'] = [
      '#type' => 'number',
      '#title' => $this->t('Thumbnail size tolerance'),
      '#default_value' => $this->options['gallery']['thumbnail_size_tolerance'],
      '#description' => $this->t('Percentage tolerance for thumbnail size.'),
    ];

    $form['image']['border'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Border'),
      '#default_value' => $this->options['gallery']['border'] ?? TRUE,
      '#description' => $this->t('Border around each items?'),
    ];

    $form['image']['captions'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display captions'),
      '#default_value' => $this->options['gallery']['captions'] ?? TRUE,
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

    // don't add grouping options as they are already defined in the parent class
    // when $this->usesGrouping = TRUE;.
    /* // Grouping options.
    $form['grouping'] = [
    '#type' => 'details',
    '#title' => $this->t('Grouping settings'),
    '#description' => $this->t('Configure grouping by taxonomy terms.'),
    '#open' => FALSE,
    '#tree' => TRUE,
    ];

    // Assurez-vous que les options 0 et 1 existent.
    $this->options['grouping'][0] = $this->options['grouping'][0] ?? ['field' => '', 'rendered' => FALSE, 'rendered_strip' => FALSE];
    $this->options['grouping'][1] = $this->options['grouping'][1] ?? ['field' => '', 'rendered' => FALSE, 'rendered_strip' => FALSE];

    $form['grouping'][0]['field'] = [
    '#type' => 'select',
    '#title' => $this->t('First grouping field'),
    '#options' => ['' => $this->t('- None -')] + $fields_taxo,
    // Notez l'accès direct aux indices numériques de Views.
    '#default_value' => $this->options['grouping'][0]['field'],
    '#description' => $this->t('Select a taxonomy field to group items (first level).'),
    ];

    $form['grouping'][1]['field'] = [
    '#type' => 'select',
    '#title' => $this->t('Second grouping field'),
    '#options' => ['' => $this->t('- None -')] + $fields_taxo,
    '#default_value' => $this->options['grouping'][1]['field'],
    '#description' => $this->t('Select a taxonomy field to group items (second level).'),
    ];
     */
    // Options for the Gallery.
    $form['gallery'] = [
      '#type' => 'details',
      '#title' => $this->t('Gallery settings'),
      '#description' => $this->t('Configure the gallery settings.'),
      '#open' => TRUE,
      '#tree' => TRUE,
    ];
    $form['gallery']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'packery' => 'Packery',
        'masonry' => 'Masory',
        'fitRows' => 'fit Rows',
        'vertical' => 'Vertical',
      ],
      '#default_value' => $this->options['gallery']['layout'],
    ];

    $form['gallery']['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#default_value' => $this->options['gallery']['width'],
      '#description' => $this->t('Width of the gallery items. Use CSS units like %, px, em, etc.'),
    ];

    $form['gallery']['columnWidth'] = [
      '#type' => 'number',
      '#title' => $this->t('Columns width (px)'),
      '#default_value' => $this->options['gallery']['columnWidth'] ?? '30%',
      '#states' => [
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => [
                      ['value' => 'packery'],
                      ['value' => 'masonry'],
          ],
        ],
      ],
    ];

    $form['gallery']['rowHeight'] = [
      '#type' => 'number',
      '#title' => $this->t('Row height (px)'),
      '#default_value' => $this->options['gallery']['rowHeight'] ?? '',
      '#description' => $this->t('Aligns items to the height of a row of a vertical grid.'),
      '#states' => [
    // This field will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => ['value' => 'packery'],
        ],
      ],
    ];

    $form['gallery']['gutter'] = [
      '#type' => 'number',
      '#title' => $this->t('Gutter'),
      '#default_value' => $this->options['gallery']['gutter'],
      '#description' => $this->t('The horizontal space between item elements.'),
    ];

    $form['gallery']["horizontal"] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Horizontal order'),
      '#default_value' => $this->options['gallery']['horizontal'] ?? FALSE,
      '#description' => $this->t('Arranges items horizontally instead of vertically.'),
      '#states' => [
    // This field will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => ['value' => 'packery'],
        ],
      ],
    ];

    $form['gallery']['horizontalOrder'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Horizontal order'),
      '#default_value' => $this->options['gallery']['horizontalOrder'] ?? 0,
      '#description' => $this->t('Lays out items to (mostly) maintain horizontal left-to-right order.'),
      '#states' => [
    // This field will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => [
                      ['value' => 'masonry'],
                      ['value' => 'vertical'],
          ],
        ],
      ],
    ];

    $form['gallery']['fitWidth'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Fit width'),
      '#default_value' => $this->options['gallery']['fitWidth'] ?? FALSE,
      '#description' => $this->t('Sets the width of the container to fit the available number of columns.'),
      '#states' => [
    // This filed will be visible only if...
        'visible' => [
          ':input[name="style_options[gallery][layout]"]' => ['value' => 'masonry'],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $id = rand();

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#rows' => [],
      '#attributes' => [
        'class' => ['album-isotope-gallery', 'lightgallery-init'],
      ],
      '#attached' => [
        'library' => [
          'lightgallery/lightgallery',
          'lightgallery_views_isotope/isotope',
        ],
        'drupalSettings' => [
          'settings' => [
            'lightgallery' => [
              'inline' => FALSE,
              'galleryId' => $id,
        // Sera rempli par processGroupRecursive.
              'albums' => [],
            ],
            'layout' => [
              'width' => $this->options['gallery']['width'] ?? '30%',
              'columnWidth' => $this->options['gallery']['columnWidth'] ?? '',
              'rowHeight' => $this->options['gallery']['rowHeight'] ?? '',
              'gutter' => $this->options['gallery']['gutter'] ?? 5,
              'horizontal' => $this->options['gallery']['horizontal'] ?? FALSE,
              'horizontalOrder' => $this->options['gallery']['horizontalOrder'] ?? TRUE,
              'fitWidth' => $this->options['gallery']['fitWidth'] ?? FALSE,
              'layout' => $this->options['gallery']['layout'] ?? 'packery',
            ],
          ],
        ],
      ],
    ];

    // Obtenir les résultats groupés de Views.
    $grouped_rows = $this->renderGrouping(
    $this->view->result,
    $this->options['grouping'],
    TRUE
    );

    // Traiter récursivement la structure groupée.
    $build['#groups'] = $this->processGroupRecursive($grouped_rows, $build);

    $build['#options'] += [
      'border' => $this->options['image']['border'] ?? TRUE,
      'captions' => $this->options['image']['captions'] ?? TRUE,
      'thumbnail_size_tolerance' => $this->options['image']['thumbnail_size_tolerance'],
    ];

    unset($this->view->row_index);
    return $build;
  }

  /**
   * Traite récursivement la structure de grouping de Views.
   *
   * @param array $groups
   *   La structure de grouping retournée par renderGrouping().
   * @param array &$build
   *   Le build array (passé par référence pour ajouter les settings).
   * @param int $depth
   *   Profondeur actuelle (pour debug/styling).
   *
   * @return array
   *   Structure normalisée pour Twig.
   */
  private function processGroupRecursive(array $groups, array &$build, int $depth = 0) {
    $processed = [];

    foreach ($groups as $group_key => $group_data) {
      $group_item = [
        'title' => $group_data['group'] ?? '',
        'level' => $group_data['level'] ?? $depth,
        'albums' => [],
        'subgroups' => [],
      ];

      // Vérifier si ce groupe contient des rows (résultats finaux)
      if (isset($group_data['rows']) && is_array($group_data['rows'])) {

        // Déterminer si les "rows" sont en fait d'autres groupes ou des vraies rows.
        $first_row = reset($group_data['rows']);

        if (is_array($first_row) && isset($first_row['group']) && isset($first_row['level'])) {
          // Ce sont des sous-groupes, traiter récursivement.
          $group_item['subgroups'] = $this->processGroupRecursive(
          $group_data['rows'],
          $build,
          $depth + 1
          );
        }
        else {
          // Ce sont des vraies rows (ResultRow), traiter les albums.
          foreach ($group_data['rows'] as $index => $row) {
            $album_data = $this->buildAlbumData($row, $index);

            if ($album_data) {
              $group_item['albums'][] = $album_data;

              // Ajouter les settings lightgallery au build.
              $build['#attached']['drupalSettings']['settings']['lightgallery']['albums'][$album_data['id']] =
              $album_data['lightgallery_settings'];

              // Ajouter les librairies des plugins.
              foreach ($album_data['lightgallery_settings']['plugins'] ?? [] as $plugin_name => $plugin) {
                $build['#attached']['library'][] = 'lightgallery/lightgallery-' . $plugin_name;
              }
            }
          }
        }
      }

      $processed[] = $group_item;
    }

    return $processed;
  }

  /**
   * Construit les données d'un album à partir d'une row.
   *
   * @param object $row
   *   La row de la vue (ResultRow).
   * @param int $index
   *   L'index de la row.
   *
   * @return array|null
   *   Les données de l'album ou NULL si erreur.
   */
  private function buildAlbumData($row, $index) {
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

    $album_id = 'album-item-' . rand();

    return [
      'id' => $album_id,
      'image_url' => $image_url,
      'title' => $title,
      'author' => $author,
      'description' => $description,
      'url' => $url,
      'medias' => $medias,
      'max_width' => $max_width,
      'max_height' => $max_height,
      'lightgallery_settings' => self::getGeneralSettings($node_settings),
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
   *
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
   *
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    $image_field = $form_state->getValue(['style_options', 'image', 'image_field']);
    $handlers = $this->displayHandler->getHandlers('field');

    if (empty($image_field) || !isset($handlers[$image_field])) {
      $form_state->setErrorByName('image_field', $this->t('You must select a valid image/media field.'));
      return;
    }

    // Récupérer le type d'entité et le bundle depuis la vue.
    // ex: 'node'.
    $entity_type = $this->view->storage->get('base_table');
    if ($entity_type === 'node_field_data') {
      $entity_type = 'node';
    }
    elseif ($entity_type === 'media_field_data') {
      $entity_type = 'media';
    }
    // ex: 'article'.
    $bundle = $this->view->display_handler->getOption('filters')['type']['value'] ?? NULL;
    if (is_array($bundle)) {
      // Prendre le premier bundle si c'est un tableau.
      $bundle = reset($bundle);
    }

    // Charger la définition du champ.
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
   *
   */
  protected function getTextAndMediaFields(ViewExecutable $view) {
    $text_fields = [];
    $media_fields = [];
    $taxo_fields = [];

    // 1. determine entity type and bundle from the view.
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
    \Drupal::logger('album_isotope')->info('Form values: @values', [
      '@values' => json_encode($values['style_options']['grouping'] ?? []),
    ]);
  }

  /**
   * {@inheritdoc}
   * Surcharge pour garantir que la valeur utilisée pour le regroupement est une
   * chaîne simple, évitant ainsi la 'TypeError' due à l'utilisation d'un objet
   * Markup comme clé de tableau.
   */
  public function renderGrouping($records, $groupings = [], $group_rendered = NULL) {

    // 1. Force l'utilisation de la valeur brute du champ pour le regroupement.
    $cleaned_grouping = $groupings;
    if (!empty($cleaned_grouping)) {
      foreach ($cleaned_grouping as $key => $grouping_info) {
        if (is_array($grouping_info) && isset($grouping_info['rendered'])) {
          // Cette ligne est cruciale : elle dit à la méthode parente de ne pas utiliser le rendu HTML.
          $cleaned_grouping[$key]['rendered'] = FALSE;
        }
      }
    }

    // 2. Évite l'exécution du bloc de rétrocompatibilité (où l'erreur se produit).
    // Ceci force $group_rendered à une valeur non-NULL, désactivant la boucle boguée.
    $group_rendered_fixed = $group_rendered ?? FALSE;

    // Appel de la méthode parente avec les paramètres corrigés.
    return parent::renderGrouping($records, $cleaned_grouping, $group_rendered_fixed);
  }

}
