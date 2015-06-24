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
    $base_url = 'https://api.ulozenka.cz/v3/transportservices/';
    if ( ! is_null( $params ) ) {
      if ( ! empty( $params['provider'] ) ) {
        $base_url = $base_url . $params['provider'] . '/branches';
      }
      if ( ! empty( $params['country'] ) ) {
        $base_url = $base_url . '?includeInactive=0&destinationOnly=1&partner=0&destinationCountry='. $params['country'];
      }
      return $base_url;
    } else {
      return $base_url . '1/branches';
    }
  }
}

// Je třeba doplnit cachovanou verzi (transients).