<?php
/**
 * Plugin Name: České služby pro WordPress
 * Plugin URI: http://www.separatista.net
 * Description: Implementace různých českých služeb do WordPressu.
 * Version: 0.6-alpha
 * Author: Pavel Hejn
 * Author URI: http://www.separatista.net
 * GitHub Plugin URI: pavelevap/ceske-sluzby 
 * License: GPL2
 */

define( 'CS_VERSION', '0.6-alpha' );

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
    try {
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
    catch ( OverflowException $o ) {
      $order->add_order_note( 'API klíč pro službu Ověřeno zákazníky nebyl správně nastaven: ' . $o->getMessage() );
    }
    catch ( HeurekaOverenoException $e ) {
      $order->add_order_note( 'Odeslání dat pro službu Ověřeno zákazníky se nezdařilo: ' . $e->getMessage() );
    }
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
  if ( ! empty( $konverze ) ) {
    $order = new WC_Order( $order_id );
    $hodnota_objednavky = round( $order->get_subtotal() ); ?>
<!-- Měřicí kód Sklik.cz -->
<iframe width="119" height="22" frameborder="0" scrolling="no" src="//c.imedia.cz/checkConversion?c=<?php echo $konverze; ?>&color=ffffff&v=<?php echo $hodnota_objednavky; ?>"></iframe>
  <?php
  }
}

