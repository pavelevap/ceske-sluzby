jQuery( function( $ ) {
  // Datepicker pro předobjednávku.
  $( '.ceske_sluzby_xml_preorder_datum_field' ).each( function() {
    var dates = $( this ).find( 'input' ).datepicker({
      defaultDate: '',
      dateFormat: 'yy-mm-dd',
      numberOfMonths: 1,
      showButtonPanel: true
    });
  });

  // Smazat datum předobjednávky.
  $( '#woocommerce-product-data' ).on( 'click', '.cancel_preorder', function() {
    var $wrap = $( this ).closest( 'div, table' );
    $( this ).hide();
    $wrap.find( '.ceske_sluzby_xml_preorder_datum_field' ).find( 'input' ).val('');
    return false;
  });

  // Umožnit odesílání notifikačního emailu pouze pokud jsou vyplněné potřebné hodnoty.
  var cs_email_value = "send_email_wc_email_ceske_sluzby_sledovani_zasilek";
  if( ! $('#ceske_sluzby_sledovani_zasilek_id_zasilky').val() || ! $('#ceske_sluzby_sledovani_zasilek_dopravce').val() ) {
    $('select[name="wc_order_action"] option[value="' + cs_email_value + '"]').prop( "disabled", true );
  } else {
    $('select[name="wc_order_action"] option[value="' + cs_email_value + '"]').prop( "disabled", false );
  }
  $( '#ceske_sluzby_sledovani_zasilek_id_zasilky, #ceske_sluzby_sledovani_zasilek_dopravce' ).change( function() {
    if( ! $('#ceske_sluzby_sledovani_zasilek_id_zasilky').val() || ! $('#ceske_sluzby_sledovani_zasilek_dopravce').val() ) {
      $('select[name="wc_order_action"] option[value="' + cs_email_value + '"]').prop( "disabled", true );
    } else {
      $('select[name="wc_order_action"] option[value="' + cs_email_value + '"]').prop( "disabled", false );
    }
  });
});
