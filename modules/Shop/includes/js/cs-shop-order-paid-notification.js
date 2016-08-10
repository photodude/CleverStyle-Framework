// Generated by LiveScript 1.4.0
/**
 * @package   Shop
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2014-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer({
    is: 'cs-shop-order-paid-notification',
    created: function(){
      if (!location.search) {
        return;
      }
      cs.Language('shop_').ready().then(function(L){
        var query;
        query = location.search.substr(1).split('&');
        query.forEach(function(q){
          q = q.split('=');
          switch (q[0]) {
          case 'paid_success':
            cs.ui.notify(L.paid_success_notification(q[1]), 'success');
            break;
          case 'paid_error':
            cs.ui.notify(L.paid_error_notification(q[1]), 'error');
          }
        });
      });
    }
  });
}).call(this);
