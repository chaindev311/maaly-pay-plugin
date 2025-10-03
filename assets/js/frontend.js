jQuery(document).ready(function($) {
  let lastMerchantTxId = '';

$('#maaly-create-payment-btn').on('click', function() {    
    $('#maaly-create-payment-result').html('<p>Loading...</p>');
    $.post(maalyPay.ajaxurl, {
        action: 'maaly_create_payment',
        security: maalyPay.nonceCreate,
    }, function(response) {
        if (response.success) {
            lastMerchantTxId = response.data.merchantTxId;
            let html = '<p>' + maalyPay.msgPaymentCreated + '</p>';
            html += '<p>' + maalyPay.msgCheckoutURL + ' <a target="_blank" href="' + response.data.checkoutUrl + '">' + response.data.checkoutUrl + '</a></p>';
            if (response.data.openCheckout === 'iframe') {
                html += '<iframe src="' + response.data.checkoutUrl + '" width="100%" height="650"></iframe>';
            }
            $('#maaly-create-payment-result').html(html);
        } else {
            $('#maaly-create-payment-result').html('<p style="color:red;">Error: ' + response.data + '</p>');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX error: ', textStatus, errorThrown);
        $('#maaly-create-payment-result').html('<p style="color:red;">AJAX error: ' + textStatus + '</p>');
    });
  });


  $('#maaly-check-status-btn').on('click', function() {
      if (!lastMerchantTxId) {
        alert(maalyPay.msgCreatePaymentFirst);
        return;
      }
      $('#maaly-check-status-result').html('<p>Loading...</p>');
      $.post(maalyPay.ajaxurl, {
          action: 'maaly_check_status',
          security: maalyPay.nonceStatus,
          merchantTxId: lastMerchantTxId,
      }, function(response) {
          if (response.success) {
              let data = response.data;
              let status = data.status ? '✅ Completed' : '⏳ Pending/Failed';
              let html = '<p>Status: ' + status + '</p>';
              html += '<p>Filled Amount: ' + (data.filledAmount || '—') + '</p>';
              html += '<p>Requested Amount: ' + (data.requestedAmount || '—') + '</p>';
              $('#maaly-check-status-result').html(html);
          } else {
              $('#maaly-check-status-result').html('<p style="color:red;">Error: ' + response.data + '</p>');
          }
      }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('AJAX error: ', textStatus, errorThrown);
        $('#maaly-create-payment-result').html('<p style="color:red;">AJAX error: ' + textStatus + '</p>');
      });
  });
});
