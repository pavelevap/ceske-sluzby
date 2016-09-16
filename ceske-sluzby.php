<?php
/**
 * Plugin Name: České služby pro WordPress
 * Plugin URI: http://www.separatista.net
 * Description: Implementace různých českých služeb do WordPressu.
 * Version: 0.5
 * Author: Pavel Hejn
 * Author URI: http://www.separatista.net
 * License: GPL2
 */

define( 'CS_VERSION', '0.5' );

$language = get_locale();
if ( $language == "sk_SK" ) {
  define( "HEUREKA_URL", "heureka.sk" );
  define( "GOOGLE_MENA", "EUR" );
}
else {
  define( "HEUREKA_URL", "heureka.cz" );
  define( "GOOGLE_MENA", "CZK" );
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
    ho.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.<?php echo HEUREKA_URL; ?>/direct/js/ext/1-roi-async.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ho, s);
})();
</script>

<?php
  }
}

function ceske_sluzby_heureka_certifikat_spokojenosti() {
  $api = get_option( 'wc_ceske_sluzby_heureka_konverze-api' );
  $certifikat = get_option( 'wc_ceske_sluzby_heureka_certifikat_spokojenosti-aktivace' );
  if ( ! empty( $api ) && $certifikat == "yes" ) {
    $umisteni = get_option( 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_umisteni' );
    $odsazeni = get_option( 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_odsazeni' );
    if ( ! empty( $umisteni ) ) {
      if ( $umisteni == "vlevo" ) {
        $umisteni = 21;
      } else {
        $umisteni = 22;
      }
    } else {
      $umisteni = 21;
    }
    if ( empty( $odsazeni ) ) {
      $odsazeni = 60;
    }
  ?>
    
<script type="text/javascript">
//<![CDATA[
var _hwq = _hwq || [];
    _hwq.push(['setKey', '<?php echo $api; ?>']);_hwq.push(['setTopPos', '<?php echo $odsazeni; ?>']);_hwq.push(['showWidget', '<?php echo $umisteni; ?>']);(function() {
    var ho = document.createElement('script'); ho.type = 'text/javascript'; ho.async = true;
    ho.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.<?php echo HEUREKA_URL; ?>/direct/i/gjs.php?n=wdgt&sak=<?php echo $api; ?>';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ho, s);
})();
//]]>
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

function ceske_sluzby_sledovani_zasilek_email( $email_classes ) {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-sledovani-zasilek-email.php';
  $email_classes['WC_Email_Ceske_Sluzby_Sledovani_Zasilek'] = new WC_Email_Ceske_Sluzby_Sledovani_Zasilek();
  return $email_classes;
}

function ceske_sluzby_sledovani_zasilek_email_akce( $email_actions ) {
  $email_actions[] = 'woocommerce_ceske_sluzby_sledovani_zasilek_email_akce';
  return $email_actions;
}
 
function ceske_sluzby_kontrola_aktivniho_pluginu() {
  if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.2', '>=' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/ceske-sluzby-functions.php';
    if ( is_admin() ) {
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-admin.php';
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-sledovani-zasilek.php';
      WC_Settings_Tab_Ceske_Sluzby_Admin::init();
      $xml_feed = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
      if ( $xml_feed == "yes" ) {
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-product-tab.php';
        new WC_Product_Tab_Ceske_Sluzby_Admin();
      }
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

    $sledovani_zasilek = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_sledovani-zasilek' );
    if ( $sledovani_zasilek == "yes" ) {
      add_filter( 'woocommerce_email_classes', 'ceske_sluzby_sledovani_zasilek_email' );
      add_filter( 'woocommerce_email_actions', 'ceske_sluzby_sledovani_zasilek_email_akce' );
    }

    $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
    if ( $aktivace_dodaci_doby == "yes" ) {
      $dodaci_doba = get_option( 'wc_ceske_sluzby_dodaci_doba_zobrazovani' );
      if ( ! empty ( $dodaci_doba ) ) {
        foreach ( $dodaci_doba as $zobrazeni ) {
          if ( $zobrazeni == 'get_availability_text' ) {
            add_filter( 'woocommerce_get_availability_text', 'ceske_sluzby_zobrazit_dodaci_dobu_filtr', 10, 2 );
          }
          if ( $zobrazeni == 'before_add_to_cart_form' ) {
            add_action( 'woocommerce_before_add_to_cart_form', 'ceske_sluzby_zobrazit_dodaci_dobu_akce' );
          }
          if ( $zobrazeni == 'after_shop_loop_item' ) {
            add_action( 'woocommerce_after_shop_loop_item', 'ceske_sluzby_zobrazit_dodaci_dobu_akce', 9 );
          }
        }
      }
      $predobjednavka = get_option( 'wc_ceske_sluzby_preorder-aktivace' );
      if ( $predobjednavka == "yes" ) {
        add_action( 'admin_enqueue_scripts', 'ceske_sluzby_load_admin_scripts' );
      }
    }

    add_action( 'product_cat_add_form_fields', 'ceske_sluzby_xml_kategorie_pridat_pole', 99 );
    add_action( 'product_cat_edit_form_fields', 'ceske_sluzby_xml_kategorie_upravit_pole', 99 );
    add_action( 'created_term', 'ceske_sluzby_xml_kategorie_ulozit', 20, 3 );
    add_action( 'edit_term', 'ceske_sluzby_xml_kategorie_ulozit', 20, 3 );
    add_filter( 'manage_edit-product_cat_columns', 'ceske_sluzby_xml_kategorie_pridat_sloupec' );
    add_filter( 'manage_product_cat_custom_column', 'ceske_sluzby_xml_kategorie_sloupec', 10, 3 );

    add_action( 'wp_footer', 'ceske_sluzby_heureka_certifikat_spokojenosti' ); // Pouze pro eshop nebo na celém webu?
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
  if ( is_ajax() ) {
    // Do budoucna možná použít spíše woocommerce_checkout_update_order_review
    parse_str( $_POST['post_data'] );
    $available_shipping = WC()->shipping->load_shipping_methods();
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
          if ( ! empty ( $ulozenka_branches ) && $ulozenka_branches == $pobocka->name ) {
            $selected = ' selected="selected"';
          } else {
            $selected = "";
          }
          echo '<option value="' . $pobocka->name . '"' . $selected . '>' . $pobocka->name . '</option>';
        } ?>
    
            </div>
          </td>
        </tr>
    
      <?php }
    }
  }
}

function ceske_sluzby_ulozenka_ulozeni_pobocky( $order_id, $item_id ) {
  if ( isset( $_POST["ulozenka_branches"] ) ) {
    if ( $_POST["ulozenka_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', esc_attr( $_POST['ulozenka_branches'] ), true );
    }
  }
}

function ceske_sluzby_ulozenka_overit_pobocku() {
  if ( isset( $_POST["ulozenka_branches"] ) ) {
    if ( $_POST["ulozenka_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
      wc_add_notice( 'Pokud chcete doručit zboží prostřednictvím Uloženky, zvolte prosím pobočku.', 'error' );
    }
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
  $available_shipping = WC()->shipping->load_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) {
      if ( ! empty( $settings['ulozenka_dobirka'] ) ) {
        $amount = $settings['ulozenka_dobirka'];
      } 
    }
    if ( $zeme == "SK" ) {
      if ( ! empty( $settings['ulozenka_dobirka-slovensko'] ) ) {
        $amount = $settings['ulozenka_dobirka-slovensko'];
      }
    }
    if ( class_exists( 'WOOCS' ) ) {
      $amount = apply_filters( 'woocs_exchange_value', $amount );
    }
  }
  return $amount;
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
  if ( is_ajax() ) {
    parse_str( $_POST['post_data'] );
    $available_shipping = WC()->shipping->load_shipping_methods();
    $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
    $settings = array();

    if ( $chosen_shipping_method[0] == "ceske_sluzby_dpd_parcelshop" ) {
      $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;

      if ( $settings['enabled'] == "yes" ) {

        $pobocky = new Ceske_Sluzby_Json_Loader();

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
          if ( ! empty ( $dpd_parcelshop_branches ) && $dpd_parcelshop_branches == $pobocka->name ) {
            $selected = ' selected="selected"';
          } else {
            $selected = '';
          }
          echo '<option value="' . $pobocka->name . '"' . $selected . '>' . $pobocka->name . '</option>';
        } ?>
    
            </div>
          </td>
        </tr>
    
      <?php }
    }
  }
}

function ceske_sluzby_dpd_parcelshop_ulozeni_pobocky( $order_id, $item_id ) {
  if ( isset( $_POST["dpd_parcelshop_branches"] ) ) {
    if ( $_POST["dpd_parcelshop_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_dpd_parcelshop" ) {
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', esc_attr( $_POST['dpd_parcelshop_branches'] ), true );
    }
  }
}

function ceske_sluzby_dpd_parcelshop_overit_pobocku() {
  if ( isset( $_POST["dpd_parcelshop_branches"] ) ) {
    if ( $_POST["dpd_parcelshop_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "ceske_sluzby_dpd_parcelshop" ) {
      wc_add_notice( 'Pokud chcete doručit zboží prostřednictvím DPD ParcelShop, zvolte prosím pobočku.', 'error' );
    }
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
  $available_shipping = WC()->shipping->load_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  if ( $chosen_shipping_method[0] == "ceske_sluzby_dpd_parcelshop" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) {
      if ( ! empty( $settings['dpd_parcelshop_dobirka'] ) ) {
        $amount = $settings['dpd_parcelshop_dobirka'];
      }
    }
    if ( $zeme == "SK" ) {
      if ( ! empty( $settings['dpd_parcelshop_dobirka-slovensko'] ) ) {
        $amount = $settings['dpd_parcelshop_dobirka-slovensko'];
      }
    }
    if ( class_exists( 'WOOCS' ) ) {
      $amount = apply_filters( 'woocs_exchange_value', $amount );
    }
  }
  return $amount;
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
    add_feed( 'google', 'google_xml_feed_zobrazeni' );

    $heureka_xml = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
    if ( $heureka_xml == "yes" ) {
      if ( ! wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml' ) ) {
        wp_schedule_event( current_time( 'timestamp', 1 ), 'daily', 'ceske_sluzby_heureka_aktualizace_xml' );
      }
    } else {
      if ( wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml' ) ) {
        $timestamp = wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml' );
        wp_unschedule_event( $timestamp, 'ceske_sluzby_heureka_aktualizace_xml' ); 
      }
    }
    
    $zbozi_xml = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
    if ( $zbozi_xml == "yes" ) {
      if ( ! wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml' ) ) {
        wp_schedule_event( current_time( 'timestamp', 1 ) + MINUTE_IN_SECONDS, 'daily', 'ceske_sluzby_zbozi_aktualizace_xml' );
      }
    } else {
      if ( wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml' ) ) {
        $timestamp = wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml' );
        wp_unschedule_event( $timestamp, 'ceske_sluzby_zbozi_aktualizace_xml' ); 
      }
    }

    $pricemania_xml = get_option( 'wc_ceske_sluzby_xml_feed_pricemania-aktivace' );
    if ( $pricemania_xml == "yes" ) {
      if ( ! wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' ) ) {
        wp_schedule_event( current_time( 'timestamp', 1 ) + ( 2 * MINUTE_IN_SECONDS ), 'daily', 'ceske_sluzby_pricemania_aktualizace_xml' );
      }
    } else {
      if ( wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' ) ) {
        $timestamp = wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' );
        wp_unschedule_event( $timestamp, 'ceske_sluzby_pricemania_aktualizace_xml' );
      }
    }
  } else {
    if ( wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_heureka_aktualizace_xml' );
    }
    if ( wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_zbozi_aktualizace_xml' );
    }
    if ( wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_pricemania_aktualizace_xml' );
    }
  }
}

add_action( 'ceske_sluzby_heureka_aktualizace_xml', 'ceske_sluzby_heureka_xml_feed_aktualizace' );
add_action( 'ceske_sluzby_heureka_aktualizace_xml_batch', 'ceske_sluzby_heureka_xml_feed_aktualizace' );
function ceske_sluzby_heureka_xml_feed_aktualizace() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
  heureka_xml_feed_aktualizace();
}

add_action( 'ceske_sluzby_zbozi_aktualizace_xml', 'ceske_sluzby_zbozi_xml_feed_aktualizace' );
add_action( 'ceske_sluzby_zbozi_aktualizace_xml_batch', 'ceske_sluzby_zbozi_xml_feed_aktualizace' );
function ceske_sluzby_zbozi_xml_feed_aktualizace() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
  zbozi_xml_feed_aktualizace();
}

add_action( 'ceske_sluzby_pricemania_aktualizace_xml', 'ceske_sluzby_pricemania_xml_feed_aktualizace' );
add_action( 'ceske_sluzby_pricemania_aktualizace_xml_batch', 'ceske_sluzby_pricemania_xml_feed_aktualizace' );
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
  $output = '<p>Pro zobrazení recenzí musíte ještě zadat API klíč pro Ověřeno zákazníky.</p>';
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
    if ( false === ( $source_xml = get_transient( 'ceske_sluzby_heureka_recenze_zakazniku' ) ) ) {
      $url = "http://www." . HEUREKA_URL . "/direct/dotaznik/export-review.php?key=" . $api;
      $source_xml = wp_remote_retrieve_body( wp_remote_get( $url ) );
      set_transient( 'ceske_sluzby_heureka_recenze_zakazniku', $source_xml, 24 * HOUR_IN_SECONDS );
    }

    $recenze_xml = simplexml_load_string( $source_xml, 'SimpleXMLElement', LIBXML_NOCDATA );
    $atributy = shortcode_atts( array( 'limit' => null ), $atts );
    $limit = $atributy['limit'];
    $i = 0;

    $output = '<div class="recenze-zakazniku">';
    if ( ! empty( $recenze_xml ) && ! is_scalar( $recenze_xml ) ) {
      foreach( $recenze_xml as $recenze ) {
        if ( ( ! empty ( $limit ) && $i < $limit ) || empty ( $limit ) ) {
          if ( ! empty ( $recenze->summary ) ) {
            $i = $i + 1;
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
        else {
          break;
        }
      }
    }
    else {
      $output .= 'Zatím žádné hodnocení.';
    }
    $output .= '</div>';
  }
	return $output;
}

function ceske_sluzby_xml_kategorie_pridat_pole() {
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' ); ?>
  <tr>
    <th scope="row" valign="top"><strong>České služby:</strong></th>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Kategorie Heureka XML</label></th>
    <td> 
      <input name="ceske-sluzby-xml-heureka-kategorie" id="ceske-sluzby-xml-heureka-kategorie" type="text" value="" size="70"/>
      <p class="description">
        Zatím je nutné ručně doplnit příslušnou kategorii z Heureky (aktuální přehled naleznete <a href="http://www.<?php echo HEUREKA_URL; ?>/direct/xml-export/shops/heureka-sekce.xml">zde</a>).<br />
        Příklad: <strong>Elektronika | Počítače a kancelář | Software | Multimediální software</strong><br />
        Z CATEGORY_FULLNAME je také třeba vynechat úvodní část "<?php echo ucfirst( HEUREKA_URL ); ?> | ".
      </p>
    </td>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Odebrat z XML</label></th>
    <td> 
      <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" />
      <span class="description">
        Zaškrtněte, pokud chcete odebrat všechny produkty této kategorie z XML feedů.
      </span>
    </td>
  </tr>
  <?php
  if ( ! empty( $global_stav_produkt ) ) {
    if ( $global_stav_produkt == 'used' ) {
      $stav_produkt_hodnota = 'Použité (bazar)';
    } else {
      $stav_produkt_hodnota = 'Repasované';
    }
    $stav_produkt_text = 'Na úrovni eshopu je <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> hodnota: <strong>' . $stav_produkt_hodnota . '</strong>. Nastavení kategorie bude mít ale přednost.';
  } else {
    $stav_produkt_text = 'Na úrovni eshopu zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.';
  } ?>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Stav produktů</label></th>
    <td>
      <select id="ceske-sluzby-xml-stav-produktu" name="ceske-sluzby-xml-stav-produktu" class="postform">
        <option value="">- Vyberte -</option>
        <option value="used">Použité (bazar)</option>
        <option value="refurbished">Repasované</option>
      </select>
      <span class="description">
        <?php echo $stav_produkt_text; ?>
      </span>
    </td>
  </tr>
<?php
}

function ceske_sluzby_xml_kategorie_upravit_pole( $term ) {
  $checked = '';
  $heureka_kategorie = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
  $xml_vynechano_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-vynechano', true );
  $xml_stav_produktu = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-stav-produktu', true );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' ); ?>
  <tr>
    <th scope="row" valign="top"><strong>České služby:</strong></th>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Kategorie Heureka</label></th>
    <td> 
      <input name="ceske-sluzby-xml-heureka-kategorie" id="ceske-sluzby-xml-heureka-kategorie" type="text" value="<?php echo esc_attr( $heureka_kategorie ); ?>" />
      <p class="description">
        Zatím je nutné ručně doplnit příslušnou kategorii z Heureky (aktuální přehled naleznete <a href="http://www.<?php echo HEUREKA_URL; ?>/direct/xml-export/shops/heureka-sekce.xml">zde</a>).<br />
        Příklad: <strong>Elektronika | Počítače a kancelář | Software | Multimediální software</strong><br />
        Z CATEGORY_FULLNAME je také třeba vynechat úvodní část "<?php echo ucfirst( HEUREKA_URL ); ?> | ".
      </p>
    </td>
  </tr>
  <?php
  if ( ! empty( $xml_vynechano_ulozeno ) ) {
    $checked = 'checked="checked"';
  } ?>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Odebrat z XML</label></th>
    <td> 
      <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" <?php echo $checked; ?>/>
      <span class="description">
        Zaškrtněte, pokud chcete odebrat všechny produkty této kategorie z XML feedů.
      </span>
    </td>
  </tr>
  <?php
  if ( ! empty( $global_stav_produkt ) ) {
    if ( $global_stav_produkt == 'used' ) {
      $stav_produkt_hodnota = 'Použité (bazar)';
    } else {
      $stav_produkt_hodnota = 'Repasované';
    }
    $stav_produkt_text = 'Na úrovni eshopu je <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> hodnota: <strong>' . $stav_produkt_hodnota . '</strong>. Nastavení kategorie bude mít ale přednost.';
  } else {
    $stav_produkt_text = 'Na úrovni eshopu zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.';
  } ?>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Stav produktů</label></th>
    <td>
      <select id="ceske-sluzby-xml-stav-produktu" name="ceske-sluzby-xml-stav-produktu" class="postform">
        <option value="" <?php selected( '', $xml_stav_produktu ); ?>>- Vyberte -</option>
        <option value="used" <?php selected( 'used', $xml_stav_produktu ); ?>>Použité (bazar)</option>
        <option value="refurbished" <?php selected( 'refurbished', $xml_stav_produktu ); ?>>Repasované</option>
      </select>
      <span class="description">
        <?php echo $stav_produkt_text; ?>
      </span>
    </td>
  </tr>
<?php // http://themehybrid.com/weblog/introduction-to-wordpress-term-meta
}

