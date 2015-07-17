<?php
/**
 * Plugin Name: České služby pro WordPress
 * Plugin URI: http://www.separatista.net
 * Description: Implementace různých českých služeb do WordPressu.
 * Version: 0.4
 * Author: Pavel Hejn
 * Author URI: http://www.separatista.net
 * License: GPL2
 */

$language = get_locale();
if ( $language == "sk_SK" ) {
  define( "HEUREKA_URL", "heureka.sk" );
}
else {
  define( "HEUREKA_URL", "heureka.cz" );
}

function ceske_sluzby_heureka_overeno_zakazniky( $order_id, $posted ) {
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
  
    $order = new WC_Order( $order_id );
    
    // https://github.com/heureka/heureka-overeno-php-api
    require_once( dirname( __FILE__ ) . '/src/HeurekaOvereno.php' );
    
    $language = get_locale();
    if ( $language == "sk_SK" ) {
      $overeno = new HeurekaOvereno( $api, HeurekaOvereno::LANGUAGE_SK );
    }
    else {
      $overeno = new HeurekaOvereno( $api );
    }
    
    $overeno->setEmail( $posted['billing_email'] );

    $products = $order->get_items();
    foreach ( $products as $product ) {
      $overeno->addProduct( $product['name'] );
    }

    $overeno->addOrderId( $order_id );
    $overeno->send();
  }
}

function ceske_sluzby_heureka_mereni_konverzi( $order_id ) {
  $api = get_option( 'wc_ceske_sluzby_heureka_konverze-api' );
  if ( ! empty( $api ) ) {
  
    $order = new WC_Order( $order_id );
    $products = $order->get_items(); ?>
    
<script type="text/javascript">
var _hrq = _hrq || [];
    _hrq.push(['setKey', '<?php echo $api; ?>']);
    _hrq.push(['setOrderId', '<?php echo $order_id; ?>']);
    <?php foreach ( $products as $product ) {
      $cena = wc_format_decimal( $order->get_item_subtotal( $product ) );
      echo "_hrq.push(['addProduct', '" . $product['name'] . "', '" . $cena . "', '" . $product['qty'] . "']);";
    } ?>
    _hrq.push(['trackOrder']);

(function() {
    var ho = document.createElement('script'); ho.type = 'text/javascript'; ho.async = true;
    ho.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.<?php echo HEUREKA_URL; ?>/direct/js/cache/1-roi-async.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ho, s);
})();
</script>

<?php
  }
}

function ceske_sluzby_sklik_mereni_konverzi( $order_id ) {
  $konverze = get_option( 'wc_ceske_sluzby_sklik_konverze-objednavky' );
  if ( ! empty( $konverze ) ) { ?>
	
<!-- Měřicí kód Sklik.cz -->
<iframe width="119" height="22" frameborder="0" scrolling="no" src="http://c.imedia.cz/checkConversion?c=<?php echo $konverze; ?>&color=ffffff&v="></iframe>

  <?php
  }
}

function ceske_sluzby_srovname_mereni_konverzi( $order_id ) {
  $klic = get_option( 'wc_ceske_sluzby_srovname_konverze-objednavky' );
  if ( ! empty( $klic ) ) {
  
    $order = new WC_Order( $order_id );
    $products = $order->get_items(); ?>

<script type="text/javascript">
var _srt = _srt || [];
    _srt.push(['_setShop', '<?php echo $klic; ?>']);
    _srt.push(['_setTransId', '<?php echo $order_id; ?>']);
    <?php foreach ( $products as $product ) {
      $cena = wc_format_decimal( $order->get_item_subtotal( $product ) );
      echo "_srt.push(['_addProduct', '" . $product['name'] . "', '" . $cena . "', '" . $product['qty'] . "']);";
    } ?>
    _srt.push(['_trackTrans']);

(function() {
    var s = document.createElement("script");
    s.type = "text/javascript";
    s.async = true;
    s.src = ("https:" == document.location.protocol ? "https" : "http") + "://www.srovname.cz/js/track-trans.js";
    var x = document.getElementsByTagName("script")[0];
    x.parentNode.insertBefore(s, x);
})();
</script>

<?php
  }
}
 
