<?php
function ceske_sluzby_xml_ziskat_parametry_dotazu( $limit, $offset ) {
  $kategorie = ceske_sluzby_xml_ziskat_vynechane_kategorie();
  $args = array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'meta_query' => array(
      array(
        'key' => 'ceske_sluzby_xml_vynechano',
        'compare' => 'NOT EXISTS',
      )
    ),
    'fields' => 'ids'
  );
  if ( ! empty( $kategorie ) ) {
    $args['tax_query'][] = array(
      'taxonomy' => 'product_cat',
      'field' => 'term_id',
      'terms' => $kategorie,
      'include_children' => false,
      'operator' => 'NOT IN'
    );
  }
  if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
    $args['meta_query'][] = array(
      'key' => '_visibility',
      'value' => 'hidden',
      'compare' => '!='
    );
  } else {
    $args['tax_query'][] = array(
      'taxonomy' => 'product_visibility',
      'field' => 'slug',
      'terms' => array( 'exclude-from-search', 'exclude-from-catalog' ),
      'operator' => 'NOT IN'
    );
  }
  if ( $limit ) {
    $args['posts_per_page'] = $limit;
  } else {
    $args['nopaging'] = true;
  }
  if ( $offset ) {
    $args['offset'] = $offset;
  }
  return $args;
}

function ceske_sluzby_xml_ziskat_vynechane_kategorie() {
  $vynechane_kategorie = array();
  $product_categories = get_terms( 'product_cat' ); // Do budoucna použít parametr meta_query?
  if ( ! empty( $product_categories ) && ! is_wp_error( $product_categories ) ) {
    foreach ( $product_categories as $kategorie_produktu ) {
      $vynechano = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-vynechano' );
      if ( ! empty( $vynechano ) ) {
        $vynechane_kategorie[] = $kategorie_produktu->term_id;
      }
    }
  }
  return $vynechane_kategorie;
}

function ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id ) {
  $kategorie = array();
  $dostupne_kategorie = get_the_terms( $product_id, 'product_cat' );
  if ( $dostupne_kategorie && ! is_wp_error( $dostupne_kategorie ) ) {
    $kategorie = $dostupne_kategorie;
  }
  return $kategorie;
}

function ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $product_categories, $termmeta_key ) {
  $prirazene_hodnoty = array();
  if ( ! empty( $product_categories ) ) {
    foreach ( $product_categories as $kategorie ) {
      $hodnota = get_woocommerce_term_meta( $kategorie->term_id, $termmeta_key, true );
      if ( ! empty( $hodnota ) ) {
        if ( is_array( $hodnota ) ) {
          $prirazene_hodnoty = array_merge( $prirazene_hodnoty, $hodnota );
        } else {
          $prirazene_hodnoty[] = $hodnota;
        }
      }
    }
  }
  return $prirazene_hodnoty;
}

function ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, $postmeta_produkt, $termmeta_kategorie, $separator ) {
  $strom_kategorie = "";
  $kategorie_produkt = "";
  $doplnena_kategorie = "";
  if ( $postmeta_produkt ) {
    $kategorie_produkt = get_post_meta( $product_id, $postmeta_produkt, true );
  }
  if ( $kategorie_produkt ) {
    $strom_kategorie = $kategorie_produkt;
  } else {
    $dostupne_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    if ( $dostupne_kategorie ) {
      if ( $termmeta_kategorie ) {
        $doplnena_kategorie = get_woocommerce_term_meta( $dostupne_kategorie[0]->term_id, $termmeta_kategorie, true );
      }
      if ( $doplnena_kategorie ) {
        $strom_kategorie = $doplnena_kategorie;
      }
      else {
        $rodice_kategorie = get_ancestors( $dostupne_kategorie[0]->term_id, 'product_cat' );
        if ( ! empty( $rodice_kategorie ) ) {
          foreach ( $rodice_kategorie as $rodic ) {
            $nazev_kategorie = get_term_by( 'id', $rodic, 'product_cat' );
            $strom_kategorie = $nazev_kategorie->name . ' ' . $separator . ' ' . $strom_kategorie;
          }
        }
        $strom_kategorie .= $dostupne_kategorie[0]->name;
      }
    }
  }
  return $strom_kategorie;
}

function ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt ) {
  $vlastnosti_produkt = array();
  if ( ! empty( $attributes_produkt ) ) {
    $i = 0;
    foreach ( $attributes_produkt as $name => $attribute ) {
      if ( ! $attribute['is_variation'] ) {
        if ( $attribute['is_taxonomy'] ) {
          $terms = wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'names' ) );
          if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $term ) {
              $vlastnosti_produkt[$i]['nazev'] = wc_attribute_label( $attribute['name'] );
              $vlastnosti_produkt[$i]['hodnota'] = $term;
              $vlastnosti_produkt[$i]['slug'] = $name;
              $vlastnosti_produkt[$i]['viditelnost'] = $attribute['is_visible'];
              if ( count( $terms ) > 1 ) {
                $vlastnosti_produkt[$i]['duplicita'] = 1;
              }
              $i = $i + 1;
            }
          }
        } else {
          $vlastnosti_produkt[$i]['nazev'] = $attribute['name'];
          $vlastnosti_produkt[$i]['hodnota'] = $attribute['value'];
          $vlastnosti_produkt[$i]['slug'] = $name;
          $vlastnosti_produkt[$i]['viditelnost'] = $attribute['is_visible'];
          $i = $i + 1;
        }
      }
    }
  }
  return $vlastnosti_produkt;
}

function ceske_sluzby_xml_ziskat_nazev_produktu( $druh, $product_id, $global_data, $doplneny_nazev_kategorie, $doplneny_nazev_produkt, $vlastnosti, $rozsirene_vlastnosti, $dostupna_postmeta, $nazev_prispevku, $feed_data ) {
  $nazev_kategorie = "";
  if ( $rozsirene_vlastnosti == false ) {
    $rozsirene_vlastnosti = $vlastnosti;
  }
  if ( $druh == 'produkt' ) {
    $nazev_varianta = "";
    if ( empty( $global_data['nazev_produktu'] ) ) {
      $global_nazev = '{PRODUCTNAME} | {KATEGORIE} | {NAZEV} {VLASTAXVID}';
    } else {
      $global_nazev = $global_data['nazev_produktu'];
    }
  }
  if ( $druh == 'varianta' ) {
    $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_varianty_vlastnosti( $vlastnosti );
    if ( empty( $global_data['nazev_variant'] ) ) {
      $global_nazev = '{PRODUCTNAME} {VLASVAR} | {KATEGORIE} | {NAZEV} {VLASVAR}';
    } else {
      $global_nazev = $global_data['nazev_variant'];
    }
    if ( ! empty( $doplneny_nazev_produkt ) && strpos( $doplneny_nazev_produkt, '{' ) !== false ) {
      $global_nazev = str_replace( '{PRODUCTNAME} {VLASVAR}', $doplneny_nazev_produkt, $global_nazev );
    }
  }
  if ( ! empty( $doplneny_nazev_produkt ) ) {
    if ( strpos( $doplneny_nazev_produkt, '{' ) === false ) {
      $global_nazev = str_replace( '{PRODUCTNAME}', $doplneny_nazev_produkt, $global_nazev );
    } else {
      $global_nazev = str_replace( '{PRODUCTNAME}', '{NAZEV}', $doplneny_nazev_produkt );
    }
  }
  if ( ! empty( $doplneny_nazev_kategorie ) ) {
    $nazev_kategorie = ceske_sluzby_xml_zpracovat_hodnoty_kategorie( $doplneny_nazev_kategorie );
    if ( strpos( $nazev_kategorie, '{' ) === false ) {
      $global_nazev = str_replace( '{KATEGORIE}', $nazev_kategorie, $global_nazev );
    } else {
      $global_nazev = $nazev_kategorie;
    }
  }
  $variables = array(
    'NAZEV' => $nazev_prispevku,
    'VLASTAX' => ceske_sluzby_xml_ziskat_nazev_produktu_vlastnosti( $rozsirene_vlastnosti, false ),
    'VLASTAXVID' => ceske_sluzby_xml_ziskat_nazev_produktu_vlastnosti( $rozsirene_vlastnosti, true ),
    'VLASVAR' => $nazev_varianta,
    'PRODUCTNAME' => $doplneny_nazev_produkt,
    'KATEGORIE' => $nazev_kategorie,
    'MANUFACTURER' => $feed_data['MANUFACTURER'],
  );
  $rozdeleno = explode( "|", $global_nazev );
  $poradi = 0;
  foreach ( $rozdeleno as $podminka ) {
    $poradi = $poradi + 1;
    $podminka = trim( $podminka );
    $pocet = substr_count( $podminka, '{' );
    if ( $pocet > 0 ) {
      $nahrada = 0;
      for ( $i = 0; $i < $pocet; ++$i ) {
        $pos = strpos( $podminka, '{' );
        $posend = strpos( $podminka, '}' );
        $delka = $posend - $pos - 1;
        $placeholder = trim( substr( $podminka, $pos + 1, $delka ) );
        if ( array_key_exists( $placeholder, $variables ) ) {
          if ( empty( $variables[$placeholder] ) ) {
            $podminka = ceske_sluzby_xml_nahradit_prazdny_placeholder( $podminka, $placeholder, $pos, $posend );
          } else {
            $podminka = str_replace( '{' . $placeholder . '}', $variables[$placeholder], $podminka );
            $nahrada = $nahrada + 1;
          }
        } else {
          $hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $rozsirene_vlastnosti, $dostupna_postmeta, $placeholder, false );
          if ( ! empty( $hodnota ) ) {
            $podminka = str_replace( '{' . $placeholder . '}', $hodnota, $podminka );
            $nahrada = $nahrada + 1;
          } else {
            $podminka = ceske_sluzby_xml_nahradit_prazdny_placeholder( $podminka, $placeholder, $pos, $posend );
          }
        }
      }
      if ( $pocet == $nahrada || count( $rozdeleno ) == $poradi ) {
        if ( strlen( $podminka ) > 0 ) {
          return $podminka;
        }
      }
    } else {
      if ( strlen( $podminka ) > 0 ) {
        return $podminka;
      }
    }
  }
}

