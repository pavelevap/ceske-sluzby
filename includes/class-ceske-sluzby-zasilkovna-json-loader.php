<?php
// http://pressing-matters.io/building-an-object-oriented-wordpress-plugin-xkcd-shortcode-part-5/
class Ceske_Sluzby_Zasilkovna_Json_Loader {
  function load( $params = null ) {
    $json = get_transient( 'ceske_sluzby_zasilkovna_pobocky' );
    if ( !$json ) {  
      $url = $this->build( $params );
      $result = $this->fetch( $url );
      $body = $this->verify( $result );
      $json = $this->parse( $body );
      set_transient( 'ceske_sluzby_zasilkovna_pobocky', $json, 24 * 60 * 60);
    }
    return $json;
  }

  function fetch( $url ) {
    return wp_remote_get( $url );
  }

  function verify( $result ) {
    if ( is_wp_error( $result ) ) {
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Nepodařilo se získat obsah z URL adresy.' );
    }

    $code = $result['response']['code'];
    if ( $code != 200 ) {
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Neplatná HTTP reakce ze serveru - ' . $code );
    }

    $body = wp_remote_retrieve_body( $result );
    if ( $body == '' ) {
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Obsah Json je prázdný.' );
    }

    return $body;
  }

  function parse( $body ) {
    $json = json_decode( $body );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Neplatný Json obsah získaný ze serveru - ' . json_last_error() );
    }
    return $json;
  }

  function build( $params ) {
    $base_url = 'https://www.zasilkovna.cz/api/v3/';
    $available_shipping = WC()->shipping->load_shipping_methods();
    $settings = $available_shipping[ "ceske_sluzby_zasilkovna" ]->settings;

    if ( ! empty( $settings['zasilkovna_api-klic'] ) ) {
      $base_url .= $settings['zasilkovna_api-klic'];
      $base_url .= '/branch.json';
      return $base_url;
    } else {
      return null;
    }
  }
}
