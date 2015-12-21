// Generated by LiveScript 1.4.0
/**
 * @package   TinyMCE
 * @category  plugins
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015, Nazar Mokrynskyi
 * @license   GNU Lesser General Public License 2.1, see license.txt
 */
(function(){
  var ref$;
  ((ref$ = Polymer.cs.behaviors).TinyMCE || (ref$.TinyMCE = {})).editor = {
    listeners: {
      tap: '_style_fix'
    },
    properties: {
      target: {
        observers: '_tinymce_init',
        type: Object
      },
      value: {
        observer: '_value_changed',
        type: String
      }
    },
    ready: function(){
      this.target = this.firstElementChild;
      this._tinymce_init();
    },
    _tinymce_init: function(){
      tinymce.init(importAll$({
        target: this.target
      }, this.editor_config));
    },
    _style_fix: function(){
      var this$ = this;
      [].slice.call(document.querySelectorAll('body > [class^=mce-]')).forEach(function(node){
        this$.scopeSubtree(node, true);
      });
    },
    _value_changed: function(){
      if (this.target.tagName === 'TEXTAREA' && this.target.tinymce_editor && this.value !== this.target.tinymce_editor.getContent()) {
        this.target.tinymce_editor.load();
      }
    }
  };
  function importAll$(obj, src){
    for (var key in src) obj[key] = src[key];
    return obj;
  }
}).call(this);
