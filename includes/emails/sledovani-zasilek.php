<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading ); ?>

<?php
$objednavka = $order;
$id_zasilky = get_post_meta( $objednavka->post->ID, '_ceske_sluzby_sledovani_zasilek_id_zasilky', true );
$dopravce = get_post_meta( $objednavka->post->ID, '_ceske_sluzby_sledovani_zasilek_dopravce', true );
$dostupni_dopravci = ceske_sluzby_sledovani_zasilek_dostupni_dopravci();
if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
  $odkaz = str_replace( '%ID%', $id_zasilky , $dostupni_dopravci[$dopravce]['url'] );
  $odkaz_html = '<a href="' . $odkaz . '" target="_blank">' . $dostupni_dopravci[$dopravce]['nazev'] . '</a>';
} ?>
<p>
  Objednávka byla odeslána a můžete ji sledovat: <?php echo $odkaz_html; ?>.
</p>

<?php do_action( 'woocommerce_email_footer' ); ?>
