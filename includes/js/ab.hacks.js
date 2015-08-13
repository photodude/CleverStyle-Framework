// Generated by CoffeeScript 1.9.3

/**
 * @package		CleverStyle CMS
 * @author		Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright	Copyright (c) 2014-2015, Nazar Mokrynskyi
 * @license		MIT License, see license.txt
 */

(function() {
  (function($) {

    /*
    	  * Fix for jQuery "ready" event, trigger it after "WebComponentsReady" event triggered by WebComponents.js
     */
    var functions, ready, ready_original;
    ready_original = $.fn.ready;
    functions = [];
    ready = false;
    $.fn.ready = function(fn) {
      return functions.push(fn);
    };
    document.addEventListener('WebComponentsReady', function() {
      if (!ready) {
        ready = true;
        $.fn.ready = ready_original;
        functions.forEach(function(fn) {
          return $(fn);
        });
        return functions = [];
      }
    });
    return $(function() {
      var registerOuterClick__original;
      registerOuterClick__original = UIkit.components.dropdown.prototype.registerOuterClick;
      return UIkit.components.dropdown.prototype.registerOuterClick = function() {
        if (!WebComponents.flags.shadow && this.element[0].matches(':host *')) {
          $(this.element[0]).find('li').one('click', function(e) {
            return UIkit.$html.trigger("click.outer.dropdown", e);
          });
        }
        return registerOuterClick__original.call(this);
      };
    });
  })(jQuery);

  Polymer.Base._addFeature({
    behaviors: [
      {
        properties: {
          L: {
            type: Object,
            value: cs.Language
          }
        },
        __: function(key) {
          if (arguments.length === 1) {
            return cs.Language.get(key);
          } else {
            return cs.Language.format.apply(cs.Language, arguments);
          }
        }
      }
    ]
  });

}).call(this);
