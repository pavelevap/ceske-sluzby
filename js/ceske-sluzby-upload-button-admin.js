jQuery(function($){
  $('body').on('click', '.ceske_sluzby_upload_button', function(e){
    e.preventDefault();
    var button = $(this),
    custom_uploader = wp.media({
      title: 'Zvolit certifikát',
      library: {
        type: 'application/x-pkcs12'
      },
      button: {
        text: 'Použít certifikát'
      },
      multiple: false
    }).on('select', function() { 
      var attachment = custom_uploader.state().get('selection').first().toJSON();
      $( '.ceske_sluzby_upload_button').before('<span class="nazev-souboru" style="padding-right:10px;"><strong>' + attachment.filename + '</strong></span>');
      $(button).next().val(attachment.id).next().show();
      $( '.ceske_sluzby_remove_button').show();
      $( '.ceske_sluzby_upload_button').hide();
    }).open();
  });
  $('body').on('click', '.ceske_sluzby_remove_button', function(){
    $('.nazev-souboru').hide();
    $(this).hide().prev().val('').prev().addClass('button').html('Nahrát certifikát');
    $(this).hide().prev().val('');
    $('.ceske_sluzby_upload_button').show();
    return false;
  });
});
