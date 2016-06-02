// Generated by LiveScript 1.4.0
/**
 * @package   Blogs
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var L;
  L = cs.Language('blogs_');
  Polymer({
    is: 'cs-blogs-add-edit-post',
    behaviors: [cs.Polymer.behaviors.Language('blogs_')],
    properties: {
      post: Object,
      original_title: String,
      sections: Array,
      settings: Object,
      local_tags: String,
      user_id: Number
    },
    observers: ['_add_close_tab_handler(post.*, local_tags)'],
    ready: function(){
      var this$ = this;
      if (!this.id) {
        this.id = false;
      }
      Promise.all([
        this.id
          ? $.getJSON('api/Blogs/posts/' + this.id)
          : {
            title: '',
            path: '',
            content: '',
            sections: [],
            tags: []
          }, $.getJSON('api/Blogs/sections'), $.ajax({
          url: 'api/Blogs',
          type: 'get_settings'
        }), $.getJSON('api/System/profile')
      ]).then(function(arg$){
        var sections, settings, profile;
        this$.post = arg$[0], sections = arg$[1], settings = arg$[2], profile = arg$[3];
        this$.original_title = this$.post.title;
        if (this$.post.title) {
          this$.$.title.textContent = this$.post.title;
        }
        this$.local_tags = this$.post.tags.join(', ');
        this$.sections = this$._prepare_sections(sections);
        settings.multiple_sections = settings.max_sections > 1;
        this$.settings = settings;
        this$.user_id = profile.id;
      });
      this.$.title.addEventListener('keydown', bind$(this, '_add_close_tab_handler'));
    },
    _add_close_tab_handler: function(){
      if (this.user_id && !this._close_tab_handler_installed && !window.onbeforeunload) {
        addEventListener('beforeunload', this._close_tab_handler);
        this._close_tab_handler_installed = true;
      }
    },
    _remove_close_tab_handler: function(){
      if (this._close_tab_handler_installed) {
        removeEventListener('beforeunload', this._close_tab_handler);
        this._close_tab_handler_installed = false;
      }
    },
    _close_tab_handler: function(e){
      e.returnValue = L.sure_want_to_exit;
    },
    _prepare_sections: function(sections){
      var sections_parents, i$, len$, section;
      sections_parents = {};
      for (i$ = 0, len$ = sections.length; i$ < len$; ++i$) {
        section = sections[i$];
        sections_parents[section.parent] = true;
      }
      for (i$ = 0, len$ = sections.length; i$ < len$; ++i$) {
        section = sections[i$];
        section.disabled = sections_parents[section.id];
      }
      return sections;
    },
    _prepare: function(){
      delete this.post.path;
      this.set('post.title', this.$.title.textContent);
      this.set('post.tags', this.local_tags.split(',').map(function(it){
        return String(it).trim();
      }));
    },
    _preview: function(){
      var close_tab_handler_installed, this$ = this;
      close_tab_handler_installed = this._close_tab_handler_installed;
      this._prepare();
      if (!close_tab_handler_installed && this._close_tab_handler_installed) {
        this._remove_close_tab_handler();
      }
      $.ajax({
        url: 'api/Blogs/posts',
        data: this.post,
        type: 'preview',
        dataType: 'text',
        success: function(result){
          this$.$.preview.innerHTML = "<article is=\"cs-blogs-post\" preview>\n	<script type=\"application/ld+json\">" + result + "</script>\n</article>";
          $('html, body').stop().animate({
            scrollTop: this$.$.preview.offsetTop
          }, 500);
        }
      });
    },
    _publish: function(){
      var this$ = this;
      this._prepare();
      this.post.mode = 'publish';
      $.ajax({
        url: 'api/Blogs/posts' + (this.id ? '/' + this.id : ''),
        data: this.post,
        type: this.id ? 'put' : 'post',
        success: function(result){
          this$._remove_close_tab_handler();
          location.href = result.url;
        }
      });
    },
    _to_drafts: function(){
      var this$ = this;
      this._prepare();
      this.post.mode = 'draft';
      $.ajax({
        url: 'api/Blogs/posts' + (this.id ? '/' + this.id : ''),
        data: this.post,
        type: this.id ? 'put' : 'post',
        success: function(result){
          this$._remove_close_tab_handler();
          location.href = result.url;
        }
      });
    },
    _delete: function(){
      var this$ = this;
      cs.ui.confirm(L.sure_to_delete_post(this.original_title), function(){
        $.ajax({
          url: 'api/Blogs/posts/' + this$.post.id,
          type: 'delete',
          success: function(result){
            this$._remove_close_tab_handler();
            location.href = 'Blogs';
          }
        });
      });
    },
    _cancel: function(){
      this._remove_close_tab_handler();
      history.go(-1);
    }
  });
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);