function ceske_sluzby_xml_ziskat_popis_produktu( $post_excerpt, $post_content, $varianta, $zkracene_zapisy ) {
  $description = "";
  $produkt_description = "";
  $varianta_description = "";
  if ( ! empty( $post_excerpt ) ) {
    $produkt_description = $post_excerpt;
  } else {
    $produkt_description = $post_content;
  }
  if ( $varianta ) {
    if ( version_compare( WOOCOMMERCE_VERSION, '3.0', '>=' ) ) {
      $varianta_description = $varianta->get_description();
    } elseif ( version_compare( WOOCOMMERCE_VERSION, '2.4', '>=' ) ) {
      $varianta_description = $varianta->get_variation_description();
    } else {
      $varianta_description = get_post_meta( $varianta->variation_id, '_variation_description', true );
    }
    if ( empty( $varianta_description ) ) {
      $varianta_description = $produkt_description;
    }
    $description = $varianta_description;
  } else {
    $description = $produkt_description;
  }
  if ( $zkracene_zapisy == "yes" && ! empty( $description ) ) {
    $description = do_shortcode( $description );
  } else {
    $description = strip_shortcodes( $description );
  }
  $description = str_replace( chr(26), '', $description );
  return wp_strip_all_tags( $description );
}

function ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt_sku, $varianta_id, $varianta_sku ) {
  $ean = "";
  if ( empty( $podpora_ean ) ) {
    $podpora_ean = 'ceske_sluzby_hodnota_ean';
  }
  if ( $podpora_ean == 'SKU' ) {
    if ( $varianta_sku ) {
      $ean = $varianta_sku;
    } else {
      $ean = $produkt_sku;
    }
  } else {
    if ( $varianta_id ) {
      $ean = get_post_meta( $varianta_id, $podpora_ean, true );
      if ( empty( $ean ) ) {
        $ean = get_post_meta( $product_id, $podpora_ean, true );
      }
    } else {
      $ean = get_post_meta( $product_id, $podpora_ean, true );
    }
  }
  return $ean;      
}

function ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav, $kategorie_stav, $new, $bazar ) {
  $aktualni_stav = "";
  if ( ! empty( $global_stav ) ) {
    $aktualni_stav = $global_stav;
  }
  $aktualni_stav = ceske_sluzby_xml_zpracovat_hodnoty_kategorie( $kategorie_stav );
  $produkt_stav = get_post_meta( $product_id, 'ceske_sluzby_xml_stav_produktu', true );
  if ( ! empty( $produkt_stav ) ) {
    $aktualni_stav = $produkt_stav;
  }
  if ( empty( $aktualni_stav ) ) {
    if ( ! empty( $new ) ) {
      $aktualni_stav = $new;
    }
  } else {
    if ( empty( $bazar ) ) {
      $aktualni_stav = "hide";
    }
    elseif ( $bazar != 'value' ) {
      $aktualni_stav = $bazar;
    }
  }
  return $aktualni_stav;      
}

function ceske_sluzby_xml_ziskat_hodnotu_dopravy_zdarma() {
  $doprava_zdarma = array();
  $doprava_zdarma_limit = 0;
  if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.6', '>=' ) ) {
    $zones = ceske_sluzby_ziskat_dopravni_oblasti();
    foreach ( $zones as $zone ) {
      foreach ( $zone['shipping_methods'] as $instance => $method ) {
        if ( $method->id == "free_shipping" ) {
          if ( $method->enabled == "yes" ) {
            $doprava_zdarma_podminka = $method->requires;
            if ( $doprava_zdarma_podminka != "coupon" ) {
              $doprava_zdarma[] = $method->min_amount;
            }
          }
        }
      }
    } 
  } else {
    $shipping_methods = WC()->shipping->load_shipping_methods();
    if ( $shipping_methods['free_shipping']->enabled == "yes" ) {
      $doprava_zdarma_podminka = $shipping_methods['free_shipping']->requires;
      if ( $doprava_zdarma_podminka != "coupon" ) {
        $doprava_zdarma[] = $shipping_methods['free_shipping']->min_amount;
      }
    }
  }
  if ( ! empty( $doprava_zdarma ) ) {
    $doprava_zdarma_limit = min( $doprava_zdarma );
  }
  return $doprava_zdarma_limit; 
}

function ceske_sluzby_xml_ziskat_extra_message( $product_id, $postmeta_id, $global_extra_message, $kategorie_extra_message, $cena ) {
  $aktualni_extra_message = array();
  if ( array_key_exists( 'free_delivery', $global_extra_message ) ) {
    $doprava_zdarma_limit = ceske_sluzby_xml_ziskat_hodnotu_dopravy_zdarma();
    if ( $cena <= $doprava_zdarma_limit ) {
      unset( $global_extra_message['free_delivery'] );
    }
  }
  if ( empty( $kategorie_extra_message ) ) {
    $kategorie_extra_message = array();
  }
  $produkt_extra_message = get_post_meta( $product_id, $postmeta_id, true );
  if ( empty( $produkt_extra_message ) ) {
    $produkt_extra_message = array();
  }
  $aktualni_extra_message = array_merge( $global_extra_message, $kategorie_extra_message, $produkt_extra_message );
  return $aktualni_extra_message;      
}

