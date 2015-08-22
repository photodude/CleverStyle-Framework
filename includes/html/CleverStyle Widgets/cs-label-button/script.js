// Generated by CoffeeScript 1.9.3

/**
 * @package   CleverStyle Widgets
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */

(function() {
  Polymer({
    'is': 'cs-label-button',
    'extends': 'label',
    properties: {
      active: {
        observer: 'active_changed',
        reflectToAttribute: true,
        type: Boolean
      },
      first: {
        reflectToAttribute: true,
        type: Boolean
      },
      last: {
        reflectToAttribute: true,
        type: Boolean
      }
    },
    ready: function() {
      var fn, i, input, inputs, len, ref, ref1;
      (function(_this) {
        return (function() {
          var next_node, ref, ref1;
          next_node = _this.nextSibling;
          console.log((ref = next_node.nextSibling) != null ? ref.getAttribute('is') : void 0);
          console.log(_this.is);
          if (next_node.nodeType === Node.TEXT_NODE && ((ref1 = next_node.nextSibling) != null ? ref1.getAttribute('is') : void 0) === _this.is) {
            return next_node.parentNode.removeChild(next_node);
          }
        });
      })(this)();
      this.local_input = this.querySelector('input');
      this.active = this.local_input.checked;
      inputs = this.parentNode.querySelectorAll('input[name="' + this.local_input.name + '"]');
      fn = (function(_this) {
        return function(input) {
          input.addEventListener('change', function() {
            _this.value = input.value;
            _this.active = _this.local_input.checked;
          });
        };
      })(this);
      for (i = 0, len = inputs.length; i < len; i++) {
        input = inputs[i];
        fn(input);
      }
      if (((ref = this.previousElementSibling) != null ? ref.is : void 0) !== this.is) {
        this.first = true;
      }
      if (((ref1 = this.nextElementSibling) != null ? ref1.getAttribute('is') : void 0) !== this.is) {
        this.last = true;
      }
    },
    active_changed: function() {
      if (this.local_input.type === 'radio') {
        if (this.active) {
          return this.click();
        }
      } else {
        return this.local_input.checked = this.active;
      }
    }
  });

}).call(this);
