<?php
/**
 * Plugin Name: České služby pro WordPress
 * Plugin URI: https://www.separatista.net
 * Description: Implementace různých českých služeb do WordPressu.
 * Version: 0.6-alpha
 * Author: Pavel Hejn
 * Author URI: https://www.separatista.net
 * GitHub Plugin URI: pavelevap/ceske-sluzby 
 * License: GPL2
 */

define( 'CS_VERSION', '0.6-alpha' );

$language = get_locale();
if ( $language == "sk_SK" ) {
  define( "HEUREKA_URL", "heureka.sk" );
  define( "GLAMI_URL", "glami.sk" );
  define( "HEUREKA_KONVERZE", "https://im9.cz/sk/js/ext/2-roi-async.js" );
  define( "GOOGLE_MENA", "EUR" );
}
else {
  define( "HEUREKA_URL", "heureka.cz" );
  define( "GLAMI_URL", "glami.cz" );
  define( "HEUREKA_KONVERZE", "https://im9.cz/js/ext/1-roi-async.js" );
  define( "GOOGLE_MENA", "CZK" );
}

function ceske_sluzby_heureka_overeno_zakazniky( $order_id, $posted ) {
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  $souhlas = get_option( 'wc_ceske_sluzby_heureka_overeno-souhlas' );
  $souhlas_check = array();
  $souhlas_text = "";
  if ( ! empty( $souhlas ) ) {
    if ( $souhlas == 'souhlas_optout' && isset( $_POST['heureka_overeno_zakazniky_souhlas_optout'] ) && (int)$_POST['heureka_overeno_zakazniky_souhlas_optout'] == 1 ) {
      $souhlas_check = array( $souhlas => current_time( 'mysql' ) );
      $souhlas_text = 'Objednávka byla úspěšně odeslána do služby Ověřeno zákazníky (Heureka) a zákazník neodmítl navržený souhlas se zpracováním dat.';
    }
    if ( $souhlas == 'nesouhlas_optout' && ! isset( $_POST['heureka_overeno_zakazniky_nesouhlas_optout'] ) ) {
      $souhlas_check = array( $souhlas => current_time( 'mysql' ) );
      $souhlas_text = 'Objednávka byla úspěšně odeslána do služby Ověřeno zákazníky (Heureka) a zákazník nepotvrdil nesouhlas se zpracováním dat.';
    }
  } else {
    $souhlas_check = array( 'neaktivni' => current_time( 'mysql' ) );
    $souhlas_text = 'Objednávka byla úspěšně odeslána do služby Ověřeno zákazníky (Heureka).';
  }
  if ( ! empty( $api ) && ! empty( $souhlas_check ) ) {
    $order = wc_get_order( $order_id );
    
    // https://github.com/heureka/heureka-overeno-php-api
    require_once( dirname( __FILE__ ) . '/src/heureka/HeurekaOvereno.php' );
    
    $language = get_locale();
    try {
      if ( $language == "sk_SK" ) {
        $overeno = new HeurekaOvereno( $api, HeurekaOvereno::LANGUAGE_SK );
      }
      else {
        $overeno = new HeurekaOvereno( $api );
      }
      $overeno->setEmail( $posted['billing_email'] );

      $items = $order->get_items();
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        foreach ( $items as $item_data ) {
          $overeno->addProduct( $item_data['name'] );
        }
      } else {
        foreach ( $items as $item_id => $item_data ) {
          $aktivace_xml = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
          $product = $item_data->get_product();
          if ( $aktivace_xml == "yes" ) {
            $overeno->addProductItemId( $product->get_id() );
          } else {
            $overeno->addProduct( $product->get_name() );
          }
        }
      }

      $overeno->addOrderId( $order_id );
      $overeno->send();
      update_post_meta( $order_id, 'ceske_sluzby_heureka_overeno_zakazniky_souhlas', $souhlas_check );
      $order->add_order_note( $souhlas_text );
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
    $order = wc_get_order( $order_id );
    $items = $order->get_items(); ?>

<script type="text/javascript">
var _hrq = _hrq || [];
    _hrq.push(['setKey', '<?php echo $api; ?>']);
    _hrq.push(['setOrderId', '<?php echo $order_id; ?>']);
    <?php if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
      foreach ( $items as $item ) {
        $cena = wc_format_decimal( $order->get_item_subtotal( $item ) );
        echo "_hrq.push(['addProduct', '" . $item['name'] . "', '" . $cena . "', '" . $item['qty'] . "']);";
      } 
    } else {
      foreach ( $items as $item_id => $item_data ) {
        $product = $item_data->get_product();
        $cena = wc_format_decimal( $item_data->get_total() / $item_data->get_quantity() );
        echo "_hrq.push(['addProduct', '" . $product->get_name() . "', '" . $cena . "', '" . $item_data->get_quantity() . "']);";
      }  
    } ?>
    _hrq.push(['trackOrder']);

(function() {
    var ho = document.createElement('script'); ho.type = 'text/javascript'; ho.async = true;
    ho.src = '<?php echo HEUREKA_KONVERZE; ?>';
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
    $order = wc_get_order( $order_id );
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
    $order = wc_get_order( $order_id );
    $items = $order->get_items(); ?>

<script type="text/javascript">
var _srt = _srt || [];
    _srt.push(['_setShop', '<?php echo $klic; ?>']);
    _srt.push(['_setTransId', '<?php echo $order_id; ?>']);
    <?php if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
      foreach ( $items as $item ) {
        $cena = wc_format_decimal( $order->get_item_subtotal( $item ) );
        echo "_srt.push(['_addProduct', '" . $item['name'] . "', '" . $cena . "', '" . $item['qty'] . "']);";
      }
    } else {
      foreach ( $items as $item_id => $item_data ) {
        $product = $item_data->get_product();
        $cena = wc_format_decimal( $item_data->get_total() / $item_data->get_quantity() );
        echo "_srt.push(['_addProduct', '" . $product->get_name() . "', '" . $cena . "', '" . $item_data->get_quantity() . "']);";
      }
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

function ceske_sluzby_zbozi_mereni_konverzi( $order_id ) {
  $id_obchodu = get_option( 'wc_ceske_sluzby_zbozi_konverze_id-obchodu' );
  if ( ! empty( $id_obchodu ) ) {
    $order = wc_get_order( $order_id );
    $hodnota_objednavky = number_format( (float)( $order->get_total() ), 2, '.', '' ); ?>

<script>
(function(w,d,s,u,n,k,c,t){w.ZboziConversionObject=n;w[n]=w[n]||function(){
(w[n].q=w[n].q||[]).push(arguments)};w[n].key=k;c=d.createElement(s);
t=d.getElementsByTagName(s)[0];c.async=1;c.src=u;t.parentNode.insertBefore(c,t)
})(window,document,"script","https://www.zbozi.cz/conversion/js/conv.js","zbozi","<?php echo $id_obchodu; ?>");
zbozi("setOrder",{
"orderId": "<?php echo $order_id; ?>",
"totalPrice": "<?php echo $hodnota_objednavky; ?>"
});
zbozi("send");
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
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-product-tab.php';
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-varianty.php';
      new WC_Product_Tab_Ceske_Sluzby_Admin();
      $xml_feed = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
      if ( $xml_feed == "yes" ) {
        add_action( 'product_cat_add_form_fields', 'ceske_sluzby_xml_kategorie_pridat_pole', 99 );
        add_action( 'product_cat_edit_form_fields', 'ceske_sluzby_xml_kategorie_upravit_pole', 99 );
        add_action( 'created_term', 'ceske_sluzby_xml_kategorie_ulozit', 20, 3 );
        add_action( 'edit_term', 'ceske_sluzby_xml_kategorie_ulozit', 20, 3 );
        add_filter( 'manage_edit-product_cat_columns', 'ceske_sluzby_xml_kategorie_pridat_sloupec' );
        add_filter( 'manage_product_cat_custom_column', 'ceske_sluzby_xml_kategorie_sloupec', 10, 3 );
      }
    }

    add_action( 'woocommerce_shipping_init', 'ceske_sluzby_doprava_ulozenka_init' );
    add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_ulozenka' );

    add_action( 'woocommerce_shipping_init', 'ceske_sluzby_doprava_dpd_parcelshop_init' );
    add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_dpd_parcelshop' );

    add_action( 'woocommerce_checkout_order_processed', 'ceske_sluzby_heureka_overeno_zakazniky', 10, 2 );
    add_action( 'woocommerce_review_order_before_submit', 'ceske_sluzby_heureka_overeno_zakazniky_souhlas' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_heureka_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_zbozi_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_sklik_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_srovname_mereni_konverzi' );
    add_action( 'wp_footer', 'ceske_sluzby_sklik_retargeting' );
    add_filter( 'wc_order_is_editable', 'ceske_sluzby_moznost_menit_dobirku', 10, 2 );
    add_filter( 'woocommerce_package_rates', 'ceske_sluzby_omezit_dopravu_pokud_dostupna_zdarma', 10, 2 );

    add_action( 'woocommerce_review_order_after_shipping', 'ceske_sluzby_ulozenka_zobrazit_pobocky' );
    add_action( 'woocommerce_new_order_item', 'ceske_sluzby_ulozenka_ulozeni_pobocky', 10, 2 );
    add_action( 'woocommerce_checkout_process', 'ceske_sluzby_ulozenka_overit_pobocku' );
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku' );

    add_action( 'woocommerce_review_order_after_shipping', 'ceske_sluzby_dpd_parcelshop_zobrazit_pobocky' );
    add_action( 'woocommerce_new_order_item', 'ceske_sluzby_dpd_parcelshop_ulozeni_pobocky', 10, 2 );
    add_action( 'woocommerce_checkout_process', 'ceske_sluzby_dpd_parcelshop_overit_pobocku' );
    add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_dpd_parcelshop_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_dpd_parcelshop_objednavka_zobrazit_pobocku' );
    add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_dpd_parcelshop_objednavka_zobrazit_pobocku' );

    $aktivace_zasilkovna = get_option( 'wc_ceske_sluzby_doprava_zasilkovna' );
    if ( $aktivace_zasilkovna == "yes" ) {
      add_action( 'woocommerce_shipping_init', 'ceske_sluzby_doprava_zasilkovna_init' );
      add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_zasilkovna' );
      $zasilkovna_settings = get_option( 'woocommerce_ceske_sluzby_zasilkovna_settings' );
      if ( isset( $zasilkovna_settings['zasilkovna_api-klic'] ) && ! empty( $zasilkovna_settings['zasilkovna_api-klic'] ) ) {
        add_action( 'wp_footer', 'ceske_sluzby_zasilkovna_scripts_checkout', 100 );
        add_action( 'woocommerce_review_order_after_shipping', 'ceske_sluzby_zasilkovna_zobrazit_pobocky' );
        add_action( 'woocommerce_new_order_item', 'ceske_sluzby_zasilkovna_ulozeni_pobocky', 10, 2 );
        add_action( 'woocommerce_checkout_process', 'ceske_sluzby_zasilkovna_overit_pobocku' );
        add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_zasilkovna_objednavka_zobrazit_pobocku' );
        add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_zasilkovna_objednavka_zobrazit_pobocku' );
        add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_zasilkovna_objednavka_zobrazit_pobocku' );
      }
    }
    add_filter( 'woocommerce_pay4pay_cod_amount', 'ceske_sluzby_ulozenka_dobirka_pay4pay' );
    add_filter( 'woocommerce_pay4pay_cod_amount', 'ceske_sluzby_dpd_parcelshop_dobirka_pay4pay' );

    $aktivace_recenzi = get_option( 'wc_ceske_sluzby_heureka_recenze_obchodu-aktivace' );
    if ( $aktivace_recenzi == "yes" ) {
      add_shortcode( 'heureka-recenze-obchodu', 'ceske_sluzby_heureka_recenze_obchodu' );
    }

    $sledovani_zasilek = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_sledovani-zasilek' );
    if ( $sledovani_zasilek == "yes" ) {
      add_filter( 'woocommerce_email_classes', 'ceske_sluzby_sledovani_zasilek_email' );
      if ( version_compare( WC_VERSION, '3.2', '>=' ) ) {
        add_filter( 'woocommerce_email_actions', 'ceske_sluzby_sledovani_zasilek_email_akce' );
      }
    }

    $aktivace_eet = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
    if ( $aktivace_eet == "yes" ) {
      add_filter( 'upload_mimes', 'ceske_sluzby_povolit_nahravani_certifikatu' );
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-eet.php';
      add_action( 'wpo_wcpdf_after_order_details', 'ceske_sluzby_zobrazit_eet_faktura_externi', 10, 2 );
      add_action( 'woocommerce_order_status_completed', 'ceske_sluzby_automaticky_ziskat_uctenku' );
      add_action( 'woocommerce_payment_complete', 'ceske_sluzby_automaticky_ziskat_uctenku' );
      add_action( 'woocommerce_email_order_meta', 'ceske_sluzby_zobrazit_eet_email', 10, 4 );
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        add_filter( 'woocommerce_order_tax_totals', 'ceske_sluzby_doplnit_danovou_sazbu' );
      } else {
        add_filter( 'woocommerce_order_get_tax_totals', 'ceske_sluzby_doplnit_danovou_sazbu' );
      }
    }

    $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
    if ( $aktivace_dodaci_doby == "yes" ) {
      $dodaci_doba = get_option( 'wc_ceske_sluzby_dodaci_doba_zobrazovani' );
      if ( ! empty( $dodaci_doba ) ) {
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
            if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
              add_filter( 'woocommerce_stock_html', 'ceske_sluzby_nahradit_zobrazeny_text_deprecated', 10, 3 );
            } else {
              add_filter( 'woocommerce_get_stock_html', 'ceske_sluzby_nahradit_zobrazeny_text', 10, 2 );
            }
            add_action( 'woocommerce_before_add_to_cart_form', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce' );
            add_filter( 'woocommerce_available_variation', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_filtr', 10, 3 );
          }
          if ( $zobrazeni == 'after_shop_loop_item' ) {
            add_action( 'woocommerce_after_shop_loop_item', 'ceske_sluzby_zobrazit_dodaci_dobu_akce', 8 );
            add_action( 'woocommerce_after_shop_loop_item', 'ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_akce', 9 );
          }
        }
      }
      add_filter( 'woocommerce_admin_stock_html', 'ceske_sluzby_zobrazeni_dodaci_doby_administrace', 10, 2 );
      add_action( 'woocommerce_variation_header', 'ceske_sluzby_zobrazeni_dodaci_doby_varianty' );
      add_action( 'admin_head', 'ceske_sluzby_zobrazeni_dodaci_doby_administrace_css' );
    }

    add_action( 'admin_enqueue_scripts', 'ceske_sluzby_load_admin_scripts' );
    add_action( 'wp_footer', 'ceske_sluzby_heureka_certifikat_spokojenosti' ); // Pouze pro eshop nebo na celém webu?
    add_action( 'woocommerce_cart_calculate_fees', 'ceske_sluzby_zaokrouhlovani_poplatek' );
    add_action( 'woocommerce_after_calculate_totals', 'ceske_sluzby_spustit_zaokrouhlovani' );
    add_action( 'wp_footer', 'ceske_sluzby_aktualizovat_checkout_javascript' );
    add_filter( 'woocommerce_available_payment_gateways', 'ceske_sluzby_dostupne_platebni_metody' );

    $nepresne_zaokrouhleni = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_nepresne-zaokrouhleni' );
    if ( $nepresne_zaokrouhleni == "yes" ) {
      add_filter( 'woocommerce_calc_tax', 'ceske_sluzby_zmena_kalkulace_dani' );
      add_filter( 'woocommerce_tax_round', 'ceske_sluzby_zmena_zaokrouhlovani_dani' );
    }
    $zmena_platby_predem = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_zmena-platby-predem' );
    if ( $zmena_platby_predem == "yes" ) {
      add_filter( 'woocommerce_bacs_process_payment_order_status','ceske_sluzby_zmena_stavu_objednavky_platba_predem', 10, 2 );
      add_filter( 'woocommerce_email_actions', 'ceske_sluzby_moznost_odesilat_emaily_zmena_stavu_platba_predem' );
      add_action( 'woocommerce_email', 'ceske_sluzby_zmena_emailovych_notifikaci_platba_predem' );
      add_action( 'init', 'ceske_sluzby_odebrat_bankovni_ucet_po_dokonceni_objednavky', 100 );
      add_action( 'admin_head', 'ceske_sluzby_stylovani_tlacitek_objednavky_administrace_css' );
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
  if ( is_ajax() ) {
    // Do budoucna možná použít spíše woocommerce_checkout_update_order_review
    $ulozenka_branches = '';
    if ( isset( $_POST['post_data'] ) ) {
      parse_str( $_POST['post_data'], $post_data );
      if ( isset( $post_data['ulozenka_branches'] ) ) {
        $ulozenka_branches = $post_data['ulozenka_branches'];
      }
    }
    $available_shipping = WC()->shipping->load_shipping_methods();
    $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
    $settings = array();
    if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
      $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
      if ( $settings['enabled'] == "yes" && ! empty( $settings['ulozenka_id-obchodu'] ) ) {
        $json_class = new Ceske_Sluzby_Json_Loader();
        // http://docs.ulozenkav3.apiary.io/#pepravnsluby
        $zeme = WC()->customer->get_shipping_country();
        if ( $zeme == "CZ" ) { $zeme_code = "CZE"; }
        if ( $zeme == "SK" ) { $zeme_code = "SVK"; }
        $parametry = array( 'provider' => 1, 'country' => $zeme_code ); ?>
        <tr class="ulozenka">
          <td>
            <img src="https://www.ulozenka.cz/logo/ulozenka.png" width="140" border="0">
          </td>
          <td>
            <font size="2">Uloženka - výběr pobočky:</font><br>
            <div id="ulozenka-branch-select-options">
              <select name="ulozenka_branches">
                <option>Vyberte pobočku</option>
                <?php $json = $json_class->load( $parametry );
                if ( isset( $json->data->destination ) && ! empty( $json->data->destination ) ) {
                  $pobocky = $json_class->sortName( $json->data->destination );
                  foreach ( $pobocky as $pobocka ) {
                    echo '<option value="' . $pobocka . '"' . selected( $pobocka, $ulozenka_branches ) . '>' . $pobocka . '</option>';
                  }
                } ?>
              </select>
            </div>
          </td>
        </tr>
      <?php }
    }
  }
}

function ceske_sluzby_ulozenka_ulozeni_pobocky( $item_id, $item ) {
  if ( isset( $_POST["ulozenka_branches"] ) ) {
    if ( $_POST["ulozenka_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $item_type = $item['order_item_type'];
      } else {
        $item_type = $item->get_type();
      }
      if ( $item_type == 'shipping' ) {
        wc_add_order_item_meta( $item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', esc_attr( $_POST['ulozenka_branches'] ), true );
      }
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
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $pobocka = $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true );
      } else {
        $pobocka = wc_get_order_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true );
      }
      if ( ! empty( $pobocka ) ) {
        echo "<p><strong>Uloženka:</strong> " . $pobocka . "</p>";
      }
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
    $dpd_parcelshop_branches = '';
    if ( isset( $_POST['post_data'] ) ) {
      parse_str( $_POST['post_data'], $post_data );
      if ( isset( $post_data['dpd_parcelshop_branches'] ) ) {
        $dpd_parcelshop_branches = $post_data['dpd_parcelshop_branches'];
      }
    }
    $available_shipping = WC()->shipping->load_shipping_methods();
    $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
    $settings = array();
    if ( $chosen_shipping_method[0] == "ceske_sluzby_dpd_parcelshop" ) {
      $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
      if ( $settings['enabled'] == "yes" ) {
        $json_class = new Ceske_Sluzby_Json_Loader();
        $zeme = WC()->customer->get_shipping_country();
        if ( $zeme == "CZ" ) { $zeme_code = "CZE"; }
        if ( $zeme == "SK" ) { $zeme_code = "SVK"; }
        $parametry = array( 'provider' => 5, 'country' => $zeme_code ); ?>
        <tr class="dpd-parcelshop">
          <td>
            <img src="http://www.dpdparcelshop.cz/images/DPD-logo.png" width="140" border="0">
          </td>
          <td>
            <font size="2">DPD ParcelShop - výběr pobočky:</font><br>
            <div id="dpd-parcelshop-branch-select-options">
              <select name="dpd_parcelshop_branches">
                <option>Vyberte pobočku</option>
                <?php $json = $json_class->load( $parametry );
                if ( isset( $json->data->destination ) && ! empty( $json->data->destination ) ) {
                  $pobocky = $json_class->sortName( $json->data->destination );
                  foreach ( $pobocky as $pobocka ) {
                    echo '<option value="' . $pobocka . '"' . selected( $pobocka, $dpd_parcelshop_branches ) . '>' . $pobocka . '</option>';
                  }
                } ?>
              </select>
            </div>
          </td>
        </tr>
      <?php }
    }
  }
}

function ceske_sluzby_dpd_parcelshop_ulozeni_pobocky( $item_id, $item ) {
  if ( isset( $_POST["dpd_parcelshop_branches"] ) ) {
    if ( $_POST["dpd_parcelshop_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_dpd_parcelshop" ) {
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $item_type = $item['order_item_type'];
      } else {
        $item_type = $item->get_type();
      }
      if ( $item_type == 'shipping' ) {
        wc_add_order_item_meta( $item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', esc_attr( $_POST['dpd_parcelshop_branches'] ), true );
      }
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
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $pobocka = $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', true );
      } else {
        $pobocka = wc_get_order_item_meta( $shipping_item_id, 'ceske_sluzby_dpd_parcelshop_pobocka_nazev', true );
      }
      if ( ! empty( $pobocka ) ) {
        echo "<p><strong>DPD ParcelShop:</strong> " . $pobocka . "</p>";
      }
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

function ceske_sluzby_doprava_zasilkovna_init() {
  if ( ! class_exists( 'WC_Shipping_Ceske_Sluzby_Zasilkovna' ) ) {
    require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-zasilkovna.php';
  }
}
 
function ceske_sluzby_doprava_zasilkovna( $methods ) {
  $methods['ceske_sluzby_zasilkovna'] = 'WC_Shipping_Ceske_Sluzby_Zasilkovna';
  return $methods;
}

function ceske_sluzby_zasilkovna_zobrazit_pobocky() {
  if ( is_ajax() ) {
    $zasilkovna_branches = '';
    if ( isset( $_POST['post_data'] ) ) {
      parse_str( $_POST['post_data'], $post_data );
      if ( isset( $post_data['packeta-point-id'] ) ) {
        $zasilkovna_branches = $post_data['packeta-point-id'];
      }
    }
    $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
    if ( strpos( $chosen_shipping_method[0], "ceske_sluzby_zasilkovna" ) !== false ) { ?>
      <tr class="zasilkovna">
        <td>
          <img src="https://files.packeta.com/web/images/page/Zasilkovna_logo_WEB_tb_nove.png" width="200" border="0">
        </td>
        <td>
          <input type="button" onclick="Packeta.Widget.pick(packetaApiKey, showSelectedPickupPoint)" value="Zvolit pobočku">
          <div>Pobočka:
            <input type="hidden" id="packeta-point-id" name="packeta-point-id" value="<?php echo $zasilkovna_branches; ?>">
            <span id="packeta-point-info" style="font-weight:bold;"><?php if ( $zasilkovna_branches ) { echo $zasilkovna_branches; } else { echo "Zatím nevybráno"; } ?></span>
          </div>
        </td>
      </tr>
    <?php } else { ?>
      <input type="hidden" id="packeta-point-id" name="packeta-point-id" value="<?php echo $zasilkovna_branches; ?>">
    <?php }
  }
}

function ceske_sluzby_zasilkovna_ulozeni_pobocky( $item_id, $item ) {
  if ( isset( $_POST["packeta-point-id"] ) ) {
    if ( ! empty( $_POST["packeta-point-id"] ) && strpos( $_POST["shipping_method"][0], "ceske_sluzby_zasilkovna" ) !== false ) {
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $item_type = $item['order_item_type'];
      } else {
        $item_type = $item->get_type();
      }
      if ( $item_type == 'shipping' ) {
        wc_add_order_item_meta( $item_id, 'ceske_sluzby_zasilkovna_pobocka_nazev', esc_attr( $_POST['packeta-point-id'] ), true );
      }
    }
  }
}

function ceske_sluzby_zasilkovna_overit_pobocku() {
  if ( isset( $_POST["packeta-point-id"] ) ) {
    if ( empty( $_POST["packeta-point-id"] ) && strpos( $_POST["shipping_method"][0], "ceske_sluzby_zasilkovna" ) !== false ) {
      wc_add_notice( 'Pokud chcete doručit zboží prostřednictvím Zásilkovny, zvolte prosím pobočku.', 'error' );
    }
  }
}

function ceske_sluzby_zasilkovna_objednavka_zobrazit_pobocku( $order ) {
  if ( $order->has_shipping_method( 'ceske_sluzby_zasilkovna' ) ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
        $pobocka = $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_zasilkovna_pobocka_nazev', true );
      } else {
        $pobocka = wc_get_order_item_meta( $shipping_item_id, 'ceske_sluzby_zasilkovna_pobocka_nazev', true );
      }
      if ( ! empty( $pobocka ) ) {
        echo "<p><strong>Zásilkovna:</strong> " . $pobocka . "</p>";
      }
    }
  }
}

function ceske_sluzby_moznost_menit_dobirku( $zmena, $objednavka ) {
  // https://www.separatista.net/forum/tema/woocommerce-a-dobirka
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
    add_feed( 'heureka', 'xml_feed_zobrazeni' );
    add_feed( 'glami', 'xml_feed_zobrazeni' );
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

    $glami_xml = get_option( 'wc_ceske_sluzby_xml_feed_glami-aktivace' );
    if ( $glami_xml == "yes" ) {
      if ( ! wp_next_scheduled( 'ceske_sluzby_glami_aktualizace_xml' ) ) {
        wp_schedule_event( current_time( 'timestamp', 1 ) + ( 3 * MINUTE_IN_SECONDS ), 'daily', 'ceske_sluzby_glami_aktualizace_xml' );
      }
    } else {
      if ( wp_next_scheduled( 'ceske_sluzby_glami_aktualizace_xml' ) ) {
        $timestamp = wp_next_scheduled( 'ceske_sluzby_glami_aktualizace_xml' );
        wp_unschedule_event( $timestamp, 'ceske_sluzby_glami_aktualizace_xml' ); 
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
    if ( wp_next_scheduled( 'ceske_sluzby_glami_aktualizace_xml' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_glami_aktualizace_xml' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_glami_aktualizace_xml' );
    }
  }
}

add_action( 'ceske_sluzby_heureka_aktualizace_xml', 'ceske_sluzby_heureka_xml_feed_aktualizace' );
add_action( 'ceske_sluzby_heureka_aktualizace_xml_batch', 'ceske_sluzby_heureka_xml_feed_aktualizace' );
function ceske_sluzby_heureka_xml_feed_aktualizace() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
  xml_feed_aktualizace_nastaveni( 'heureka' );
}

add_action( 'ceske_sluzby_glami_aktualizace_xml', 'ceske_sluzby_glami_xml_feed_aktualizace' );
add_action( 'ceske_sluzby_glami_aktualizace_xml_batch', 'ceske_sluzby_glami_xml_feed_aktualizace' );
function ceske_sluzby_glami_xml_feed_aktualizace() {
  require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-xml.php';
  xml_feed_aktualizace_nastaveni( 'glami' );
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

// https://docs.woothemes.com/document/hide-other-shipping-methods-when-free-shipping-is-available/
function ceske_sluzby_omezit_dopravu_pokud_dostupna_zdarma( $rates, $package ) {
  $omezit_dopravu = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_doprava-pouze-zdarma' );
  if ( $omezit_dopravu == "yes" ) {
    $rates_omezeno = array();
    if ( version_compare( WC_VERSION, '2.6', '<' ) ) {
      if ( isset( $rates['free_shipping'] ) ) {
        $free_shipping = $rates['free_shipping'];
        if ( isset( $rates['local_pickup'] ) ) {
          $local_pickup = $rates['local_pickup'];
        }
        $rates_omezeno['free_shipping'] = $free_shipping;
        if ( isset( $local_pickup ) ) {
          $rates_omezeno['local_pickup'] = $local_pickup;
        }
      }
    } else {
      if ( isset( $rates['legacy_free_shipping'] ) ) {
        $free_shipping = $rates['legacy_free_shipping'];
        if ( isset( $rates['legacy_local_pickup'] ) ) {
          $local_pickup = $rates['legacy_local_pickup'];
        }
        $rates_omezeno['legacy_free_shipping'] = $free_shipping;
        if ( isset( $local_pickup ) ) {
          $rates_omezeno['legacy_local_pickup'] = $local_pickup;
        }
      }
      $free = $pickup = array();
      foreach ( $rates as $rate_id => $rate ) {
        if ( 'free_shipping' === $rate->method_id ) {
          $rates_omezeno[ $rate_id ] = $rate;
          $free[] = $rate_id;
        }
        if ( 'local_pickup' === $rate->method_id ) {
          $rates_omezeno[ $rate_id ] = $rate;
          $pickup[] = $rate_id;
        }
      }
      if ( empty( $free ) && ! empty( $pickup ) ) {
        foreach ( $pickup as $pickup_id ) {
          unset( $rates_omezeno[ $pickup_id ] );
        }
      }
    }
  }
  if ( ! empty( $rates_omezeno ) ) {
    return $rates_omezeno;
  } else {
    return $rates;
  }
}

function ceske_sluzby_heureka_recenze_obchodu( $atts ) {
  $process = true;
  $output = '<div class="recenze-zakazniku">';
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
    if ( false === ( $source_xml = get_transient( 'ceske_sluzby_heureka_recenze_zakazniku' ) ) ) {
      $url = "https://www." . HEUREKA_URL . "/direct/dotaznik/export-review.php?key=" . $api;
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
          if ( ( ! empty( $limit ) && $i < $limit ) || empty( $limit ) ) {
            if ( ! empty( $recenze->summary ) ) {
              $i = $i + 1;
              $output .= '<ul>';
              $output .= '<li>';
              $output .= '<strong>' . $recenze->summary . '</strong><br />';
              if ( ! empty( $recenze->total_rating ) ) {
                $output .= 'Hodnocení: ' . $recenze->total_rating . '/5 | ';
              }
              $output .= 'Datum: před ' . human_time_diff( $recenze->unix_timestamp );
              if ( ! empty( $recenze->name ) ) {
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

function ceske_sluzby_heureka_overeno_zakazniky_souhlas() {
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  $souhlas = get_option( 'wc_ceske_sluzby_heureka_overeno-souhlas' );
  if ( ! empty( $api ) ) {
    if ( $souhlas == 'nesouhlas_optout' ) {
      woocommerce_form_field( 'heureka_overeno_zakazniky_nesouhlas_optout',
        array(
          'type' => 'checkbox',
          'label' => 'Nesouhlasím se zasláním dotazníku spokojenosti v rámci programu Ověřeno zákazníky (Heureka), který pomáhá zlepšovat naše služby.',
        )
      );
    }
    if ( $souhlas == 'souhlas_optout' ) {
      woocommerce_form_field( 'heureka_overeno_zakazniky_souhlas_optout',
        array(
          'type' => 'checkbox',
          'label' => 'Souhlasím se zasláním dotazníku spokojenosti v rámci programu Ověřeno zákazníky (Heureka), který pomáhá zlepšovat naše služby.',
        ), 1 
      );
    }
  }
}

function ceske_sluzby_xml_kategorie_pridat_pole() {
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
  $xml_feed_zbozi = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
  $xml_feed_glami = get_option( 'wc_ceske_sluzby_xml_feed_glami-aktivace' );
  if ( $xml_feed_heureka == "yes" ) { ?>
    <div style="font-size: 14px; font-weight: bold;">České služby: Heureka</div>
    <div class="form-field">
      <label for="ceske-sluzby-xml-heureka-kategorie">Kategorie</label>
      <input name="ceske-sluzby-xml-heureka-kategorie" id="ceske-sluzby-xml-heureka-kategorie" type="text" value="" placeholder="CATEGORYTEXT" size="70"/>
      <p>
        Zatím je nutné doplnit příslušnou kategorii z Heureky ručně (aktuální přehled naleznete <a href="https://www.<?php echo HEUREKA_URL; ?>/direct/xml-export/shops/heureka-sekce.xml">zde</a>).<br />
        Příklad: <strong>Elektronika | Počítače a kancelář | Software | Antiviry</strong><br />
        Poznámka: Z <code>CATEGORY_FULLNAME</code> je třeba vynechat část <code><?php echo ucfirst( HEUREKA_URL ); ?> | </code>.
      </p>
    </div>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <div class="form-field">
        <label for="ceske-sluzby-xml-heureka-productname">Název produktů</label>
        <input name="ceske-sluzby-xml-heureka-productname" id="ceske-sluzby-xml-heureka-productname" type="text" value="" placeholder="PRODUCTNAME" size="70"/>
        <p>
          Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Heureky (aktuální přehled naleznete <a href="https://sluzby.<?php echo HEUREKA_URL; ?>/napoveda/povinne-nazvy/" target="_blank">zde</a>).<br />
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
        Zatím je nutné doplnit příslušnou kategorii ze Zbozi.cz ručně (aktuální přehled naleznete <a href="https://www.zbozi.cz/static/categories.csv">zde</a>).<br />
        Příklad: <strong>Počítače | Software | Grafický a video software</strong><br />
      </p>
    </div>
    <?php if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{KATEGORIE}' ) !== false ) { ?>
      <div class="form-field">
        <label for="ceske-sluzby-xml-zbozi-productname">Název produktů</label>
        <input name="ceske-sluzby-xml-zbozi-productname" id="ceske-sluzby-xml-zbozi-productname" type="text" value="" placeholder="PRODUCTNAME" size="70" />
        <p>
          Pomocí placeholderů můžete doplnit obecný název pro všechny produkty z příslušné kategorie Zboží.cz (aktuální přehled naleznete <a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/pravidla-pojmenovani-nabidek/" target="_blank">zde</a>).<br />
          Příklad pro konrétní kategorii: <strong>Výrobce | Druh | Barva</strong><br />
          Pokud používáte nastavení výrobce, druh máte jako název produktu a barvu zase uloženou jako vlastnost v podobě taxonomie, tak můžete zadat: <code>{MANUFACTURER} {NAZEV} {pa_barva}</code>
        </p>
      </div>
    <?php }
    $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
    if ( ! empty( $extra_message_aktivace ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $extra_message_aktivace as $extra_message ) {
        if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) { ?>
          <div class="form-field">
            <label for="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]"><?php echo $extra_message_array[ $extra_message ]; ?></label>
            <span>
              Není potřeba nic zadávat, protože na úrovni eshopu je tato informace <a href="<?php echo admin_url(); ?>admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.
            </span>
          </div>
        <?php } else { ?>
          <div class="form-field">
            <label for="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]"><?php echo $extra_message_array[ $extra_message ]; ?></label>
            <input name="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" id="ceske-sluzby-xml-zbozi-extra-message[<?php echo $extra_message; ?>]" type="checkbox" value="yes" />
            <span>
              Po zaškrtnutí budou produkty v příslušné kategorii označeny příslušnou doplňkovou informací. Na úrovni eshopu zatím není nic <a href="<?php echo admin_url(); ?>admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.
            </span>
          </div>
        <?php } ?>
      <?php }
    }
  }
  if ( $xml_feed_glami == "yes" ) { ?>
    <div style="font-size: 14px; font-weight: bold;">České služby: Glami</div>
    <div class="form-field">
      <label for="ceske-sluzby-xml-glami-kategorie">Kategorie</label>
      <input name="ceske-sluzby-xml-glami-kategorie" id="ceske-sluzby-xml-glami-kategorie" type="text" value="" placeholder="CATEGORYTEXT" size="70"/>
      <p>
        Zatím je nutné doplnit příslušnou kategorii z Glami ručně (aktuální přehled naleznete <a href="https://www.<?php echo GLAMI_URL; ?>/category-xml/">zde</a>).<br />
        Příklad: <strong>Dámské oblečení a obuv | Dámské boty | Dámské outdoorové boty</strong><br />
        Poznámka: Z <code>CATEGORY_FULLNAME</code> je třeba vynechat část <code><?php echo ucfirst( GLAMI_URL ); ?> | </code>.
      </p>
    </div>
  <?php } ?>
  <div style="font-size: 14px; font-weight: bold;">České služby: XML feedy</div>
  <div class="form-field">
    <label for="ceske-sluzby-xml-vynechano">Odebrat z XML</label>
    <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" />
    <strong><span style="padding-right: 10px;">Vše</span></strong>
      <?php $feeds = ceske_sluzby_prehled_xml_feedu();
      foreach ( $feeds as $feed_id => $feed_name ) { ?>
        <input name="ceske-sluzby-xml-feed-vynechano[<?php echo $feed_id; ?>]" id="ceske-sluzby-xml-feed-vynechano[<?php echo $feed_id; ?>]" type="checkbox" value="yes" <?php checked( isset( $xml_feed_vynechano_ulozeno[$feed_id] ) ? $xml_feed_vynechano_ulozeno[$feed_id] : '', "yes" ); ?>/>
        <span style="padding-right: 10px;"><?php echo $feed_name; ?></span>
      <?php } ?>
    <p>
      Zaškrtněte pokud chcete odebrat produkty této kategorie z XML feedů.
    </p>
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
  $glami_kategorie = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-glami-kategorie', true );
  $kategorie_extra_message_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-zbozi-extra-message', true );
  $xml_vynechano_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-vynechano', true );
  $xml_feed_vynechano_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-feed-vynechano', true );
  $xml_erotika_ulozeno = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-erotika', true );
  $xml_stav_produktu = get_woocommerce_term_meta( $term->term_id, 'ceske-sluzby-xml-stav-produktu', true );
  $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
  $xml_feed_zbozi = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
  $xml_feed_glami = get_option( 'wc_ceske_sluzby_xml_feed_glami-aktivace' );
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
    if ( ! empty( $extra_message_aktivace ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $extra_message_aktivace as $extra_message ) {
        if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) {
          $extra_message_text = ''; ?>
          <tr class="form-field">
            <th scope="row" valign="top"><label><?php echo $extra_message_array[ $extra_message ]; ?></label></th>
            <td> 
              <span class="description">
                Není potřeba nic zadávat, protože na úrovni eshopu je tato informace <a href="<?php echo admin_url(); ?>admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.
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
                Po zaškrtnutí budou produkty v příslušné kategorii označeny příslušnou doplňkovou informací. Na úrovni eshopu zatím není nic <a href="<?php echo admin_url(); ?>admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.
              </span>
            </td>
          </tr>
        <?php }
      }
    }
  }
  if ( $xml_feed_glami == "yes" ) { ?>
    <tr>
      <th scope="row" valign="top"><strong>České služby: Glami</strong></th>
    </tr>
    <tr class="form-field">
      <th scope="row" valign="top"><label>Kategorie</label></th>
      <td> 
        <input name="ceske-sluzby-xml-glami-kategorie" id="ceske-sluzby-xml-glami-kategorie" type="text" value="<?php echo esc_attr( $glami_kategorie ); ?>" placeholder="CATEGORYTEXT" />
        <p class="description">
          Zatím je nutné doplnit příslušnou kategorii z Glami ručně (aktuální přehled naleznete <a href="http://www.<?php echo GLAMI_URL; ?>/category-xml/">zde</a>).<br />
          Příklad: <strong>Dámské oblečení a obuv | Dámské boty | Dámské outdoorové boty</strong><br />
          Poznámka: Z <code>CATEGORY_FULLNAME</code> je třeba vynechat část <code><?php echo ucfirst( GLAMI_URL ); ?> | </code>.
        </p>
      </td>
    </tr>
  <?php } ?>
  <tr>
    <th scope="row" valign="top"><strong>České služby: XML feedy</strong></th>
  </tr>
  <tr class="form-field">
    <th scope="row" valign="top"><label>Odebrat z XML</label></th>
    <td> 
      <input name="ceske-sluzby-xml-vynechano" id="ceske-sluzby-xml-vynechano" type="checkbox" value="yes" <?php checked( $xml_vynechano_ulozeno, "yes" ); ?>/>
      <strong><span class="description" style="padding-right: 10px;">Vše</span></strong>
      <?php $feeds = ceske_sluzby_prehled_xml_feedu();
      foreach ( $feeds as $feed_id => $feed_name ) { ?>
        <input name="ceske-sluzby-xml-feed-vynechano[<?php echo $feed_id; ?>]" id="ceske-sluzby-xml-feed-vynechano[<?php echo $feed_id; ?>]" type="checkbox" value="yes" <?php checked( isset( $xml_feed_vynechano_ulozeno[$feed_id] ) ? $xml_feed_vynechano_ulozeno[$feed_id] : '', "yes" ); ?>/>
        <span class="description" style="padding-right: 10px;"><?php echo $feed_name; ?></span>
      <?php } ?>
      <p class="description">
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
      'ceske-sluzby-xml-glami-kategorie',
      'ceske-sluzby-xml-stav-produktu'
    );
    foreach ( $ukladana_data_text as $key ) {
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        if ( $key == 'ceske-sluzby-xml-heureka-kategorie' ) {
          $value = str_replace( 'Heureka.cz | ', '', $value );
          $value = str_replace( 'Heureka.sk | ', '', $value );
        }
        if ( $key == 'ceske-sluzby-xml-glami-kategorie' ) {
          $value = str_replace( 'Glami.cz | ', '', $value );
          $value = str_replace( 'Glami.sk | ', '', $value );
        }
        $ulozeno_text = get_woocommerce_term_meta( $term_id, $key, true );
        if ( ! empty( $value ) ) {
          update_woocommerce_term_meta( $term_id, $key, esc_attr( $value ) );
        } elseif ( ! empty( $ulozeno_text ) ) {
          delete_woocommerce_term_meta( $term_id, $key ); 
        }
      }
    }

    $ukladana_data_checkbox = array(
      'ceske-sluzby-xml-vynechano',
      'ceske-sluzby-xml-feed-vynechano',
      'ceske-sluzby-xml-erotika',
      'ceske-sluzby-xml-zbozi-extra-message'
    );
    foreach ( $ukladana_data_checkbox as $key ) {
      $ulozeno_checkbox = get_woocommerce_term_meta( $term_id, $key, true );
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        if ( ! empty( $value ) ) {
          update_woocommerce_term_meta( $term_id, $key, $value );
        }
      } elseif ( ! empty( $ulozeno_checkbox ) ) {
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
      $columns .= 'Zboží: <a href="#" title="' . $zbozi_kategorie . '">KA</a>';
      $zbozi_nazev = true;
    }
    $zbozi_productname = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-zbozi-productname', true );
    if ( $zbozi_productname ) {
      if ( $zbozi_nazev ) {
        $columns .= ' <a href="#" title="' . $zbozi_productname . '">PR</a>';
      } else {
        $columns .= 'Zboží: <a href="#" title="' . $zbozi_productname . '">PR</a>';
        $zbozi_nazev = true;
      }
    }
    $glami_kategorie = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-glami-kategorie', true );
    $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
    $kategorie_extra_message_ulozeno = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-zbozi-extra-message', true );
    if ( ! empty( $kategorie_extra_message_ulozeno ) ) {
      $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
      foreach ( $kategorie_extra_message_ulozeno as $key => $value ) {
        if ( ! empty( $extra_message_aktivace ) && in_array( $key, $extra_message_aktivace ) ) {
          $kategorie_extra_message[] = $extra_message_array[ $key ];
        }
      }
      if ( ! empty( $kategorie_extra_message ) ) {
        $kategorie_extra_message_text = implode( ', ', $kategorie_extra_message );
        if ( $zbozi_nazev ) {
          $columns .= ' <a href="#" title="' . $kategorie_extra_message_text . '">EM</a>';
        } else {
          $columns .= 'Zboží: <a href="#" title="' . $kategorie_extra_message_text . '">EM</a>';
        }
      }
    }
    if ( $glami_kategorie ) {
      if ( ! empty( $columns ) ) {
        $columns .= '<br />';
      }
      $columns .= 'Glami: <a href="#" title="' . $glami_kategorie . '">KA</a>';
    }
    $kategorie_vynechano = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-vynechano', true );
    $kategorie_feed_vynechano = get_woocommerce_term_meta( $id, 'ceske-sluzby-xml-feed-vynechano', true );
    if ( $kategorie_vynechano ) {
      $columns .= '<span style="margin-left: 10px; color: red; font-weight: bold;">X</span>';
    } elseif ( $kategorie_feed_vynechano ) {
      $feeds = ceske_sluzby_prehled_xml_feedu();
      $title = ' title="';
      $i = 0;
      foreach ( $kategorie_feed_vynechano as $feed => $value ) {
        if ( $i == 0 ) {
          $title .= $feeds[$feed];
        } else {
          $title .= ', ' . $feeds[$feed];
        };
        $i = $i + 1;
      }
      $title .= '"';
      $columns .= '<span style="margin-left: 10px; color: red;"' . $title . '>x</span>';
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
    if ( ! empty( $dostupnost ) ) {
      $availability['availability'] = $dostupnost['text'];
    }
    return $availability;
  }
  $dostupnost = ceske_sluzby_ziskat_predobjednavku( $product, false );
  if ( ! empty( $dostupnost ) ) {
    $availability['availability'] = $dostupnost;
    return $availability;
  }
  if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
    $dostupnost = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity() );
    if ( ! empty( $dostupnost ) ) {
      return $dostupnost;
    }
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
  if ( ! empty( $dostupnost ) ) {
    if ( ( ! $product->is_on_backorder( 1 ) && (string)$dostupnost['value'] == '0' ) || (string)$dostupnost['value'] != '0' ) {
      $availability['availability'] = $dostupnost['text'];
      return $availability;
    }
  }
  if ( $product->is_on_backorder( 1 ) ) {
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 98 );
    if ( ! empty( $dostupnost ) ) {
      $availability['availability'] = $dostupnost['text'];
    }
    return $availability;
  }
  return $availability;
}