function ceske_sluzby_kontrola_aktivniho_pluginu() {
  if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
    if( is_admin() ) {
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-admin.php';
      WC_Settings_Tab_Ceske_Sluzby_Admin::init();
    }

    add_action( 'woocommerce_shipping_init', 'ceske_sluzby_doprava_ulozenka_init' );
    add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_ulozenka' );

    add_action( 'woocommerce_shipping_init', 'ceske_sluzby_doprava_dpd_parcelshop_init' );
    add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_dpd_parcelshop' );
  
    add_action( 'woocommerce_checkout_order_processed', 'ceske_sluzby_heureka_overeno_zakazniky', 10, 2 );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_heureka_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_sklik_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_srovname_mereni_konverzi' );
    add_filter( 'wc_order_is_editable', 'ceske_sluzby_moznost_menit_dobirku', 10, 2 );
    add_filter( 'woocommerce_package_rates', 'ceske_sluzby_omezit_dopravu_pokud_dostupna_zdarma', 10, 2 );
    
    add_action( 'woocommerce_review_order_after_shipping', 'ceske_sluzby_ulozenka_zobrazit_pobocky' );
    add_action( 'woocommerce_add_shipping_order_item', 'ceske_sluzby_ulozenka_ulozeni_pobocky', 10, 2 );
    add_action( 'woocommerce_checkout_process', 'ceske_sluzby_ulozenka_overit_pobocku' );
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_ulozenka_pobocka_email' );
    add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_ulozenka_pobocka_email' );
    
    add_action( 'woocommerce_review_order_after_shipping', 'ceske_sluzby_dpd_parcelshop_zobrazit_pobocky' );
    add_action( 'woocommerce_add_shipping_order_item', 'ceske_sluzby_dpd_parcelshop_ulozeni_pobocky', 10, 2 );
    add_action( 'woocommerce_checkout_process', 'ceske_sluzby_dpd_parcelshop_overit_pobocku' );
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_dpd_parcelshop_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_dpd_parcelshop_pobocka_email' );
    add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_dpd_parcelshop_pobocka_email' );
    
    add_filter( 'woocommerce_pay4pay_cod_amount', 'ceske_sluzby_ulozenka_dobirka_pay4pay' );
    add_filter( 'woocommerce_pay4pay_cod_amount', 'ceske_sluzby_dpd_parcelshop_dobirka_pay4pay' );
    
    $aktivace_recenzi = get_option( 'wc_ceske_sluzby_heureka_recenze_obchodu-aktivace' );
    if ( $aktivace_recenzi == "yes" ) {
      add_shortcode( 'heureka-recenze-obchodu', 'ceske_sluzby_heureka_recenze_obchodu' );
    }
	}
}
add_action( 'plugins_loaded', 'ceske_sluzby_kontrola_aktivniho_pluginu' );

function ceske_sluzby_doprava_ulozenka_init() {
	if ( ! class_exists( 'WC_Shipping_Ceske_Sluzby_Ulozenka' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-ulozenka.php';
	}
}
 
function ceske_sluzby_doprava_ulozenka( $methods ) {
	$methods[] = 'WC_Shipping_Ceske_Sluzby_Ulozenka';
	return $methods;
}

function ceske_sluzby_ulozenka_zobrazit_pobocky() {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  $settings = array();

  if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;

    if ( $settings['enabled'] == "yes" && ! empty ( $settings['ulozenka_id-obchodu'] ) ) {

    $pobocky = new Ceske_Sluzby_Json_Loader();
    // http://docs.ulozenkav3.apiary.io/#pepravnsluby

    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) { $zeme_code = "CZE"; }
    if ( $zeme == "SK" ) { $zeme_code = "SVK"; }

    $parametry = array( 'provider' => 1, 'country' => $zeme_code );
    ?>
    
    <tr class="ulozenka">
      <td>
        <img src="https://www.ulozenka.cz/logo/ulozenka.png" width="140" border="0">
      </td>
      <td>
        <font size="2">Uloženka - výběr pobočky:</font><br>
        <div id="ulozenka-branch-select-options">
          <select name="ulozenka_branches">
          <option>Vyberte pobočku</option>
    
    <?php
    foreach ( $pobocky->load( $parametry )->data->destination as $pobocka ) {
      echo '<option value="' . $pobocka->name . '">' . $pobocka->name . '</option>';
    } ?>
    
        </div>
      </td>
    </tr>
    
    <?php }
  }
}

