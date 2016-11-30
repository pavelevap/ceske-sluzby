<?php
function ceske_sluzby_zpracovat_dodaci_dobu_produktu( $dodatek ) {
  $dodaci_doba_array = array();
  $dodaci_doba_hodnoty = get_option( 'wc_ceske_sluzby_dodaci_doba_hodnoty' );
  if ( ! empty ( $dodaci_doba_hodnoty ) ) {
    $dodaci_doba_tmp = array_values( array_filter( explode( PHP_EOL, $dodaci_doba_hodnoty ) ) );
    foreach ( $dodaci_doba_tmp as $dodaci_doba_hodnota ) {
      $rozdeleno = explode( "|", $dodaci_doba_hodnota );
      if ( is_numeric( $rozdeleno[0] ) ) {
        if ( $dodatek ) {
          if ( count( $rozdeleno ) == 3 ) {
            $dodaci_doba_array[ $rozdeleno[0] ] = $rozdeleno[2];
          } elseif ( count( $rozdeleno ) == 2 ) {
            $dodaci_doba_array[ $rozdeleno[0] ] = $rozdeleno[1];
          }
        } else {
          if ( count( $rozdeleno ) == 2 || count( $rozdeleno ) == 3 ) {
            $dodaci_doba_array[ $rozdeleno[0] ] = $rozdeleno[1];
          }
        }
      }
    }
    if ( ! empty ( $dodaci_doba_array ) ) {
      ksort( $dodaci_doba_array );
    }
  }
  return $dodaci_doba_array;
}

function ceske_sluzby_zpracovat_pocet_skladem( $pocet ) {
  $pocet_skladem_array = array();
  $pocet_skladem_hodnoty = get_option( 'wc_ceske_sluzby_dodaci_doba_intervaly' );
  if ( ! empty ( $pocet_skladem_hodnoty ) ) {
    $pocet_skladem_tmp = array_values( array_filter( explode( PHP_EOL, $pocet_skladem_hodnoty ) ) );
    foreach ( $pocet_skladem_tmp as $pocet_skladem_hodnota ) {
      $rozdeleno = explode( "|", $pocet_skladem_hodnota );
      if ( count( $rozdeleno ) == 2 && is_numeric( $rozdeleno[0] ) ) {
        $pocet_skladem_array[ $rozdeleno[0] ] = str_replace( '{VALUE}', $pocet, $rozdeleno[1] );
      }
    }
    if ( ! empty ( $pocet_skladem_array ) ) {
      ksort( $pocet_skladem_array );
    }
  }
  return $pocet_skladem_array;
}

function ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $actual_dodaci_doba ) {
  $availability = array();
  if ( ! empty ( $actual_dodaci_doba ) || (string)$actual_dodaci_doba === '0' ) {
    if ( array_key_exists( $actual_dodaci_doba, $dodaci_doba ) ) {
      $availability['value'] = $actual_dodaci_doba;
      $availability['text'] = $dodaci_doba[ $actual_dodaci_doba ];
    }
  }
  return $availability;
}

function ceske_sluzby_ziskat_interval_pocet_skladem( $availability, $mnozstvi, $format ) {
  $dostupnost = array();
  $actual_pocet_skladem = $mnozstvi;
  $pocet_skladem = ceske_sluzby_zpracovat_pocet_skladem( $mnozstvi );
  if ( ! empty ( $pocet_skladem ) ) {
    if ( ! empty ( $actual_pocet_skladem ) ) {
      foreach( $pocet_skladem as $pocet => $text ) {
        if ( $actual_pocet_skladem > $pocet ) {
          $dostupnost['value'] = $pocet;
          $dostupnost['text'] = $text;
        }
      }
    }
  }
  if ( ! empty ( $dostupnost ) ) {
    if ( $format ) {
      $availability = '<p class=skladem-' . $dostupnost['value']. '">' . $dostupnost['text'] . '</p>'; // A co možnost nastavení vlastního formátu?
    } else {
      $availability['class'] .= ' skladem-' . $dostupnost['value'];
      $availability['availability'] = $dostupnost['text'];
    }
  }
  return $availability;
}