function ceske_sluzby_zobrazit_dodaci_dobu_akce() {
  global $product;
  $format = "";
  if ( $product->is_type( 'variable' ) ) {
    return;
  }
  if ( ! $product->is_in_stock() ) {
    $dodaci_doba_text = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
    if ( ! empty( $dodaci_doba_text ) ) {
      $dostupnost['value'] = 99;
      $dostupnost['text'] = $dodaci_doba_text['text'];
      $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
    echo $format;
    return;
  }
  $format = ceske_sluzby_ziskat_predobjednavku( $product, true );
  if ( ! empty( $format ) ) {
    echo $format;
    return;
  }
  $availability = $product->get_availability();
  if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
    $dostupnost = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity() );
    echo '<p class="skladem-' . $dostupnost['class']. '">' . $dostupnost['availability'] . '</p>';
    return;
  }
  $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
  if ( ! empty( $dostupnost ) ) {
    if ( ( ! $product->is_on_backorder( 1 ) && (string)$dostupnost['value'] == '0' ) || (string)$dostupnost['value'] != '0' ) {
      $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
      echo $format;
    }
  }
  if ( $product->is_on_backorder( 1 ) ) {
    $dodaci_doba_text = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 98 );
    if ( ! empty( $dodaci_doba_text ) ) {
      $dostupnost['value'] = 98;
      $dostupnost['text'] = $dodaci_doba_text['text'];
      $format = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
    echo $format;
  }
}