function ceske_sluzby_xml_kategorie_ulozit( $term_id, $tt_id = '', $taxonomy = '' ) {
  if ( isset( $_POST['ceske-sluzby-xml-heureka-kategorie'] ) && 'product_cat' === $taxonomy ) {
    $heureka_kategorie = str_replace( 'Heureka.cz | ', '', $_POST['ceske-sluzby-xml-heureka-kategorie'] );
    $heureka_kategorie = str_replace( 'Heureka.sk | ', '', $_POST['ceske-sluzby-xml-heureka-kategorie'] );
    update_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-heureka-kategorie', esc_attr( $heureka_kategorie ) );
  }

  $xml_vynechano_ulozeno = get_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-vynechano', true );
  if ( isset( $_POST['ceske-sluzby-xml-vynechano'] ) && 'product_cat' === $taxonomy ) {
    $xml_vynechano = $_POST['ceske-sluzby-xml-vynechano'];
    if ( ! empty( $xml_vynechano ) ) {
      update_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-vynechano', esc_attr( $xml_vynechano ) );  
    }
  } elseif ( ! empty( $xml_vynechano_ulozeno ) ) {
    delete_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-vynechano' );   
  }

  $xml_stav_produktu_ulozeno = get_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-stav-produktu', true );
  if ( isset( $_POST['ceske-sluzby-xml-stav-produktu'] ) && ! empty( $_POST['ceske-sluzby-xml-stav-produktu'] ) && 'product_cat' === $taxonomy ) {
    update_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-stav-produktu', esc_attr( $_POST['ceske-sluzby-xml-stav-produktu'] ) );  
  } elseif ( ! empty( $xml_stav_produktu_ulozeno ) ) {
    delete_woocommerce_term_meta( $term_id, 'ceske-sluzby-xml-stav-produktu' );   
  }
}