function ceske_sluzby_ziskat_format_dodaci_doby( $availability ) {
  $format = get_option( 'wc_ceske_sluzby_dodaci_doba_format_zobrazeni' );
  if ( is_array( $availability ) ) {
    if ( ! empty ( $format ) && (string)$availability['value'] != '0' )  {
      $variables = array( 'VALUE' => $availability['value'], 'TEXT' => $availability['text'] );
      foreach( $variables as $key => $value ) {
        $format = str_replace( '{' . $key . '}', $value, $format );
      }
    } else {
      $format = '<p class=dodaci-doba">' . $availability['text'] . '</p>';
    }
  } else {
    $format = '<p class=dodaci-doba">' . $availability . '</p>';
  }
  return $format;
}

function ceske_sluzby_ziskat_nastavenou_dostupnost_produktu( $product, $dodatek ) {
  $dodaci_doba_produkt = "";
  $dostupnost = "";
  $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( $dodatek );
  if ( empty ( $dodaci_doba ) ) {
    return $dostupnost;
  }
  if ( get_class( $product ) == "WC_Product_Variation" ) {
    $dodaci_doba_varianta = get_post_meta( $product->variation_id, 'ceske_sluzby_dodaci_doba', true );
    if ( empty ( $dodaci_doba_varianta ) ) {
      $dodaci_doba_produkt = get_post_meta( $product->parent->id, 'ceske_sluzby_dodaci_doba', true );
    } else {
      $dodaci_doba_produkt = $dodaci_doba_varianta;
    }
  }
  elseif ( get_class( $product ) == "WC_Product_Simple" ) {
    $dodaci_doba_produkt = get_post_meta( $product->id, 'ceske_sluzby_dodaci_doba', true );
  }
  if ( ! empty ( $dodaci_doba_produkt ) ) {
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $dodaci_doba_produkt );
  }
  // Pokud stále nemáme žádné údaje, tak zjistíme globální nastavení...
  if ( empty ( $dostupnost ) ) {
    $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
    $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $global_dodaci_doba );
  }
  // A pokud stále nemáme žádné údaje, tak alespoň doplníme případně nastavené texty...
  if ( empty ( $dostupnost ) ) {
    if ( $product->managing_stock() && $product->is_on_backorder(1) ) {
      if ( $product->backorders_allowed() ) {
        if ( $product->backorders_require_notification() ) {
          $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 98 );
        } else {
          $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 0 );
        }
      } else {
        $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 99 );
      }
    }
  }
  return $dostupnost;
}

function ceske_sluzby_ziskat_predobjednavku( $product, $text ) {
  $dostupnost = "";
  $predobjednavka = get_post_meta( $product->id, 'ceske_sluzby_xml_preorder_datum', true );
  if ( ! empty ( $predobjednavka ) && $product->is_in_stock() ) {
    if ( (int)$predobjednavka >= strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
      $predobjednavka = date_i18n( 'j.n.Y', $predobjednavka );
      if ( $text ) {
        $format = get_option( 'wc_ceske_sluzby_preorder_format_zobrazeni' );
        if ( ! empty ( $format ) ) {
          $variables = array( 'DATUM' => $predobjednavka );
          foreach( $variables as $key => $value ) {
            $dostupnost = str_replace( '{' . $key . '}', $value, $format );
          }
        } else {
          $dostupnost = '<p class=predobjednavka">Předobjednávka: ' . $predobjednavka . '</p>';
        }
      } else {
        $dostupnost = 'Předobjednávka: ' . $predobjednavka;
      }
    }
  }
  return $dostupnost;
}

function ceske_sluzby_ziskat_format_dodatecneho_poctu( $dostupnost, $product ) {
  $format = get_option( 'wc_ceske_sluzby_dodatecne_produkty_format_zobrazeni' );
  if ( ! empty ( $format ) ) {
    // Pokud je produkt obecně skladem...
    if ( (string)$dostupnost['value'] === '0' ) {
      $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( true );
      if ( ! empty ( $dodaci_doba ) ) {
        if ( $product->backorders_require_notification() ) {
          $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 98 );
        } else {
          $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 0 );
        }
      }
      if ( empty ( $dostupnost ) ) {
        $html = "";
      }
    }
    if ( ! empty ( $dostupnost ) && is_array( $dostupnost ) ) {
      $variables = array( 'VALUE' => $dostupnost['value'], 'TEXT' => $dostupnost['text'] );
      foreach( $variables as $key => $value ) {
        $html = str_replace( '{' . $key . '}', $value, $format );
      }
    }
  } 
  return $html;
}