function ceske_sluzby_nahradit_zobrazeny_text_deprecated( $html, $availability, $product ) {
  ceske_sluzby_nahradit_zobrazeny_text( $html, $product );
}

function ceske_sluzby_nahradit_zobrazeny_text( $html, $product ) {
  if ( get_class( $product ) == "WC_Product_Simple" ) {
    $html = "";
  }
  elseif ( get_class( $product ) == "WC_Product_Variation" ) {
    if ( ! $product->is_in_stock() ) {
      $dodaci_doba_text = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
      if ( ! empty( $dodaci_doba_text ) ) {
        $dostupnost['value'] = 99;
        $dostupnost['text'] = $dodaci_doba_text;
        $html = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
      }
      return $html;
    }
    $html = ceske_sluzby_ziskat_predobjednavku( $product, true );
    if ( ! empty( $html ) ) {
      return $html;
    }
    if ( $product->managing_stock() && (int)$product->get_stock_quantity() > 0 ) {
      $availability = $product->get_availability();
      $dostupnost = ceske_sluzby_ziskat_interval_pocet_skladem( $availability, (int)$product->get_stock_quantity() );
      $html = '<p class="skladem-' . $dostupnost['class']. '">' . $dostupnost['availability'] . '</p>';
      return $html;
    }
    $dostupnost = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, false );
    if ( ! empty( $dostupnost ) ) {
      $html = ceske_sluzby_ziskat_format_dodaci_doby( $dostupnost );
    }
  }
  return $html;
}

