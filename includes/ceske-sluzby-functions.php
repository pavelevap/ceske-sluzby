<?php
function ceske_sluzby_zpracovat_dodaci_dobu_produktu( $dodatek, $dropdown ) {
  $dodaci_doba_array = array();
  $dodaci_doba_hodnoty = get_option( 'wc_ceske_sluzby_dodaci_doba_hodnoty' );
  if ( ! empty ( $dodaci_doba_hodnoty ) ) {
    $dodaci_doba_tmp = array_values( array_filter( explode( PHP_EOL, $dodaci_doba_hodnoty ) ) );
    foreach ( $dodaci_doba_tmp as $dodaci_doba_hodnota ) {
      $rozdeleno = explode( "|", $dodaci_doba_hodnota );
      if ( is_numeric( $rozdeleno[0] ) ) {
        if ( $dropdown && in_array( $rozdeleno[0], array( 0, 98, 99 ) ) ) {
          $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
          if ( $global_dodaci_doba == $rozdeleno[0] ) {
            continue;
          }
        }
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
      if ( $dropdown ) {
        $dodaci_doba_array = array ( '' => '- Vyberte -' ) + $dodaci_doba_array;
      }
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
  if ( empty ( $dodaci_doba ) ) {
    $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( false, false );
  }
  if ( empty ( $dodaci_doba ) ) {
    return $availability;
  }
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
  $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( $dodatek, false );
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
  $html = "";
  $format = get_option( 'wc_ceske_sluzby_dodatecne_produkty_format_zobrazeni' );
  if ( ! empty ( $format ) ) {
    // Pokud je produkt obecně skladem...
    if ( (string)$dostupnost['value'] === '0' ) {
      $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( true, false );
      if ( ! empty ( $dodaci_doba ) ) {
        if ( $product->backorders_require_notification() ) {
          $dostupnost = ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, 98 );
        } else {
          return $html;
        }
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

function ceske_sluzby_xml_nahradit_prazdny_placeholder( $podminka, $placeholder, $pos, $posend ) {
  if ( $pos == 0 ) {
    $pos_pred = substr( $podminka, $pos, 1 );
  } else {
    $pos_pred = substr( $podminka, $pos - 1, 1 );
  }
  if ( $posend == ( strlen( $podminka ) - 1 ) ) {
    $pos_za = substr( $podminka, $posend, 1 );
  } else {
    $pos_za = substr( $podminka, $posend + 1, 1 );
  }
  if ( $pos_pred == " " && $pos_za == " " ) {
    $podminka = str_replace( ' {' . $placeholder . '}', "", $podminka );
  }
  if ( $pos_pred != " " && $pos_za == " " ) {
    $podminka = str_replace( '{' . $placeholder . '} ', "", $podminka );
  } 
  if ( $pos_pred == " " && $pos_za != " " ) {
    $podminka = str_replace( ' {' . $placeholder . '}', "", $podminka );
  }
  return $podminka; 
}

function ceske_sluzby_zobrazit_dostupne_taxonomie( $druh, $vlastnosti ) {
  $dostupne_taxonomie = "";
  if ( $vlastnosti == false ) {
    $taxonomies = get_object_taxonomies( 'product', 'objects' );
    foreach ( $taxonomies as $name => $taxonomy ) {
      if ( $druh == "vlastnosti" ) {
        if ( taxonomy_is_product_attribute( $name ) ) {
          if ( empty ( $dostupne_taxonomie ) ) {
            $dostupne_taxonomie = '<strong>' . $name . '</strong> (' . $taxonomy->label .  ')';
          }
          else {
            $dostupne_taxonomie .= ', <strong>' . $name . '</strong> (' . $taxonomy->label .  ')';
          }
        }
      } elseif ( $druh == "obecne" ) {
        if ( ! taxonomy_is_product_attribute( $name ) ) {
          if ( empty ( $dostupne_taxonomie ) ) {
            $dostupne_taxonomie = '<strong>' . $name . '</strong> (' . $taxonomy->label .  ')';
          }
          else {
            $dostupne_taxonomie .= ', <strong>' . $name . '</strong> (' . $taxonomy->label .  ')';
          }
        }
      }
    }
    if ( empty( $dostupne_taxonomie ) && ( $druh == "vlastnosti" ) ) {
      $dostupne_taxonomie = 'Zatím žádné, ale snadno můžete nějaké <a href="' . admin_url(). 'edit.php?post_type=product&page=product_attributes">vytvořit</a>.';
    }
  } else {
    if ( is_array( $vlastnosti ) && ! empty( $vlastnosti ) ) {
      foreach ( $vlastnosti as $name => $vlastnost ) {
        if ( ! $vlastnost['is_taxonomy'] ) {
          if ( empty ( $dostupne_taxonomie ) ) {
            $dostupne_taxonomie = '<code>{' . $vlastnost['name'] . '}</code>';
          }
          else {
            $dostupne_taxonomie .= ', <code>{' . $vlastnost['name'] . '}</code>';
          }
        } else { 
          if ( empty ( $dostupne_taxonomie ) ) {
            $dostupne_taxonomie = '<code>{' . $name . '}</code> (' . wc_attribute_label( $vlastnost['name'] ) .  ')';
          }
          else {
            $dostupne_taxonomie .= ', <code>{' . $name . '}</code> (' . wc_attribute_label( $vlastnost['name'] ) .  ')';
          }
        }
      } 
    }
  }
  return $dostupne_taxonomie;
}

function ceske_sluzby_zobrazit_xml_hodnotu( $postmeta_id, $product_id, $post, $termmeta_id, $global_data, $custom_labels_array ) {
  $produkt = wc_get_product( $product_id );
  $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
  $kategorie_nazev_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, $termmeta_id );
  $aktualni_kategorie_nazev_produkt = ceske_sluzby_xml_zpracovat_hodnoty_kategorie( $kategorie_nazev_produkt );
  $product_categories = wp_get_post_terms( $post->ID, 'product_cat' );
  if ( ! empty( $product_categories ) ) {
    foreach ( $product_categories as $kategorie_produktu ) {
      $kategorie = get_woocommerce_term_meta( $kategorie_produktu->term_id, $termmeta_id, true );
      if ( ! empty ( $kategorie ) ) {
        if ( $kategorie == $aktualni_kategorie_nazev_produkt ) {
          $kategorie_url = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
        }
      }
    }
  }
  $attributes_produkt = $produkt->get_attributes();
  $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
  $doplneny_nazev_produkt = get_post_meta( $product_id, $postmeta_id, true );
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], $custom_labels_array );
  $feed_data['MANUFACTURER'] = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], false );
  if ( $produkt->is_type( 'simple' ) ) {
    $xml_productname = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data['nazev_produktu'], $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_produkt, $dostupna_postmeta, $post->post_title, $feed_data );
    if ( empty( $global_data['nazev_produktu'] ) ) {
      if ( empty( $aktualni_kategorie_nazev_produkt ) ) {
        echo '<div style="margin-left: 161px;"><code>' . $xml_productname . '</code> (defaultní nastavení, možno změnit na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>)</div>';
      } else {
        echo '<div style="margin-left: 161px;"><code>' . $xml_productname . '</code> (nastaveno na úrovni kategorie ' . $kategorie_url . ', možno změnit i na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>)</div>';
      }
    } else {
      if ( empty( $aktualni_kategorie_nazev_produkt ) ) {
        echo '<div style="margin-left: 161px;"><code>' . $xml_productname . '</code> (nastaveno na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>)</div>';
      } else {
        echo '<div style="margin-left: 161px;"><code>' . $xml_productname . '</code> (nastaveno na úrovni kategorie ' . $kategorie_url . ' a <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>)</div>';
      }
    }
  }
  if ( $produkt->is_type( 'variable' ) ) {
    if ( ! empty( $produkt->get_available_variations() ) ) {
      if ( empty( $global_data['nazev_produktu'] ) ) {
        if ( empty( $aktualni_kategorie_nazev_produkt ) ) {
          echo '<div style="margin-left: 161px;"><strong>Přehled názvů variant</strong> (defaultní nastavení, možno změnit na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>):</div>';
        } else {
          echo '<div style="margin-left: 161px;"><strong>Přehled názvů variant</strong> (nastaveno na úrovni kategorie ' . $kategorie_url . ', možno změnit na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>):</div>';
        }
      } else {
        if ( empty( $aktualni_kategorie_nazev_produkt ) ) {
          echo '<div style="margin-left: 161px;"><strong>Přehled názvů variant</strong> (nastaveno na úrovni <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>):</div>';
        } else {
          echo '<div style="margin-left: 161px;"><strong>Přehled názvů variant</strong> (nastaveno na úrovni kategorie ' . $kategorie_url . ' a <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">eshopu</a>):</div>';
        }
      }
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        $attributes_varianta = $varianta->get_variation_attributes();
        $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
        if ( $vlastnosti_produkt && ! empty( $doplneny_nazev_produkt ) ) {
          $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
        } else {
          $vlastnosti_varianta = $vlastnosti_varianta_only;
        }
        $xml_productname = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $post->ID, $global_data['nazev_produktu'], $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_varianta, $dostupna_postmeta, $post->post_title, $feed_data );
        echo '<div style="margin-left: 161px;"><code>' . $xml_productname . '</code></div>';
      }
      echo '<div style="margin-left: 161px;">Dostupné vlastnosti: ' . ceske_sluzby_zobrazit_dostupne_taxonomie( 'vlastnosti', $attributes_produkt ) . '</div>';
    } else {
      echo '<div style="margin-left: 161px;">Zatím nebyly pro produkt vytvořeny žádné varianty.</div>';
    }
  }
}

function ceske_sluzby_xml_zpracovat_hodnoty_kategorie( $hodnoty ) {
  $aktualni_hodnota = "";
  if ( ! empty ( $hodnoty ) ) {
    if ( count( $hodnoty ) == 1 ) {
      $aktualni_hodnota = $hodnoty[0];
    } else {
      $pocet_hodnot = array_count_values( $hodnoty );
      $i = 0;
      foreach ( $pocet_hodnot as $hodnota => $pocet ) {
        if ( $i == 0 ) {
          $hodnota_tmp = $hodnota;
          $pocet_tmp = $pocet;
        } else {
          if ( $pocet > $pocet_tmp ) {
            $hodnota_tmp = $hodnota;
          }
          if ( $pocet == $pocet_tmp && empty ( $hodnota_tmp ) ) {
            $hodnota_tmp = $hodnota;
          }
        }
        $i = $i + 1;
      }
      $aktualni_hodnota = $hodnota_tmp;
    }
  }
  return $aktualni_hodnota;
}