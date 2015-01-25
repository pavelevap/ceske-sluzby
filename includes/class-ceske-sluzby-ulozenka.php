<?php
// http://docs.woothemes.com/document/settings-api/
// http://speakinginbytes.com/2014/07/woocommerce-settings-tab/
class WC_Shipping_Ceske_Sluzby_Ulozenka extends WC_Shipping_Method {

  public function __construct() {
     $this->id = 'ceske_sluzby_ulozenka';
     $this->method_title = 'Uloženka';
     $this->method_description = 'Základní možnosti nastavení.';
     $this->title = $this->get_option( 'ulozenka_nazev' );
     $this->enabled = $this->get_option( 'enabled' );
     $this->init();
   }
 
  function init() {
    $this->init_form_fields();
    $this->init_settings();
    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}
 
  public function calculate_shipping( $package ) {
    $rate = array(
      'id' => $this->id,
      'label' => $this->title,
      'cost' => $this->get_option( 'ulozenka_zakladni-cena' ),
      'calc_tax' => 'per_item'
    );
    $this->add_rate( $rate );
	}
      
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => 'Povolit',
			  'type'    => 'checkbox',
			  'label'   => 'Aktivovat a zobrazit v nabídce dostupných možností dopravy.',
			  'default' => 'no'
      ),
      'ulozenka_nazev' => array(
				'title'       => 'Název',
				'type'        => 'text',
				'description' => 'Název pro zobrazení v eshopu.',
				'default'     => 'Uloženka',
        'css'         => 'width: 300px;'
      ),
      'ulozenka_id-obchodu' => array(
				'title'       => 'ID Obchodu',
				'type'        => 'text',
				'description' => 'Zadejte ID obchodu z administrace Uloženka.',
				'default'     => '',
				'css'         => 'width: 100px;'
        ),
      'ulozenka_zakladni-cena' => array(
				'title'       => 'Základní cena',
				'type'        => 'price',
				'description' => 'Pokud nebude cena vyplněna, tak bude nulová.',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      ),
			'ulozenka_dobirka' => array(
				'title'       => 'Poplatek za dobírku',
				'type'        => 'price',
				'description' => 'Pro fungování dodatečného poplatku za dobírku je třeba použít plugin <a href="https://wordpress.org/plugins/woocommerce-pay-for-payment/">WooCommerce Pay for Payment</a> a nastavit u něj stejný poplatek za dobírku (menu Pokladna - Hotově při doručení).',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      )
    );
  }      
}