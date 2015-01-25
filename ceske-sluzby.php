<?php
/**
 * Plugin Name: České služby pro WordPress
 * Plugin URI: http://www.separatista.net
 * Description: Implementace různých českých služeb do WordPressu.
 * Version: 0.1
 * Author: Pavel Hejn
 * Author URI: http://www.separatista.net
 * License: GPL2
 */

function ceske_sluzby_heureka_overeno_zakazniky( $order_id, $posted ) {
  $api = get_option( 'wc_ceske_sluzby_heureka_overeno-api' );
  if ( ! empty( $api ) ) {
  
    $order = new WC_Order( $order_id );
    
    // https://github.com/heureka/heureka-overeno-php-api
    require_once( dirname( __FILE__ ) . '/src/HeurekaOvereno.php' );

    $overeno = new HeurekaOvereno( $api );
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
    ho.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.heureka.cz/direct/js/cache/1-roi-async.js';
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
 
function ceske_sluzby_kontrola_aktivniho_pluginu() {
	if( class_exists( 'WooCommerce' ) ) {
    if( is_admin() ) {
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-admin.php';
      WC_Settings_Tab_Ceske_Sluzby_Admin::init();
    }
    add_action( 'woocommerce_checkout_order_processed', 'ceske_sluzby_heureka_overeno_zakazniky', 10, 2 );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_heureka_mereni_konverzi' );
    add_action( 'woocommerce_thankyou', 'ceske_sluzby_sklik_mereni_konverzi' );
	}
}
add_action( 'plugins_loaded', 'ceske_sluzby_kontrola_aktivniho_pluginu' );

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	function your_shipping_method_init() {
		if ( ! class_exists( 'WC_Shipping_Ceske_Sluzby_Ulozenka' ) ) {
      require_once plugin_dir_path( __FILE__ ) . 'includes/class-ceske-sluzby-ulozenka.php';
		}
	} 
	add_action( 'woocommerce_shipping_init', 'your_shipping_method_init' );
 
	function ceske_sluzby_doprava_ulozenka( $methods ) {
		$methods[] = 'WC_Shipping_Ceske_Sluzby_Ulozenka';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'ceske_sluzby_doprava_ulozenka' );
}

add_action( 'woocommerce_review_order_before_payment', 'ceske_sluzby_ulozenka_zobrazit_pobocky' );
function ceske_sluzby_ulozenka_zobrazit_pobocky() {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  $settings = array();
  
  if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;

    if ( $settings['enabled'] == "yes" && ! empty ( $settings['ulozenka_id-obchodu'] ) ) { ?>
  
<table class="ulozenka">
    <tr>
        <td>
            <img src="https://www.ulozenka.cz/logo/ulozenka.png" width="140" border="0">
        </td>
        <td style="padding: 7px; vertical-align: middle;">
            <font size="2">Uloženka - vyberte prosím pobočku:</font><br>
            <div id="ulozenka-branch-select-options"></div>
        </td>
    </tr>
</table>

<script>
    var response = "";
    var request = new XMLHttpRequest();
    optionsDiv = document.getElementById('ulozenka-branch-select-options');
    request.open("GET", "https://api.ulozenka.cz/v2/branches?shopId=<?php echo $settings['ulozenka_id-obchodu']; ?>&partner=0", true);
        request.setRequestHeader('Accept', 'application/json')
        request.onreadystatechange = function() {
            if (request.readyState == 4) {
                if (request.status == 200 || request.status == 0) {
                    response = JSON.parse(request.responseText);
                    branches = response.data;
                    select = document.createElement("select");
                    select.setAttribute('name', "ulozenka_branches");
                    optionsDiv.appendChild(select);
                    option = document.createElement("option");
                    option.innerHTML = 'Vyberte pobočku';
                    select.appendChild(option);
                    for (i = 0; i < branches.length; i++) {
                        branch = branches[i];
                        option = document.createElement("option");
                        option.setAttribute('value', branch.name);
                        option.innerHTML = ""+branch.name+"";
                        select.appendChild(option);
                    }
                } else {
                    optionsDiv.innerHTML = "Nepodařilo se načíst seznam poboček.";
                }
            }
        }
        request.send();
</script>

<script>
jQuery(document).ready(function($){
  $doprava = $("#shipping_method input[type='radio']:checked").val();
  if ( $doprava == 'ceske_sluzby_ulozenka') {
    $('.ulozenka').show();  
  } else {
    $('.ulozenka').hide();
  }
})
</script>

<?php }
  }
}

add_action( 'woocommerce_add_shipping_order_item', 'ceske_sluzby_ulozenka_ulozeni_pobocky', 10, 2 );
function ceske_sluzby_ulozenka_ulozeni_pobocky( $order_id, $item_id ) {
    if ( $_POST["ulozenka_branches"] && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', esc_attr( $_POST['ulozenka_branches'] ), true );
    }
}

add_action('woocommerce_checkout_process', 'ceske_sluzby_ulozenka_overit_pobocku');
function ceske_sluzby_ulozenka_overit_pobocku() {
	global $woocommerce;
	if ( $_POST["ulozenka_branches"] == "Vyberte pobočku" && $_POST["shipping_method"][0] == "ceske_sluzby_ulozenka" ) {
		$woocommerce->add_error( 'Pokud chcete platit prostřednictvím Uloženky, zvolte prosím pobočku.' );
  }
}

add_action( 'woocommerce_admin_order_data_after_billing_address', 'ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku' );
function ceske_sluzby_ulozenka_objednavka_zobrazit_pobocku( $order ) {
  if ( $order->has_shipping_method('ceske_sluzby_ulozenka') ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>Uloženka:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true ) . "</p>";
    }
  }
}

add_filter( 'woocommerce_pay4pay_cod_amount', 'ceske_sluzby_ulozenka_dobirka_pay4pay' );
function ceske_sluzby_ulozenka_dobirka_pay4pay( $amount ) {
  $available_shipping = WC()->shipping->get_shipping_methods();
  $chosen_shipping_method = WC()->session->get( 'chosen_shipping_methods' );
  if ( $chosen_shipping_method[0] == "ceske_sluzby_ulozenka" ) {
    $settings = $available_shipping[ $chosen_shipping_method[0] ]->settings;
    return $settings['ulozenka_dobirka'];
  }
  else {
    return $amount;
  }
}

add_action( 'woocommerce_email_after_order_table', 'ceske_sluzby_ulozenka_pobocka_email' );
add_action( 'woocommerce_order_details_after_order_table', 'ceske_sluzby_ulozenka_pobocka_email' );
function ceske_sluzby_ulozenka_pobocka_email( $order ) {
  if ( $order->has_shipping_method('ceske_sluzby_ulozenka') ) {
    foreach ( $order->get_shipping_methods() as $shipping_item_id => $shipping_item ) {
      echo "<p><strong>Uloženka:</strong> " . $order->get_item_meta( $shipping_item_id, 'ceske_sluzby_ulozenka_pobocka_nazev', true ) . "</p>";
    }
  }
}