function ceske_sluzby_sklik_retargeting() {
  $konverze = get_option( 'wc_ceske_sluzby_sklik_retargeting' );
  if ( ! empty( $konverze ) ) { ?>
<script type="text/javascript">
/* <![CDATA[ */
var seznam_retargeting_id = <?php echo $konverze; ?>;
/* ]]> */
</script>
<script type="text/javascript" src="//c.imedia.cz/js/retargeting.js"></script>
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
        require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-varianty.php';
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
    add_action( 'wp_footer', 'ceske_sluzby_sklik_retargeting' );
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
            // Původně použitý filtr woocommerce_get_availability_text je funkční až od WooCommerce 2.6.2
            // https://github.com/woocommerce/woocommerce/commit/33346938855b334861678bccecef4a58e5fc0cfa
            add_filter( 'woocommerce_get_availability', 'ceske_sluzby_zobrazit_dodaci_dobu_filtr', 10, 2 );
            add_action( 'woocommerce_before_add_to_cart_form', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce' );
            add_filter( 'woocommerce_available_variation', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_filtr', 10, 3 );
          }
          if ( $zobrazeni == 'before_add_to_cart_form' ) {
            add_action( 'woocommerce_before_add_to_cart_form', 'ceske_sluzby_zobrazit_dodaci_dobu_akce' );
            add_filter( 'woocommerce_stock_html', 'ceske_sluzby_nahradit_zobrazeny_text', 10, 3 );
            add_action( 'woocommerce_before_add_to_cart_form', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce' );
            add_filter( 'woocommerce_available_variation', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_filtr', 10, 3 );
          }
          if ( $zobrazeni == 'after_shop_loop_item' ) {
            add_action( 'woocommerce_after_shop_loop_item', 'ceske_sluzby_zobrazit_dodaci_dobu_akce', 8 );
            add_action( 'woocommerce_after_shop_loop_item', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce', 9 );
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
              </select>    	
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
  $process = true;
  $output = '<div class="recenze-zakazniku">';
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
    if ( false === ( $source_xml = get_transient( 'ceske_sluzby_heureka_recenze_zakazniku' ) ) ) {
      $url = "http://www." . HEUREKA_URL . "/direct/dotaznik/export-review.php?key=" . $api;
      $response = wp_remote_get( $url );
      if ( ! is_wp_error( $response ) ) {
        $source_xml = wp_remote_retrieve_body( $response );
        if ( ! empty( $source_xml ) && wp_remote_retrieve_response_code( $response ) == 200 ) {
          set_transient( 'ceske_sluzby_heureka_recenze_zakazniku', $source_xml, 24 * HOUR_IN_SECONDS );
        } else {
          $process = false;
        }
      } else {
        $output .= 'Nepodařilo se získat data:' . $response->get_error_message();
      }
    }

    if ( $process ) {
      $recenze_xml = simplexml_load_string( $source_xml, 'SimpleXMLElement', LIBXML_NOCDATA );
      $atributy = shortcode_atts( array( 'limit' => null ), $atts );
      $limit = $atributy['limit'];
      $i = 0;

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
    } else {
      $output .= 'Nepodařilo se získat data.';
    }
  } else {
    $output .= 'Pro zobrazení recenzí musíte ještě <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby">zadat</a> API klíč pro Ověřeno zákazníky.';
  }
  $output .= '</div>';
  return $output;
}

function ceske_sluzby_xml_kategorie_pridat_pole() {
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
  $xml_feed_zbozi = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
  if ( $xml_feed_heureka == "yes" ) { ?>
    <div style="font-size: 14px; font-weight: bold;">České služby: Heureka</div>
    <div class="form-field">
      <label for="ceske-sluzby-xml-heureka-kategorie">Kategorie</label>
      <input name="ceske-sluzby-xml-heureka-kategorie" id="ceske-sluzby-xml-heureka-kategorie" type="text" value="" placeholder="CATEGORYTEXT" size="70"/>
      <p>
        Zatím je nutné doplnit příslušnou kategorii z Heureky ručně (aktuální přehled naleznete <a href="http://www.<?php echo HEUREKA_URL; ?>/direct/xml-export/shops/heureka-sekce.xml">zde</a>).<br />
        Příklad: <strong>Elektronika | Počítače a kancelář | Software | Antiviry</strong><br />
        Poznámka: Z <code>CATEGORY_FULLNAME</code> je třeba vynechat část <code><?php echo ucfirst( HEUREKA_URL ); ?> | </code>.
      </p>
    </div>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <div class="form-field">
        <label for="ceske-sluzby-xml-heureka-productname">Název produktů</label>
        <input name="ceske-sluzby-xml-heureka-productname" id="ceske-sluzby-xml-heureka-productname" type="text" value="" placeholder="PRODUCTNAME" size="70"/>
        <p>
          Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Heureky (aktuální přehled naleznete <a href="http://sluzby.<?php echo HEUREKA_URL; ?>/napoveda/povinne-nazvy/" target="_blank">zde</a>).<br />
          Příklad (Svatební dekorace): <strong>Výrobce | Druh | Barva</strong><br />
          Pokud používáte nastavení výrobce, druh máte jako název produktu a barvu zase uloženou jako vlastnost v podobě taxonomie, tak můžete zadat: <code>{MANUFACTURER} {NAZEV} {pa_barva}</code>
        </p>
      </div>
    <?php } ?>
  <?php }
  if ( $xml_feed_zbozi == "yes" ) { ?>
    <div style="font-size: 14px; font-weight: bold;">České služby: Zboží.cz</div>
    <div class="form-field">
      <label for="ceske-sluzby-xml-zbozi-kategorie">Kategorie</label>
      <input name="ceske-sluzby-xml-zbozi-kategorie" id="ceske-sluzby-xml-zbozi-kategorie" type="text" value="" placeholder="CATEGORYTEXT" size="70" />
      <p>
        Zatím je nutné doplnit příslušnou kategorii ze Zbozi.cz ručně (aktuální přehled naleznete <a href="http://www.zbozi.cz/static/categories.csv">zde</a>).<br />
        Příklad: <strong>Počítače | Software | Grafický a video software</strong><br />
      </p>
    </div>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <div class="form-field">
        <label for="ceske-sluzby-xml-zbozi-productname">Název produktů</label>
        <input name="ceske-sluzby-xml-zbozi-productname" id="ceske-sluzby-xml-zbozi-productname" type="text" value="" placeholder="PRODUCTNAME" size="70" />
        <p>
          Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Zboží.cz (aktuální přehled naleznete <a href="http://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/pravidla-pojmenovani-nabidek/" target="_blank">zde</a>).<br />
          Příklad pro konrétní kategorii: <strong>Výrobce | Druh | Barva</strong><br />
          Pokud používáte nastavení výrobce, druh máte jako název produktu a barvu zase uloženou jako vlastnost v podobě taxonomie, tak můžete zadat: <code>{MANUFACTURER} {NAZEV} {pa_barva}</code>
        </p>
      </div>
    <?php }
    $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
    if ( ! empty ( $extra_message_aktivace ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $extra_message_aktivace as $extra_message ) {
        if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) { ?>
          <div class="form-field">
            <label for="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]"><?php echo $extra_message_array[ $extra_message ]; ?></label>
            <span>
              Není potřeba nic zadávat, protože na úrovni eshopu je tato informace <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.
            </span>
          </div>
        <?php } else { ?>
          <div class="form-field">
            <label for="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]"><?php echo $extra_message_array[ $extra_message ]; ?></label>
            <input name="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" id="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" type="checkbox" value="yes" />
            <span>
              Po zaškrtnutí budou produkty v příslušné kategorii označeny příslušnou doplňkovou informací. Na úrovni eshopu zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.
            </span>
          </div>
        <?php } ?>
      <?php }
    }
  } ?>
  <div style="font-size: 14px; font-weight: bold;">České služby: XML feedy</div>
  <div class="form-field">
    <label for="ceske-sluzby-xml-vynechano">Odebrat z XML</label>
    <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" />
    <span>
      Zaškrtněte pokud chcete odebrat produkty této kategorie z XML feedů.
    </span>
  </div>
  <?php
  if ( ! empty( $global_data['stav_produktu'] ) ) {
    if ( $global_data['stav_produktu'] == 'used' ) {
      $stav_produkt_hodnota = 'Použité (bazar)';
    } else {
      $stav_produkt_hodnota = 'Repasované';
    }
    $stav_produkt_text = 'Na úrovni eshopu je <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> hodnota: <strong>' . $stav_produkt_hodnota . '</strong>. Nastavení kategorie bude mít ale přednost.';
  } else {
    $stav_produkt_text = 'Na úrovni eshopu zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.';
  } ?>
  <div class="form-field">
    <label for="ceske-sluzby-xml-stav-produktu">Stav produktů</label>
    <select id="ceske-sluzby-xml-stav-produktu" name="ceske-sluzby-xml-stav-produktu" class="postform">
      <option value="">- Vyberte -</option>
      <option value="used">Použité (bazar)</option>
      <option value="refurbished">Repasované</option>
    </select>
    <span>
      <?php echo $stav_produkt_text; ?>
    </span>
  </div>
  <div class="form-field">
    <label for="ceske-sluzby-xml-erotika">Erotický obsah</label>
    <input name="ceske-sluzby-xml-erotika" id="ceske-sluzby-xml-erotika" type="checkbox" value="yes" />
    <span>
      Zaškrtněte pokud chcete označit obsah webu jako erotický.
    </span>
  </div>
<?php
}

function ceske_sluzby_xml_kategorie_upravit_pole( $term ) {
  $checked = '';
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $heureka_kategorie = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
  $heureka_productname = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-heureka-productname', true );
  $zbozi_kategorie = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-zbozi-kategorie', true );
  $zbozi_productname = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-zbozi-productname', true );
  $kategorie_extra_message_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-zbozi-extra-message', true );
  $xml_vynechano_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-vynechano', true );
  $xml_erotika_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-erotika', true );
  $xml_stav_produktu = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-stav-produktu', true );
  $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
  $xml_feed_zbozi = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
  if ( $xml_feed_heureka == "yes" ) { ?>
    <tr>
      <th scope="row" valign="top"><strong>České služby: Heureka</strong></th>
    </tr>
    <tr class="form-field">
      <th scope="row" valign="top"><label>Kategorie</label></th>
      <td> 
        <input name="ceske-sluzby-xml-heureka-kategorie" id="ceske-sluzby-xml-heureka-kategorie" type="text" value="<?php echo esc_attr( $heureka_kategorie ); ?>" placeholder="CATEGORYTEXT" />
        <p class="description">
          Zatím je nutné doplnit příslušnou kategorii z Heureky ručně (aktuální přehled naleznete <a href="http://www.<?php echo HEUREKA_URL; ?>/direct/xml-export/shops/heureka-sekce.xml">zde</a>).<br />
          Příklad: <strong>Elektronika | Počítače a kancelář | Software | Antiviry</strong><br />
          Poznámka: Z <code>CATEGORY_FULLNAME</code> je třeba vynechat část <code><?php echo ucfirst( HEUREKA_URL ); ?> | </code>.
        </p>
      </td>
    </tr>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <tr class="form-field">
        <th scope="row" valign="top"><label>Název produktů</label></th>
        <td> 
          <input name="ceske-sluzby-xml-heureka-productname" id="ceske-sluzby-xml-heureka-productname" type="text" value="<?php echo esc_attr( $heureka_productname ); ?>" placeholder="PRODUCTNAME" />
            <p class="description">
              Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Heureky (aktuální přehled naleznete <a href="http://sluzby.<?php echo HEUREKA_URL; ?>/napoveda/povinne-nazvy/" target="_blank">zde</a>).<br />
              Příklad (Svatební dekorace): <strong>Výrobce | Druh | Barva</strong><br />
              Pokud používáte nastavení výrobce, druh máte jako název produktu a barvu zase uloženou jako vlastnost v podobě taxonomie, tak můžete zadat: <code>{MANUFACTURER} {NAZEV} {pa_barva}</code>
            </p>
        </td>
      </tr>
    <?php } ?>
  <?php }
  if ( $xml_feed_zbozi == "yes" ) { ?>
    <tr>
      <th scope="row" valign="top"><strong>České služby: Zboží.cz</strong></th>
    </tr>
    <tr class="form-field">
      <th scope="row" valign="top"><label>Kategorie</label></th>
      <td> 
        <input name="ceske-sluzby-xml-zbozi-kategorie" id="ceske-sluzby-xml-zbozi-kategorie" type="text" value="<?php echo esc_attr( $zbozi_kategorie ); ?>" placeholder="CATEGORYTEXT" />
        <p class="description">
          Zatím je nutné doplnit příslušnou kategorii ze Zbozi.cz ručně (aktuální přehled naleznete <a href="http://www.zbozi.cz/static/categories.csv">zde</a>).<br />
          Příklad: <strong>Počítače | Software | Grafický a video software</strong><br />
        </p>
      </td>
    </tr>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <tr class="form-field">
        <th scope="row" valign="top"><label>Název produktů</label></th>
        <td> 
          <input name="ceske-sluzby-xml-zbozi-productname" id="ceske-sluzby-xml-zbozi-productname" type="text" value="<?php echo esc_attr( $zbozi_productname ); ?>" placeholder="PRODUCTNAME" />
            <p class="description">
              Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Zboží.cz (aktuální přehled naleznete <a href="http://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/pravidla-pojmenovani-nabidek/" target="_blank">zde</a>).<br />
              Příklad pro konrétní kategorii: <strong>Výrobce | Druh | Barva</strong><br />
              Pokud používáte nastavení výrobce, druh máte jako název produktu a barvu zase uloženou jako vlastnost v podobě taxonomie, tak můžete zadat: <code>{MANUFACTURER} {NAZEV} {pa_barva}</code>
            </p>
        </td>
      </tr>
    <?php }
    $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
    if ( ! empty ( $extra_message_aktivace ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $extra_message_aktivace as $extra_message ) {
        if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) {
          $extra_message_text = ''; ?>
          <tr class="form-field">
            <th scope="row" valign="top"><label><?php echo $extra_message_array[ $extra_message ]; ?></label></th>
            <td> 
              <span class="description">
                Není potřeba nic zadávat, protože na úrovni eshopu je tato informace <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.
              </span>
            </td>
          </tr>
        <?php } else {
          $checked = "";
          if ( ! empty( $kategorie_extra_message_ulozeno ) && array_key_exists( $extra_message, $kategorie_extra_message_ulozeno ) ) {
            $checked = 'checked="checked"';
          } ?>
          <tr class="form-field">
            <th scope="row" valign="top"><label><?php echo $extra_message_array[ $extra_message ]; ?></label></th>
            <td> 
              <input name="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" id="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" type="checkbox" value="yes" <?php echo $checked; ?>/>
              <span class="description">
                Po zaškrtnutí budou produkty v příslušné kategorii označeny příslušnou doplňkovou informací. Na úrovni eshopu zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.
              </span>
            </td>
          </tr>
        <?php }
      }
    }
  } ?>
  <tr>
    <th scope="row" valign="top"><strong>České služby: XML feedy</strong></th>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Odebrat z XML</label></th>
    <td> 
      <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" <?php checked( $xml_vynechano_ulozeno, "yes" ); ?>/>
      <span class="description">
        Zaškrtněte pokud chcete odebrat produkty této kategorie z XML feedů.
      </span>
    </td>
  </tr>
  <?php
  if ( ! empty( $global_data['stav_produktu'] ) ) {
    if ( $global_data['stav_produktu'] == 'used' ) {
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
  <tr class="form-field">
    <th scope="row" valign="top"><label>Erotický obsah</label></th>
    <td> 
      <input name="ceske-sluzby-xml-erotika" id="ceske-sluzby-xml-erotika" type="checkbox" value="yes" <?php checked( $xml_erotika_ulozeno, "yes" ); ?>/>
      <span class="description">
        Zaškrtněte pokud chcete označit obsah webu jako erotický.
      </span>
    </td>
  </tr>
<?php // http://themehybrid.com/weblog/introduction-to-wordpress-term-meta
}

function ceske_sluzby_xml_kategorie_ulozit( $term_id, $tt_id = '', $taxonomy = '' ) {
  if ( 'product_cat' === $taxonomy ) {
    $ukladana_data_text = array(
      'ceske-sluzby-xml-heureka-kategorie',
      'ceske-sluzby-xml-heureka-productname',
      'ceske-sluzby-xml-zbozi-kategorie',
      'ceske-sluzby-xml-zbozi-productname',
      'ceske-sluzby-xml-stav-produktu'
    );
    foreach ( $ukladana_data_text as $key ) {
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        if ( $key == 'ceske-sluzby-xml-heureka-kategorie' ) {
          $value = str_replace( 'Heureka.cz | ', '', $value );
          $value = str_replace( 'Heureka.sk | ', '', $value );
        }
        $ulozeno = get_woocommerce_term_meta( $term_id, $key, true );
        if ( ! empty( $value ) ) {
          update_woocommerce_term_meta( $term_id, $key, esc_attr( $value ) );
        } elseif ( ! empty( $ulozeno ) ) {
          delete_woocommerce_term_meta( $term_id, $key ); 
        }
      }
    }
    
    $ukladana_data_checkbox = array(
      'ceske-sluzby-xml-vynechano',
      'ceske-sluzby-xml-erotika',
      'ceske-sluzby-xml-zbozi-extra-message'
    );
    foreach ( $ukladana_data_checkbox as $key ) {
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        $ulozeno = get_woocommerce_term_meta( $term_id, $key, true );
        if ( ! empty( $value ) ) {
          update_woocommerce_term_meta( $term_id, $key, $value );
        }
      } elseif ( ! empty( $ulozeno ) ) {
        delete_woocommerce_term_meta( $term_id, $key ); 
      }
    }
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
    $heureka_nazev = false;
    if ( $heureka_kategorie ) {
      $columns .= 'Heureka: <a href="#" title="' . $heureka_kategorie . '">KA</a>';
      $heureka_nazev = true;
    }
    $heureka_productname = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-heureka-productname', true );
    if ( $heureka_productname ) {
      if ( $heureka_nazev ) {
        $columns .= ' <a href="#" title="' . $heureka_productname . '">PR</a>';
      } else {
        $columns .= 'Heureka: <a href="#" title="' . $heureka_productname . '">PR</a>';
        $heureka_nazev = true;
      }
    }
    if ( $heureka_nazev ) {
      $columns .= '<br />';
    }
    $zbozi_kategorie = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-zbozi-kategorie', true );
    $zbozi_nazev = false;
    if ( $zbozi_kategorie ) {
      $columns .= 'Zboží.cz: <a href="#" title="' . $zbozi_kategorie . '">KA</a>';
      $zbozi_nazev = true;
    }
    $zbozi_productname = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-zbozi-productname', true );
    if ( $zbozi_productname ) {
      if ( $zbozi_nazev ) {
        $columns .= ' <a href="#" title="' . $zbozi_productname . '">PR</a>';
      } else {
        $columns .= 'Zboží.cz: <a href="#" title="' . $zbozi_productname . '">PR</a>';
        $zbozi_nazev = true;
      }
    }
    $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
    $kategorie_extra_message_ulozeno = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-zbozi-extra-message', true );
    if ( ! empty( $kategorie_extra_message_ulozeno ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $kategorie_extra_message_ulozeno as $key => $value ) {
        if ( ! empty ( $extra_message_aktivace ) && in_array( $key, $extra_message_aktivace ) ) {
          $kategorie_extra_message[] = $extra_message_array[ $key ];
        }
      }
      if ( ! empty( $kategorie_extra_message ) ) {
        $kategorie_extra_message_text = implode( ', ', $kategorie_extra_message );
        if ( $zbozi_nazev ) {
          $columns .= ' <a href="#" title="' . $kategorie_extra_message_text . '">EM</a>';
        } else {
          $columns .= 'Zboží.cz: <a href="#" title="' . $kategorie_extra_message_text . '">EM</a>';
        }
      }
    }
    $kategorie_vynechano = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-vynechano', true );
    if ( $kategorie_vynechano ) {
      $columns .= '<span style="margin-left: 10px; color: red;">x</span>';
    }
    $stav_produktu = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-stav-produktu', true );
    if ( ! empty( $stav_produktu ) ) {
      if ( $stav_produktu == 'used' ) {
        $stav_produktu_hodnota = 'Použité (bazar)';
      } else {
        $stav_produktu_hodnota = 'Repasované';
      }
      $columns .= '<br />' . $stav_produktu_hodnota;
    }
    $erotika = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-erotika', true );
    if ( $erotika ) {
      if ( $erotika == 'yes' ) {
        $erotika_hodnota = 'Erotický obsah';
      }
      $columns .= '<br />' . $erotika_hodnota;
    }
  }
  return $columns;
}

