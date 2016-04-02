// Generated by LiveScript 1.4.0
/**
 * @package   Blogs
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer({
    'is': 'cs-blogs-post',
    'extends': 'article',
    behaviors: [cs.Polymer.behaviors.Language('blogs_')],
    properties: {
      can_edit: false,
      can_delete: false,
      comments_enabled: false
    },
    ready: function(){
      this.jsonld = JSON.parse(this.querySelector('script').innerHTML);
      this.$.content.innerHTML = this.jsonld.content;
    },
    sections_path: function(index){
      return this.jsonld.sections_paths[index];
    },
    tags_path: function(index){
      return this.jsonld.tags_paths[index];
    }
  });
}).call(this);