function ceske_sluzby_xml_ziskat_erotiku( $product_id, $global_erotika, $kategorie_erotika, $value ) {
  $aktualni_erotika = "";
  if ( $global_erotika == "yes" ) {
    $aktualni_erotika = $global_erotika;
  }
  if ( ! empty( $kategorie_erotika ) ) {
    if ( count( $kategorie_erotika ) == 1 ) {
      $aktualni_erotika = $kategorie_erotika[0];
    }
    else {
      $pocet_hodnot = array_count_values( $kategorie_erotika );
      $i = 0;
      foreach ( $pocet_hodnot as $hodnota => $pocet ) {
        if ( $i == 0 ) {
          $erotika_tmp = $hodnota;
          $pocet_tmp = $pocet;
        } else {
          if ( $pocet > $pocet_tmp ) {
            $erotika_tmp = $hodnota;
          }
          if ( $pocet == $pocet_tmp && empty( $erotika_tmp ) ) {
            $erotika_tmp = $hodnota;
          }
        }
        $i = $i + 1;
      }
      $aktualni_erotika = $erotika_tmp;
    }
  }
  if ( ! empty( $aktualni_erotika ) ) {
    if ( ! empty( $value ) ) {
      $aktualni_erotika = $value;
    }
  }
  return $aktualni_erotika;    
}

function ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt ) {
  $galerie = array();
  if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
    $obrazky_ids = $produkt->get_gallery_attachment_ids();
  } else {
    $obrazky_ids = $produkt->get_gallery_image_ids();
  }
  if ( ! empty( $obrazky_ids ) ) {
    foreach ( $obrazky_ids as $obrazek_id ) {
      $galerie[] = wp_get_attachment_url( $obrazek_id );
    }
  }
  return $galerie; 
}

function ceske_sluzby_xml_ziskat_cenu( $produkt ) {
  if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
    $cena_produkt = $produkt->get_price_including_tax();
  } else {
    $cena_produkt = wc_get_price_including_tax( $produkt );
  }
  return $cena_produkt; 
}

function ceske_sluzby_xml_ziskat_post_data( $produkt ) {
  if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
    $post_data = $produkt->post;
  } else {
    $post_data = get_post( $produkt->get_id() );
  }
  return $post_data; 
}
      
function ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $item_id, $item, $global_predbezna_objednavka, $global_neni_skladem ) {
  $dodaci_doba_item = "";
  $dodaci_doba = $global_data['dodaci_doba'];
  if ( $item->is_on_backorder( 1 ) && (int)$global_data['dodaci_doba'] == 0 ) {
    $dodaci_doba = $global_predbezna_objednavka;
  }
  $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
  if ( $aktivace_dodaci_doby == "yes" ) {
    if ( ! empty( $global_data['vlastni_dodaci_doba'] ) ) {
      $dodaci_doba_item = get_post_meta( $item_id, $global_data['vlastni_dodaci_doba'], true );
      if ( ( ! empty( $dodaci_doba_item ) || (string)$dodaci_doba_item === '0' ) && is_numeric( $dodaci_doba_item ) ) {
        $dodaci_doba = $dodaci_doba_item;
      }
    } else {
      $dodaci_doba_nastaveni = ceske_sluzby_zpracovat_dodaci_dobu_produktu( false, false );
      if ( ! empty( $dodaci_doba_nastaveni ) ) {
        if ( ( (int)$item->get_stock_quantity() <= 0 && $item->managing_stock() ) || ! $item->managing_stock() ) {
          if ( get_class( $item ) == "WC_Product_Variation" ) {
            $varianta_id = is_callable( array( $item, 'get_id' ) ) ? $item->get_id() : $item->variation_id;
            $dodaci_doba_varianta = get_post_meta( $varianta_id, 'ceske_sluzby_dodaci_doba', true );
            if ( empty( $dodaci_doba_varianta ) ) {
              if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
                $varianta_parent_id = $item->parent->id;
              } else {
                $varianta_parent_id = $item->get_parent_id();
              }
              $dodaci_doba_item = get_post_meta( $varianta_parent_id, 'ceske_sluzby_dodaci_doba', true );
            } else {
              $dodaci_doba_item = $dodaci_doba_varianta;
            }
          }
          elseif ( get_class( $item ) == "WC_Product_Simple" ) {
            $dodaci_doba_item = get_post_meta( $item_id, 'ceske_sluzby_dodaci_doba', true );
          }
        } else {
          $dodaci_doba_item = 0;
        }
        if ( ! empty( $dodaci_doba_item ) || (string)$dodaci_doba_item === '0' ) {
          $dodaci_doba = $dodaci_doba_item;
        }
      }
    }
    $aktivace_predobjednavek = get_option( 'wc_ceske_sluzby_preorder-aktivace' );
    if ( $aktivace_predobjednavek == "yes" ) {
      $datum_predobjednavky = get_post_meta( $item_id, 'ceske_sluzby_xml_preorder_datum', true );
      if ( ! empty( $datum_predobjednavky ) && (int)$datum_predobjednavky >= strtotime( 'NOW', current_time( 'timestamp' ) ) ) {
        if ( $global_predbezna_objednavka == 'preorder' ) {
          $dodaci_doba = date_i18n( 'c', $datum_predobjednavky );
        } else {
          $dodaci_doba = date_i18n( 'Y-m-d', $datum_predobjednavky );
        }
      }
    }
  }

  if ( is_numeric( $dodaci_doba ) && $global_predbezna_objednavka == 'preorder' ) {
    $dodaci_doba = 'in stock';
  }
  if ( $global_neni_skladem && ! $item->is_in_stock() ) {
    $dodaci_doba = $global_neni_skladem;
  }
  return $dodaci_doba;      
}

function ceske_sluzby_xml_ziskat_nazev_produktu_vlastnosti( $vlastnosti_produkt, $viditelnost ) {
  $mezera = "";
  $nazev_produkt_vlastnosti = "";
  if ( ! empty( $vlastnosti_produkt ) ) {
    foreach ( $vlastnosti_produkt as $vlastnost ) {
      if ( ! empty( $nazev_produkt_vlastnosti ) ) {
        $mezera = " ";
      }
      if ( taxonomy_is_product_attribute( $vlastnost['slug'] ) && ! isset( $vlastnost['duplicita'] ) ) {
        if ( $viditelnost ) {
          if ( $vlastnost['viditelnost'] ) {
            $nazev_produkt_vlastnosti .= $mezera . $vlastnost['hodnota'];
          }
        } else {
          $nazev_produkt_vlastnosti .= $mezera . $vlastnost['hodnota'];
        }
      }
    }
  }
  return $nazev_produkt_vlastnosti;
}

function ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt ) {
  $vlastnosti_varianta = array();
  if ( $attributes_varianta ) {
    $i = 0;
    foreach ( $attributes_varianta as $nazev => $hodnota ) {
      if ( strpos( $nazev, '_pa_' ) !== false ) {
        $term = get_term_by( 'slug', $hodnota, esc_attr( str_replace( 'attribute_', '', $nazev ) ) );
        if ( $term ) {
          $vlastnosti_varianta[$i]['nazev'] = wc_attribute_label( str_replace( 'attribute_', '', $nazev ) ); 
          $vlastnosti_varianta[$i]['hodnota'] = $term->name;
          $vlastnosti_varianta[$i]['slug'] = str_replace( 'attribute_', '', $nazev );
          $vlastnosti_varianta[$i]['viditelnost'] = 1;
        }
      } else {
        $vlastnosti_varianta[$i]['nazev'] = $attributes_produkt[str_replace( 'attribute_', '', $nazev )]['name'];
        $vlastnosti_varianta[$i]['hodnota'] = $hodnota;
        $vlastnosti_varianta[$i]['slug'] = str_replace( 'attribute_', '', $nazev );
        $vlastnosti_varianta[$i]['viditelnost'] = 1;
      }
      $i = $i + 1;
    }
  }
  return $vlastnosti_varianta;
}

function ceske_sluzby_xml_ziskat_nazev_varianty_vlastnosti( $vlastnosti_varianta ) {
  $mezera = "";
  $nazev_varianta_vlastnosti = "";
  if ( ! empty( $vlastnosti_varianta ) ) {
    foreach ( $vlastnosti_varianta as $vlastnost ) {
      if ( ! empty( $nazev_varianta_vlastnosti ) ) {
        $mezera = " ";
      }
      $nazev_varianta_vlastnosti .= $mezera . $vlastnost['hodnota'];
    }
  }
  return $nazev_varianta_vlastnosti;
}

function ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $data, $zobrazit_nazev ) {
  $value = "";
  $rozpoznano = false;
  if ( ! empty( $data ) ) {
    if ( taxonomy_exists( $data ) ) {
      $rozpoznano = true;
      $polozky_taxonomie = array();
      if ( taxonomy_is_product_attribute( $data ) ) {
        if ( ! empty( $vlastnosti_produkt ) ) {
          foreach ( $vlastnosti_produkt as $vlastnost ) {
            if ( $vlastnost['slug'] == $data ) {
              $polozky_taxonomie[] = $vlastnost['hodnota'];
            }
          }
          if ( ! empty( $polozky_taxonomie ) ) {
            $value = implode( ', ', $polozky_taxonomie );
          }
        }
      } else {
        $terms = get_the_terms( $product_id, $data );             
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
          $value = implode( ', ', wp_list_pluck( $terms, 'name' ) );
        }
      }
    }
    // Kontrola dostupných textových vlastností u konkrétního produktu (hodnota postmeta _product_attributes).
    // Nelze snadno zjistit, zda se vlastnost nevyskytuje u nějakého jiného produktu a jde tedy vůbec o vlastnost.
    // Pokud tedy konkrétní produkt vlastnost neobsahuje, tak bude zobrazen název vlastnosti na základě vlastnosti $zobrazit_nazev.
    if ( ! empty( $vlastnosti_produkt ) ) {
      foreach ( $vlastnosti_produkt as $vlastnost ) {
        if ( $vlastnost['nazev'] == $data ) {
          $value = $vlastnost['hodnota'];
          $rozpoznano = true;
        }
      }
    }
    if ( ! empty( $dostupna_postmeta ) ) {
      if ( in_array( $data, $dostupna_postmeta ) ) {
        $value = get_post_meta( $product_id, $data, true );
        $rozpoznano = true;
      }
    }
    if ( $zobrazit_nazev ) {
      if ( $rozpoznano ) {
        if ( is_string( $zobrazit_nazev ) && empty( $value ) ) {
          $value = $zobrazit_nazev;
        }
      } else {
        $value = $data;
      }
    }
  } else {
    if ( $zobrazit_nazev && is_string( $zobrazit_nazev ) ) {
      $value = $zobrazit_nazev;
    }
  }
  return $value;
}

function ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt ) {
  $viditelne_vlastnosti_produkt = array();
  if ( ! empty( $vlastnosti_produkt ) ) {
    $i = 0;
    foreach ( $vlastnosti_produkt as $vlastnost ) {
      if ( $vlastnost['viditelnost'] ) {
        $viditelne_vlastnosti_produkt[$i] = $vlastnost;
        $i = $i + 1;
      }
    }
  }
  return $viditelne_vlastnosti_produkt;
}

function ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky() {
  $custom_labels_array = array();
  $custom_labels = get_option( 'wc_ceske_sluzby_xml_feed_dodatecne_oznaceni' );
  if ( ! empty( $custom_labels ) ) {
    $custom_labels_array = array_values( array_filter( explode( PHP_EOL, $custom_labels ) ) );
  }
  return $custom_labels_array;
}

function ceske_sluzby_xml_ziskat_dostupna_postmeta( $vyrobce, $custom_labels ) {
  global $wpdb;
  $dostupna_postmeta = array();
  if ( ! empty( $vyrobce ) || ! empty( $custom_labels ) ) {
    $dostupna_postmeta = get_transient( 'ceske_sluzby_dostupna_postmeta' );
    // Dotaz z WP funkce meta_form(), do budoucna využít register_meta()...
    if ( $dostupna_postmeta === false ) {
      $sql = "SELECT DISTINCT meta_key
        FROM $wpdb->postmeta
        WHERE meta_key NOT BETWEEN '_' AND '_z'
        HAVING meta_key NOT LIKE %s
        ORDER BY meta_key";
      $dostupna_postmeta = $wpdb->get_col( $wpdb->prepare( $sql, $wpdb->esc_like( '_' ) . '%' ) );
      set_transient( 'ceske_sluzby_dostupna_postmeta', $dostupna_postmeta, WEEK_IN_SECONDS );
    }
  }
  return $dostupna_postmeta;
}

function glami_xml_feed_nastaveni() {
  $settings['dodaci_doba']['predbezna_objednavka'] = false;
  $settings['dodaci_doba']['neni_skladem'] = false;
  $settings['sku']['element'] = 'PRODUCTNO';
  $settings['product']['element'] = false;
  $settings['nazev_produktu'] = '{PRODUCTNAME} | {KATEGORIE} | {NAZEV}';
  $settings['nazev_variant'] = '{PRODUCTNAME} | {KATEGORIE} | {NAZEV}';
  $settings['kategorie']['produkt'] = 'ceske_sluzby_xml_glami_kategorie';
  $settings['kategorie']['kategorie'] = 'ceske-sluzby-xml-glami-kategorie';
  xml_feed_zobrazeni( $settings );
}

function heureka_xml_feed_nastaveni() {
  $settings['dodaci_doba']['predbezna_objednavka'] = '';
  $settings['dodaci_doba']['neni_skladem'] = false;
  $settings['sku']['element'] = false;
  $settings['product']['element'] = 'PRODUCT';
  $settings['nazev_produktu'] = '{PRODUCTNAME} | {KATEGORIE} | {NAZEV} {VLASTAXVID}';
  $settings['nazev_variant'] = '{PRODUCTNAME} {VLASVAR} | {KATEGORIE} | {NAZEV} {VLASVAR}';
  $settings['kategorie']['produkt'] = 'ceske_sluzby_xml_heureka_kategorie';
  $settings['kategorie']['kategorie'] = 'ceske-sluzby-xml-heureka-kategorie';
  xml_feed_zobrazeni( $settings );
}

function xml_feed_zobrazeni( $settings ) {
  // http://codeinthehole.com/writing/creating-large-xml-files-with-php/
  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( false, false );
  $products = get_posts( $args );
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  if ( empty( $global_data['nazev_produktu'] ) ) {
    $global_data['nazev_produktu'] = $settings['nazev_produktu'];
  }
  if ( empty( $global_data['nazev_variant'] ) ) {
    $global_data['nazev_variant'] = $settings['nazev_variant'];
  }
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], false );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );
    $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, $settings['kategorie']['produkt'], $settings['kategorie']['kategorie'], '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt_doplnek = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_product', true );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
    $feed_data['MANUFACTURER'] = $vyrobce_produkt;
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_data['stav_produktu'], $kategorie_stav_produkt, false, 'bazar' );
    $galerie = ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt );
    $sku_produkt = $produkt->get_sku();
    $kategorie_nazev_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-heureka-productname' );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $sku_varianta = $varianta->get_sku();
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $variation['variation_id'], $varianta, $settings['dodaci_doba']['predbezna_objednavka'], $settings['dodaci_doba']['neni_skladem'] );
          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, $varianta, $global_data['zkracene_zapisy'] );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
          } else {
            $vlastnosti_varianta = $vlastnosti_varianta_only;
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
          $feed_data['MANUFACTURER'] = $vyrobce_varianta;
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_varianta_only, $vlastnosti_varianta, $dostupna_postmeta, $post_data->post_title, $feed_data );

          $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $variation['variation_id'] );
          if ( ! empty( $nazev_varianta ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_varianta );
            $xmlWriter->endElement();
          }
          if ( ! empty( $settings['sku']['element'] ) && ! empty( $sku_varianta ) ) {
            $xmlWriter->writeElement( $settings['sku']['element'], $sku_varianta );
          }
          if ( ! empty( $popis_varianta ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_varianta );
            $xmlWriter->endElement();
          }
          if ( ! empty( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty( $vyrobce_varianta ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( $varianta->get_image_id() ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'IMGURL_ALTERNATIVE', $obrazek_url );
            }
          }
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
          $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $varianta ) );
          $viditelne_vlastnosti_varianta = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_varianta );
          if ( $viditelne_vlastnosti_varianta ) {
            foreach ( $viditelne_vlastnosti_varianta as $vlastnost_varianta ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_varianta['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_varianta['hodnota'] );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          $xmlWriter->endElement();
        }
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, $settings['dodaci_doba']['predbezna_objednavka'], $settings['dodaci_doba']['neni_skladem'] );
        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
            if ( ! empty( $settings['product']['element'] ) && ! empty( $nazev_produkt_doplnek ) ) {
              $xmlWriter->writeElement( 'PRODUCT', $nazev_produkt . " " . $nazev_produkt_doplnek );
            }
          }
          if ( ! empty( $settings['sku']['element'] ) && ! empty( $sku_produkt ) ) {
            $xmlWriter->writeElement( $settings['sku']['element'], $sku_produkt );
          }
          if ( ! empty( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'IMGURL_ALTERNATIVE', $obrazek_url );
            }
          }
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $produkt ) );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
        $xmlWriter->endElement();
        // https://nnarhinen.github.io/2011/01/15/Serving-large-xml-files.html
      }
    }
  }

  $xmlWriter->endElement();

  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  echo $xmlWriter->outputMemory();
}