function ceske_sluzby_ulozenka_ulozeni_pobocky( $order_id, $item_id ) {
    if ( $_POST["ulozenka_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', esc_attr( $_POST['ulozenka_branches'] ), true );
    }
}

function ceske_sluzby_ulozenka_overit_pobocku() {
	if ( $_POST["ulozenka_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
		wc_add_notice( 'Pokud chcete doručit zboží prostřednictvím Uloženky, zvolte prosím pobočku.', 'error' );
  }
}

function ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku( $order ) {
  if ( $order->has_shipping_method( 'ceske_sluzby_ulozenka' ) ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>Uloženka:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true ) . "</p>";
    }
  }
}

function ceske_sluzby_ulozenka_dobirka_pay4pay( $amount ) {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) { if ( empty( $settings['ulozenka_dobirka'] ) ) { return $amount; } else { return $settings['ulozenka_dobirka']; } }
    if ( $zeme == "SK" ) { if ( empty( $settings['ulozenka_dobirka-slovensko'] ) ) { return $amount; } else { return $settings['ulozenka_dobirka-slovensko']; } }
  }
  else {
    return $amount;
  }
}

function ceske_sluzby_ulozenka_pobocka_email( $order ) {
  if ( $order->has_shipping_method( 'ceske_sluzby_ulozenka' ) ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>Uloženka:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true ) . "</p>";
    }
  }
}

function ceske_sluzby_doprava_dpd_parcelshop_init() {
	if ( ! class_exists( 'WC_Shipping_Ceske_Sluzby_DPD_ParcelShop' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-dpd-parcelshop.php';
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-json-loader.php';
	}
}
 
function ceske_sluzby_doprava_dpd_parcelshop( $methods ) {
	$methods[] = 'WC_Shipping_Ceske_Sluzby_DPD_ParcelShop';
	return $methods;
}

function ceske_sluzby_dpd_parcelshop_zobrazit_pobocky() {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  $settings = array();

  if ( $chosen_shipping_method[0] == "ceske_sluzby_dpd_parcelshop" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;

    if ( $settings['enabled'] == "yes" ) {

    $pobocky = new Ceske_Sluzby_Json_Loader();
    // http://docs.ulozenkav3.apiary.io/#pepravnsluby

    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) { $zeme_code = "CZE"; }
    if ( $zeme == "SK" ) { $zeme_code = "SVK"; }

    $parametry = array( 'provider' => 5, 'country' => $zeme_code );
    ?>
    
    <tr class="dpd-parcelshop">
      <td>
        <img src="http://www.dpdparcelshop.cz/images/DPD-logo.png" width="140" border="0">
      </td>
      <td>
        <font size="2">DPD ParcelShop - výběr pobočky:</font><br>
        <div id="dpd-parcelshop-branch-select-options">
          <select name="dpd_parcelshop_branches">
          <option>Vyberte pobočku</option>
    
    <?php
    foreach ( $pobocky->load( $parametry )->data->destination as $pobocka ) {
      echo '<option value="' . $pobocka->name . '">' . $pobocka->name . '</option>';
    } ?>
    
        </div>
      </td>
    </tr>
    
    <?php }
  }
}

function ceske_sluzby_dpd_parcelshop_ulozeni_pobocky( $order_id, $item_id ) {
    if ( $_POST["dpd_parcelshop_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_dpd_parcelshop" ) {
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', esc_attr( $_POST['dpd_parcelshop_branches'] ), true );
    }
}

function ceske_sluzby_dpd_parcelshop_overit_pobocku() {
	if ( $_POST["dpd_parcelshop_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "ceske_sluzby_dpd_parcelshop" ) {
		wc_add_notice( 'Pokud chcete doručit zboží prostřednictvím DPD ParcelShop, zvolte prosím pobočku.', 'error' );
  }
}

function ceske_sluzby_dpd_parcelshop_objednavka_zobrazit_pobocku( $order ) {
  if ( $order->has_shipping_method( 'ceske_sluzby_dpd_parcelshop' ) ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>DPD ParcelShop:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', true ) . "</p>";
    }
  }
}

function ceske_sluzby_dpd_parcelshop_dobirka_pay4pay( $amount ) {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  if ( $chosen_shipping_method[0] == "ceske_sluzby_dpd_parcelshop" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) { if ( empty( $settings['dpd_parcelshop_dobirka'] ) ) { return $amount; } else { return $settings['dpd_parcelshop_dobirka']; } }
    if ( $zeme == "SK" ) { if ( empty( $settings['dpd_parcelshop_dobirka-slovensko'] ) ) { return $amount; } else { return $settings['dpd_parcelshop_dobirka-slovensko']; } }
  }
  else {
    return $amount;
  }
}

