<?php
class Ceske_Sluzby_EET_SoapClient extends SoapClient { 
  public function __doRequest( $request, $location, $action, $version, $one_way = 0 ) {
    $heslo = "eet";
    // http://www.etrzby.cz/assets/cs/prilohy/EET_CA1_Playground_v1.zip
    $local_cert = dirname( dirname( __FILE__ ) ) . "/src/eet/EET_CA1_Playground-CZ1212121218.p12";
    $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
    if ( ! empty( $prostredi ) ) {
      if ( $prostredi != "test" ) {
        $certifikat = get_option( 'wc_ceske_sluzby_eet_certifikat' );
        $ulozene_heslo = get_option( 'wc_ceske_sluzby_eet_heslo' );
        if ( ! empty( $certifikat ) && ! empty( $ulozene_heslo ) ) {
          $local_cert = get_attached_file( $certifikat );
          $heslo = $ulozene_heslo;
        }
      }
    }
    // https://github.com/robrichards/wse-php/blob/1.2/src/WSSESoap.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/wse-php/WSSESoap.php' );
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityDSig.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityDSig.php' );
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityKey.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityKey.php' );
    $dom = new DOMDocument('1.0');
    $dom->loadXML( $request );
    $wsSoap = new WSSESoap( $dom );
    $wsSoap->addTimestamp();
    $objKey = new XMLSecurityKey( XMLSecurityKey::RSA_SHA256, array( 'type' => 'private' ) );
    $certs = array();   
    if ( openssl_pkcs12_read( file_get_contents( $local_cert ), $certs, $heslo ) ) {
      $objKey->loadKey( $certs['pkey'] );
      $wsSoap->signSoapDoc( $objKey, array( 'algorithm' => XMLSecurityDSig::SHA256 ) );
      $token = $wsSoap->addBinaryToken( $certs['cert'] );
      $wsSoap->attachTokentoSig( $token );
      $request = $wsSoap->saveXML();
    }
    $result = parent::__doRequest( $request, $location, $action, $version, $one_way ); 
    return $result; 
  } 
}