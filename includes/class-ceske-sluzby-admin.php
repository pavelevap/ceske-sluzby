<?php
// http://docs.woothemes.com/document/adding-a-section-to-a-settings-tab/
class WC_Settings_Tab_Ceske_Sluzby_Admin {

  public static function init() {
    add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 100 );
    add_action( 'woocommerce_settings_tabs_ceske-sluzby', __CLASS__ . '::settings_tab' );
    add_action( 'woocommerce_update_options_ceske-sluzby', __CLASS__ . '::update_settings' );
  }

  public static function add_settings_tab( $settings_tabs ) {
    $settings_tabs['ceske-sluzby'] = 'České služby';
    return $settings_tabs;
  }

  public static function settings_tab() {
    woocommerce_admin_fields( self::get_settings() );
  }

  public static function update_settings() {
    woocommerce_update_options( self::get_settings() );
  }

  public static function get_settings() {
    $settings = array(
      array(
        'title' => 'Služby pro WordPress',
        'type' => 'title',
        'desc' => 'Pokud nebude konkrétní hodnota vyplněna, tak se nebude příslušná služba vůbec spouštět.',
        'id' => 'wc_ceske_sluzby_title'
      ),
      array(
        'title' => 'Heureka.cz',
        'type' => 'title',
        'desc' => '',
        'id' => 'wc_ceske_sluzby_heureka_title'
      ),
      array(
        'title' => 'API klíč: Ověřeno zákazníky',
        'type' => 'text',
        'desc' => 'API klíč pro službu Ověřeno zákazníky naleznete <a href="http://sluzby.heureka.cz/sluzby/certifikat-spokojenosti/">zde</a>.',
        'id' => 'wc_ceske_sluzby_heureka_overeno-api',
        'css' => 'width: 300px'
      ),
      array(
        'title' => 'API klíč: Měření konverzí',
        'type' => 'text',
        'desc' => 'API klíč pro službu Měření konverzí naleznete <a href="http://sluzby.heureka.cz/obchody/mereni-konverzi/">zde</a>.',
        'id'   => 'wc_ceske_sluzby_heureka_konverze-api',
        'css'   => 'width: 300px'
      ),
      array(
        'type' => 'sectionend',
        'id' => 'wc_ceske_sluzby_heureka_title'
      ),
      array(
        'title' => 'Sklik.cz',
        'type' => 'title',
        'desc' => '',
        'id' => 'wc_ceske_sluzby_sklik_title'
      ),
      array(
        'title' => 'ID konverzního kódu',
        'type' => 'text',
        'desc' => 'ID získaného kódu pro měření konverzí naleznete <a href="https://www.sklik.cz/seznam-konverzi">zde</a>. Je třeba vytvořit konverzní kód typu "vytvoření objednávky" a z něho získat potřebné ID.',
        'id' => 'wc_ceske_sluzby_sklik_konverze-objednavky'
      ),
      array(
        'type' => 'sectionend',
        'id' => 'wc_ceske_sluzby_sklik_title'
      ),
      array(
        'title' => 'Další nastavení',
        'type' => 'title',
        'desc' => '',
        'id' => 'wc_ceske_sluzby_dalsi_nastaveni_title'
      ),
      array(
        'title' => 'Možnost změny objednávek pro dobírku',
        'type' => 'checkbox',
        'desc' => 'Povolí možnost změny objednávek, které jsou provedené prostřednictvím dobírky.',
        'id' => 'wc_ceske_sluzby_dalsi_nastaveni_dobirka-zmena'
      ),
      array(
        'type' => 'sectionend',
        'id' => 'wc_ceske_sluzby_dalsi_nastaveni_title'
      )
    );

    return $settings;
  }
}