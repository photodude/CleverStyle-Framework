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
  Polymer({
    'is': 'cs-system-admin-databases-form',
    behaviors: [cs.Polymer.behaviors.Language('system_admin_databases_')],
    properties: {
      add: Boolean,
      databaseIndex: Number,
      mirrorIndex: Number,
      databases: Array,
      database: {
        type: Object,
        value: {
          mirror: -1,
          host: '',
          type: 'MySQLi',
          prefix: '',
          name: '',
          user: '',
          password: ''
        }
      },
      engines: Array
    },
    ready: function(){
      var this$ = this;
      cs.api(['get		api/System/admin/databases', 'engines	api/System/admin/databases']).then(function(arg$){
        this$.databases = arg$[0], this$.engines = arg$[1];
        if (this$.add) {
          if (!isNaN(this$.databaseIndex)) {
            this$.set('database.mirror', this$.databaseIndex);
          }
        } else {
          this$.databases.forEach(function(database){
            if (this$.databaseIndex == database.index) {
              if (isNaN(this$.mirrorIndex)) {
                this$.set('database', database);
              } else {
                database.mirrors.forEach(function(mirror){
                  if (this$.mirrorIndex == mirror.index) {
                    this$.set('database', mirror);
                  }
                });
              }
            }
          });
        }
      });
    },
    _save: function(){
      var method, suffix, this$ = this;
      method = this.add ? 'post' : 'patch';
      suffix = !isNaN(this.databaseIndex) ? '/' + this.databaseIndex + (!isNaN(this.mirrorIndex) ? '/' + this.mirrorIndex : '') : '';
      cs.api(method + " api/System/admin/databases" + suffix, this.database).then(function(){
        cs.ui.notify(this$.L.changes_saved, 'success', 5);
      });
    },
    _db_name: function(index, host, name){
      if (index) {
        return host + "/" + name;
      } else {
        return this.L.core_db;
      }
    },
    _test_connection: function(e){
      var modal, this$ = this;
      modal = cs.ui.simple_modal("<div>\n	<h3 class=\"cs-text-center\">" + this.L.test_connection + "</h3>\n	<progress is=\"cs-progress\" infinite></progress>\n</div>");
      cs.api('test api/System/admin/databases', this.database).then(function(){
        modal.querySelector('progress').outerHTML = "<p class=\"cs-text-center cs-block-success cs-text-success\" style=text-transform:capitalize;\">" + this$.L.success + "</p>";
      })['catch'](function(o){
        clearTimeout(o.timeout);
        modal.querySelector('progress').outerHTML = "<p class=\"cs-text-center cs-block-error cs-text-error\" style=text-transform:capitalize;\">" + this$.L.failed + "</p>";
      });
    }
  });
}).call(this);
