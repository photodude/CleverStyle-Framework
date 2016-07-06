// Generated by LiveScript 1.4.0
/**
 * @package   Blogs
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer({
    'is': 'cs-blogs-admin-general',
    behaviors: [cs.Polymer.behaviors.Language('blogs_')],
    properties: {
      settings: Object,
      settings_api_url: 'api/Blogs/admin'
    },
    ready: function(){
      this._reload_settings();
    },
    _reload_settings: function(){
      var this$ = this;
      cs.api('get_settings ' + this.settings_api_url).then(function(settings){
        this$.set('settings', settings);
      });
    },
    _save: function(){
      var this$ = this;
      cs.api('save_settings ' + this.settings_api_url, this.settings).then(function(){
        this$._reload_settings();
        cs.ui.notify(this$.L.changes_saved, 'success', 5);
      });
    }
  });
}).call(this);