function ceske_sluzby_xml_kategorie_pridat_sloupec( $columns ) {
  $new_columns = array();
  $new_columns['xml-heureka'] = 'Nastavení XML';
  return array_merge( $columns, $new_columns );
}

function ceske_sluzby_xml_kategorie_sloupec( $columns, $column, $id ) {
  if ( 'xml-heureka' == $column ) {
    $heureka_kategorie = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-heureka-kategorie', true );
    if ( $heureka_kategorie ) {
      $columns .= '<a href="#" title="Heureka: ' . $heureka_kategorie . '">Kategorie</a>';
    }
    $kategorie_vynechano = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-vynechano', true );
    if ( $kategorie_vynechano ) {
      $columns .= '<span style="margin-left: 10px; color: red;">x</span>';
    }
    $stav_produktu = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-stav-produktu', true );
    if ( $stav_produktu ) {
      if ( $stav_produktu == 'used' ) {
        $stav_produktu_hodnota = 'Použité (bazar)';
      } else {
        $stav_produktu_hodnota = 'Repasované';
      }
      $columns .= '<br />' . $stav_produktu_hodnota;
    }
  }
  return $columns;
}

function ceske_sluzby_zobrazit_dodaci_dobu_filtr( $availability, $product ) {
  $dostupnost = array();
  $predobjednavka = get_post_meta( $product->id, 'ceske_sluzby_xml_preorder_datum', true );
  if ( ! empty ( $predobjednavka ) && $product->is_in_stock() ) {
    if ( (int)$predobjednavka >= strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
      $availability_predobjednavka = 'Předobjednávka: ' . date_i18n( 'j.n.Y', $predobjednavka );
      $availability = $availability_predobjednavka;
    }
  }
  $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu();
  if ( ! empty ( $dodaci_doba ) && $product->is_in_stock() && empty ( $availability_predobjednavka ) ) {
    $dodaci_doba_produkt = get_post_meta( $product->id, 'ceske_sluzby_dodaci_doba', true );
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $dodaci_doba_produkt );
    if ( empty ( $dostupnost ) ) {
      $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
      $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $global_dodaci_doba );
    }
    if ( ! empty ( $dostupnost ) ) {
      $availability = $dostupnost['text'];
    }
  }
  return $availability;
}