function heureka_xml_feed_aktualizace() {
  global $wpdb;
  $lock_name = 'heureka_xml.lock';
  $lock_result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $lock_name, time() ) );
  if ( ! $lock_result ) {
    $lock_result = get_option( $lock_name );
    if ( ! $lock_result || ( $lock_result > ( time() - HOUR_IN_SECONDS ) ) ) {
      wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'ceske_sluzby_heureka_aktualizace_xml_batch' );
      return;
    }
  }
  update_option( $lock_name, time() );

  $limit = 1000; // Defaultní počet produktů zpracovaných najednou...
  $offset = 0;
  $progress = get_option( 'heureka_xml_progress' );
  if ( ! empty( $progress ) ) {
    $offset = $progress;
  }

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );

  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( $limit, $offset );
  $products = get_posts( $args );

  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );

  if ( ! $products ) {
    if ( wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml_batch' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_heureka_aktualizace_xml_batch' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_heureka_aktualizace_xml_batch' );
    }
    $xmlWriter->endElement();
    $xmlWriter->endDocument();
    $output = $xmlWriter->outputMemory();
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = str_replace( '<SHOP/>', '</SHOP>', $output );
    file_put_contents( WP_CONTENT_DIR . '/heureka-tmp.xml', $output, FILE_APPEND );
    if ( file_exists( WP_CONTENT_DIR . '/heureka.xml' ) ) {
      unlink( WP_CONTENT_DIR . '/heureka.xml' );
    }
    if ( file_exists( WP_CONTENT_DIR . '/heureka-tmp.xml' ) ) {
      rename( WP_CONTENT_DIR . '/heureka-tmp.xml', WP_CONTENT_DIR . '/heureka.xml' );
    }
    delete_option( 'heureka_xml_progress' );
    delete_option( $lock_name );
    return;
  }
  wp_schedule_single_event( current_time( 'timestamp', 1 ) + ( 3 * MINUTE_IN_SECONDS ), 'ceske_sluzby_heureka_aktualizace_xml_batch' );

  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], false );
  $pocet_produkt = 0;
  $prubezny_pocet = 0;

  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }

    $produkt = wc_get_product( $product_id );
    $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, 'ceske_sluzby_xml_heureka_kategorie', 'ceske-sluzby-xml-heureka-kategorie', '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt_doplnek = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_product', true );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
    $feed_data['MANUFACTURER'] = $vyrobce_produkt;
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_data['stav_produktu'], $kategorie_stav_produkt, false, 'bazar' );
    $galerie = ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt );
    $kategorie_nazev_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-heureka-productname' );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $variation['variation_id'], $varianta, '', false );
          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, $varianta, $global_data['zkracene_zapisy'] );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
          } else {
            $vlastnosti_varianta = $vlastnosti_varianta_only;
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
          $feed_data['MANUFACTURER'] = $vyrobce_varianta;
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_varianta_only, $vlastnosti_varianta, $dostupna_postmeta, $post_data->post_title, $feed_data );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $variation['variation_id'] );
            if ( ! empty( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
            }
            if ( ! empty( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( $varianta->get_image_id() ) );
            if ( $galerie ) {
              foreach ( $galerie as $obrazek_url ) {
                $xmlWriter->writeElement( 'IMGURL_ALTERNATIVE', $obrazek_url );
              }
            }
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $varianta ) );
            $viditelne_vlastnosti_varianta = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_varianta );
            if ( $viditelne_vlastnosti_varianta ) {
              foreach ( $viditelne_vlastnosti_varianta as $vlastnost_varianta ) {
                $xmlWriter->startElement( 'PARAM' ); 
                  $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_varianta['nazev'] );
                  $xmlWriter->writeElement( 'VAL', $vlastnost_varianta['hodnota'] );
                $xmlWriter->endElement();
              }
              $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
            }
            if ( ! empty( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
          $xmlWriter->endElement();
        }
        $prubezny_pocet = $prubezny_pocet + 1;
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, '', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
            if ( ! empty( $nazev_produkt_doplnek ) ) {
              $xmlWriter->writeElement( 'PRODUCT', $nazev_produkt . " " . $nazev_produkt_doplnek );
            }
          }
          if ( ! empty( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'IMGURL_ALTERNATIVE', $obrazek_url );
            }
          }
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $produkt ) );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
        $xmlWriter->endElement();
      }
      $prubezny_pocet = $prubezny_pocet + 1;
    }
    $pocet_produkt = $pocet_produkt + 1;
  }

  $output = $xmlWriter->outputMemory();
  if ( ! empty( $progress ) ) {
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
  }
  else {
    header( 'Content-type: text/xml' );
  }
  file_put_contents( WP_CONTENT_DIR . '/heureka-tmp.xml', $output, FILE_APPEND );
  $xmlWriter->flush( true );
  
  $offset = $offset + $pocet_produkt;
  update_option( 'heureka_xml_progress', $offset );
  delete_option( $lock_name );
}

