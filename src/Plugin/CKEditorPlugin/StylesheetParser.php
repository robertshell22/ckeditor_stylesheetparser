<?php

namespace Drupal\ckeditor_stylesheetparser\Plugin\CKEditorPlugin;

use Drupal;
use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\Core\Plugin\PluginBase;
use Drupal\ckeditor\CKEditorPluginInterface;
use Drupal\ckeditor\CKEditorPluginButtonsInterface;
use Drupal\ckeditor\CKEditorPluginContextualInterface;
use Drupal\editor\Entity\Editor;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension;
use Drupal\Core\Asset\LibraryDiscoveryInterface;

/**
 * Defines the "Stylesheet Parser" plugin.
 *
 * @CKEditorPlugin(
 *   id = "stylesheetparser",
 *   label = @Translation("Stylesheet Parser"),
 *   module = "ckeditor_stylesheetparser"
 * )
 */
class StylesheetParser extends CKEditorPluginBase implements CKEditorPluginConfigurableInterface {

  /**
   * Drupal\Core\Asset\LibraryDiscoveryInterface definition.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * Implements
   * \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getDependencies().
   */
  function getDependencies(Editor $editor) {
    return [];
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::isInternal().
   */
  function isInternal() {
    return FALSE;
  }

  /**
   * Implements
   * \Drupal\ckeditor\Plugin\CKEditorPluginContextualInterface::isEnabled().
   * Checks if this plugin should be enabled based on the editor configuration.
   */
  public function isEnabled(Editor $editor) {
    return TRUE;
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getFile().
   * Returns the Drupal root-relative file path to the plugin JavaScript file.
   */
  public function getFile() {
    return drupal_get_path('module', 'ckeditor_stylesheetparser') . '/js/plugins/stylesheetparser/plugin.js';
  }

  /**
   * Implements \Drupal\ckeditor\Plugin\CKEditorPluginInterface::getConfig().
   * Returns the additions to CKEDITOR.config for a specific CKEditor instance.
   */
  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    $config = [];
    $settings = $editor->getSettings();

    if (!isset($settings['plugins']['stylesheetparser']['stylesheet'])) {
      return $config;
    }
    $stylesheet = $settings['plugins']['stylesheetparser']['stylesheet'];
    $config['styleSheets'] = $this->getStyleSheetSetting($stylesheet);

    if (!isset($settings['plugins']['stylesheetparser']['styles'])) {
      return $config;
    }
    $skipselectors = $settings['plugins']['stylesheetparser']['skipselectors'];
    $config['stylesheetParser_validSelectors'] = $this->getSkipSelectorsSetting($skipselectors);

    if (!isset($settings['plugins']['stylesheetparser']['styles'])) {
      return $config;
    }
    $validselectors = $settings['plugins']['stylesheetparser']['validselectors'];
    $config['stylesheetParser_validSelectors'] = $this->getValidSelectorsSetting($validselectors);

    if (!isset($settings['plugins']['stylesheetparser']['styles'])) {
      return $config;
    }
    $styles = $settings['plugins']['stylesheetparser']['styles'];
    $config['stylesSet'] = $this->generateStylesSetSetting($styles);
  }

  /**
   * Implements
   * \Drupal\ckeditor\Plugin\CKEditorPluginButtonsInterface::getButtons().
   * Returns the buttons that this plugin provides, along with metadata.
   */
  public function getButtons() {
    return [
      'StylesheetParser' => [
        'label' => t('Parse a stylesheet for CSS classes.'),
        'image' => drupal_get_path('module', 'ckeditor_stylesheetparser') . '/js/plugins/stylesheetparser/icons/hidpi/stylesheet.png',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state, Editor $editor) {
    $config = [
      'stylesheet' => '',
      'skipselectors' => '',
      'validselectors' => '',
      'styles' => '',
    ];
    $settings = $editor->getSettings();

    if (isset($settings['plugins']['stylesheetparser'])) {
      $config = $settings['plugins']['stylesheetparser'];
    }

    $stylesheet = $this->getStylesheet();
    $skipselectors = $this->getSkipSelectors();
    $validselectors = $this->getValidSelectors();
    natcasesort($skipselectors);
    natcasesort($validselectors);

    $form['#attached']['library'][] = 'ckeditor_stylesheetparser/ckeditor.stylesheetparser.admin';

    $form['stylesheet'] = [
      '#type' => 'select',
      '#title' => 'Stylesheet',
      '#description' => t('The css file defining the styles. By default, it looks in your default theme directory for a css file.'),
      '#attached' => [
        'library' => ['ckeditor_stylesheetparser/ckeditor.stylesheetparser.admin'],
      ],
      '#options' => $stylesheet,
      '#default_value' => !empty($config['stylesheet']) ? $config['stylesheet'] : $this->getDefaultStylesheet(),
    ];

    $form['skipselectors'] = [
      '#type' => 'checkboxes',
      '#title' => 'Skip CSS Selectors',
      '#options' => $skipselectors,
      '#description' => $this->t('Select CSS selectors to skip from the selected stylesheet.'),
      '#default_value' => !empty($config['skipselectors']) ? $config['skipselectors'] : $this->getDefaultSkipSelectors(),
    ];

    $form['validselectors'] = [
      '#type' => 'checkboxes',
      '#title' => 'Valid CSS Selectors',
      '#options' => $skipselectors,
      '#description' => $this->t('Select CSS selectors to include from the selected stylesheet.'),
      '#default_value' => !empty($config['validselectors']) ? $config['validselectors'] : $this->getDefaultValidSelectors(),
    ];

    $form['styles'] = [
      '#title' => $this->t('Styles'),
      '#title_display' => 'invisible',
      '#type' => 'hidden',
      '#default_value' => !empty($form['stylesheet']) ? file_get_contents($form['stylesheet']) : file_get_contents($this->getDefaultStylesheet()),
      '#element_validate' => [
        [$this, 'validateStylesValue'],
      ],
    ];

    return $form;
  }

  /**
   * #element_validate handler for the "styles" element in settingsForm().
   */
  public function validateStylesValue(array $element, FormStateInterface $form_state) {
    $styles_setting = $this->generateStylesSetSetting($element['#value']);
    if ($styles_setting === FALSE) {
      $form_state->setError($element, $this->t('The provided list of styles is syntactically incorrect.'));
    }
    else {
      $style_names = array_map(function ($style) {
        return $style['name'];
      }, $styles_setting);
      if (count($style_names) !== count(array_unique($style_names))) {
        $form_state->setError($element, $this->t('Each style must have a unique label.'));
      }
    }
  }

  /**
   * Return an array of stylesheets from installed themes..
   *
   * This should be used when presenting selector options to the user in a form
   * element.
   *
   * @return array
   *   Set of stylesheet filenames.
   */
  private function getStylesheets() {
    $themes = [];
    $stylesheet_options = [];
    foreach ($this->listInfo() as $name => $theme) {
      $themes[$name] = $this->root . '/' . $theme
          ->getPath();
      $stylesheets = preg_grep('/\.css/', scandir($themes[$name]));
      foreach ($stylesheets as $stylesheet) {
        if (file_exists(DRUPAL_ROOT . '/' . $stylesheet)) {
          $stylesheet_name = str_replace('.css', '', $stylesheet);
          $stylesheet_options[$stylesheet_name] = [$stylesheet => $stylesheet_name];
        }
        elseif (file_exists(DRUPAL_ROOT . '/' . $stylesheet)) {
          $defaultThemeName = Drupal::service('theme.manager')
            ->getActiveTheme()
            ->getName();
          $defaultThemePath = DRUPAL_ROOT . '/' . drupal_get_path('theme', $defaultThemeName);
          $stylesheets = preg_grep('/\.css/', scandir($defaultThemePath));
          foreach ($stylesheets as $stylesheet) {
            if (file_exists(DRUPAL_ROOT . '/' . $stylesheet)) {
              $stylesheet_name = str_replace('.css', '', $stylesheet);
              $stylesheet_options[$stylesheet_name] = [$stylesheet => $stylesheet_name];
            }
          }
        }
        else {
          $defaultstylesheet = drupal_get_path('module', 'ckeditor_stylesheetparser') . '/js/plugins/stylesheetparser/samples/assets/sample.css';
          $stylesheet = str_replace('.css', '', $defaultstylesheet);
          if (file_exists(DRUPAL_ROOT . '/' . $stylesheet)) {
            $stylesheet_name = str_replace('.css', '', $stylesheet);
            $stylesheet_options[$stylesheet_name] = [$stylesheet => $stylesheet_name];
          }
        }
      }
    }
    return $stylesheet_options;
  }

  /**
   * Return the default stylesheet if one is not set in active config.
   *
   * This will be * the first one in the list of stylesheets returned from
   * getStylesheets().
   *
   * @return string
   *   Default stylesheet.
   */
  private function getDefaultStylesheet() {
    $stylesheet = $this->getStylesheets();
    return reset($stylesheet);
  }

  /**
   * Return ckeditor stylesheetparser plugin path relative to drupal root.
   *
   * @return string
   *   Relative path to the ckeditor stylesheetparser plugin folder
   */
  private function getPluginPath() {
    $pluginPath = drupal_get_path('module', 'ckeditor_stylesheetparser') . '/js/plugins/stylesheetparser';

    return $pluginPath;
  }

  /**
   * Return an array of CSS Skip selectors.
   *
   * This is used to set the list of checkboxes to be set as all TRUE when first
   * configuring the plugin.
   *
   * @return array
   *   Default CSS Skip selectors.
   */
  private function getDefaultSkipSelectors() {
    $skipselectors = array_keys($this->getSkipSelectors());
    return array_combine($skipselectors, $skipselectors);
  }

  /**
   * Return an array of CSS Skip selectors.
   *
   * This should be used when presenting selector options to the user in a form
   * element.
   *
   * @return array
   *   Set of CSS Skip selectors.
   */
  private function getSkipSelectors() {
    return [
      'body' => '<body>',
      'div' => '<div>',
      'blockquote' => '<blockquote>',
      'section' => '<section>',
      'html' => '<html>',
    ];
  }

  /**
   * Return an array of CSS Valid selectors.
   *
   * This is used to set the list of checkboxes to be set as all TRUE when first
   * configuring the plugin.
   *
   * @return array
   *   Default CSS Valid selectors.
   */
  private function getDefaultValidSelectors() {
    $validselectors = array_keys($this->getValidSelectors());
    return array_combine($validselectors, $validselectors);
  }

  /**
   * Return an array of CSS Valid selectors.
   *
   * This should be used when presenting selector options to the user in a form
   * element.
   *
   * @return array
   *   Set of CSS Valid selectors.
   */
  private function getValidSelectors() {
    return [
      'p' => '<p>',
      'span' => '<span>',
      'h1' => 'h1',
      'h2' => 'h2',
      'h3' => 'h3',
      'h4' => 'h4',
      'h5' => 'h5',
      'h6' => 'h6',
      'div' => '<div>',
      'strong' => '<strong>',
    ];
  }

  /**
   * Builds the "stylesSet" configuration part of the CKEditor JS settings.
   *
   * @param string $styles
   *   The "styles" setting.
   *
   * @return array|false
   *   An array containing the "stylesSet" configuration, or FALSE when the
   *   syntax is invalid.
   * @see getConfig()
   *
   */
  protected function generateStylesSetSetting($styles) {
    $styles_set = [];

    // Early-return when empty.
    $styles = trim($styles);
    if (empty($styles)) {
      return $styles_set;
    }

    $styles = str_replace(["\r\n", "\r"], "\n", $styles);
    foreach (explode("\n", $styles) as $style) {
      $style = trim($style);

      // Ignore empty lines in between non-empty lines.
      if (empty($style)) {
        continue;
      }

      // Validate syntax: element[.class...]|label pattern expected.
      if (!preg_match('@^ *[a-zA-Z0-9]+ *(\\.[a-zA-Z0-9_-]+ *)*\\| *.+ *$@', $style)) {
        return FALSE;
      }

      // Parse.
      list($selector, $label) = explode('|', $style);
      $classes = explode('.', $selector);
      $element = array_shift($classes);

      // Build the data structure CKEditor's stylescombo plugin expects.
      // @see https://ckeditor.com/docs/ckeditor4/latest/guide/dev_howtos_styles.html
      $configured_style = [
        'name' => trim($label),
        'element' => trim($element),
      ];
      if (!empty($classes)) {
        $configured_style['attributes'] = [
          'class' => implode(' ', array_map('trim', $classes)),
        ];
      }
      $styles_set[] = $configured_style;
    }
    return $styles_set;
  }

}