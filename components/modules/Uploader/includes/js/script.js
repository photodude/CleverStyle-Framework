// Generated by LiveScript 1.4.0
/**
 * @package   Uploader
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var L, uploader, files_handler, _on, _off;
  L = cs.Language('uploader_');
  uploader = function(file, progress, state){
    return new Promise(function(resolve, reject){
      var form_data, xhr;
      form_data = new FormData;
      form_data.append('file', file);
      xhr = new XMLHttpRequest();
      state.xhr = xhr;
      xhr.onload = function(){
        var data;
        data = JSON.parse(this.responseText);
        if (this.status >= 400) {
          reject(data);
        } else {
          resolve(data);
        }
      };
      xhr.onerror = function(){
        reject({
          timeout: timeout,
          xhr: xhr
        });
      };
      xhr.onprogress = function(e){
        if (typeof progress == 'function') {
          progress(e, file);
        }
      };
      xhr.open('post'.toUpperCase(), 'api/Uploader');
      xhr.send(form_data);
    });
  };
  files_handler = function(files, success, error, progress, state){
    var uploaded_files, next_upload;
    uploaded_files = [];
    next_upload = function(uploaded_file){
      var file;
      if (uploaded_file) {
        uploaded_files.push(uploaded_file);
      }
      file = files.shift();
      if (file) {
        uploader(file, progress, state).then(function(data){
          next_upload(data.url);
        })['catch'](function(e){
          if (error) {
            error.call(error, L.file_uploading_failed(file.name, e.error_description), state.xhr, file);
          } else {
            cs.ui.notify(L.file_uploading_failed(file.name, e.error_description), 'error');
          }
          next_upload();
        });
      } else {
        if (uploaded_files.length) {
          success(uploaded_files);
        } else {
          cs.ui.notify(L.no_files_uploaded, 'error');
        }
      }
    };
    next_upload();
  };
  _on = function(element, event, callback){
    if (element.addEventListener) {
      element.addEventListener(event, callback);
    } else if (element.on) {
      element.on(event, callback);
    }
  };
  _off = function(element, event, callback){
    if (element.removeEventListener) {
      element.removeEventListener(event, callback);
    } else if (element.off) {
      element.off(event, callback);
    }
  };
  /**
   * Files uploading interface
   *
   * @param {object}				button
   * @param {function}			success
   * @param {function}			error
   * @param {function}			progress
   * @param {bool}				multi
   * @param {object}|{object}[]	drop_element
   *
   * @return {function}
   */
  cs.file_upload = function(button, success, error, progress, multi, drop_element){
    var state, local_files_handler, x$, input, click, dragover, drop;
    if (!success) {
      return;
    }
    state = {};
    local_files_handler = function(files){
      var total_files, total_size, res$, i$, len$, file, progress_local;
      total_files = files.length;
      total_size = 0;
      res$ = [];
      for (i$ = 0, len$ = files.length; i$ < len$; ++i$) {
        file = files[i$];
        total_size += file.size;
        res$.push(file);
      }
      files = res$;
      if (!files.length) {
        return;
      }
      progress_local = function(e, file){
        var uploaded_bytes, total_uploaded, i$, ref$, len$, f;
        if (!e.lengthComputable) {
          return;
        }
        uploaded_bytes = e.loaded / e.total * file.size;
        total_uploaded = total_size - file.size + uploaded_bytes;
        for (i$ = 0, len$ = (ref$ = files).length; i$ < len$; ++i$) {
          f = ref$[i$];
          total_uploaded -= f.size;
        }
        progress(Math.round(e.loaded / e.total * 100), file.size, uploaded_bytes, file.name, Math.round(total_uploaded / total_size * 100), total_size, total_uploaded, total_files - files.length, total_files);
      };
      files_handler(files, success, error, progress && progress_local, state);
    };
    x$ = input = document.createElement('input');
    x$.type = 'file';
    x$.multiple = !!multi;
    x$.addEventListener('change', function(){
      if (this.files.length) {
        local_files_handler(this.files);
      }
    });
    click = input.click.bind(input);
    _on(button, 'click', click);
    dragover = function(e){
      e.preventDefault();
    };
    drop = function(e){
      var files;
      e.preventDefault();
      files = e.originalEvent.dataTransfer.files;
      if (files) {
        if (multi) {
          local_files_handler(files);
        } else {
          local_files_handler([files[0]]);
        }
      }
    };
    if (drop_element) {
      _on(drop_element, 'dragover', dragover);
      _on(drop_element, 'drop', drop);
    }
    return {
      stop: function(){
        var ref$;
        return state != null ? (ref$ = state.xhr) != null ? ref$.abort() : void 8 : void 8;
      },
      destroy: function(){
        var ref$;
        if (state != null) {
          if ((ref$ = state.xhr) != null) {
            ref$.abort();
          }
        }
        _off(button, 'click', click);
        if (drop_element) {
          _off(drop_element, 'dragover', dragover);
          return _off(drop_element, 'drop', drop);
        }
      }
    };
  };
}).call(this);
