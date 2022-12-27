<?php
// http://pressing-matters.io/building-an-object-oriented-wordpress-plugin-xkcd-shortcode-part-5/
class Ceske_Sluzby_Json_Loader {
  function load( $params = null ) {
    $url = $this->build( $params );
    $result = $this->fetch( $url );
    $body = $this->verify( $result );
    return $this->parse( $body );
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
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Json je prázdný.' );
    }

    return $body;
  }

  function parse( $body ) {
    $json = json_decode( $body );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
      throw new Exception( 'Ceske_Sluzby_Json_Loader Failed: Neplatný Json získaný ze serveru - ' . json_last_error() );
    }
    return $json;
  }

  function sortName( $array ) {
    $arraySorted = array();
    if ( is_array( $array ) ) {
      foreach ( $array as $item ) {
        $arraySorted[] = $item->name;
      }
      // https://www.itnetwork.cz/php/ostatni/metoda-pro-razeni-pole-podle-nejen-ceske-abecedy
      require_once dirname( dirname( __FILE__ ) ) . '/src/SortUTF.php';
      $arraySorted = SortUTF::sort( $arraySorted, true );
    }
    return $arraySorted;
  }

  function build( $params ) {
    $base_url = 'https://api.ulozenka.cz/v3/transportservices/';
    $available_shipping = WC()->shipping->load_shipping_methods();
    $settings = $available_shipping[ "ceske_sluzby_ulozenka" ]->settings;

    if ( ! is_null( $params ) ) {
      if ( ! empty( $params['provider'] ) ) {
        $base_url = $base_url . $params['provider'] . '/branches';
      }
      if ( ! empty( $params['country'] ) ) {
        $base_url .= '?includeInactive=0&destinationOnly=1&destinationCountry=' . $params['country'];
      }
      if ( ! empty( $settings['ulozenka_id-obchodu'] ) ) {
        $base_url .= '&shopId=' . $settings['ulozenka_id-obchodu'];
      }
      return $base_url;
    } else {
      return $base_url . '1/branches';
    }
  }
}
// Je třeba doplnit cachovanou verzi (transients).