function ceske_sluzby_dpd_parcelshop_pobocka_email( $order ) {
  if ( $order->has_shipping_method( 'ceske_sluzby_dpd_parcelshop' ) ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>DPD ParcelShop:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', true ) . "</p>";
    }
  }
}

function ceske_sluzby_moznost_menit_dobirku( $zmena, $objednavka ) {
// http://www.separatista.net/forum/tema/woocommerce-a-dobirka
  $moznost_zmeny = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dobirka-zmena' );
  $status = $objednavka->get_status();
  if ( $moznost_zmeny == "yes" && $status == "processing" ) {
    $zmena = true;
  }
  return $zmena;
}

add_action( 'init', 'ceske_sluzby_aktivace_xml_feed' );
function ceske_sluzby_aktivace_xml_feed() {
	$aktivace_xml = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
	if ( $aktivace_xml == "yes" ) {
		require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
		add_feed( 'heureka', 'heureka_xml_feed_zobrazeni' );
		add_feed( 'zbozi', 'zbozi_xml_feed_zobrazeni' );

		if ( ! wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' ) ) {
			pricemania_xml_feed_aktualizace(); // Musíme poprvé spustit?
			wp_schedule_event( current_time( 'timestamp' ), 'daily', 'ceske_sluzby_pricemania_aktualizace_xml' );
		}
	}
}

add_action( 'ceske_sluzby_pricemania_aktualizace_xml', 'ceske_sluzby_pricemania_xml_feed_aktualizace' );
function ceske_sluzby_pricemania_xml_feed_aktualizace() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
	pricemania_xml_feed_aktualizace();
}

// http://docs.woothemes.com/document/hide-other-shipping-methods-when-free-shipping-is-available/
function ceske_sluzby_omezit_dopravu_pokud_dostupna_zdarma( $rates, $package ) {
  $omezit_dopravu = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_doprava-pouze-zdarma' );
  if ( $omezit_dopravu == "yes" ) {
    if ( isset( $rates['free_shipping'] ) ) {
      $free_shipping = $rates['free_shipping'];
      if ( isset( $rates['local_pickup'] ) ) {
        $local_pickup = $rates['local_pickup'];
      }
      $rates = array();
      $rates['free_shipping'] = $free_shipping;
      if ( isset( $local_pickup ) ) {
        $rates['local_pickup'] = $local_pickup;
      }
    }
  }
	return $rates;
}

function ceske_sluzby_heureka_recenze_obchodu( $atts ) {
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
    if ( false === ( $source_xml = get_transient( 'ceske_sluzby_heureka_recenze_zakazniku' ) ) ) {
      $url = "http://www." . HEUREKA_URL . "/direct/dotaznik/export-review.php?key=" . $api;
      $source_xml = wp_remote_retrieve_body( wp_remote_get( $url ) );
      set_transient( 'ceske_sluzby_heureka_recenze_zakazniku', $source_xml, 24 * HOUR_IN_SECONDS );
    }

    $recenze_xml = simplexml_load_string( $source_xml, 'SimpleXMLElement', LIBXML_NOCDATA );

    $output = '<div class="recenze-zakazniku">';
    foreach( $recenze_xml as $recenze ) {
      if ( ! empty ( $recenze->summary ) ) {
        $output .= '<ul>';
        $output .= '<li>';
        $output .= '<strong>' . $recenze->summary . '</strong><br />';
        if ( ! empty ( $recenze->total_rating ) ) {
          $output .= 'Hodnocení: ' . $recenze->total_rating . '/5 | ';
        }
        $output .= 'Datum: před ' . human_time_diff( $recenze->unix_timestamp );
        if ( ! empty ( $recenze->name ) ) {
          $output .= ' | Autor: ' . $recenze->name;
        } 
        $output .= '</li>';
        $output .= '</ul>';
      }
    }
    $output .= '</div>';
  }
	return $output;
}