function ceske_sluzby_zobrazit_dodaci_dobu_akce() {
  global $product;
  $format = "";
  $dostupnost = array();
  $predobjednavka = get_post_meta( $product->id, 'ceske_sluzby_xml_preorder_datum', true );
  if ( ! empty ( $predobjednavka ) && $product->is_in_stock() ) {
    if ( (int)$predobjednavka >= strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
      $format = ceske_sluzby_ziskat_format_predobjednavky( $predobjednavka );
    }
  }
  $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu();
  if ( ! empty ( $dodaci_doba ) && $product->is_in_stock() && empty ( $format ) )  {
    $dodaci_doba_produkt = get_post_meta( $product->id, 'ceske_sluzby_dodaci_doba', true );
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $dodaci_doba_produkt );
    if ( empty ( $dostupnost ) ) {
      $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
      $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $global_dodaci_doba );
    }
    if ( ! empty ( $dostupnost ) ) {
      $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
  }
  echo $format;
}

function ceske_sluzby_load_admin_scripts() {
  $screen = get_current_screen();
  $screen_id = $screen ? $screen->id : '';
  if ( in_array( $screen_id, array( 'product', 'edit-product', 'shop_order' ) ) ) {
    wp_register_script( 'wc-admin-ceske-sluzby', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/ceske-sluzby-admin.js', array( 'jquery-ui-datepicker' ), CS_VERSION );
    wp_enqueue_script( 'wc-admin-ceske-sluzby' );
  }
}