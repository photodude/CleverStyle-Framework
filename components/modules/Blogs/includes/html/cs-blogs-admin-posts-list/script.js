// Generated by LiveScript 1.4.0
/**
 * @package   Blogs
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer({
    'is': 'cs-blogs-admin-posts-list',
    behaviors: [cs.Polymer.behaviors.Language('blogs_')],
    properties: {
      posts: Array,
      sections: Object
    },
    ready: function(){
      var this$ = this;
      $.getJSON('api/Blogs/admin/sections', function(sections){
        var normalized_sections, i$, len$, section;
        normalized_sections = {};
        for (i$ = 0, len$ = sections.length; i$ < len$; ++i$) {
          section = sections[i$];
          normalized_sections[section.id] = section;
        }
        this$.sections = normalized_sections;
        this$._reload_posts();
      });
    },
    _reload_posts: function(){
      var this$ = this;
      $.ajax({
        url: 'api/Blogs/admin/posts',
        type: 'get',
        success: function(posts){
          var i$, len$, post, index, ref$, section;
          for (i$ = 0, len$ = posts.length; i$ < len$; ++i$) {
            post = posts[i$];
            for (index in ref$ = post.sections) {
              section = ref$[index];
              post.sections[index] = this$.sections[section];
            }
          }
          this$.set('posts', posts);
        }
      });
    },
    _delete: function(e){
      var this$ = this;
      cs.ui.confirm(this.L.sure_to_delete_post(e.model.item.title), function(){
        $.ajax({
          url: 'api/Blogs/admin/posts/' + e.model.item.id,
          type: 'delete',
          success: function(){
            cs.ui.notify(this$.L.changes_saved, 'success', 5);
            this$._reload_posts();
          }
        });
      });
    }
  });
}).call(this);
