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
    is: 'cs-blogs-posts',
    'extends': 'section',
    ready: function(){
      this.jsonld = JSON.parse(this.children[0].innerHTML);
      this.posts = this.jsonld['@graph'];
    }
  });
}).call(this);