function zbozi_xml_feed_zobrazeni() {
  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( false, false );
  $products = get_posts( $args );
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], $custom_labels_array );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );
    $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $kategorie_erotika = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-erotika' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, 'ceske_sluzby_xml_zbozi_kategorie', 'ceske-sluzby-xml-zbozi-kategorie', '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt_doplnek = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_product', true );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
    $feed_data['MANUFACTURER'] = $vyrobce_produkt;
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_data['stav_produktu'], $kategorie_stav_produkt, false, false );
    $erotika_produkt = ceske_sluzby_xml_ziskat_erotiku( $product_id, $global_data['erotika'], $kategorie_erotika, 1 );
    $galerie = ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt );
    $kategorie_nazev_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-zbozi-productname' );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );
    $kategorie_extra_message = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-zbozi-extra-message' );
    $extra_message_produkt = ceske_sluzby_xml_ziskat_extra_message( $product_id, 'ceske_sluzby_xml_zbozi_extra_message', $global_data['extra_message'], $kategorie_extra_message, ceske_sluzby_xml_ziskat_cenu( $produkt ) );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $variation['variation_id'], $varianta, '-1', false );
          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, $varianta, $global_data['zkracene_zapisy'] );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
          } else {
            $vlastnosti_varianta = $vlastnosti_varianta_only;
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
          $feed_data['MANUFACTURER'] = $vyrobce_varianta;
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_varianta_only, $vlastnosti_varianta, $dostupna_postmeta, $post_data->post_title, $feed_data );
          $extra_message_varianta = ceske_sluzby_xml_ziskat_extra_message( $product_id, 'ceske_sluzby_xml_zbozi_extra_message', $global_data['extra_message'], $kategorie_extra_message, ceske_sluzby_xml_ziskat_cenu( $varianta ) );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $variation['variation_id'] );
            if ( ! empty( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( $stav_produkt == 'hide' ) {
              $xmlWriter->writeElement( 'VISIBILITY', 0 );
            }
            if ( ! empty( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
            if ( $galerie ) {
              foreach ( $galerie as $obrazek_url ) {
                $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
              }
            }
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $varianta ) ); 
            $viditelne_vlastnosti_varianta = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_varianta );
            if ( $viditelne_vlastnosti_varianta ) {
              foreach ( $viditelne_vlastnosti_varianta as $vlastnost_varianta ) {
                $xmlWriter->startElement( 'PARAM' );  
                  $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_varianta['nazev'] );
                  $xmlWriter->writeElement( 'VAL', $vlastnost_varianta['hodnota'] );
                $xmlWriter->endElement();
              }
              $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
            }
            if ( ! empty( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty( $erotika_produkt ) ) {
              $xmlWriter->writeElement( 'EROTIC', $erotika_produkt );
            }
            if ( ! empty( $extra_message_produkt ) ) {
              foreach ( $extra_message_varianta as $key => $value ) {
                $xmlWriter->writeElement( 'EXTRA_MESSAGE', $key );
              }
            }
            if ( ! empty( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
            if ( ! empty( $custom_labels_array ) ) {
              $a = 0;
              foreach ( $custom_labels_array as $custom_label ) {
                $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                if ( ! empty( $custom_label_hodnota ) && $a <= 1 ) {
                  $xmlWriter->writeElement( 'CUSTOM_LABEL_' . $a, $custom_label_hodnota );
                }
                $a = $a + 1;
              }
            }
          $xmlWriter->endElement();
        }
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, '-1', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
            if ( ! empty( $nazev_produkt_doplnek ) ) {
              $xmlWriter->writeElement( 'PRODUCT', $nazev_produkt . " " . $nazev_produkt_doplnek );
            }
          }
          if ( ! empty( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( $stav_produkt == 'hide' ) {
            $xmlWriter->writeElement( 'VISIBILITY', 0 );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
            }
          }
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $produkt ) );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty( $erotika_produkt ) ) {
            $xmlWriter->writeElement( 'EROTIC', $erotika_produkt );
          }
          if ( ! empty( $extra_message_produkt ) ) {
            foreach ( $extra_message_produkt as $key => $value ) {
              $xmlWriter->writeElement( 'EXTRA_MESSAGE', $key );
            }
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          if ( ! empty( $custom_labels_array ) ) {
            $a = 0;
            foreach ( $custom_labels_array as $custom_label ) {
              $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
              if ( ! empty( $custom_label_hodnota ) && $a <= 1 ) {
                $xmlWriter->writeElement( 'CUSTOM_LABEL_' . $a, $custom_label_hodnota );
              }
              $a = $a + 1;
            }
          }
        $xmlWriter->endElement();
      }
    }
  }
  $xmlWriter->endElement();

  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  echo $xmlWriter->outputMemory();
}

function zbozi_xml_feed_aktualizace() {
  global $wpdb;
  $lock_name = 'zbozi_xml.lock';
  $lock_result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $lock_name, time() ) );
  if ( ! $lock_result ) {
    $lock_result = get_option( $lock_name );
    if ( ! $lock_result || ( $lock_result > ( time() - HOUR_IN_SECONDS ) ) ) {
      wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'ceske_sluzby_zbozi_aktualizace_xml_batch' );
      return;
    }
  }
  update_option( $lock_name, time() );

  $limit = 1000; // Defaultní počet produktů zpracovaných najednou...
  $offset = 0;
  $progress = get_option( 'zbozi_xml_progress' );
  if ( ! empty( $progress ) ) {
    $offset = $progress;
  }

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );

  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( $limit, $offset );
  $products = get_posts( $args );

  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );

  if ( ! $products ) {
    if ( wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml_batch' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_zbozi_aktualizace_xml_batch' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_zbozi_aktualizace_xml_batch' );
    }
    $xmlWriter->endElement();
    $xmlWriter->endDocument();

    $output = $xmlWriter->outputMemory();
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = str_replace( '<SHOP/>', '</SHOP>', $output );
    file_put_contents( WP_CONTENT_DIR . '/zbozi-tmp.xml', $output, FILE_APPEND );

    if ( file_exists( WP_CONTENT_DIR . '/zbozi.xml' ) ) {
      unlink( WP_CONTENT_DIR . '/zbozi.xml' );
    }
    if ( file_exists( WP_CONTENT_DIR . '/zbozi-tmp.xml' ) ) {
      rename( WP_CONTENT_DIR . '/zbozi-tmp.xml', WP_CONTENT_DIR . '/zbozi.xml' );
    }

    delete_option( 'zbozi_xml_progress' );
    delete_option( $lock_name );
    return;
  }
  wp_schedule_single_event( current_time( 'timestamp', 1 ) + ( 3 * MINUTE_IN_SECONDS ), 'ceske_sluzby_zbozi_aktualizace_xml_batch' );

  $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], $custom_labels_array );
  $pocet_produkt = 0;
  $prubezny_pocet = 0;

  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }

    $produkt = wc_get_product( $product_id );
    $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $kategorie_erotika = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-erotika' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, 'ceske_sluzby_xml_zbozi_kategorie', 'ceske-sluzby-xml-zbozi-kategorie', '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt_doplnek = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_product', true );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
    $feed_data['MANUFACTURER'] = $vyrobce_produkt;
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_data['stav_produktu'], $kategorie_stav_produkt, false, false );
    $erotika_produkt = ceske_sluzby_xml_ziskat_erotiku( $product_id, $global_data['erotika'], $kategorie_erotika, 1 );
    $galerie = ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt );
    $kategorie_nazev_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-zbozi-productname' );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );
    $kategorie_extra_message = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-zbozi-extra-message' );
    $extra_message_produkt = ceske_sluzby_xml_ziskat_extra_message( $product_id, 'ceske_sluzby_xml_zbozi_extra_message', $global_data['extra_message'], $kategorie_extra_message, ceske_sluzby_xml_ziskat_cenu( $produkt ) );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach ( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $variation['variation_id'], $varianta, '-1', false );
          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, $varianta, $global_data['zkracene_zapisy'] );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
          } else {
            $vlastnosti_varianta = $vlastnosti_varianta_only;
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
          $feed_data['MANUFACTURER'] = $vyrobce_varianta;
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $global_data, $kategorie_nazev_produkt, $doplneny_nazev_produkt, $vlastnosti_varianta_only, $vlastnosti_varianta, $dostupna_postmeta, $post_data->post_title, $feed_data );
          $extra_message_varianta = ceske_sluzby_xml_ziskat_extra_message( $product_id, 'ceske_sluzby_xml_zbozi_extra_message', $global_data['extra_message'], $kategorie_extra_message, ceske_sluzby_xml_ziskat_cenu( $varianta ) );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $variation['variation_id'] );
            if ( ! empty( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( $stav_produkt == 'hide' ) {
              $xmlWriter->writeElement( 'VISIBILITY', 0 );
            }
            if ( ! empty( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
            if ( $galerie ) {
              foreach ( $galerie as $obrazek_url ) {
                $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
              }
            }
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $varianta ) );
            $viditelne_vlastnosti_varianta = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_varianta );
            if ( $viditelne_vlastnosti_varianta ) {
              foreach ( $viditelne_vlastnosti_varianta as $vlastnost_varianta ) {
                $xmlWriter->startElement( 'PARAM' );  
                  $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_varianta['nazev'] );
                  $xmlWriter->writeElement( 'VAL', $vlastnost_varianta['hodnota'] );
                $xmlWriter->endElement();
              }
              $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
            }
            if ( ! empty( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty( $erotika_produkt ) ) {
              $xmlWriter->writeElement( 'EROTIC', $erotika_produkt );
            }
            if ( ! empty( $extra_message_produkt ) ) {
              foreach ( $extra_message_varianta as $key => $value ) {
                $xmlWriter->writeElement( 'EXTRA_MESSAGE', $key );
              }
            }
            if ( ! empty( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
            if ( ! empty( $custom_labels_array ) ) {
              $a = 0;
              foreach ( $custom_labels_array as $custom_label ) {
                $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                if ( ! empty( $custom_label_hodnota ) && $a <= 1 ) {
                  $xmlWriter->writeElement( 'CUSTOM_LABEL_' . $a, $custom_label_hodnota );
                }
                $a = $a + 1;
              }
            }
          $xmlWriter->endElement();
        }
        $prubezny_pocet = $prubezny_pocet + 1;
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, '-1', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
            if ( ! empty( $nazev_produkt_doplnek ) ) {
              $xmlWriter->writeElement( 'PRODUCT', $nazev_produkt . " " . $nazev_produkt_doplnek );
            }
          }
          if ( ! empty( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( $stav_produkt == 'hide' ) {
            $xmlWriter->writeElement( 'VISIBILITY', 0 );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
            }
          }
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', ceske_sluzby_xml_ziskat_cenu( $produkt ) );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty( $erotika_produkt ) ) {
            $xmlWriter->writeElement( 'EROTIC', $erotika_produkt );
          }
          if ( ! empty( $extra_message_produkt ) ) {
            foreach ( $extra_message_produkt as $key => $value ) {
              $xmlWriter->writeElement( 'EXTRA_MESSAGE', $key );
            }
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          if ( ! empty( $custom_labels_array ) ) {
            $a = 0;
            foreach ( $custom_labels_array as $custom_label ) {
              $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
              if ( ! empty( $custom_label_hodnota ) && $a <= 1 ) {
                $xmlWriter->writeElement( 'CUSTOM_LABEL_' . $a, $custom_label_hodnota );
              }
              $a = $a + 1;
            }
          }
        $xmlWriter->endElement();
      }
      $prubezny_pocet = $prubezny_pocet + 1;
    }
    $pocet_produkt = $pocet_produkt + 1;
  }
  
  $output = $xmlWriter->outputMemory();
  if ( ! empty( $progress ) ) {
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
  }
  else {
    header( 'Content-type: text/xml' );
  }

  file_put_contents( WP_CONTENT_DIR . '/zbozi-tmp.xml', $output, FILE_APPEND );
  $xmlWriter->flush( true );
  
  $offset = $offset + $pocet_produkt;
  update_option( 'zbozi_xml_progress', $offset );
  delete_option( $lock_name );
}