function ceske_sluzby_zobrazit_dodatecnou_dodaci_dobu_filtr( $data, $variable_product_object, $variation ) {
  $dostupnost = ceske_sluzby_ziskat_predobjednavku( $variation, false );
  if ( ! empty( $dostupnost ) ) {
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
  if ( ! empty( $format ) ) {
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
  $predobjednavka = get_option( 'wc_ceske_sluzby_preorder-aktivace' );
  $aktivace_eet = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
  if ( ( in_array( $screen_id, array( 'product', 'edit-product' ) ) && $predobjednavka == "yes" ) || $screen_id == 'shop_order' ) {
    wp_register_script( 'wc-admin-ceske-sluzby', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/ceske-sluzby-admin.js', array( 'jquery-ui-datepicker' ), CS_VERSION );
    wp_enqueue_script( 'wc-admin-ceske-sluzby' );
  }
  if ( in_array( $screen_id, array( 'woocommerce_page_wc-settings' ) ) && $aktivace_eet == "yes" ) {
    if ( ! did_action( 'wp_enqueue_media' ) ) {
      wp_enqueue_media();
    } 
    wp_register_script( 'wc-admin-ceske-sluzby-upload-button', untrailingslashit( plugins_url( '/', __FILE__ ) ) . '/js/ceske-sluzby-upload-button-admin.js', array( 'jquery' ), CS_VERSION );
    wp_enqueue_script( 'wc-admin-ceske-sluzby-upload-button' );
  }
}

function ceske_sluzby_povolit_nahravani_certifikatu( $mime_types ) {
  $mime_types['p12'] = 'application/x-pkcs12';
  return $mime_types;
}

function ceske_sluzby_zobrazit_eet_email( $order, $sent_to_admin, $plain_text, $email ) {
  if ( $email->id == 'customer_completed_order' || $email->id == 'customer_processing_order' || $email->id == 'customer_invoice' ) {
    $eet_format = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_format', 'eet_format', 'ceske_sluzby_eet_format' );
    if ( ! empty( $eet_format ) && ( $eet_format == 'email-completed' || $eet_format == 'email-processing' || $eet_format == 'email-faktura' ) ) {
      $eet = new Ceske_Sluzby_EET();
      $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
      if ( $plain_text ) {
        $eet->ceske_sluzby_zobrazit_eet_uctenku( $order_id, false, '', '', true );
      } else {
        $eet->ceske_sluzby_zobrazit_eet_uctenku( $order_id, false, '<br>' );
      }
    }
  }
}

function ceske_sluzby_zobrazit_eet_faktura_externi( $template_type, $order ) {
  $eet_format = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_format', 'eet_format', 'ceske_sluzby_eet_format' );
  if ( ! empty( $eet_format ) && $eet_format == 'faktura-plugin' ) {
    $eet = new Ceske_Sluzby_EET();
    $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
    $eet->ceske_sluzby_zobrazit_eet_uctenku( $order_id, false );
  }
}

function ceske_sluzby_automaticky_ziskat_uctenku( $order_id ) {
  $order = wc_get_order( $order_id );
  $eet_podminka = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_podminka', 'eet_podminka', 'ceske_sluzby_eet_podminka' );
  if ( ! empty( $eet_podminka ) && ( $eet_podminka == 'platba' || $eet_podminka == 'dokonceno' ) ) {
    $eet = new Ceske_Sluzby_EET();
    $odeslana_trzba = $eet->ziskat_odeslanou_trzbu( $order );
    if ( $odeslana_trzba > 0 ) {
      $eet->ceske_sluzby_ziskat_eet_uctenku( $order );
    }
  }
}

function ceske_sluzby_spustit_zaokrouhlovani( $cart ) {
  $zaokrouhlovani = zkontrolovat_nastavenou_hodnotu( '', array( 'wc_ceske_sluzby_nastaveni_pokladna' ), 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni', 'zaokrouhlovani', 'ceske_sluzby_zaokrouhleni' );
  if ( $zaokrouhlovani == 'nahoru' ) {
    if ( version_compare( WC_VERSION, '3.2', '<' ) ) {
      $dalsi_poplatky = is_callable( array( $cart, 'get_fees' ) ) ? $cart->get_fees() : $cart->fees;
      $cart->calculate_fees();
      if ( ! empty( $dalsi_poplatky ) ) {
        foreach ( $dalsi_poplatky as $poplatek ) {
          if ( $poplatek->taxable ) {
            $id_sazby = key( $poplatek->tax_data );
            if ( array_key_exists( $id_sazby, $cart->taxes ) )  {
              $cart->taxes[$id_sazby] = $cart->taxes[$id_sazby] - $poplatek->tax;
            }
          }
        }
      }
      if ( $cart->round_at_subtotal && wc_tax_enabled() ) {
        $cart->tax_total = WC_Tax::get_tax_total( $cart->taxes );
      } else {
        $cart->tax_total = array_sum( $cart->taxes );
      }
    } else {
      new WC_Cart_Totals( $cart );
    }
  }
}

function ceske_sluzby_zaokrouhlovani_poplatek_dane( $cart ) {
  $taxes['tax_class'] = '';
  $taxes['tax_rates'] = array();
  $shipping_tax_class = get_option( 'woocommerce_shipping_tax_class' );
  if ( ( version_compare( WC_VERSION, '3.0', '<' ) && $shipping_tax_class == '' ) || ( version_compare( WC_VERSION, '3.0', '=>' ) && $shipping_tax_class == 'inherit' ) ) {
    $cart_taxes = is_callable( array( $cart, 'get_cart_contents_taxes' ) ) ? $cart->get_cart_contents_taxes() : $cart->taxes;
    foreach ( $cart_taxes as $rate_id => $tax_rate ) {
      $shipping_taxes = is_callable( array( $cart, 'get_shipping_taxes' ) ) ? $cart->get_shipping_taxes() : $cart->shipping_taxes;
      if ( array_key_exists( $rate_id, $shipping_taxes ) ) {
        $tax_rate = $tax_rate + $shipping_taxes[$rate_id];
      }
      $kompletni_dane[$rate_id] = $tax_rate;
    }
    if ( ! empty( $kompletni_dane ) && is_array( $kompletni_dane ) ) {
      $max_dan = array_keys( $kompletni_dane, max( $kompletni_dane ) );
      if ( ! empty( $max_dan ) && is_array( $max_dan ) ) {
        foreach ( $max_dan as $rate_id ) {
          $tax_class_tmp = wc_get_tax_class_by_tax_id( $rate_id );
          $tax_rates_tmp = WC_Tax::get_rates( $tax_class_tmp );
          $sazba_tmp = $tax_rates_tmp[$rate_id]['rate'];
          if ( $sazba_tmp >= 0 ) {
            $taxes['tax_rates'] = $tax_rates_tmp;
            $taxes['tax_class'] = $tax_class_tmp;
          }
        }
      }
    }
  }
  else {
    $taxes['tax_rates'] = WC_Tax::get_rates( $shipping_tax_class );
    $taxes['tax_class'] = $shipping_tax_class;  
  }
  return $taxes;
}

function ceske_sluzby_zaokrouhlovani_poplatek( $cart ) {
  $dane = false;
  $tax_class = '';
  $decimals = get_option( 'woocommerce_price_num_decimals' );
  $poplatek = zkontrolovat_nastavenou_hodnotu( '', array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_doprava_poplatek_platba', 'poplatek_platba', 'ceske_sluzby_poplatek_platba' );
  $poplatek = str_replace( ',', '.', $poplatek );
  $poplatek = floatval( $poplatek );
  if ( ! empty( $poplatek ) ) {
    $poplatek_celkem = $poplatek;
    $nazev_poplatku = get_option( 'wc_ceske_sluzby_doprava_poplatek_platba_nazev' );
    if ( empty( $nazev_poplatku ) ) {
      $nazev_poplatku = 'Poplatek za způsob platby';
    }
    if ( wc_tax_enabled() ) {
      $dane = true;
      $taxes = ceske_sluzby_zaokrouhlovani_poplatek_dane( $cart );
      $tax_class = $taxes['tax_class'];
      $cena_dan = get_option( 'woocommerce_prices_include_tax' );
      if ( $cena_dan == 'yes' ) {
        $dan_poplatek = WC_Tax::calc_tax( $poplatek, $taxes['tax_rates'], true );
        $poplatek_celkem = $poplatek - reset( $dan_poplatek );
      }
    }
    $cart->add_fee( $nazev_poplatku, $poplatek_celkem, $dane, $tax_class );
  }
  if ( $cart->total > 0 && $decimals > 0 ) {
    $zaokrouhlovani = zkontrolovat_nastavenou_hodnotu( '', array( 'wc_ceske_sluzby_nastaveni_pokladna' ), 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni', 'zaokrouhlovani', 'ceske_sluzby_zaokrouhleni' );
    if ( $zaokrouhlovani == 'nahoru' ) {
      $celkem = $cart->total;
      $zao_total = ceil( $cart->total ) - $celkem;
      $zao = $zao_total;
      if ( wc_tax_enabled() ) {
        $dane = true;
        $taxes = ceske_sluzby_zaokrouhlovani_poplatek_dane( $cart );
        $tax_class = $taxes['tax_class'];
        $zao_taxes = WC_Tax::calc_tax( $zao_total, $taxes['tax_rates'], true );
        $zao = $zao_total - reset( $zao_taxes );
      }
      if ( $zao > 0 ) {
        $cart->add_fee( 'Zaokrouhlení', $zao, $dane, $tax_class );
        $cart->total += $zao_total;
      }
    }
  }
}

function ceske_sluzby_aktualizovat_checkout_javascript() {
  if ( is_checkout() ) {
    $nastaveni_pokladna = get_option( 'wc_ceske_sluzby_nastaveni_pokladna' );
    $nastaveni_pokladna_doprava = get_option( 'wc_ceske_sluzby_nastaveni_pokladna_doprava' );
    if ( ( is_array( $nastaveni_pokladna ) && ( in_array( 'zaokrouhlovani', $nastaveni_pokladna ) || in_array( 'poplatek_platba', $nastaveni_pokladna ) ) ) || 
    ( is_array( $nastaveni_pokladna_doprava ) && in_array( 'poplatek_platba', $nastaveni_pokladna_doprava ) ) ) { ?>
      <script type="text/javascript">
        jQuery(document).ready(function($){
          $(document.body).off().on('change', 'input[name="payment_method"]', function() {
            $('body').trigger('update_checkout');
          });
        });
      </script><?php
    }
  } 
}

function ceske_sluzby_compare_sazba( $a, $b ) {
  if ( $a->sazba == $b->sazba ) {
    return 0;
  }
  return ( $a->sazba > $b->sazba ) ? -1 : 1;
}

function ceske_sluzby_doplnit_danovou_sazbu( $tax_totals ) {
  foreach ( $tax_totals as $code => $tax ) {
    $tax_class = wc_get_tax_class_by_tax_id( $tax->rate_id );
    $tax_rates = WC_Tax::get_rates( $tax_class );
    if ( array_key_exists( $tax->rate_id, $tax_rates ) && array_key_exists( 'rate', $tax_rates[$tax->rate_id] ) ) {
      $tax_totals[ $code ]->sazba = (float)$tax_rates[$tax->rate_id]['rate'];
    }
  }
  if ( ! empty( $tax_totals ) ) {
    usort( $tax_totals, 'ceske_sluzby_compare_sazba' );
  }
  return $tax_totals;
}

function ceske_sluzby_dostupne_platebni_metody( $available_gateways ) {
  if ( ! is_admin() ) {
    $platebni_metody = zkontrolovat_nastavenou_hodnotu( '', array( 'wc_ceske_sluzby_nastaveni_doprava' ), '', 'platebni_metody', 'ceske_sluzby_platebni_metody' );
    if ( ! empty( $platebni_metody ) ) {
      foreach ( $platebni_metody as $platebni_metoda ) {
        if ( array_key_exists( $platebni_metoda, $available_gateways ) ) {
          unset( $available_gateways[$platebni_metoda] );
        }
      }
    }
  }
  return $available_gateways;
}

function ceske_sluzby_zmena_zaokrouhlovani_dani( $in ) {
  return round( $in, wc_get_price_decimals() );
}

function ceske_sluzby_zmena_kalkulace_dani( $taxes ) {
  $taxes = array_map( 'ceske_sluzby_rounding', $taxes );
  return $taxes;
};

function ceske_sluzby_rounding( $in ) {
  return round( $in, wc_get_price_decimals() );
}

function ceske_sluzby_zobrazeni_dodaci_doby_administrace( $stock_html, $produkt ) {
  $dodatek = "";
  if ( $produkt->is_type( 'simple' ) ) {
    $source = "";
    if ( ! $produkt->is_in_stock() ) {
      $dodaci_doba = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 99 );
      if ( ! empty( $dodaci_doba ) ) {
        $source = ' class="neni-skladem"';
        $stock_html .= '<br><span' . $source . '>' . $dodaci_doba['text'] . '</span>';
      }
      return $stock_html;
    }
    $dodaci_doba = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $produkt, false );
    if ( ! empty( $dodaci_doba ) ) {
      if ( ( ! $produkt->is_on_backorder( 1 ) && (string)$dodaci_doba['value'] == '0' ) || (string)$dodaci_doba['value'] != '0' ) {
        if ( isset( $dodaci_doba['source'] ) ) {
          $source = ' class="' . $dodaci_doba['source'] . '"';
        }
        $stock_html .= '<br><span' . $source . '>' . $dodaci_doba['text'] . '</span>';
        return $stock_html;
      }
    }
    if ( $produkt->is_on_backorder( 1 ) ) {
      $dodaci_doba = ceske_sluzby_ziskat_zadanou_dodaci_dobu( "", 98 );
      if ( ! empty( $dodaci_doba ) ) {
        $source = ' class="objednavka"';
        $stock_html .= '<br><span' . $source . '>' . $dodaci_doba['text'] . '</span>';
      }
      return $stock_html;
    }
  }
  if ( $produkt->is_type( 'variable' ) ) {
    $dostupne_varianty = $produkt->get_available_variations();
    if ( ! empty( $dostupne_varianty ) ) {
      foreach ( $dostupne_varianty as $variation ) {
        $varianta = wc_get_product( $variation['variation_id'] );
        $attributes_varianta = $varianta->get_variation_attributes();
        if ( ! empty( $attributes_varianta ) ) {
          $source = "";
          $dodaci_doba = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $varianta, false );
          $value_html = "";
          $i = 0;
          foreach ( $attributes_varianta as $key => $value ) {
            if ( $i == 0 ) {
              $value_html = $value;
            } else {
              $value_html .= ' & ' . $value;
            }
            $i = $i + 1;
          }
          if ( isset( $dodaci_doba['source'] ) ) {
            $source = ' class="' . $dodaci_doba['source'] .'"';
            if ( $dodaci_doba['source'] == "product" ) {
              $dodatek = ' title="Nastaveno na úrovni produktu"';
            }
          }
          if ( empty( $dodaci_doba ) ) {
            $availability = $varianta->get_availability();
            $dodaci_doba['text'] = $availability['availability'];
          }
          $stock_html .= '<br><span' . $source . $dodatek . '>' . $value_html . ': ' . $dodaci_doba['text'] . '</span>';
        }
      }
    }
  }
  return $stock_html;
}

function ceske_sluzby_zobrazeni_dodaci_doby_administrace_css() {
  global $pagenow;
  if ( $pagenow == 'edit.php' || $pagenow == 'post.php' ) { ?>
    <style type="text/css">
      table.wp-list-table .manage-column.column-is_in_stock {
        width: 15%;
      }
      span.external {
        font-size: 11px;
        color: grey;
      }
    </style>
  <?php }
}

function ceske_sluzby_zobrazeni_dodaci_doby_varianty( $variation ) {
  $source = "";
  $dodatek = "";
  $varianta = wc_get_product( $variation->ID );
  $dodaci_doba = ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $varianta, false );
  if ( $dodaci_doba ) {
    if ( isset( $dodaci_doba['source'] ) ) {
      $source = ' class="' . $dodaci_doba['source'] .'"';
      if ( $dodaci_doba['source'] == "product" ) {
        $dodatek = ' (nastaveno na úrovni produktu)';
      }
    }
    echo '<span' . $source . '>' . $dodaci_doba['text'] . $dodatek . '</span>';
  }
}

// http://docs.packetery.com/01-pickup-point-selection/01-widget.html#toc-quick-start-examples
function ceske_sluzby_zasilkovna_scripts_checkout() {
  if ( is_checkout() ) {
    $zasilkovna_settings = get_option( 'woocommerce_ceske_sluzby_zasilkovna_settings' ); 
    if ( isset( $zasilkovna_settings['zasilkovna_api-klic'] ) && ! empty( $zasilkovna_settings['zasilkovna_api-klic'] ) ) {
      $api_klic = $zasilkovna_settings['zasilkovna_api-klic']; ?>
      <script src="https://widget.packeta.com/www/js/library.js"></script>
      <script type="text/javascript">
        var packetaApiKey = '<?php echo $api_klic; ?>';
        var $storage_support = true;
        try {
          $storage_support = ( 'sessionStorage' in window && window.sessionStorage !== null );
          window.localStorage.setItem( 'ceske_sluzby', 'test' );
          window.localStorage.removeItem( 'ceske_sluzby' );
        } catch( err ) {
          $storage_support = false;
        }
        if ( $storage_support ) {
          jQuery( document ).ready(function( $ ) {
            $( document.body ).on( 'updated_checkout', function() {
              var ceske_sluzby_zasilkovna = localStorage.getItem( 'ceske_sluzby_zasilkovna' );
              if ( document.getElementById( 'packeta-point-info' ) !== null ) {
                var paragraph = document.getElementById( 'packeta-point-info' ).firstChild;
                if ( ceske_sluzby_zasilkovna !== null ) {
                  paragraph.nodeValue = ceske_sluzby_zasilkovna;
                  document.getElementById( 'packeta-point-id' ).value = ceske_sluzby_zasilkovna;
                } else if ( paragraph !== "Zatím nevybráno" ) {
                  paragraph.nodeValue = "Zatím nevybráno";
                }
              }
            })
          });
        }
        function showSelectedPickupPoint(point) {
          var spanElement = document.getElementById( 'packeta-point-info' );
          var idElement = document.getElementById( 'packeta-point-id' );
          if ( point ) {
            spanElement.innerText = point.name;
            idElement.value = point.name;
            if ( $storage_support ) {
              localStorage.setItem( 'ceske_sluzby_zasilkovna', point.name );
            }
          }
          else {
            if ( $storage_support ) {
              var ceske_sluzby_zasilkovna = localStorage.getItem( 'ceske_sluzby_zasilkovna' );
            } else {
              var ceske_sluzby_zasilkovna = null;
            }
            if ( ceske_sluzby_zasilkovna !== null ) {
              spanElement.innerText = ceske_sluzby_zasilkovna;
              idElement.value = ceske_sluzby_zasilkovna;
            } else {
              spanElement.innerText = "Zatím nevybráno";
              idElement.value = "";
            }
          }
        };
      </script>
    <?php }
  }
}

add_filter( 'woocommerce_package_rates', 'ceske_sluzby_doprava_cenove_intervaly' );
function ceske_sluzby_doprava_cenove_intervaly( $rates ) {
  if ( ! is_admin() ) {
    $nastaveni_doprava = get_option( 'wc_ceske_sluzby_nastaveni_doprava' );
    if ( is_array( $nastaveni_doprava ) && in_array( 'cena_dopravy', $nastaveni_doprava ) ) {
      $available_shipping = WC()->shipping->load_shipping_methods();
      $cena_kosik = WC()->cart->subtotal;
      $ceny_doprava = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_doprava-zpusob-dane' );
      foreach( $rates as $key => $rate ) {
        $cena_dopravy_array = array();
        if ( strpos( $key, ':' ) === false ) {
          if ( isset( $available_shipping[ $key ] ) ) {
            $shipping_method = $available_shipping[$key];
            $settings = $shipping_method->settings;
          }
        } else {
          $pieces = explode( ":", $key );
          if ( is_array( $pieces ) && ! empty( $pieces ) && count( $pieces ) == 2 ) {
            $order_method = $pieces[0];
            $order_instance = $pieces[1];
            if ( is_numeric( $order_instance ) ) {
              $shipping_method = WC_Shipping_Zones::get_shipping_method( $order_instance );
              $settings = $shipping_method->instance_settings;
            }
          }
        }
        if ( isset( $settings['ceske_sluzby_cena_dopravy'] ) && ! empty( $settings['ceske_sluzby_cena_dopravy'] ) ) {
          $cena_dopravy_array = ceske_sluzby_zpracovat_ceny_dopravy( $settings['ceske_sluzby_cena_dopravy'], $cena_kosik );
        }
        if ( empty( $cena_dopravy_array ) && wc_prices_include_tax() && $ceny_doprava == 'yes' ) {
          $cena_dopravy_array['cena'] = $rates[$key]->cost;
        }
        if ( ! empty( $cena_dopravy_array ) ) {
          if ( $cena_dopravy_array['cena'] == 0 ) {
            $rates[$key]->taxes = 0;
          }
          $new_cost = wc_format_decimal( $cena_dopravy_array['cena'], wc_get_price_decimals() );
          $rates[$key]->cost = $new_cost;
          if ( wc_tax_enabled() && false !== $rates[$key]->taxes && $rates[$key]->cost > 0 && $shipping_method->is_taxable()) {
            if ( wc_prices_include_tax() ) {
              $taxes = WC_Tax::calc_inclusive_tax( $new_cost, WC_Tax::get_shipping_tax_rates() );
            } else {
              $taxes = WC_Tax::calc_exclusive_tax( $new_cost, WC_Tax::get_shipping_tax_rates() );
            }
            $rates[$key]->taxes = $taxes;
          }
          if ( wc_prices_include_tax() && $rates[$key]->cost > 0 ) {
            $rates[$key]->cost = $new_cost - current( $taxes );
          }
        }
      }
    }
  }
  return $rates;
}

add_filter( 'woocommerce_cart_shipping_method_full_label', 'ceske_sluzby_doprava_text_pro_dopravu_zdarma', 10, 2 );
function ceske_sluzby_doprava_text_pro_dopravu_zdarma( $label, $method ) {
  if ( $method->cost == 0 ) {
    $text_doprava_zdarma = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_doprava-text-dopravy-zdarma' );
    if ( ! empty( $text_doprava_zdarma ) ) {
      if ( $text_doprava_zdarma == '{VALUE}' ) {
        $label .= ': ' . wc_price( $method->cost );
      } else {
        $label .= ': <span class="woocommerce-Price-amount amount">' . $text_doprava_zdarma . '</span>';
      }
    }
  }
  return $label;
}

function ceske_sluzby_zmena_stavu_objednavky_platba_predem( $status, $order ) {
  return 'processing';
}

function ceske_sluzby_moznost_odesilat_emaily_zmena_stavu_platba_predem( $email_actions ) {
  $email_actions[] = 'woocommerce_order_status_processing_to_on-hold';
  return $email_actions;
}

function ceske_sluzby_zmena_emailovych_notifikaci_platba_predem( $email_class ) {
  remove_action( 'woocommerce_order_status_on-hold_to_processing_notification', array( $email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger' ) );
  add_action( 'woocommerce_order_status_processing_to_on-hold_notification', array( $email_class->emails['WC_Email_Customer_On_Hold_Order'], 'trigger' ) );
}

function ceske_sluzby_odebrat_bankovni_ucet_po_dokonceni_objednavky() {
  if ( ! function_exists( 'WC' ) ) {
    return;
  }
  $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
  $gateway = isset( $available_gateways['bacs'] ) ? $available_gateways['bacs'] : false;
  if ( false == $gateway ) {
    return;
  }
  remove_action( 'woocommerce_thankyou_bacs', array( $gateway, 'thankyou_page' ) );
}

add_filter( 'woocommerce_admin_order_actions', 'ceske_sluzby_zmena_stavu_platba_predem_administrace_ikony', 100, 2 );
function ceske_sluzby_zmena_stavu_platba_predem_administrace_ikony( $actions, $order ) {
  $zmena_platby_predem = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_zmena-platby-predem' );
  $aktivace_odeslano = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_status-odeslano' );
  if ( $zmena_platby_predem == "yes" ) {
    if ( $order->has_status( 'processing' ) ) {
      $on_hold = array( 'on-hold' => array(
        'url' => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=on-hold&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
        'name' => __( 'On-hold', 'woocommerce' ),
        'action' => 'on-hold',
      ) );
      $actions = array_merge( $on_hold, $actions );
      unset( $actions['complete'] );
    }
    if ( $order->has_status( 'odeslano' ) ) {
      $actions['complete'] = array(
        'url' => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
        'name' => __( 'Complete', 'woocommerce' ),
        'action' => 'complete',
      );
    }
    unset( $actions['processing'] );
  }
  elseif ( $aktivace_odeslano == "yes" ) {
    if ( $order->has_status( 'odeslano' ) ) {
      $actions['complete'] = array(
        'url' => wp_nonce_url( admin_url( 'admin-ajax.php?action=woocommerce_mark_order_status&status=completed&order_id=' . $order->get_id() ), 'woocommerce-mark-order-status' ),
        'name' => __( 'Complete', 'woocommerce' ),
        'action' => 'complete',
      );
    }
  }
  return $actions;
}

function ceske_sluzby_stylovani_tlacitek_objednavky_administrace_css() {
  global $pagenow;
  if ( $pagenow == 'edit.php' || $pagenow == 'post.php' ) { ?>
    <style type="text/css">
      .widefat .column-wc_actions a.on-hold::after {
        font-family: "WooCommerce";
        content: "\e00f";
      }
    </style>
  <?php }
}

add_action( 'init', 'ceske_sluzby_registrace_stavu_objednavky_odeslano' );
function ceske_sluzby_registrace_stavu_objednavky_odeslano() {
  $aktivace_odeslano = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_status-odeslano' );
  if ( $aktivace_odeslano == "yes" ) {
    register_post_status( 'wc-odeslano', array(
      'label' => 'Odesláno',
      'public' => true,
      'show_in_admin_status_list' => true,
      'show_in_admin_all_list' => true,
      'exclude_from_search' => false,
      'label_count' => _n_noop( 'Odesláno <span class="count">(%s)</span>', 'Odesláno <span class="count">(%s)</span>' )
    ) );
    add_filter( 'wc_order_statuses', 'ceske_sluzby_zobrazovat_status_objednano' );
  }
}

function ceske_sluzby_zobrazovat_status_objednano( $order_statuses ) {
  $new_order_statuses = array();
  foreach ( $order_statuses as $key => $status ) {
    $new_order_statuses[ $key ] = $status;
    if ( 'wc-processing' === $key || 'wc-on-hold' === $key ) {
      $new_order_statuses['wc-odeslano'] = 'Odesláno';
    }
  }
  return $new_order_statuses;
}

add_filter( 'woocommerce_order_number', 'ceske_sluzby_zmenit_cislo_objednavky', 10, 2 );
function ceske_sluzby_zmenit_cislo_objednavky( $order_id, $order ) {
  $format_cisla = get_option( 'wc_ceske_sluzby_format_cisla_objednavky' );
  if ( ! empty( $format_cisla ) && $format_cisla == "{DATE:Ymd}{SEQUENCE:d|2}" ) {
    $cislo_objednavky = get_post_meta( $order_id, '_ceske_sluzby_cislo_objednavky', true );
    if ( ! empty( $cislo_objednavky ) ) {
      return $cislo_objednavky;
    }
  }
  return $order_id;
}

// https://github.com/joydipnath/Custom-Order-Number-Woo
add_action( 'woocommerce_new_order', 'ceske_sluzby_ulozit_nastavene_cislo_objednavky' );
function ceske_sluzby_ulozit_nastavene_cislo_objednavky( $order_id ) {
  $format_cisla = get_option( 'wc_ceske_sluzby_format_cisla_objednavky' );
  if ( ! empty( $format_cisla ) && $format_cisla == "{DATE:Ymd}{SEQUENCE:d|2}" ) {
    $last_order = get_option( 'ceske_sluzby_cislo_objednavky' );
    $actual_date = current_time( 'Ymd' );
    if ( ! empty( $last_order ) ) {
      $last_order_date = substr( $last_order, 0, 8 );
      $last_sequence = substr( $last_order, 8, 2 );
      $last_sequence_number = ltrim( $last_sequence, "0" );
      if ( $actual_date == $last_order_date ) {
        $sequence = $last_sequence_number + 1;
        $sequence = sprintf( '%02d', $sequence );
      } else {
        $sequence = sprintf( '%02d', 1 );
      }
    } else {
      $sequence = sprintf( '%02d', 1 );
    }
    $order_number = $actual_date . $sequence;
    update_post_meta( $order_id, '_ceske_sluzby_cislo_objednavky', $order_number );
    update_option( 'ceske_sluzby_cislo_objednavky', $order_number );
  }
};

add_filter( 'woocommerce_bacs_account_fields', 'ceske_sluzby_platba_predem_variabilni_symbol', 10, 2 );
function ceske_sluzby_platba_predem_variabilni_symbol( $account_fields, $order_id ) {
  $bacs_settings = get_option( 'woocommerce_bacs_settings' ); 
  if ( isset( $bacs_settings['ceske_sluzby_variabilni_symbol'] ) && $bacs_settings['ceske_sluzby_variabilni_symbol'] == "yes" ) {
    if ( isset( $account_fields['account_number']['value'] ) && ! empty( $account_fields['account_number']['value'] ) ) {
      $order = wc_get_order( $order_id );;
      $account_fields_new = array();
      foreach( $account_fields as $key => $value ) {
        $account_fields_new[$key] = $value;
        if ( $key === 'account_number' ) {
          $account_fields_new['variabilni-symbol'] = array(
            'label' => 'Variabilní symbol',
            'value' => $order->get_order_number()
          );
        }
      }
      return $account_fields_new;
    }
  }
  return $account_fields;
}
