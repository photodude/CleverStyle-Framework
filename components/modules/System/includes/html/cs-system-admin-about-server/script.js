// Generated by LiveScript 1.4.0
/**
 * @package    CleverStyle CMS
 * @subpackage System module
 * @category   modules
 * @author     Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright  Copyright (c) 2015, Nazar Mokrynskyi
 * @license    MIT License, see license.txt
 */
(function(){
  Polymer({
    'is': 'cs-system-admin-about-server',
    behaviors: [cs.Polymer.behaviors.Language],
    properties: {
      server_config: Object
    },
    ready: function(){
      var this$ = this;
      $.getJSON('api/System/admin/about_server', function(server_config){
        this$.server_config = server_config;
      });
    }
  });
}).call(this);