function google_xml_feed_zobrazeni() {
  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( false, false );
  $products = get_posts( $args );
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], $custom_labels_array );
  $nazev_webu = get_bloginfo();

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'rss' );
    $xmlWriter->writeAttribute( 'version', '2.0' );
    $xmlWriter->writeAttribute( 'xmlns:g', 'http://base.google.com/ns/1.0' );
    $xmlWriter->startElement( 'channel' );
    $xmlWriter->writeElement( 'title', get_bloginfo() );
    $xmlWriter->writeElement( 'link', get_bloginfo( 'url' ) );
    $xmlWriter->writeElement( 'description', get_bloginfo( 'description' ) );

    foreach ( $products as $product_id ) {

      $produkt = wc_get_product( $product_id );
      $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
      $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
      $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
      $kategorie_erotika = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-erotika' );
      $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '>' );
      $attributes_produkt = $produkt->get_attributes();
      $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
      $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
      $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], $nazev_webu );
      $feed_data['MANUFACTURER'] = $vyrobce_produkt;
      $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_data['stav_produktu'], $kategorie_stav_produkt, 'new', 'value' );
      $erotika_produkt = ceske_sluzby_xml_ziskat_erotiku( $product_id, $global_data['erotika'], $kategorie_erotika, 'yes' );
      $galerie = ceske_sluzby_xml_ziskat_obrazky_galerie( $produkt );
      $sku_produkt = $produkt->get_sku();
      $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, false, false, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );

      if ( $produkt->is_type( 'variable' ) ) {
        foreach( $produkt->get_available_variations() as $variation ) {
          $varianta = new WC_Product_Variation( $variation['variation_id'] );
          if ( $varianta->variation_is_visible() ) {
            $sku_varianta = $varianta->get_sku();
            $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $sku_produkt, $variation['variation_id'], $sku_varianta );
            $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $variation['variation_id'], $varianta, 'preorder', 'out of stock' );
            $attributes_varianta = $varianta->get_variation_attributes();
            $vlastnosti_varianta_only = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
            $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, $varianta, $global_data['zkracene_zapisy'] );
            if ( $vlastnosti_produkt ) {
              $vlastnosti_varianta = array_merge( $vlastnosti_varianta_only, $vlastnosti_produkt );
            } else {
              $vlastnosti_varianta = $vlastnosti_varianta_only;
            }
            $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $global_data['podpora_vyrobcu'], $nazev_webu );
            $feed_data['MANUFACTURER'] = $vyrobce_varianta;
            $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $global_data, false, false, $vlastnosti_varianta_only, $vlastnosti_varianta, $dostupna_postmeta, $post_data->post_title, $feed_data );

            $xmlWriter->startElement( 'item' );
              $xmlWriter->writeElement( 'g:id', $variation['variation_id'] );
              if ( ! empty( $nazev_varianta ) ) {
                $xmlWriter->startElement( 'g:title' );
                  $xmlWriter->text( $nazev_varianta );
                $xmlWriter->endElement();
              }
              if ( ! empty( $popis_varianta ) ) {
                $xmlWriter->startElement( 'g:description' );
                  $xmlWriter->text( $popis_varianta );
                $xmlWriter->endElement();
              }
              if ( ! empty( $stav_produkt ) ) {
                $xmlWriter->writeElement( 'g:condition', $stav_produkt );
              }
              if ( ! empty( $strom_kategorie ) ) {
                $xmlWriter->startElement( 'g:product_type' );
                  $xmlWriter->text( $strom_kategorie );
                $xmlWriter->endElement();
              }
              $xmlWriter->writeElement( 'g:link', $varianta->get_permalink() );
              $xmlWriter->writeElement( 'g:image_link', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
              if ( $galerie ) {
                foreach ( $galerie as $obrazek_url ) {
                  $xmlWriter->writeElement( 'g:additional_image_link', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
                }
              }
              if ( strtotime ( $dodaci_doba ) ) {
                $xmlWriter->writeElement( 'g:availability', 'preorder' );
                $xmlWriter->writeElement( 'g:availability_date', $dodaci_doba );
              } else {
                $xmlWriter->writeElement( 'g:availability', $dodaci_doba );
              }
              if ( $global_data['postovne'] != "" ) {
                $xmlWriter->startElement( 'g:shipping' );
                  $xmlWriter->writeElement( 'g:price', $global_data['postovne'] . ' ' . GOOGLE_MENA );
                $xmlWriter->endElement();
              }
              $xmlWriter->writeElement( 'g:price', ceske_sluzby_xml_ziskat_cenu( $varianta ) . ' ' . GOOGLE_MENA );
              $identifier = 0;
              if ( ! empty( $vyrobce_varianta ) ) {
                $xmlWriter->writeElement( 'g:brand', $vyrobce_varianta );
                $identifier = $identifier + 1;
              }
              if ( ! empty( $ean ) ) {
                $xmlWriter->writeElement( 'g:gtin', $ean );
                $identifier = $identifier + 1;
              }
              if ( $global_data['podpora_ean'] != 'SKU' && ! empty( $sku_varianta ) ) {
                $xmlWriter->writeElement( 'g:mpn', $sku_varianta );
                $identifier = $identifier + 1;
              }
              if ( $identifier <= 1 ) {
                $xmlWriter->writeElement( 'g:identifier_exists', 'no' );
              }
              if ( ! empty( $erotika_produkt ) ) {
                $xmlWriter->writeElement( 'g:adult', $erotika_produkt );
              }
              if ( ! empty( $custom_labels_array ) ) {
                $a = 0;
                foreach ( $custom_labels_array as $custom_label ) {
                  $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                  if ( ! empty( $custom_label_hodnota ) && $a <= 4 ) {
                    $xmlWriter->writeElement( 'g:custom_label_' . $a, $custom_label_hodnota );
                  }
                  $a = $a + 1;
                }
              }
            $xmlWriter->endElement();
          }
        }
      } elseif ( $produkt->is_type( 'simple' ) ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $sku_produkt, false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, 'preorder', 'out of stock' );

        $xmlWriter->startElement( 'item' );
          $xmlWriter->writeElement( 'g:id', $product_id );
          if ( ! empty( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'g:title' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty( $popis_produkt ) ) {
            $xmlWriter->startElement( 'g:description' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'g:condition', $stav_produkt );
          }
          if ( ! empty( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'g:product_type' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'g:link', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'g:image_link', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
          if ( $galerie ) {
            foreach ( $galerie as $obrazek_url ) {
              $xmlWriter->writeElement( 'g:additional_image_link', str_replace( array( '%3A', '%2F' ), array( ':', '/' ), urlencode( $obrazek_url ) ) );
            }
          }
          if ( strtotime ( $dodaci_doba ) ) {
            $xmlWriter->writeElement( 'g:availability', 'preorder' );
            $xmlWriter->writeElement( 'g:availability_date', $dodaci_doba );
          } else {
            $xmlWriter->writeElement( 'g:availability', $dodaci_doba );
          }
          if ( $global_data['postovne'] != "" ) {
            $xmlWriter->startElement( 'g:shipping' );
              $xmlWriter->writeElement( 'g:price', $global_data['postovne'] . ' ' . GOOGLE_MENA );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'g:price', ceske_sluzby_xml_ziskat_cenu( $produkt ) . ' ' . GOOGLE_MENA );
          $identifier = 0;
          if ( ! empty( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'g:brand', $vyrobce_produkt );
            $identifier = $identifier + 1;
          }
          if ( ! empty( $ean ) ) {
            $xmlWriter->writeElement( 'g:gtin', $ean );
            $identifier = $identifier + 1;
          }
          if ( $global_data['podpora_ean'] != 'SKU' && ! empty( $sku_produkt ) ) {
            $xmlWriter->writeElement( 'g:mpn', $sku_produkt );
            $identifier = $identifier + 1;
          }
          if ( $identifier <= 1 ) {
            $xmlWriter->writeElement( 'g:identifier_exists', 'no' );
          }
          if ( ! empty( $erotika_produkt ) ) {
            $xmlWriter->writeElement( 'g:adult', $erotika_produkt );
          }
          if ( ! empty( $custom_labels_array ) ) {
            $a = 0;
            foreach ( $custom_labels_array as $custom_label ) {
              $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
              if ( ! empty( $custom_label_hodnota ) && $a <= 4 ) {
                $xmlWriter->writeElement( 'g:custom_label_' . $a, $custom_label_hodnota );
              }
              $a = $a + 1;
            }
          }
        $xmlWriter->endElement();
      }
    }
    $xmlWriter->endElement();
  $xmlWriter->endElement();

  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  echo $xmlWriter->outputMemory();
}