function ceske_sluzby_zobrazit_dodaci_dobu_filtr( $availability, $product ) {
  if ( ! $product->is_in_stock() ) {
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
    if ( ! empty ( $dostupnost ) ) {
      $availability['availability'] = $dostupnost['text'];
    }
    return $availability;
  }
  $dostupnost = ceske_sluzby_ziskat_predobjednavku( $product, false );
  if ( ! empty ( $dostupnost ) ) {
    $availability['availability'] = $dostupnost;
    return $availability;
  }
  if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
    $dostupnost = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity(), false );
    if ( ! empty ( $dostupnost ) ) {
      return $dostupnost;
    }
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
  if ( ! empty ( $dostupnost ) ) {
    $availability['availability'] = $dostupnost['text'];
    return $availability;
  }
  return $availability;
}

function ceske_sluzby_zobrazit_dodaci_dobu_akce() {
  global $product;
  if ( $product->is_type( 'variable' ) ) {
    return;
  }
  if ( ! $product->is_in_stock() ) {
    $dodaci_doba_text = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
    if ( ! empty ( $dodaci_doba_text ) ) {
      $dostupnost['value'] = 99;
      $dostupnost['text'] = $dodaci_doba_text;
      $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
    echo $format;
    return;
  }
  $format = ceske_sluzby_ziskat_predobjednavku( $product, true );
  if ( ! empty ( $format ) ) {
    echo $format;
    return;
  }
  $availability = $product->get_availability();
  if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
    $format = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity(), true );
    echo $format;
    return;
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
  if ( ! empty ( $dostupnost ) ) {
    $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    echo $format;
  }
}

