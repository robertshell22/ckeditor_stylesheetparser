/**
 * Provides the summary for the "stylesheetparser" plugin settings vertical tab.
 *
 * @type {Drupal~behavior}
 *
 * @prop {Drupal~behaviorAttach} attach
 *   Attaches summary behaviour to the plugin settings vertical tab.
 */
(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.ckeditorStylesComboSettingsSummary = {
    attach: function attach() {
      $('[data-ckeditor-plugin-id="stylesheetparser"]').drupalSetSummary(function (context) {
        var styleSheetValue = $('input[name="editor[settings][plugins][stylessheetparser][stylesheet]').val();
        var stylesheetParser_validSelectors = $('input[name="editor[settings][plugins][stylessheetparser][validselectors]').val();

        var output = '';
        output += Drupal.t('Stylesheet @stylesheet is set, Valid selectors set. @validselectors valid selectors are set. @count styles configured.', {
          '@stylesheet': styleSheetValue,
          '@validselectors': stylesheetParser_validSelectors,
        });
        return output;
      });
    }
  };
})(jQuery, Drupal, drupalSettings, _);