function pricemania_xml_feed_aktualizace() {
  global $wpdb;

  $lock_name = 'pricemania_xml.lock';
  $lock_result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $lock_name, time() ) );
  if ( ! $lock_result ) {
    $lock_result = get_option( $lock_name );
    if ( ! $lock_result || ( $lock_result > ( time() - HOUR_IN_SECONDS ) ) ) {
      wp_schedule_single_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'ceske_sluzby_pricemania_aktualizace_xml_batch' );
      return;
    }
  }
  update_option( $lock_name, time() );

  $limit = 1000; // Defaultní počet produktů zpracovaných najednou...
  $offset = 0;
  $progress = get_option( 'pricemania_xml_progress' );
  if ( ! empty( $progress ) ) {
    $offset = $progress;
  }

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );

  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( $limit, $offset );
  $products = get_posts( $args );

  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'products' );

  if ( ! $products ) {
    if ( wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml_batch' ) ) {
      $timestamp = wp_next_scheduled( 'ceske_sluzby_pricemania_aktualizace_xml_batch' );
      wp_unschedule_event( $timestamp, 'ceske_sluzby_pricemania_aktualizace_xml_batch' );
    }

    $xmlWriter->endElement();
    $xmlWriter->endDocument();

    $output = $xmlWriter->outputMemory();
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = str_replace( '<products/>', '</products>', $output );
    file_put_contents( WP_CONTENT_DIR . '/pricemania-tmp.xml', $output, FILE_APPEND );

    if ( file_exists( WP_CONTENT_DIR . '/pricemania.xml' ) ) {
      unlink( WP_CONTENT_DIR . '/pricemania.xml' );
    }
    if ( file_exists( WP_CONTENT_DIR . '/pricemania-tmp.xml' ) ) {
      rename( WP_CONTENT_DIR . '/pricemania-tmp.xml', WP_CONTENT_DIR . '/pricemania.xml' );
    }

    delete_option( 'pricemania_xml_progress' );
    delete_option( $lock_name );
    return;
  }

  wp_schedule_single_event( current_time( 'timestamp', 1 ) + ( 3 * MINUTE_IN_SECONDS ), 'ceske_sluzby_pricemania_aktualizace_xml_batch' );
  $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $global_data['podpora_vyrobcu'], false );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );
    $post_data = ceske_sluzby_xml_ziskat_post_data( $produkt );
    $ean = ceske_sluzby_xml_ziskat_ean_produktu( $global_data['podpora_ean'], $product_id, $produkt->get_sku(), false, false );
    $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_data, $product_id, $produkt, '50', '100' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '>' );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $post_data->post_excerpt, $post_data->post_content, false, $global_data['zkracene_zapisy'] );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $global_data['podpora_vyrobcu'], true );
    $feed_data['MANUFACTURER'] = $vyrobce_produkt;
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $global_data, false, false, $vlastnosti_produkt, false, $dostupna_postmeta, $post_data->post_title, $feed_data );

    $xmlWriter->startElement( 'product' );
    $xmlWriter->writeElement( 'id', $product_id );
    if ( ! empty( $nazev_produkt ) ) {    
      $xmlWriter->startElement( 'name' );
        $xmlWriter->text( $nazev_produkt );
      $xmlWriter->endElement();
    }
    if ( ! empty( $popis_produkt ) ) {
      $xmlWriter->startElement( 'description' );
        $xmlWriter->text( $popis_produkt );
      $xmlWriter->endElement();
    }
    if ( ! empty( $strom_kategorie ) ) {
      $xmlWriter->startElement( 'category' );
        $xmlWriter->text( $strom_kategorie );
      $xmlWriter->endElement();
    }
    if ( ! empty( $vyrobce_produkt ) ) {
      $xmlWriter->writeElement( 'manufacturer', $vyrobce_produkt );
    }
    $xmlWriter->writeElement( 'url', get_permalink( $product_id ) );
    $xmlWriter->writeElement( 'picture', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
    $xmlWriter->writeElement( 'availability', $dodaci_doba );
    if ( $global_data['postovne'] != "" ) {
      $xmlWriter->writeElement( 'shipping', $global_data['postovne'] );
    }
    $xmlWriter->writeElement( 'price', ceske_sluzby_xml_ziskat_cenu( $produkt ) );
    if ( ! empty( $ean ) ) {
      $xmlWriter->writeElement( 'ean', $ean );
    }
    $xmlWriter->endElement();
  } 

  $output = $xmlWriter->outputMemory();
  if ( ! empty( $progress ) ) {
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
    $output = substr( $output, strpos( $output, "\n" ) + 1 );
  }
  else {
    header( 'Content-type: text/xml' );
  }

  file_put_contents( WP_CONTENT_DIR . '/pricemania-tmp.xml', $output, FILE_APPEND );
  $xmlWriter->flush( true );
  
  $offset = $offset + $limit;
  update_option( 'pricemania_xml_progress', $offset );
  delete_option( $lock_name );
}