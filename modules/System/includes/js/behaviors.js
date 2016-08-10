// Generated by LiveScript 1.4.0
/**
 * @package    CleverStyle Framework
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
(function(){
  var ref$, ref1$;
  ((ref$ = (ref1$ = cs.Polymer || (cs.Polymer = {})).behaviors || (ref1$.behaviors = {})).admin || (ref$.admin = {})).System = {
    components: {
      _enable_module: function(component, meta){
        var this$ = this;
        Promise.all([cs.api(["get			api/System/admin/modules/" + component + "/dependencies", 'get_settings	api/System/admin/system']), cs.Language('system_admin_').ready()]).then(function(arg$){
          var ref$, dependencies, settings, L, title, message, message_more, modal, i$, len$, p;
          ref$ = arg$[0], dependencies = ref$[0], settings = ref$[1], L = arg$[1];
          delete dependencies.db_support;
          delete dependencies.storage_support;
          title = "<h3>" + L.modules_enabling_of_module(component) + "</h3>";
          message = '';
          message_more = '';
          if (Object.keys(dependencies).length) {
            message = this$._compose_dependencies_message(L, component, meta.category, dependencies);
            if (settings.simple_admin_mode) {
              cs.ui.notify(message, 'error', 5);
              return;
            }
          }
          if (meta && meta.optional) {
            message_more += '<p class="cs-text-success cs-block-success">' + L.for_complete_feature_set(meta.optional.join(', ')) + '</p>';
          }
          modal = cs.ui.confirm(title + "" + message + message_more, function(){
            cs.Event.fire("admin/System/modules/enable/before", {
              name: component
            }).then(function(){
              return cs.api("enable api/System/admin/modules/" + component);
            }).then(function(){
              cs.ui.notify(L.changes_saved, 'success', 5);
              return cs.Event.fire("admin/System/modules/enable/after", {
                name: component
              });
            }).then(bind$(location, 'reload'));
          });
          modal.ok.innerHTML = L[!message ? 'enable' : 'force_enable_not_recommended'];
          modal.ok.primary = !message;
          modal.cancel.primary = !modal.ok.primary;
          for (i$ = 0, len$ = (ref$ = modal.querySelectorAll('p:not([class])')).length; i$ < len$; ++i$) {
            p = ref$[i$];
            p.classList.add('cs-text-error', 'cs-block-error');
          }
        });
      },
      _disable_module: function(component){
        var this$ = this;
        Promise.all([cs.api(["get			api/System/admin/modules/" + component + "/dependent_packages", 'get_settings	api/System/admin/system']), cs.Language('system_admin_').ready()]).then(function(arg$){
          var ref$, dependent_packages, settings, L, title, message, type, packages, i$, len$, _package, modal, p;
          ref$ = arg$[0], dependent_packages = ref$[0], settings = ref$[1], L = arg$[1];
          title = "<h3>" + L.modules_disabling_of_module(component) + "</h3>";
          message = '';
          if (Object.keys(dependent_packages).length) {
            for (type in dependent_packages) {
              packages = dependent_packages[type];
              for (i$ = 0, len$ = packages.length; i$ < len$; ++i$) {
                _package = packages[i$];
                message += "<p>" + L.this_package_is_used_by_module(_package) + "</p>";
              }
            }
            message += "<p>" + L.dependencies_not_satisfied + "</p>";
            if (settings.simple_admin_mode) {
              cs.ui.notify(message, 'error', 5);
              return;
            }
          }
          modal = cs.ui.confirm(title + "" + message, function(){
            cs.Event.fire("admin/System/modules/disable/before", {
              name: component
            }).then(function(){
              return cs.api("disable api/System/admin/modules/" + component);
            }).then(function(){
              cs.ui.notify(L.changes_saved, 'success', 5);
              return cs.Event.fire("admin/System/modules/disable/after", {
                name: component
              });
            }).then(bind$(location, 'reload'));
          });
          modal.ok.innerHTML = L[!message ? 'disable' : 'force_disable_not_recommended'];
          modal.ok.primary = !message;
          modal.cancel.primary = !modal.ok.primary;
          for (i$ = 0, len$ = (ref$ = modal.querySelectorAll('p')).length; i$ < len$; ++i$) {
            p = ref$[i$];
            p.classList.add('cs-text-error', 'cs-block-error');
          }
        });
      },
      _update_component: function(existing_meta, new_meta){
        var component, category, this$ = this;
        component = new_meta['package'];
        category = new_meta.category;
        Promise.all([cs.api(["get			api/System/admin/" + category + "/" + component + "/update_dependencies", 'get_settings	api/System/admin/system']), cs.Language('system_admin_').ready()]).then(function(arg$){
          var ref$, dependencies, settings, L, translation_key, title, message, message_more, modal, i$, len$, p;
          ref$ = arg$[0], dependencies = ref$[0], settings = ref$[1], L = arg$[1];
          delete dependencies.db_support;
          delete dependencies.storage_support;
          translation_key = (function(){
            switch (category) {
            case 'modules':
              if (component === 'System') {
                return 'modules_updating_of_system';
              } else {
                return 'modules_updating_of_module';
              }
              break;
            case 'themes':
              return 'appearance_updating_theme';
            }
          }());
          title = "<h3>" + L[translation_key](component) + "</h3>";
          message = '';
          if (component === 'System') {
            message_more = '<p class>' + L.modules_update_system(existing_meta.version, new_meta.version) + '</p>';
          } else {
            translation_key = (function(){
              switch (category) {
              case 'modules':
                return 'modules_update_module';
              case 'themes':
                return 'appearance_update_theme';
              }
            }());
            message_more = '<p class>' + L[translation_key](component, existing_meta.version, new_meta.version) + '</p>';
          }
          if (Object.keys(dependencies).length) {
            message = this$._compose_dependencies_message(L, component, category, dependencies);
            if (settings.simple_admin_mode) {
              cs.ui.notify(message, 'error', 5);
              return;
            }
          }
          if (new_meta.optional) {
            message_more += '<p class="cs-text-success cs-block-success">' + L.for_complete_feature_set(new_meta.optional.join(', ')) + '</p>';
          }
          modal = cs.ui.confirm(title + "" + message + message_more, function(){
            (component === 'System'
              ? cs.Event.fire('admin/System/modules/update_system/before')
              : cs.Event.fire("admin/System/" + category + "/update/before", {
                name: component
              })).then(function(){
              return cs.api("update api/System/admin/" + category + "/" + component);
            }).then(function(){
              cs.ui.notify(L.changes_saved, 'success', 5);
              if (component === 'System') {
                return cs.Event.fire('admin/System/modules/update_system/after');
              } else {
                return cs.Event.fire("admin/System/" + category + "/update/after", {
                  name: component
                });
              }
            }).then(bind$(location, 'reload'));
          });
          modal.ok.innerHTML = L[!message ? 'yes' : 'force_update_not_recommended'];
          modal.ok.primary = !message;
          modal.cancel.primary = !modal.ok.primary;
          for (i$ = 0, len$ = (ref$ = modal.querySelectorAll('p:not([class])')).length; i$ < len$; ++i$) {
            p = ref$[i$];
            p.classList.add('cs-text-error', 'cs-block-error');
          }
        });
      },
      _remove_completely_component: function(component, category){
        var translation_key, this$ = this;
        translation_key = (function(){
          switch (category) {
          case 'modules':
            return 'modules_completely_remove_module';
          case 'themes':
            return 'appearance_completely_remove_theme';
          }
        }());
        cs.Language('system_admin_').ready().then(function(L){
          cs.ui.confirm(L[translation_key](component)).then(function(){
            return cs.api("delete api/System/admin/" + category + "/" + component);
          }).then(function(){
            this$.reload();
            cs.ui.notify(L.changes_saved, 'success', 5);
          });
        });
      },
      _compose_dependencies_message: function(L, component, category, dependencies){
        var message, what, details, i$, len$, detail, translation_key, required_version;
        message = '';
        for (what in dependencies) {
          details = dependencies[what];
          if (!(details instanceof Array) || (what === 'db_support' || what === 'storage_support')) {
            details = [details];
          }
          for (i$ = 0, len$ = details.length; i$ < len$; ++i$) {
            detail = details[i$];
            message += "<p class=\"cs-block-error cs-text-error\">" + (fn$()) + "</p>";
          }
        }
        return message + "<p class=\"cs-block-error cs-text-error\">" + L.dependencies_not_satisfied + "</p>";
        function fn$(){
          switch (what) {
          case 'update_from':
            if (component === 'System') {
              return L.modules_update_system_impossible_from_version_to(detail.from, detail.to, detail.can_update_from);
            } else {
              return L.modules_module_cant_be_updated_from_version_to(component, detail.from, detail.to, detail.can_update_from);
            }
            break;
          case 'update_older':
            translation_key = (function(){
              switch (category) {
              case 'modules':
                if (component === 'System') {
                  return 'modules_update_system_impossible_older_version';
                } else {
                  return 'modules_update_module_impossible_older_version';
                }
                break;
              case 'themes':
                return 'appearance_update_theme_impossible_older_version';
              }
            }());
            return L[translation_key](component, detail.from, detail.to);
          case 'update_same':
            translation_key = (function(){
              switch (category) {
              case 'modules':
                if (component === 'System') {
                  return 'modules_update_system_impossible_same_version';
                } else {
                  return 'modules_update_module_impossible_same_version';
                }
                break;
              case 'themes':
                return 'appearance_update_theme_impossible_same_version';
              }
            }());
            return L[translation_key](component, detail.version);
          case 'provide':
            return L.module_already_provides_functionality(detail.name, detail.features.join('", "'));
          case 'require':
            required_version = detail.required_version[1] ? ' ' + detail.required_version.join(' ') : '';
            if (detail.existing_version) {
              return L.modules_unsatisfactory_version_of_the_module(detail['package'], required_version, detail.existing_version);
            } else {
              return L.package_or_functionality_not_found(detail['package'] + required_version);
            }
            break;
          case 'conflict':
            return L.package_is_incompatible_with(detail['package'], detail.conflicts_with + (detail.of_version[1] ? ' ' + detail.of_version.join(' ') : ''));
          case 'db_support':
            return L.modules_compatible_databases_not_found(details.join('", "'));
          case 'storage_support':
            return L.modules_compatible_storages_not_found(details.join('", "'));
          }
        }
      }
    },
    upload: {
      _upload_package: function(file_input){
        var form_data;
        if (!file_input.files.length) {
          throw new Error('file should be selected');
        }
        form_data = new FormData;
        form_data.append('file', file_input.files[0]);
        return cs.api('post api/System/admin/upload', form_data);
      }
    },
    settings: {
      properties: {
        settings_api_url: {
          observer: '_reload_settings',
          type: String
        },
        settings: Object,
        simple_admin_mode: Boolean
      },
      _reload_settings: function(){
        var this$ = this;
        cs.api(['get_settings ' + this.settings_api_url, 'get_settings api/System/admin/system']).then(function(arg$){
          var settings, system_settings;
          settings = arg$[0], system_settings = arg$[1];
          this$.simple_admin_mode = system_settings.simple_admin_mode === 1;
          this$.set('settings', settings);
        });
      },
      _apply: function(){
        var this$ = this;
        Promise.all([cs.Language('system_admin_').ready(), cs.api('apply_settings ' + this.settings_api_url, this.settings)]).then(function(arg$){
          var L;
          L = arg$[0];
          this$._reload_settings();
          cs.ui.notify(L.changes_applied, 'warning', 5);
        });
      },
      _save: function(){
        var this$ = this;
        Promise.all([cs.Language('system_admin_').ready(), cs.api('save_settings ' + this.settings_api_url, this.settings)]).then(function(arg$){
          var L;
          L = arg$[0];
          this$._reload_settings();
          cs.ui.notify(L.changes_saved, 'success', 5);
        });
      },
      _cancel: function(){
        var this$ = this;
        Promise.all([cs.Language('system_admin_').ready(), cs.api('cancel_settings ' + this.settings_api_url)]).then(function(arg$){
          var L;
          L = arg$[0];
          this$._reload_settings();
          cs.ui.notify(L.changes_canceled, 'success', 5);
        });
      }
    }
  };
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);
