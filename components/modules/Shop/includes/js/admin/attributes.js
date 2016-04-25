// Generated by LiveScript 1.4.0
/**
 * @package   Shop
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  $(function(){
    var L, set_attribute_types, make_modal;
    L = cs.Language('shop_');
    set_attribute_types = [1, 2, 6, 9];
    make_modal = function(types, title, action){
      var res$, index, type;
      res$ = [];
      for (index in types) {
        type = types[index];
        res$.push("<option value=\"" + index + "\">" + type + "</option>");
      }
      types = res$;
      types = types.join('');
      return cs.ui.simple_modal("<form is=\"cs-form\">\n	<h3 class=\"cs-text-center\">" + title + "</h3>\n	<label>" + L.attribute_type + "</label>\n	<select is=\"cs-select\" name=\"type\" required>" + types + "</select>\n	<label>" + L.possible_values + "</label>\n	<textarea is=\"cs-textarea\" autosize name=\"value\"></textarea>\n	<label>" + L.title + "</label>\n	<input is=\"cs-input-text\" name=\"title\" required>\n	<label>" + L.title_internal + "</label>\n	<input is=\"cs-input-text\" name=\"title_internal\" required>\n	<br>\n	<button is=\"cs-button\" primary type=\"submit\">" + action + "</button>\n</form>");
    };
    return $('html').on('mousedown', '.cs-shop-attribute-add', function(){
      $.getJSON('api/Shop/admin/attributes/types', function(types){
        var $modal;
        $modal = $(make_modal(types, L.attribute_addition, L.add));
        $modal.on('submit', 'form', function(){
          var type, value;
          type = $modal.find('[name=type]').val();
          value = set_attribute_types.indexOf(parseInt(type)) !== -1 ? $modal.find('[name=value]').val().split('\n') : '';
          $.ajax({
            url: 'api/Shop/admin/attributes',
            type: 'post',
            data: {
              type: type,
              title: $modal.find('[name=title]').val(),
              title_internal: $modal.find('[name=title_internal]').val(),
              value: value
            },
            success: function(){
              alert(L.added_successfully);
              location.reload();
            }
          });
          return false;
        }).on('change', '[name=type]', function(){
          var value_container, type;
          value_container = $(this).parent().next();
          type = $(this).val();
          if (set_attribute_types.indexOf(parseInt(type)) !== -1) {
            value_container.show();
          } else {
            value_container.hide();
          }
        });
      });
    }).on('mousedown', '.cs-shop-attribute-edit', function(){
      var id;
      id = $(this).data('id');
      Promise.all([$.getJSON('api/Shop/admin/attributes/types'), $.getJSON("api/Shop/admin/attributes/" + id)]).then(function(arg$){
        var types, attribute, $modal;
        types = arg$[0], attribute = arg$[1];
        $modal = $(make_modal(types, L.attribute_edition, L.edit));
        $modal.on('submit', 'form', function(){
          var type, value;
          type = $modal.find('[name=type]').val();
          value = set_attribute_types.indexOf(parseInt(type)) !== -1 ? $modal.find('[name=value]').val().split('\n') : '';
          $.ajax({
            url: "api/Shop/admin/attributes/" + id,
            type: 'put',
            data: {
              type: type,
              title: $modal.find('[name=title]').val(),
              title_internal: $modal.find('[name=title_internal]').val(),
              value: value
            },
            success: function(){
              alert(L.edited_successfully);
              location.reload();
            }
          });
          return false;
        }).on('change', '[name=type]', function(){
          var value_container, type;
          value_container = $(this).parent().next();
          type = $(this).val();
          if (set_attribute_types.indexOf(parseInt(type)) !== -1) {
            value_container.show();
          } else {
            value_container.hide();
          }
        });
        $modal.find('[name=type]').val(attribute.type).change();
        $modal.find('[name=value]').val(attribute.value ? attribute.value.join('\n') : '');
        $modal.find('[name=title]').val(attribute.title);
        $modal.find('[name=title_internal]').val(attribute.title_internal);
      });
    }).on('mousedown', '.cs-shop-attribute-delete', function(){
      var id;
      id = $(this).data('id');
      if (confirm(L.sure_want_to_delete)) {
        $.ajax({
          url: "api/Shop/admin/attributes/" + id,
          type: 'delete',
          success: function(){
            alert(L.deleted_successfully);
            location.reload();
          }
        });
      }
    });
  });
}).call(this);
