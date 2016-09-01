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
});
