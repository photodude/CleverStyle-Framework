// Generated by LiveScript 1.4.0
/**
 * @package   Blockchain payment
 * @category  modules
 * @author    Nazar Mokrynskyi <nazar@mokrynskyi.com>
 * @copyright Copyright (c) 2015-2016, Nazar Mokrynskyi
 * @license   MIT License, see license.txt
 */
(function(){
  Polymer({
    is: 'cs-blockchain-payment-pay',
    properties: {
      description: '',
      address: '',
      amount: Number,
      progress_text: String
    },
    ready: function(){
      var this$ = this;
      cs.Language('blockchain_payment_').ready().then(function(L){
        this$.progress_text = L.waiting_for_payment;
        this$.text = L.scan_or_transfer(this$.amount, this$.address);
      });
      this.description(JSON.parse(this.description));
      new QRCode(this.$.qr, {
        height: 512,
        text: 'bitcoin:' + this.address + '?amount=' + this.amount,
        width: 512
      });
      this.update_status();
    },
    update_status: function(){
      var this$ = this;
      cs.api('get api/Blockchain_payment/' + this.dataset.id).then(function(data){
        if (parseInt(data.confirmed)) {
          location.reload();
          return;
        }
        if (parseInt(data.paid)) {
          cs.Language('blockchain_payment_').ready().then(function(L){
            this$.progress_text = L.waiting_for_confirmations;
          });
        }
        setTimeout(bind$(this$, 'update_status'), 5000);
      });
    }
  });
  function bind$(obj, key, target){
    return function(){ return (target || obj)[key].apply(obj, arguments) };
  }
}).call(this);
