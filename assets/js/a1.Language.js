// Generated by LiveScript 1.4.0
/**
 * @package   CleverStyle Framework
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  var translations, is_ready, fill_prefixed, vsprintf, get_formatted, fill_translations, x$, slice$ = [].slice;
  translations = cs.Language;
  is_ready = false;
  fill_prefixed = function(prefix){
    var prefix_length, key;
    prefix_length = prefix.length;
    for (key in Language) {
      if (key.indexOf(prefix) === 0) {
        this[key.substr(prefix_length)] = Language[key];
      }
    }
  };
  function Language(prefix){
    var prefixed;
    prefixed = Object.create(Language);
    prefixed.ready = function(){
      return Language.ready().then(function(){
        return prefixed;
      });
    };
    if (is_ready) {
      fill_prefixed.call(prefixed, prefix);
    } else {
      Language.ready().then(fill_prefixed.bind(prefixed, prefix));
    }
    return prefixed;
  }
  get_formatted = function(){
    return '' + (arguments.length ? vsprintf(this, slice$.call(arguments)) : this);
  };
  fill_translations = function(translations){
    var key, value;
    for (key in translations) {
      value = translations[key];
      if (value.indexOf('%') === -1) {
        Language[key] = value;
      } else {
        Language[key] = get_formatted.bind(value);
        Language[key].toString = Language[key];
      }
    }
  };
  x$ = cs.Language = Language;
  x$.get = function(key){
    return this[key].toString();
  };
  x$.format = function(key){
    var args;
    args = slice$.call(arguments, 1);
    return this[key].apply(this, args);
  };
  x$.ready = function(){
    var ready;
    ready = new Promise(function(resolve){
      Promise.all([
        translations
          ? [translations]
          : require(["storage/public_cache/languages-" + cs.current_language.language + "-" + cs.current_language.hash]), require(['sprintf-js'])
      ]).then(function(arg$){
        var translations, sprintfjs;
        translations = arg$[0][0], sprintfjs = arg$[1][0];
        fill_translations(translations);
        vsprintf = sprintfjs.vsprintf;
        is_ready = true;
        resolve(Language);
      });
    });
    this.ready = function(){
      return ready;
    };
    ready.then(function(){
      translations = void 8;
    });
    return ready;
  };
}).call(this);
