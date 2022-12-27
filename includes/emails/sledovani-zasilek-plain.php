<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo "= " . $email_heading . " =\n\n";

$order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
$id_zasilky = get_post_meta( $order_id, '_ceske_sluzby_sledovani_zasilek_id_zasilky', true );
$dopravce = get_post_meta( $order_id, '_ceske_sluzby_sledovani_zasilek_dopravce', true );
$dostupni_dopravci = ceske_sluzby_sledovani_zasilek_dostupni_dopravci();
if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
  $odkaz = str_replace( '%ID%', $id_zasilky , $dostupni_dopravci[$dopravce]['url'] );
}

echo 'Objednávka byla odeslána a můžete ji sledovat:' . "\r\n";
echo $odkaz . "\n\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );
