// Generated by CoffeeScript 1.9.3

/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */

(function() {
  (function(L) {
    return Polymer({
      tooltip_animation: '{animation:true,delay:200}',
      translations: {
        plugin_name: L.plugin_name,
        state: L.state,
        information_about_plugin: L.information_about_plugin,
        license: L.license,
        click_to_view_details: L.click_to_view_details,
        action: L.action,
        enable: L.enable,
        disable: L.disable
      },
      plugins: [],
      ready: function() {
        var plugins;
        plugins = JSON.parse(this.querySelector('script').innerHTML);
        plugins.forEach(function(plugin) {
          plugin["class"] = plugin.active ? 'uk-alert-success' : 'uk-alert-warning';
          plugin.icon = plugin.active ? 'uk-icon-check' : 'uk-icon-minus';
          plugin.icon_text = plugin.active ? L.enabled : L.disabled;
          plugin.name_localized = L[plugin.name] || plugin.name.replace('_', ' ');
          return (function(meta) {
            if (!meta) {
              return;
            }
            return $(function() {
              return plugin.info = L.plugin_info(meta["package"], meta.version, meta.description, meta.author, meta.website || L.none, meta.license, meta.provide ? [].concat(meta.provide).join(', ') : L.none, meta.require ? [].concat(meta.require).join(', ') : L.none, meta.conflict ? [].concat(meta.conflict).join(', ') : L.none, meta.optional ? [].concat(meta.optional).join(', ') : L.none, meta.multilingual && meta.multilingual.indexOf('interface') !== -1 ? L.yes : L.no, meta.multilingual && meta.multilingual.indexOf('content') !== -1 ? L.yes : L.no, meta.languages ? meta.languages.join(', ') : L.none);
            });
          })(plugin.meta);
        });
        return this.plugins = plugins;
      },
      domReady: function() {
        return $(this.shadowRoot).cs().tooltips_inside();
      },
      generic_modal: function(event, detail, sender) {
        var $sender, index, key, plugin, tag;
        $sender = $(sender);
        index = $sender.closest('[data-plugin-index]').data('plugin-index');
        plugin = this.plugins[index];
        key = $sender.data('modal-type');
        tag = plugin[key].type === 'txt' ? 'pre' : 'div';
        return $("<div class=\"uk-modal-dialog uk-modal-dialog-large\">\n	<div class=\"uk-overflow-container\">\n		<" + tag + ">" + plugin[key].content + "</" + tag + ">\n	</div>\n</div>").appendTo('body').cs().modal('show').on('hide.uk.modal', function() {
          return $(this).remove();
        });
      }
    });
  })(cs.Language);

}).call(this);
