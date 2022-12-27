<?php
// https://github.com/woocommerce/woocommerce/wiki/Shipping-Method-API
class WC_Shipping_Ceske_Sluzby_Zasilkovna extends WC_Shipping_Method {
  public function __construct( $instance_id = 0 ) {
    $this->instance_id = absint( $instance_id );
    $this->id = 'ceske_sluzby_zasilkovna';
    $this->method_title = 'Zásilkovna';
    $this->method_description = 'Základní možnosti nastavení.';
    $aktivace_zasilkovna = get_option( 'wc_ceske_sluzby_doprava_zasilkovna' );
    $this->enabled = $aktivace_zasilkovna;
    $this->supports = array(
      'shipping-zones',
      'settings',
      'instance-settings',
      'instance-settings-modal',
    );
    $this->init();
  }

  function init() {
    $this->init_form_fields();
    $this->init_settings();
    $this->init_instance_form_fields();
    $this->init_instance_settings();
    $this->title = $this->get_option( 'zasilkovna_nazev' );
    add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
  }
 
  public function calculate_shipping( $package = array() ) {
    $rate = array(
      'id' => $this->get_rate_id(),
      'label' => $this->title,
      'cost' => $this->get_option( 'zasilkovna_zakladni-cena' ),
      'package' => $package,
    );
    $this->add_rate( $rate );
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'zasilkovna_api-klic' => array(
        'title' => 'API klíč',
        'type' => 'text',
        'description' => 'API klíč naleznete v administraci Zásilkovny (<a href="https://client.packeta.com/cs/support/">zde</a>).',
        'default' => '',
        'css' => 'width: 300px;'
      ),
    );
  } 

  public function init_instance_form_fields() {
    $this->instance_form_fields = array(
      'zasilkovna_nazev' => array(
        'title' => 'Název',
        'type' => 'text',
        'description' => 'Název pro zobrazení v eshopu.',
        'default' => 'Zásilkovna',
        'css' => 'width: 300px;'
      ),
      'zasilkovna_zakladni-cena' => array(
        'title' => 'Základní cena',
        'type' => 'price',
        'description' => 'Pokud nebude cena vyplněna, tak bude nulová.',
        'default' => '',
        'css' => 'width: 100px;',
        'placeholder' => wc_format_localized_price( 0 )
      ),
    );
  }      
}