function ceske_sluzby_nahradit_zobrazeny_text( $html, $availability, $product ) {
  if ( get_class( $product ) == "WC_Product_Simple" ) {
    $html = "";
  }
  elseif ( get_class( $product ) == "WC_Product_Variation" ) {
    if ( ! $product->is_in_stock() ) {
      $dodaci_doba_text = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
      if ( ! empty ( $dodaci_doba_text ) ) {
        $dostupnost['value'] = 99;
        $dostupnost['text'] = $dodaci_doba_text;
        $html = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
      }
      return $html;
    }
    $html = ceske_sluzby_ziskat_predobjednavku( $product, true );
    if ( ! empty ( $html ) ) {
      return $html;
    }
    if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
      $html = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity(), true );
      return $html;
    }
    $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
    if ( ! empty ( $dostupnost ) ) {
      $html = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
  }
  return $html;
}

function ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_filtr( $data, $this, $variation ) {
  $dostupnost = ceske_sluzby_ziskat_predobjednavku( $variation, false );
  if ( ! empty ( $dostupnost ) ) {
    return $data;
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $variation, true );
  if ( $variation->managing_stock() && (int)$variation->get_stock_quantity() > 0 ) {
    $format = ceske_sluzby_ziskat_format_dodatecneho_poctu( $dostupnost, $variation );
    $data['availability_html'] .= $format;
  }
  return $data;
}

function ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce() {
  global $product;
  if ( ! $product->is_type( 'simple' ) ) {
    return;
  }
  $format = ceske_sluzby_ziskat_predobjednavku( $product, true );
  if ( ! empty ( $format ) ) {
    return;
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, true );
  if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
    $format = ceske_sluzby_ziskat_format_dodatecneho_poctu( $dostupnost, $product );
    echo $format;
  }
}

function ceske_sluzby_load_admin_scripts() {
  $screen = get_current_screen();
  $screen_id = $screen ? $screen->id : '';
  if ( in_array( $screen_id, array( 'product', 'edit-product', 'shop_order' ) ) ) {
    wp_register_script( 'wc-admin-ceske-sluzby', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/ceske-sluzby-admin.js', array( 'jquery-ui-datepicker' ), CS_VERSION );
    wp_enqueue_script( 'wc-admin-ceske-sluzby' );
  }
}
