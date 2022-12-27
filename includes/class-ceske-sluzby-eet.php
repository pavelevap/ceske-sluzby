<?php
function spustit_Ceske_Sluzby_EET() {
  $screen = get_current_screen();
  if ( $screen->post_type == 'shop_order' ) {
    new Ceske_Sluzby_EET();
  }
}

if ( is_admin() ) {
  $eet_aktivace = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
  if ( $eet_aktivace == "yes" ) {
    add_action( 'load-post.php', 'spustit_Ceske_Sluzby_EET' );
    add_action( 'load-edit.php', 'spustit_Ceske_Sluzby_EET' );
  }
}

class Ceske_Sluzby_EET {

  public function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_box_eet' ) );
    add_filter( 'woocommerce_order_actions', array( $this, 'odeslat_eet_uctenku' ) );
    add_action( 'woocommerce_order_action_ziskat_eet_uctenku', array( $this, 'ceske_sluzby_ziskat_eet_uctenku' ) );
    add_action( 'woocommerce_order_action_smazat_eet_uctenky', array( $this, 'ceske_sluzby_smazat_eet_uctenky' ) );
    add_action( 'woocommerce_admin_order_items_after_shipping', array( $this, 'ceske_sluzby_zobrazit_eet_uctenku_administrace' ) );
    add_action( 'manage_shop_order_posts_custom_column' , array( $this, 'zobrazit_eet_uctenky_administrace_prehled' ), 10, 2 );
    if ( version_compare( WC_VERSION, '3.0', '>' ) ) {
      // https://github.com/woocommerce/woocommerce/issues/14961
      add_filter( 'woocommerce_order_type_to_group', array( $this, 'ceske_sluzby_eet_group' ) );
      add_filter( 'woocommerce_get_order_item_classname', array( $this, 'ceske_sluzby_eet_classname' ), 10, 2 );
      add_filter( 'woocommerce_data_stores', array( $this, 'ceske_sluzby_eet_stores' ) );
    } 
  }

  public function ceske_sluzby_eet_group( $types ) {
    $types['ceske_sluzby_eet'] = 'ceske_sluzby_eet_lines';
    return $types;
  }

  public function ceske_sluzby_eet_classname( $classname, $item_type ) {
    if ( $item_type == 'ceske_sluzby_eet' ) {
      $classname = 'WC_Order_Item_Eet';
    }
    return $classname;
  }

  public function ceske_sluzby_eet_stores( $stores ) {
    $stores['order-item-ceske_sluzby_eet'] = 'WC_Order_Item_Eet_Data_Store';
    return $stores;
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
  
  public function zobrazit_eet_uctenky_administrace_prehled( $column, $post_id ) {
    if ( $column == 'order_notes' ) {
      $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $post_id );
      $celkem = count( $eet_uctenky );
      $pocet = 0;
      $test = '';
      if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
        foreach ( $eet_uctenky as $item_id => $uctenka ) {
          $pocet = $pocet + 1;
          if ( $pocet == $celkem ) {
            if ( array_key_exists( 'Odpoved', $uctenka ) ) {
              if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                $test = '(T)';
              }
              if ( array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
                if ( $uctenka['Data']['celk_trzba'] > 0 ) {
                  echo '<span class="eet" style="color: green; font-weight: bold;">EET' . $test . '</span>';
                } else {
                  echo '<span class="eet" style="color: orange; font-weight: bold;">EET' . $test . '</span>';
                }
              }
              if ( array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
                echo '<span class="eet" style="color: red; font-weight: bold;">EET' . $test . '</span>';
              }
            }
          }
        }
      }
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
    $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
    if ( ! empty( $prostredi ) ) {
      if ( $prostredi == "test" ) {
        $zakladni_data = array(
          'dic_popl' => 'CZ1212121218',
          'id_provoz' => '273',
          'id_pokl' => '1',
        );
        return $zakladni_data;
      }
    }
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

  public function kontrolni_odkaz( $uctenka ) {
    // https://github.com/jakubboucek/eet-check
    if ( array_key_exists( 'Odpoved', $uctenka ) && array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
      $url = 'https://eet-check.appspot.com/check?';
      $url .= 'dic=' . $uctenka['Data']['dic_popl'];
      $date = date_create( $uctenka['Data']['dat_trzby'], timezone_open('Europe/Prague') );
      $timezone_offset = timezone_offset_get( timezone_open( "Europe/Prague" ), $date );
      $date->modify( '-' . $timezone_offset . ' seconds' );
      $url .= '&date=' . $date->format( 'Y-m-d\TH:i:s\Z' );
      $url .= '&price=' . $uctenka['Data']['celk_trzba'];
      $url .= '&bkp=' . $uctenka['KontrolniKody']['bkp'];
      $url .= '&fik=' . $uctenka['Odpoved']['fik'];
    }
    return $url;
  }

  public function odeslat_eet_uctenku( $actions ) {
    // https://www.skyverge.com/blog/add-woocommerce-custom-order-actions/
    global $theorder;
    $order = wc_get_order( $theorder );
    $eet_podminka = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_podminka', 'eet_podminka', 'ceske_sluzby_eet_podminka' );
    if ( ! empty( $eet_podminka ) ) {
      if ( is_array( $this->ziskat_zakladni_data() ) ) {
        $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $theorder );
        if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
          foreach ( $eet_uctenky as $item_id => $uctenka ) {
            if ( array_key_exists( 'Odpoved', $uctenka ) ) {
              if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                $actions['smazat_eet_uctenky'] = 'EET: Smazat testovací účtenky';
              }
              if ( ! array_key_exists( 'test', $uctenka['Odpoved'] ) && array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
                $actions['smazat_eet_uctenky'] = 'EET: Smazat chybné pokusy';
              }
            }
          }
        }
        $actions['ziskat_eet_uctenku'] = 'EET: Získat účtenku';
      }
    }
    return $actions;
  }

  public function eet_prehled_danovych_sazeb() {
    $danove_sazby = array(
      '21' => 'dan1',
      '15' => 'dan2',
      '10' => 'dan3'
    );
    return $danove_sazby;
  }

  public function eet_uctenka_dane( $order, $dane_uctenky ) {
    $danova_data = array();
    if ( wc_tax_enabled() ) {
      $zaklad_celkem = 0;
      $decimals = get_option( 'woocommerce_price_num_decimals' );
      $objednavka_dane = $order->get_tax_totals();
      $danove_sazby = $this->eet_prehled_danovych_sazeb();
      if ( ! empty( $objednavka_dane ) && is_array( $objednavka_dane ) ) {
        foreach ( $objednavka_dane as $code => $tax ) {
          if ( array_key_exists( 'sazba', $tax ) && ! empty( $tax->sazba ) ) {
            $sazba = $tax->sazba;
            $zaklad_dane = number_format( (float)( round( $tax->amount / $sazba * 100, $decimals ) ), 2, '.', '' );
            $dan = number_format( (float)( round( $tax->amount, $decimals ) ), 2, '.', '' );
            if ( array_key_exists( (string)$sazba, $danove_sazby ) ) {
              $eet_sazba = $danove_sazby[$sazba];
              $danova_data['zakl_' . $eet_sazba] = $zaklad_dane;
              $danova_data[$eet_sazba] = $dan;
            }
            $zaklad_celkem = $zaklad_celkem + $zaklad_dane;
          }
        }
        $zakl_nepodl_dph = $order->get_total() - $order->get_total_tax() - abs( $zaklad_celkem );
        if ( round( $zakl_nepodl_dph, $decimals ) > 0 ) {
          $danova_data['zakl_nepodl_dph'] = number_format( (float)( $zakl_nepodl_dph ), 2, '.', '' );
        }
        if ( ! empty( $dane_uctenky ) && ! empty( $danova_data ) ) {
          if ( $danova_data != $dane_uctenky ) {
            foreach ( $danova_data as $id => $hodnota ) {
              $danova_data[$id] = number_format( (float)( round( $danova_data[$id] - $dane_uctenky[$id], $decimals ) ), 2, '.', '' );
              if ( $danova_data[$id] == 0 ) {
                unset( $danova_data[$id] );
              }
            }
          } else {
            foreach ( $danova_data as $id => $hodnota ) {
              $danova_data[$id] = number_format( (float)( round( -$danova_data[$id], $decimals ) ), 2, '.', '' );
              if ( $danova_data[$id] == 0 ) {
                unset( $danova_data[$id] );
              }
            }
          }
        }
      }
    }
    return $danova_data;
  }

  function ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id ) {
    $parameters = array();
    $order = wc_get_order( $order_id );
    $eet_items = $order->get_items( 'ceske_sluzby_eet' );
    if ( ! empty( $eet_items ) && is_array( $eet_items ) ) {
      foreach ( $eet_items as $item_id => $item ) {
        $response_data = wc_get_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_response' );
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
        $request_data = wc_get_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_request' );
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

  public function ceske_sluzby_zobrazit_eet_uctenku( $order_id, $always = true, $before = '', $after = '', $plain = false ) {
    $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id );
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      if ( ! empty( $before ) ) {
        echo $before;
      }
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( ! empty( $uctenka ) && is_array( $uctenka ) ) {
          if ( array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
            if ( $always ) {
              if ( $plain ) {
                if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                  echo "Testovací elektronickou účtenku se nepodařilo odeslat\n";
                } else {
                  echo "Elektronickou účtenku se nepodařilo odeslat\n";
                }
                echo "Chyba: " . $uctenka['Odpoved']['chyba'] . "\n";
                echo "Odeslaná data: (debug)\n";
                foreach ( $uctenka['Data'] as $key => $value ) {
                  echo $key . ": " . $value . "\n";
                }
                echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
              } else {
                if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                  echo '<p><strong>Testovací elektronickou účtenku se nepodařilo odeslat</strong></p>';
                } else {
                  echo '<p><strong>Elektronickou účtenku se nepodařilo odeslat</strong></p>';
                }
                echo '<p><strong>Chyba:</strong> ' . $uctenka['Odpoved']['chyba'] . '</p>';
                echo '<p><strong>Odeslaná data:</strong> (debug)<br>';
                foreach ( $uctenka['Data'] as $key => $value ) {
                  echo '<strong>' . $key . '</strong>: ' . $value . '<br>';
                }
                echo '</p>';
              }
            }
          } else {
            if ( $plain ) {
              $date = date_create( $uctenka['Data']['dat_trzby'], timezone_open('Europe/Prague') );
              $decimals = get_option( 'woocommerce_price_num_decimals' );
              if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                echo "Testovací elektronická účtenka (EET):\n";
              } else {
                echo "Elektronická účtenka (EET):\n";
              }
              echo "DIČ: " . $uctenka['Data']['dic_popl'] . " - ";
              echo "Číslo účtenky: " . $uctenka['Data']['porad_cis'] . "\n";
              if ( ! array_key_exists( 'test', $uctenka['Odpoved'] ) && $always ) {
                echo "Kontrolní odkaz: " . $this->kontrolni_odkaz( $uctenka ) . "\n";
              }
              echo "Provozovna: " . $uctenka['Data']['id_provoz'] . " - ";
              echo "Pokladna: " . $uctenka['Data']['id_pokl'] . "\n";
              echo "Datum: " . $date->format( 'j.n.Y' ) . " - ";
              echo "Čas: " . $date->format( 'G:i:s' ) . "\n";
              echo "Celková částka: " . number_format( round( $uctenka['Data']['celk_trzba'], $decimals ), 2, ',', '' ) . " Kč - ";
              echo "Režim tržby: běžný\n";
              if ( array_key_exists( 'zakl_dan1', $uctenka['Data'] ) ) {
                echo "DPH 21 %: " . number_format( round( $uctenka['Data']['zakl_dan1'], $decimals ), 2, ',', '' ) . " Kč (základ daně) + ";
                echo number_format( round( $uctenka['Data']['dan1'], $decimals ), 2, ',', '' ) . " Kč (daň) = ";
                echo number_format( round( $uctenka['Data']['zakl_dan1'] + $uctenka['Data']['dan1'], $decimals ), 2, ',', '' ) . " Kč (celkem)\n";
              }
              if ( array_key_exists( 'zakl_dan2', $uctenka['Data'] ) ) {
                echo "DPH 15 %: " . number_format( round( $uctenka['Data']['zakl_dan2'], $decimals ), 2, ',', '' ) . " Kč (základ daně) + ";
                echo number_format( round( $uctenka['Data']['dan2'], $decimals ), 2, ',', '' ) . " Kč (daň) = ";
                echo number_format( round( $uctenka['Data']['zakl_dan2'] + $uctenka['Data']['dan2'], $decimals ), 2, ',', '' ) . " Kč (celkem)\n";
              }
              if ( array_key_exists( 'zakl_dan3', $uctenka['Data'] ) ) {
                echo "DPH 10 %: " . number_format( round( $uctenka['Data']['zakl_dan3'], $decimals ), 2, ',', '' ) . " Kč (základ daně) + ";
                echo number_format( round( $uctenka['Data']['dan3'], $decimals ), 2, ',', '' ) . " Kč (daň) = ";
                echo number_format( round( $uctenka['Data']['zakl_dan3'] + $uctenka['Data']['dan3'], $decimals ), 2, ',', '' ) . " Kč (celkem)\n";
              }
              if ( array_key_exists( 'zakl_nepodl_dph', $uctenka['Data'] ) ) {
                echo "Základ nepodléhající DPH: " . number_format( round( $uctenka['Data']['zakl_nepodl_dph'], $decimals ), 2, ',', '' ) . " Kč\n";
              }
              echo "FIK: " . $uctenka['Odpoved']['fik'] . "\n";
              echo "BKP: " . $uctenka['KontrolniKody']['bkp'] . "\n";
              echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
            } else {
              $date = date_create( $uctenka['Data']['dat_trzby'], timezone_open('Europe/Prague') );
              if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
                echo '<p><strong>Testovací elektronická účtenka (EET)</strong>:<br>';
              } else {
                echo '<p><strong>Elektronická účtenka (EET)</strong>:<br>';
              }
              echo '<strong>DIČ</strong>: ' . $uctenka['Data']['dic_popl'] . ' - ';
              echo '<strong>Číslo účtenky</strong>: ' . $uctenka['Data']['porad_cis'];
              if ( ! array_key_exists( 'test', $uctenka['Odpoved'] ) && $always ) {
                echo ' (kontrolní <a target="_blank" href="' . $this->kontrolni_odkaz( $uctenka ) . '">odkaz</a>)<br>';
              } else {
                echo '<br>';
              }
              echo '<strong>Provozovna</strong>: ' . $uctenka['Data']['id_provoz'] . ' - ';
              echo '<strong>Pokladna</strong>: ' . $uctenka['Data']['id_pokl'] . '<br>';
              echo '<strong>Datum</strong>: ' . $date->format( 'j.n.Y' ) . ' - ';
              echo '<strong>Čas</strong>: ' . $date->format( 'G:i:s' ) . '<br>';
              echo '<strong>Celková částka</strong>: ' . wc_price( $uctenka['Data']['celk_trzba'] ) . ' - ';
              echo '<strong>Režim tržby</strong>: běžný<br>';
              if ( array_key_exists( 'zakl_dan1', $uctenka['Data'] ) ) {
                echo '<strong>DPH 21 %</strong>: ' . wc_price( $uctenka['Data']['zakl_dan1'] ) . ' (základ daně) + ';
                echo wc_price( $uctenka['Data']['dan1'] ) . ' (daň) = ';
                echo '<strong>' . wc_price( $uctenka['Data']['zakl_dan1'] + $uctenka['Data']['dan1'] ) . '</strong> (celkem)<br>';
              }
              if ( array_key_exists( 'zakl_dan2', $uctenka['Data'] ) ) {
                echo '<strong>DPH 15 %</strong>: ' . wc_price( $uctenka['Data']['zakl_dan2'] ) . ' (základ daně) + ';
                echo wc_price( $uctenka['Data']['dan2'] ) . ' (daň) = ';
                echo '<strong>' . wc_price( $uctenka['Data']['zakl_dan2'] + $uctenka['Data']['dan2'] ) . '</strong> (celkem)<br>';
              }
              if ( array_key_exists( 'zakl_dan3', $uctenka['Data'] ) ) {
                echo '<strong>DPH 10 %</strong>: ' . wc_price( $uctenka['Data']['zakl_dan3'] ) . ' (základ daně) + ';
                echo wc_price( $uctenka['Data']['dan3'] ) . ' (daň) = ';
                echo '<strong>' . wc_price( $uctenka['Data']['zakl_dan3'] + $uctenka['Data']['dan3'] ) . '</strong> (celkem)<br>';
              }
              if ( array_key_exists( 'zakl_nepodl_dph', $uctenka['Data'] ) ) {
                echo '<strong>Základ nepodléhající DPH</strong>: ' . wc_price( $uctenka['Data']['zakl_nepodl_dph'] ) . '<br>';
              }
              echo '<strong>FIK</strong>: ' . $uctenka['Odpoved']['fik'] . '<br>';
              echo '<strong>BKP</strong>: ' . $uctenka['KontrolniKody']['bkp'] . '<br>';
              echo '</p>';
            }
          }
        }
      }
      if ( ! empty( $after ) ) {
        echo $after;
      }
    }
  }

  public function ceske_sluzby_zobrazit_eet_uctenku_administrace( $order_id ) {
    $this->ceske_sluzby_zobrazit_eet_uctenku( $order_id, true, '<tr><td colspan=7 class="ceske_sluzby_eet_items">', '</td></tr>' );
  }

  function ceske_sluzby_ziskat_eet_uctenku( $order ) {
    $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
    // http://www.etrzby.cz/assets/cs/prilohy/EETServiceSOAP.wsdl
    $wsdl = dirname( dirname( __FILE__ ) ) . '/src/eet/EETServiceSOAP.wsdl';
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
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityDSig.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityDSig.php' );
    // https://github.com/robrichards/xmlseclibs/blob/1.4/src/XMLSecurityKey.php
    require_once( dirname( dirname( __FILE__ ) ) . '/src/eet/xmlseclibs/XMLSecurityKey.php' );
    require_once( 'eet-soap-client.php' );
    $certs = array();   
    if ( openssl_pkcs12_read( file_get_contents( $local_cert ), $certs, $heslo ) ) {
      $objKey = new XMLSecurityKey( XMLSecurityKey::RSA_SHA256, array( 'type' => 'private' ) );
      $objKey->loadKey( $certs['pkey'] );
    } else {
      $message = 'Nepodařilo se načíst certifikát, patrně je špatně zadané heslo.';
      $order->add_order_note( $message );
      return;
    }
    // https://core.trac.wordpress.org/ticket/20973
    $date = date_create( 'now', timezone_open( 'Europe/Prague' ) );
    $dat_trzby = $date->format( 'c' );
    $celk_trzba = $this->ziskat_odeslanou_trzbu( $order );
    $zakladni_data = $this->ziskat_zakladni_data();
    if ( is_array( $zakladni_data ) && ! empty( $local_cert ) ) {
      $pkp_data = array_merge( $zakladni_data, array(
        'porad_cis' => $this->ziskat_poradove_cislo(),
        'dat_trzby' => $dat_trzby,
        'celk_trzba' => $celk_trzba,
      ) );
      $pkp = $objKey->signData( implode( '|', $pkp_data ) );
      $bkp = wordwrap( substr( sha1( $pkp, false ), 0, 40 ), 8, '-', true );
      $kompletni_data = $pkp_data;

      $danova_data = $this->ziskat_odeslane_danove_informace( $order );
      if ( $danova_data && ! empty( $danova_data ) && is_array( $danova_data ) ) {
        $kompletni_data = array_merge( $pkp_data, $danova_data );
      }
      $kompletni_data['rezim'] = '0';

      $parameters = array(
        'Hlavicka' => array(
          'uuid_zpravy' => $this->generovat_uuid(),
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

      $location = "https://pg.eet.cz:443/eet/services/EETServiceSOAP/v3";
      $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
      if ( ! empty( $prostredi ) ) {
        if ( $prostredi != "test" ) {
          $location = "https://prod.eet.cz:443/eet/services/EETServiceSOAP/v3";
        }
      }
      $soapClient = new Ceske_Sluzby_EET_SoapClient( $wsdl, array( 'trace' => 1, 'location' => $location ) );
      $values = $soapClient->OdeslaniTrzby( $parameters );
      $item_id = wc_add_order_item( $order_id, array( 'order_item_name' => 'EET (' . $this->ziskat_poradove_cislo() . ')', 'order_item_type' => 'ceske_sluzby_eet' ) );
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_request', $soapClient->__getLastRequest() );
      wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_response', $soapClient->__getLastResponse() );

      if ( ! empty( $prostredi ) ) {
        if ( $prostredi == "test" ) {
          $message = 'Testovací elektronická účtenka byla úspěšně odeslána.';
        } else {
          $message = 'Elekronická účtenka č. ' . $this->ziskat_poradove_cislo() . ' byla úspěšně odeslána.';
          wc_add_order_item_meta( $item_id, 'ceske_sluzby_eet_uctenka_poradove_cislo', $this->ziskat_poradove_cislo() );
          update_option( 'ceske_sluzby_eet_poradove_cislo', $this->ziskat_poradove_cislo() );
        }
      }
      $order->add_order_note( $message );
    }
  }

  public function ziskat_odeslane_danove_informace( $order ) {
    $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
    $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id );
    $dane = array();
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( array_key_exists( 'Odpoved', $uctenka ) && array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
          $danove_sazby = $this->eet_prehled_danovych_sazeb();
          foreach ( $danove_sazby as $sazba => $eet_sazba ) {
            if ( array_key_exists( 'zakl_' . $eet_sazba, $uctenka['Data'] ) ) {
              if ( array_key_exists( 'zakl_' . $eet_sazba, $dane ) ) {
                $dane['zakl_' . $eet_sazba] += $uctenka['Data']['zakl_' . $eet_sazba];
              } else {
                $dane['zakl_' . $eet_sazba] = $uctenka['Data']['zakl_' . $eet_sazba];
              }
            }
            if ( array_key_exists( $eet_sazba, $uctenka['Data'] ) ) {
              if ( array_key_exists( $eet_sazba, $dane ) ) {
                $dane[$eet_sazba] += $uctenka['Data'][$eet_sazba];
              } else {
                $dane[$eet_sazba] = $uctenka['Data'][$eet_sazba];
              }
            }
          }
          if ( array_key_exists( 'zakl_nepodl_dph', $uctenka['Data'] ) ) {
            if ( array_key_exists( 'zakl_nepodl_dph', $dane ) ) {
              $dane['zakl_nepodl_dph'] += $uctenka['Data']['zakl_nepodl_dph'];
            } else {
              $dane['zakl_nepodl_dph'] = $uctenka['Data']['zakl_nepodl_dph'];
            }
          }
        }
      }
    }
    $danova_data = $this->eet_uctenka_dane( $order, $dane );     
    return $danova_data;
  }

  public function ziskat_odeslanou_trzbu( $order ) {
    $order_id = is_callable( array( $order, 'get_id' ) ) ? $order->get_id() : $order->id;
    $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id );
    $celk_trzba = 0;
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( array_key_exists( 'Odpoved', $uctenka ) ) {
          if ( array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
            $celk_trzba += (float)$uctenka['Data']['celk_trzba'];
          }
        }
      }
    }
    $decimals = get_option( 'woocommerce_price_num_decimals' );
    $aktualni_trzba = number_format( (float)round( $order->get_total(), $decimals ), 2, '.', '' );
    if ( ! empty( $celk_trzba ) ) {
      $aktualni_trzba = $aktualni_trzba - $celk_trzba;
      if ( $celk_trzba == number_format( (float)round( $order->get_total(), $decimals ), 2, '.', '' ) && $aktualni_trzba == 0 ) {
        $aktualni_trzba = -$celk_trzba;
      }
    }
    $celk_trzba = number_format( (float)round( $aktualni_trzba, $decimals ), 2, '.', '' );      
    return $celk_trzba;
  }

  function ceske_sluzby_smazat_eet_uctenky( $order ) {
    if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
      $order_id = $order->id;
    } else {
      $order_id = $order->get_id();
    }
    $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $order_id );
    if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
      foreach ( $eet_uctenky as $item_id => $uctenka ) {
        if ( array_key_exists( 'Odpoved', $uctenka ) ) {
          if ( array_key_exists( 'test', $uctenka['Odpoved'] ) || array_key_exists( 'chyba', $uctenka['Odpoved'] ) ) {
            wc_delete_order_item( $item_id );
            $message = 'Elektronické účtenky byly úspěšně smazány.';
            $order->add_order_note( $message );
          }
        }
      }
    }
  }

  public function zobrazit_meta_box_eet( $post ) {
    $item_id = $post->ID;
    $order = wc_get_order( $post->ID );

    $eet_podminka = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_podminka', 'eet_podminka', 'ceske_sluzby_eet_podminka' );
    $eet_format = zkontrolovat_nastavenou_hodnotu( $order, array( 'wc_ceske_sluzby_nastaveni_pokladna', 'wc_ceske_sluzby_nastaveni_pokladna_doprava' ), 'wc_ceske_sluzby_eet_format', 'eet_format', 'ceske_sluzby_eet_format' );
    if ( empty( $eet_podminka ) ) {
      echo '<p style="color:red;">Pro tuto platební metodu není EET aktivováno!</p>';
    } else {
      $prostredi = get_option( 'wc_ceske_sluzby_eet_prostredi' );
      if ( ! empty( $prostredi ) ) {
        if ( $prostredi == "test" ) {
          echo '<p style="color:red;">Používáte testovací prostředí, účtenky jsou sice odesílány, ale nebudou oficiálně evidovány!</p>';
        } else {
          echo '<p style="color:green;">Jedeme naostro, účtenky jsou odesílány a budou oficiálně evidovány. Doporučujeme zkontrolovat odesílaná data!</p>';
        }
      }
      if ( empty( $eet_format ) ) {
        echo '<p style="color:red;">Účtenka bude sice vytvořena, ale nebude nikde zobrazena!</p>';
      } else {
        $eet_format_array = WC_Settings_Tab_Ceske_Sluzby_Admin::moznosti_nastaveni( 'wc_ceske_sluzby_eet_format' );
        if ( array_key_exists( $eet_format, $eet_format_array ) ) {
          $eet_format_text = $eet_format_array[$eet_format];
          echo '<p>- Formát účtenky: <strong>' . $eet_format_text . '</strong></p>';
        }
      }
      $eet_podminka_array = WC_Settings_Tab_Ceske_Sluzby_Admin::moznosti_nastaveni( 'wc_ceske_sluzby_eet_podminka' );
      if ( array_key_exists( $eet_podminka, $eet_podminka_array ) ) {
        $eet_podminka_text = $eet_podminka_array[$eet_podminka];
        echo '<p>- Podmínka odeslání: <strong>' . $eet_podminka_text . '</strong></p>';
      }
      $eet_uctenky = $this->ceske_sluzby_zpracovat_data_pro_eet_uctenky( $item_id );
      if ( ! empty( $eet_uctenky ) && is_array( $eet_uctenky ) ) {
        foreach ( $eet_uctenky as $item_id => $uctenka ) {
          if ( array_key_exists( 'Odpoved', $uctenka ) && array_key_exists( 'fik', $uctenka['Odpoved'] ) ) {
            if ( array_key_exists( 'test', $uctenka['Odpoved'] ) ) {
              echo '<p>Testovací elektronická účtenka pro tuto objednávku <strong>byla úspěšně vytvořena</strong> a můžete ji stornovat nebo zcela smazat.</p>';
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
      }

      $zakladni_data = $this->ziskat_zakladni_data();
      if ( ! is_array( $zakladni_data ) ) {
        echo '<p style="color:red;">' . $zakladni_data . '</p>';
      } else {
        echo '<p>Kontrolní informace pro odeslání účtenky:<br>';
        $date = date_create( 'now', timezone_open( 'Europe/Prague' ) );
        $dat_trzby = $date->format( 'c' );
        $celk_trzba = $this->ziskat_odeslanou_trzbu( $order );
        $pkp_data = array(
          'porad_cis' => $this->ziskat_poradove_cislo(),
          'dat_trzby' => $dat_trzby,
          'celk_trzba' => $celk_trzba,
        );
        $kompletni_data = $pkp_data;
        $danova_data = $this->ziskat_odeslane_danove_informace( $order );
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
    echo '<p>Nastavení pro celý eshop můžete provést <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=eet">zde</a>, případně ho <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=checkout">upřesnit</a> podle jednotlivých platebních metod.</p>';
  }
}

class WC_Order_Item_Eet extends WC_Order_Item {
  public function get_type() {
		return 'ceske_sluzby_eet';
  }
}

class WC_Order_Item_Eet_Data_Store extends Abstract_WC_Order_Item_Type_Data_Store implements WC_Object_Data_Store_Interface, WC_Order_Item_Type_Data_Store_Interface {
}