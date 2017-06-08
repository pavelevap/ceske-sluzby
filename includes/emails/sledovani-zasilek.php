<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading ); ?>

<?php
$odkaz_html = "";
$item_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
$shipping = $order->get_shipping_methods();
if ( ! empty ( $shipping ) && is_array ( $shipping ) ) {
  $shipping_item_id = key( $shipping );
  $item_id = $shipping_item_id;
}
$id_zasilky = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_id_zasilky', true );
$dopravce = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_dopravce', true );
$zeme_doruceni = is_callable( array( $order, 'get_shipping_country' ) ) ? $order->get_shipping_country() : $order->shipping_country;
$dostupni_dopravci = ceske_sluzby_sledovani_zasilek_dostupni_dopravci( $zeme_doruceni );
if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
  $odkaz = str_replace( '%ID%', $id_zasilky , $dostupni_dopravci[$dopravce]['url'] );
  $odkaz_html = '<a href="' . $odkaz . '" target="_blank">' . $dostupni_dopravci[$dopravce]['nazev'] . '</a>';
} ?>
<p>
  Objednávka byla odeslána a můžete ji sledovat zde: <?php echo $odkaz_html; ?>.
</p>

<?php do_action( 'woocommerce_email_footer' ); ?>
