// Generated by LiveScript 1.4.0
/**
 * @package   CleverStyle Widgets
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer.cs.behaviors.csTextarea = [
    Polymer.cs.behaviors.ready, Polymer.cs.behaviors.size, Polymer.cs.behaviors['this'], Polymer.cs.behaviors.tooltip, Polymer.cs.behaviors.value, {
      properties: {
        autosize: {
          observer: '_autosize_changed',
          reflectToAttribute: true,
          type: Boolean
        },
        initialized: Boolean
      },
      attached: function(){
        this.initialized = true;
        this._when_ready(bind$(this, '_do_autosizing'));
      },
      _autosize_changed: function(){
        this._do_autosizing();
      },
      _do_autosizing: function(){
        if (!this.initialized || this.autosize === undefined) {
          return;
        }
        if (window.autosize) {
          this._do_autosizing_callback(autosize);
        } else if (window.require) {
          require(['autosize'], bind$(this, '_do_autosizing_callback'));
        }
      },
      _do_autosizing_callback: function(autosize){
        if (this.autosize) {
          autosize(this);
          autosize.update(this);
        } else {
          autosize.destroy(this);
        }
      }
    }
  ];
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);