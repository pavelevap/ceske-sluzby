<?php
// http://calebserna.com/how-to-add-multiple-local-pickup-locations-to-woocommerce/
class WC_Shipping_Ceske_Sluzby_DPD_ParcelShop extends WC_Shipping_Method {

  public function __construct() {
     $this->id = 'ceske_sluzby_dpd_parcelshop';
     $this->method_title = 'DPD ParcelShop';
     $this->method_description = 'Základní možnosti nastavení. Funguje samostatně nebo jako doplňkové pobočky pro Uloženku (v tomto případě je vhodné <a href="' . site_url() . '/wp-admin/admin.php?page=wc-settings&tab=shipping&section=wc_shipping_ceske_sluzby_ulozenka">zadat</a> ID obchodu).';
     $this->title = $this->get_option( 'dpd_parcelshop_nazev' );
     $this->enabled = $this->get_option( 'enabled' );
     $this->init();
   }
 
  function init() {
    $this->init_form_fields();
    $this->init_settings();
    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
  }
 
  public function calculate_shipping( $package = array() ) {
    $zeme = WC()->customer->get_shipping_country();
    if ( $zeme == "CZ" ) { $cena = $this->get_option( 'dpd_parcelshop_zakladni-cena' ); }
    if ( $zeme == "SK" ) { $cena = $this->get_option( 'dpd_parcelshop_zakladni-cena-slovensko' ); }
    
    $rate = array(
      'id' => $this->id,
      'label' => $this->title,
      'cost' => $cena
    );
    $this->add_rate( $rate );
  }
      
  public function init_form_fields() {
    $zakladni = array(
      'enabled' => array(
				'title'   => 'Povolit',
				'type'    => 'checkbox',
				'label'   => 'Aktivovat a zobrazit v nabídce dostupných možností dopravy.',
				'default' => 'no'
      ),
      'dpd_parcelshop_nazev' => array(
				'title'       => 'Název',
				'type'        => 'text',
				'description' => 'Název pro zobrazení v eshopu.',
				'default'     => 'DPD ParcelShop',
				'css'         => 'width: 300px;'
      ),
      'dpd_parcelshop_zakladni-cena' => array(
				'title'       => 'Základní cena',
				'type'        => 'price',
				'description' => 'Pokud nebude cena vyplněna, tak bude nulová.',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      ),
      'dpd_parcelshop_dobirka' => array(
				'title'       => 'Poplatek za dobírku',
				'type'        => 'price',
				'description' => 'Pro fungování dodatečného poplatku za dobírku je třeba použít plugin <a href="https://wordpress.org/plugins/woocommerce-pay-for-payment/">WooCommerce Pay for Payment</a> a nastavit u něj stejný poplatek za dobírku (menu Pokladna - Hotově při doručení).',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      )
    );
    
    $slovensko = array(
      'dpd_parcelshop_zakladni-cena-slovensko' => array(
				'title'       => 'Základní cena (Slovensko)',
				'type'        => 'price',
				'description' => 'Pokud nebude cena vyplněna, tak bude nulová.',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      ),
      'dpd_parcelshop_dobirka-slovensko' => array(
				'title'       => 'Poplatek za dobírku (Slovensko)',
				'type'        => 'price',
				'description' => 'Pro fungování dodatečného poplatku za dobírku je třeba použít plugin <a href="https://wordpress.org/plugins/woocommerce-pay-for-payment/">WooCommerce Pay for Payment</a> a nastavit u něj stejný poplatek za dobírku (menu Pokladna - Hotově při doručení).',
				'default'     => '',
				'css'         => 'width: 100px;',
				'placeholder' => wc_format_localized_price( 0 )
      )
    );

    $zvolene_zeme = WC()->countries->get_shipping_countries();
    if ( array_key_exists( 'SK', $zvolene_zeme ) ) {
      $this->form_fields = array_merge( $zakladni, $slovensko );
    } else {
      $this->form_fields = $zakladni;
    }
  }      
}