<?php
function spustit_Ceske_Sluzby_EET() {
  new Ceske_Sluzby_EET();
}

if ( is_admin() ) {
  $eet_aktivace = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
  if ( $eet_aktivace == "yes" ) {
    add_action( 'load-post.php', 'spustit_Ceske_Sluzby_EET' );
  }
}

class Ceske_Sluzby_EET {

  public function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_box_eet' ) );
    add_filter( 'woocommerce_order_actions', array( $this, 'odeslat_eet_uctenku' ) );
    add_action( 'woocommerce_order_action_ziskat_eet_uctenku', array( $this, 'ceske_sluzby_ziskat_eet_uctenku' ) );
    add_action( 'woocommerce_order_action_smazat_eet_uctenku', array( $this, 'ceske_sluzby_smazat_eet_uctenku' ) );
    add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'ceske_sluzby_zobrazit_eet_uctenku_administrace' ) );
  }

  public function add_meta_box_eet( $post_type ) {
    $post_types = array( 'shop_order' );
    if ( in_array( $post_type, $post_types )) {
      add_meta_box(
        'ceske_sluzby_eet',
        'Elektronická evidence tržeb',
        array( $this, 'zobrazit_meta_box_eet' ),
        $post_type,
        'side',
        'high'
      );
    }
  }

  public function generovat_uuid() {
    // http://php.net/manual/en/function.uniqid.php#94959
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
      mt_rand( 0, 0xffff ),
      mt_rand( 0, 0x0fff ) | 0x4000,
      mt_rand( 0, 0x3fff ) | 0x8000,
      mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
  }

  public function ziskat_poradove_cislo() {
    $ulozene_cislo = get_option( 'ceske_sluzby_eet_poradove_cislo' );
    $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
    if ( empty ( $ulozene_cislo ) || ( ! empty( $prostredi ) && $prostredi == "test" ) ) {
      $poradove_cislo = 1;
    } else {
      $poradove_cislo = $ulozene_cislo + 1;
    }
    return $poradove_cislo;
  }

  public function ziskat_zakladni_data() {
    $dic_popl = get_option( 'wc_ceske_sluzby_eet_dic' );
    if ( empty( $dic_popl ) ) {
      return 'DIČ nebylo vyplněno.';
    }
    if ( preg_match( '/^CZ[0-9]{8,10}$/', $dic_popl, $matches, 0, 0) !== 1 ) {
      return 'DIČ nebylo správně vyplněno.';
    }
    $id_provoz = get_option( 'wc_ceske_sluzby_eet_id_provozovna' );
    if ( empty( $id_provoz ) ) {
      return 'ID provozovny nebylo vyplněno.';
    }
    if ( preg_match( '/^[1-9][0-9]{0,5}$/', $id_provoz, $matches, 0, 0) !== 1 ) {
      return 'ID provozovny nebylo správně vyplněno.';
    }
    $id_pokl = get_option( 'wc_ceske_sluzby_eet_id_pokladna' );
    if ( empty( $id_pokl ) ) {
      return 'ID pokladního zařízení nebylo vyplněno.';
    }
    if ( preg_match('/^[0-9a-zA-Z\.,:;\/#\-_ ]{1,20}$/', $id_pokl, $matches, 0, 0) !== 1 ) {
      return 'ID pokladního zařízení nebylo správně vyplněno.';
    }
    $zakladni_data = array(
      'dic_popl' => $dic_popl,
      'id_provoz' => $id_provoz,
      'id_pokl' => $id_pokl,
    );
    return $zakladni_data;
  }

  public function odeslat_eet_uctenku( $actions ) {
    // https://www.skyverge.com/blog/add-woocommerce-custom-order-actions/
    global $theorder;
    $order = wc_get_order( $theorder );
    if ( is_array( self::ziskat_zakladni_data() ) ) {
      $eet_uctenky = self::ceske_sluzby_zpracovat_data_pro_eet_uctenky( $theorder );
      if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
        foreach ( $eet_uctenky as $item_id => $uctenka ) {
          if ( array_key_exists( 'Odpoved', $uctenka ) ) {
            if ( array_key_exists( 'test', $uctenka['Odpoved'] ) || array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
              $actions['smazat_eet_uctenku'] = 'Smazat testovací EET účtenku';
            } elseif ( array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
              $actions['stornovat_eet_uctenku'] = 'Stornovat EET účtenku';
            }
          }
        }
      } else {
        $actions['ziskat_eet_uctenku'] = 'Získat EET účtenku';
      }
    }
    return $actions;
  }

  public function eet_uctenka_dane( $order ) {
    if ( wc_tax_enabled() ) {
      $tax_rates = WC_Tax::get_rates();
      if ( ! empty( $tax_rates ) && is_array( $tax_rates ) ) {
        $zaklad_celkem = 0;
        $objednavka_dane = $order->get_tax_totals();
        foreach ( $objednavka_dane as $code => $tax ) {
          if ( array_key_exists( $tax->rate_id, $tax_rates ) && array_key_exists( 'rate', $tax_rates[$tax->rate_id] ) ) {
            $sazba = (float)$tax_rates[$tax->rate_id]['rate'];
            if ( $sazba == 21 ) {
              $danova_data['zakl_dan1'] = number_format( (float)( $tax->amount / $sazba * 100 ), 2, '.', '' );
              $danova_data['dan1'] = $tax->amount;
            } elseif ( $sazba == 15 ) {
              $danova_data['zakl_dan2'] = number_format( (float)( $tax->amount / $sazba * 100 ), 2, '.', '' );
              $danova_data['dan2'] = $tax->amount;
            } elseif ( $sazba == 10 ) {
              $danova_data['zakl_dan3'] = number_format( (float)( $tax->amount / $sazba * 100 ), 2, '.', '' );
              $danova_data['dan3'] = $tax->amount;
            }
            $zaklad_celkem = $zaklad_celkem + number_format( (float)( $tax->amount / $sazba * 100 ), 2, '.', '' );
          }
        }
        $zakl_nepodl_dph = $order->get_total() - $order->get_total_tax() - $zaklad_celkem;
        if ( $zakl_nepodl_dph > 0 ) {
          $danova_data['zakl_nepodl_dph'] = number_format( (float)( $zakl_nepodl_dph ), 2, '.', '' );
        }
      }
      return $danova_data;
    }
    return false;
  }

  function ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id ) {
    $parameters = array();
    $order = wc_get_order( $order_id );
    $eet_items = $order->get_items( 'ceske_sluzby_eet' );
    if ( ! empty( $eet_items ) && is_array( $eet_items ) ) {
      foreach ( $eet_items as $item_id => $item ) {
        $response_data = $item['item_meta']['ceske_sluzby_eet_uctenka_response'][0];
        $response_xml = simplexml_load_string( $response_data );
        $chyba_text = $response_xml->children( 'soapenv', true )->Body->children( 'eet', true )->Odpoved->Chyba;
        if ( ! empty( $chyba_text ) ) {
          $parameters[$item_id]['Odpoved']['chyba'] = (string)$chyba_text;
          $chyba = $response_xml->children( 'soapenv', true )->Body->children( 'eet', true )->Odpoved->Chyba->attributes();
          foreach ( $chyba as $key => $value ) {
            if ( $key == "test") {
              $parameters[$item_id]['Odpoved']['test'] = true;
            }
          }
        } else {
          $potvrzeni = $response_xml->children( 'soapenv', true )->Body->children( 'eet', true )->Odpoved->Potvrzeni->attributes();
          if ( ! empty( $potvrzeni ) ) {
            foreach ( $potvrzeni as $key => $value ) {
              if ( $key == "fik") {
                $parameters[$item_id]['Odpoved']['fik'] = (string)$value;
              }
              if ( $key == "test") {
                $parameters[$item_id]['Odpoved']['test'] = true;
              }
            }
          }
        }
        $request_data = $item['item_meta']['ceske_sluzby_eet_uctenka_request'][0];
        $request_xml = simplexml_load_string( $request_data );
        $hlavicka = $request_xml->children( 'SOAP-ENV', true )->Body->children( 'ns1', true )->Trzba->Hlavicka->attributes();
        foreach ( $hlavicka as $key => $value ) {
          $parameters[$item_id]['Hlavicka'][$key] = (string)$value;
        }
        $data = $request_xml->children( 'SOAP-ENV', true )->Body->children( 'ns1', true )->Trzba->Data->attributes();
        foreach ( $data as $key => $value ) {
          $parameters[$item_id]['Data'][$key] = (string)$value;
        }
        $bkp = $request_xml->children( 'SOAP-ENV', true )->Body->children( 'ns1', true )->Trzba->KontrolniKody->bkp;
        $parameters[$item_id]['KontrolniKody']['bkp'] = (string)$bkp;
      }
      return $parameters;
    }
  }

  public function ceske_sluzby_zobrazit_eet_uctenku( $order_id, $always = true, $before = '', $after = '' ) {
    $eet_uctenky = self::ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id );
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      if ( ! empty( $before ) ) {
        echo $before;
      }
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( ! empty( $uctenka ) && is_array( $uctenka ) ) {
          if ( array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
            if ( $always ) {
              if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                echo '<p><strong>Testovací elektronickou účtenku se nepodařilo vytvořit</strong></p>';
              } else {
                echo '<p><strong>Elektronickou účtenku se nepodařilo vytvořit</strong></p>>';
              }
              echo '<p><strong>Chyba:</strong> ' . $uctenka['Odpoved']['chyba'] . '</p>';
              echo '<p><strong>Odeslaná data:</strong> (debug)<br>';
              foreach ( $uctenka['Data'] as $key => $value ) {
                echo '<strong>' . $key . '</strong>: ' . $value . '<br>';
              }
              echo '</p>';
            }
          } else {
            $date = date_create( $uctenka['Data']['dat_trzby'], timezone_open('Europe/Prague') );
            if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
              echo '<p><strong>Testovací elektronická účtenka (EET)</strong>:<br>';
            } else {
              echo '<p><strong>Elektronická účtenka (EET)</strong>:<br>';
            }
            echo '<strong>DIČ</strong>: ' . $uctenka['Data']['dic_popl'] . ' - ';
            echo '<strong>Číslo účtenky</strong>: ' . $uctenka['Data']['porad_cis'] . '<br>';
            echo '<strong>Provozovna</strong>: ' . $uctenka['Data']['id_provoz'] . ' - ';
            echo '<strong>Pokladna</strong>: ' . $uctenka['Data']['id_pokl'] . '<br>';
            echo '<strong>Datum</strong>: ' . $date->format( 'j.n.Y' ) . ' - ';
            echo '<strong>Čas</strong>: ' . $date->format( 'G:i:s' ) . '<br>';
            echo '<strong>Celková částka</strong>: ' . wc_price( $uctenka['Data']['celk_trzba'] ) . ' - ';
            echo '<strong>Režim tržby</strong>: běžný<br>';
            echo '<strong>FIK</strong>: ' . $uctenka['Odpoved']['fik'] . '<br>';
            echo '<strong>BKP</strong>: ' . $uctenka['KontrolniKody']['bkp'] . '<br>';
            echo '</p>';
          }
        }
      }
      if ( ! empty( $after ) ) {
        echo $after;
      }
    }
  }

  public function ceske_sluzby_zobrazit_eet_uctenku_administrace( $order_id ) {
    self::ceske_sluzby_zobrazit_eet_uctenku( $order_id, true, '<tr><td colspan=7 class="ceske_sluzby_eet_items">', '</td></tr>' );
  }

  function ceske_sluzby_ziskat_eet_uctenku( $order ) {
    // http://www.etrzby.cz/assets/cs/prilohy/EETServiceSOAP.wsdl
    $wsdl = dirname( dirname( __FILE__ ) ) . '/src/eet/EETServiceSOAP.wsdl';
    $heslo = get_option( 'wc_ceske_sluzby_eet_heslo' );
    $dic_popl = get_option( 'wc_ceske_sluzby_eet_dic' );
    $certifikat = get_option( 'wc_ceske_sluzby_eet_certifikat' );
    $local_cert = "";
    if ( $dic_popl = 'CZ1212121218' ) {
      // http://www.etrzby.cz/assets/cs/prilohy/EET_CA1_Playground_v1.zip
      $local_cert = dirname( dirname( __FILE__ ) ) . '/src/eet/EET_CA1_Playground-CZ1212121218.p12';
    } elseif ( ! empty( $certifikat ) ) {
      $local_cert = get_attached_file( $certifikat );
    }
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityDSig.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityDSig.php' );
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityKey.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityKey.php' );
    require_once( 'eet-soap-client.php' );
    $certs = array();   
    if ( openssl_pkcs12_read( file_get_contents( $local_cert ), $certs, $heslo ) ) {
      $objKey = new XMLSecurityKey( XMLSecurityKey::RSA_SHA256, array( 'type' => 'private' ) );
      $objKey->loadKey( $certs['pkey'] );
    }
    // https://core.trac.wordpress.org/ticket/20973
    $date = date_create( 'now', timezone_open( 'Europe/Prague' ) );
    $dat_trzby = $date->format( 'c' );
    $celk_trzba = number_format( (float)ceil( $order->get_total() ), 2, '.', '' );
    $zakladni_data = self::ziskat_zakladni_data();
    if ( is_array( $zakladni_data ) && ! empty( $local_cert ) ) {
      $pkp_data = array_merge( $zakladni_data, array(
        'porad_cis' => self::ziskat_poradove_cislo(),
        'dat_trzby' => $dat_trzby,
        'celk_trzba' => $celk_trzba,
      ) );
      $pkp = $objKey->signData( implode( '|', $pkp_data ) );
      $bkp = wordwrap( substr( sha1( $pkp, false ), 0, 40 ), 8, '-', true );
      $kompletni_data = $pkp_data;
      $danova_data = self::eet_uctenka_dane( $order );
      if ( $danova_data && ! empty( $danova_data ) && is_array( $danova_data ) ) {
        $kompletni_data = array_merge( $pkp_data, $danova_data );
      }
      $kompletni_data['rezim'] = '0';

      $parameters = array(
        'Hlavicka' => array(
          'uuid_zpravy' => self::generovat_uuid(),
          'dat_odesl' => $dat_trzby,
          'prvni_zaslani' => true,
          'overeni' => false,
        ),
        'Data' => $kompletni_data,
        'KontrolniKody' => array(
          'pkp' => array(
            '_' => $pkp,
            'digest' => 'SHA256',
            'cipher' => 'RSA2048',
            'encoding' => 'base64',
          ),
          'bkp' => array(
            '_' => $bkp,
            'digest' => 'SHA1',
            'encoding' => 'base16',
          ),
        )
      );

      $soapClient = new Ceske_Sluzby_EET_SoapClient( $wsdl, array( 'trace' => 1 ) );
      $values = $soapClient->OdeslaniTrzby( $parameters );
      $item_id = wc_add_order_item( $order->id, array( 'order_item_name' => 'EET (' . self::ziskat_poradove_cislo() . ')', 'order_item_type' => 'ceske_sluzby_eet' ) );
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_poradove_cislo', self::ziskat_poradove_cislo() );
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_request', $soapClient->__getLastRequest() );
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_response', $soapClient->__getLastResponse() );

      $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
      if ( ! empty( $prostredi ) ) {
        if ( $prostredi == "test" ) {
          $message = 'Testovací elektronická účtenka byla úspěšně odeslána.';
        } else {
          $message = 'Elekronická účtenka č. ' . self::ziskat_poradove_cislo() . ' byla úspěšně odeslána.';
          update_option( 'ceske_sluzby_eet_poradove_cislo', self::ziskat_poradove_cislo() );
        }
      }
      $order->add_order_note( $message );
    }
  }

  function ceske_sluzby_smazat_eet_uctenku( $order ) {
    $eet_uctenky = self::ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order->id );
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( array_key_exists( 'Odpoved', $uctenka ) ) {
          if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
            wc_delete_order_item( $item_id );
            $message = 'Testovací elektronická účtenka byla úspěšně smazána.';
            $order->add_order_note( $message );
          }
        }
      }
    }
  }

  public function zobrazit_meta_box_eet( $post ) {
    $item_id = $post->ID;
    $order = wc_get_order( $post->ID );
    $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
    if ( ! empty( $prostredi ) ) {
      if ( $prostredi == "test" ) {
        echo '<p style="color:red;">Používáte testovací prostředí, účtenky jsou sice odesílány, ale nebudou oficiálně evidovány!</p>';
      }
    }

    $eet_uctenky = self::ceske_sluzby_zpracovat_data_pro_eet_uctenky( $item_id );
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( array_key_exists( 'Odpoved', $uctenka ) && array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
          if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
            echo '<p>Testovací elektronická účtenka pro tuto objednávku <strong>byla úspěšně vytvořena</strong> a můžete ji následně smazat.</p>';
          } else {
            echo '<p>Elektronická účtenka pro tuto objednávku <strong>byla úspěšně vytvořena</strong>, v případě potřeby ji můžete stornovat.</p>';
          }
        } elseif ( array_key_exists( 'Odpoved', $uctenka ) && array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
          if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
            echo '<p>Testovací elektronickou účtenku pro tuto objednávku se <strong>nepodařilo vytvořit</strong>. Můžete ji smazat a zkusit to znovu.</p>';
          } else {
            echo '<p>Elektronickou účtenku pro tuto objednávku se <strong>nepodařilo vytvořit</strong>. Můžete ji smazat a zkusit to znovu.</p>';
          }
          echo '<p><strong>Chyba:</strong> ' . $uctenka['Odpoved']['chyba'] . '</p>';
        }
      }
    } else {
      $zakladni_data = self::ziskat_zakladni_data();
      if ( ! is_array( $zakladni_data ) ) {
        echo '<p style="color:red;">' . $zakladni_data . '</p>';
      } else {
        echo '<p>Kontrolní informace pro odeslání účtenky:<br>';
        $date = date_create( 'now', timezone_open( 'Europe/Prague' ) );
        $dat_trzby = $date->format( 'c' );
        $celk_trzba = number_format( (float)ceil( $order->get_total() ), 2, '.', '' );
        $pkp_data = array(
          'porad_cis' => self::ziskat_poradove_cislo(),
          'dat_trzby' => $dat_trzby,
          'celk_trzba' => $celk_trzba,
        );
        $kompletni_data = $pkp_data;
        $danova_data = self::eet_uctenka_dane( $order );
        if ( $danova_data && ! empty( $danova_data ) && is_array( $danova_data ) ) {
          $kompletni_data = array_merge( $pkp_data, $danova_data );
        }
        $kompletni_data['rezim'] = 'běžný';
        foreach ( $kompletni_data as $key => $value ) {
          echo "<strong>" . $key . ":</strong> " . $value . "<br>";
        }
        echo '</p>';
      }
    }
  }
}