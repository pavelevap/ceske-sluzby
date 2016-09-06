<?php
function ceske_sluzby_xml_ziskat_parametry_dotazu( $limit, $offset ) {
  $args = array(
    'post_type' => 'product',
    'post_status' => 'publish',
    'meta_query' => array(
      array(
        'key' => '_visibility',
        'value' => 'hidden',
        'compare' => '!=',
      ),
      array(
        'key' => 'ceske_sluzby_xml_vynechano',
        'compare' => 'NOT EXISTS',
      ),
    ),
    'tax_query' => array(
      array(
        'taxonomy' => 'product_cat',
        'field' => 'term_id',
        'terms' => ceske_sluzby_xml_ziskat_vynechane_kategorie(),
        'include_children' => false,
        'operator' => 'NOT IN',
      ),
    ),
    'fields' => 'ids'
  );
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
  if ( ! empty ( $product_categories ) && ! is_wp_error( $product_categories ) ) {
    foreach ( $product_categories as $kategorie_produktu ) {
      $vynechano = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-vynechano' );
      if ( ! empty ( $vynechano ) ) {
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
  if ( ! empty ( $product_categories ) ) {
    foreach ( $product_categories as $kategorie ) {
      $hodnota = get_woocommerce_term_meta( $kategorie->term_id, $termmeta_key );
      if ( ! empty ( $hodnota ) ) {
        $prirazene_hodnoty[] = $hodnota;
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
        if ( ! empty ( $rodice_kategorie ) ) {
          foreach ( $rodice_kategorie as $rodic ) {
            $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
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
  if ( ! empty ( $attributes_produkt ) ) {
    $i = 0;
    foreach ( $attributes_produkt as $name => $attribute ) {
      if ( ! $attribute['is_variation'] ) {
        if ( $attribute['is_taxonomy'] ) {
          $terms = wc_get_product_terms( $product_id, $attribute['name'], array( 'fields' => 'names' ) );
          if ( ! empty ( $terms ) && ! is_wp_error( $terms ) ) {
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

function ceske_sluzby_xml_ziskat_nazev_produktu( $druh, $product_id, $doplneny_nazev_produkt, $vlastnosti, $nazev_prispevku ) {
  $nazev = "";
  if ( $druh == 'varianta' ) {
    $nazev_varianta_vlastnosti = ceske_sluzby_xml_ziskat_nazev_varianty_vlastnosti( $vlastnosti );
    if ( ! empty ( $doplneny_nazev_produkt ) ) {
      $nazev = $doplneny_nazev_produkt . $nazev_varianta_vlastnosti;
    } else {
      $nazev = $nazev_prispevku . $nazev_varianta_vlastnosti;
    }
  }
  if ( $druh == 'produkt' ) {
    $nazev_produkt_vlastnosti = ceske_sluzby_xml_ziskat_nazev_produktu_vlastnosti( $vlastnosti );
    if ( ! empty ( $doplneny_nazev_produkt ) ) {
      $nazev = $doplneny_nazev_produkt;
    } else {
      $nazev = $nazev_prispevku . $nazev_produkt_vlastnosti;
    }
  }    
  return wp_strip_all_tags( $nazev ); // Potřebujeme wp_strip_all_tags()?
}

function ceske_sluzby_xml_ziskat_popis_produktu( $post_excerpt, $post_content, $varianta, $zkracene_zapisy ) {
  $description = "";
  $produkt_description = "";
  $varianta_description = "";
  if ( ! empty ( $post_excerpt ) ) {
    $produkt_description = $post_excerpt;
  } else {
    $produkt_description = $post_content;
  }
  if ( $varianta ) {
    if ( defined( 'WOOCOMMERCE_VERSION' ) && version_compare( WOOCOMMERCE_VERSION, '2.4', '>=' ) ) {
      $varianta_description = $varianta->get_variation_description();
    } else {
      $varianta_description = get_post_meta( $varianta->variation_id, '_variation_description', true );
    }
    if ( empty ( $varianta_description ) ) {
      $varianta_description = $produkt_description;
    }
    $description = $varianta_description;
  } else {
    $description = $produkt_description;
  }
  if ( $zkracene_zapisy == "yes" && ! empty ( $description ) ) {
    $description = do_shortcode( $description );
  } else {
    $description = strip_shortcodes( $description );
  }
  return wp_strip_all_tags( $description );
}

function ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt_sku, $varianta_id, $varianta_sku ) {
  $ean = "";
  if ( ! empty ( $podpora_ean ) ) {
    if ( $podpora_ean == 'SKU' ) {
      if ( $varianta_sku ) {
        $ean = $varianta_sku;
      } else {
        $ean = $produkt_sku;
      }
    } else {
      if ( $varianta_id ) {
        $ean = get_post_meta( $varianta_id, $podpora_ean, true );
      } else {
        $ean = get_post_meta( $product_id, $podpora_ean, true );
      }
    }
  }
  return $ean;      
}

function ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav, $kategorie_stav, $new, $bazar ) {
  $aktualni_stav = "";
  if ( ! empty ( $global_stav ) ) {
    $aktualni_stav = $global_stav;
  }
  if ( ! empty ( $kategorie_stav ) ) {
    if ( count( $kategorie_stav == 1 ) ) {
      $aktualni_stav = $kategorie_stav[0];
    }
    else {
      $pocet_hodnot = array_count_values( $kategorie_stav );
      $i = 0;
      $nejnizsi = 1;
      foreach ( $pocet_hodnot as $hodnota => $pocet ) {
        if ( $i == 0 ) {
          $stav_tmp = $hodnota;
          $pocet_tmp = $pocet;
        } else {
          if ( $pocet > $pocet_tmp ) {
            $stav_tmp = $hodnota;
          }
        }
      }
      $aktualni_stav = $stav_tmp;
    }
  }
  $produkt_stav = get_post_meta( $product_id, 'ceske_sluzby_xml_stav_produktu', true );
  if ( ! empty ( $produkt_stav ) ) {
    $aktualni_stav = $produkt_stav;
  }
  if ( empty ( $aktualni_stav ) ) {
    if ( ! empty ( $new ) ) {
      $aktualni_stav = $new;
    }
  } else {
    if ( empty ( $bazar ) ) {
      $aktualni_stav = "hide";
    }
    elseif ( $bazar != 'value' ) {
      $aktualni_stav = $bazar;
    }
  }
  return $aktualni_stav;      
}

function ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $item_id, $item, $global_predbezna_objednavka, $global_neni_skladem ) {
  $dodaci_doba = $global_dodaci_doba;
  if ( $item->is_on_backorder( 1 ) && (int)$global_dodaci_doba == 0 ) {
    $dodaci_doba = $global_predbezna_objednavka;
  }

  $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
  if ( $aktivace_dodaci_doby == "yes" ) {
    if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
      $dodaci_doba_item = get_post_meta( $item_id, $dodaci_doba_vlastni_reseni, true );
      if ( ( ! empty ( $dodaci_doba_item ) || $dodaci_doba_item === '0' ) && is_numeric( $dodaci_doba_item ) ) {
        $dodaci_doba = $dodaci_doba_item;
      }
    } else {
      $dodaci_doba_nastaveni = ceske_sluzby_zpracovat_dodaci_dobu_produktu();
      if ( ! empty ( $dodaci_doba_nastaveni ) ) {
        $dodaci_doba_item = get_post_meta( $item_id, 'ceske_sluzby_dodaci_doba', true );
        if ( ! empty ( $dodaci_doba_item ) || $dodaci_doba_item === '0' ) {
          $dodaci_doba = $dodaci_doba_item;
        }
      }
    }
    $aktivace_predobjednavek = get_option( 'wc_ceske_sluzby_preorder-aktivace' );
    if ( $aktivace_predobjednavek == "yes" ) {
      $datum_predobjednavky = get_post_meta( $item_id, 'ceske_sluzby_xml_preorder_datum', true );
      if ( ! empty ( $datum_predobjednavky ) ) {
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

function ceske_sluzby_xml_ziskat_nazev_produktu_vlastnosti( $vlastnosti_produkt ) {
  $nazev_produkt_vlastnosti = "";
  if ( ! empty ( $vlastnosti_produkt ) ) {
    foreach ( $vlastnosti_produkt as $vlastnost ) {
      if ( taxonomy_is_product_attribute( $vlastnost['slug'] ) && ! isset ( $vlastnost['duplicita'] ) && $vlastnost['viditelnost'] ) {
        $nazev_produkt_vlastnosti .= " " . $vlastnost['hodnota'];
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
  $nazev_varianta_vlastnosti = "";
  if ( ! empty ( $vlastnosti_varianta ) ) {
    foreach ( $vlastnosti_varianta as $vlastnost ) {
        $nazev_varianta_vlastnosti .= " " . $vlastnost['hodnota'];
    }
  }
  return $nazev_varianta_vlastnosti;
}

function ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $data, $zobrazit_nazev ) {
  $value = "";
  $rozpoznano = false;
  if ( ! empty ( $data ) ) {
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
        if ( ! empty ( $terms ) && ! is_wp_error( $terms ) ) {
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
        if ( is_string( $zobrazit_nazev ) && empty ( $value ) ) {
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
  if ( ! empty ( $vlastnosti_produkt ) ) {
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
  if ( ! empty ( $custom_labels ) ) {
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

function heureka_xml_feed_zobrazeni() {
  // http://codeinthehole.com/writing/creating-large-xml-files-with-php/
  $args = ceske_sluzby_xml_ziskat_parametry_dotazu( false, false );
  $products = get_posts( $args );

  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, false );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );

    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, 'ceske_sluzby_xml_heureka_kategorie', 'ceske-sluzby-xml-heureka-kategorie', '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $doplneny_nazev_produkt, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, true );
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav_produkt, $kategorie_stav_produkt, false, 'bazar' );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $variation['variation_id'], $varianta, '', false );

          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $doplneny_nazev_produkt, $vlastnosti_varianta, $produkt->post->post_title );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, $varianta, $zkracene_zapisy );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $podpora_vyrobcu, true );

          $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
          if ( ! empty ( $nazev_varianta ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_varianta );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $popis_varianta ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_varianta );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty ( $vyrobce_varianta ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
          }
          if ( ! empty ( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( $varianta->get_image_id() ) );
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
          $xmlWriter->writeElement( 'PRICE_VAT', $varianta->get_price_including_tax() );
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
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          $xmlWriter->endElement();
        }
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, '', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty ( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty ( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', $produkt->get_price_including_tax() );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
        $xmlWriter->endElement();
      // http://narhinen.net/2011/01/15/Serving-large-xml-files.html
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
  if ( ! empty ( $progress ) ) {
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
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, false );
  $pocet_produkt = 0;
  $prubezny_pocet = 0;
  
  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }

    $produkt = wc_get_product( $product_id );

    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, 'ceske_sluzby_xml_heureka_kategorie', 'ceske-sluzby-xml-heureka-kategorie', '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $doplneny_nazev_produkt, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, true );
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav_produkt, $kategorie_stav_produkt, false, 'bazar' );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $variation['variation_id'], $varianta, '', false );

          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $doplneny_nazev_produkt, $vlastnosti_varianta, $produkt->post->post_title );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, $varianta, $zkracene_zapisy );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $podpora_vyrobcu, true );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
            }
            if ( ! empty ( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty ( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( $varianta->get_image_id() ) );
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', $varianta->get_price_including_tax() );
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
            if ( ! empty ( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
          $xmlWriter->endElement();
        }
        $prubezny_pocet = $prubezny_pocet + 1;
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, '', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $stav_produkt ) ) {
            $xmlWriter->writeElement( 'ITEM_TYPE', $stav_produkt );
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce );
          }
          if ( ! empty ( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', $produkt->get_price_including_tax() );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
        $xmlWriter->endElement();
      }
      $prubezny_pocet = $prubezny_pocet + 1;
    }
    $pocet_produkt = $pocet_produkt + 1;
  }

  $output = $xmlWriter->outputMemory();
  if ( ! empty ( $progress ) ) {
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

  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, $custom_labels_array );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );

    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $doplneny_nazev_produkt, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, true );
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav_produkt, $kategorie_stav_produkt, false, false );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $variation['variation_id'], $varianta, '-1', false );

          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $doplneny_nazev_produkt, $vlastnosti_varianta, $produkt->post->post_title );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, $varianta, $zkracene_zapisy );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $podpora_vyrobcu, true );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( $stav_produkt == 'hide' ) {
              $xmlWriter->writeElement( 'VISIBILITY', 0 );
            }
            if ( ! empty ( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', $varianta->get_price_including_tax() );
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
            if ( ! empty ( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty ( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
            if ( ! empty ( $custom_labels_array ) ) {
              $a = 0;
              foreach ( $custom_labels_array as $custom_label ) {
                $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                if ( ! empty ( $custom_label_hodnota ) && $a <= 1 ) {
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
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, '-1', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( $stav_produkt == 'hide' ) {
            $xmlWriter->writeElement( 'VISIBILITY', 0 );
          }
          if ( ! empty ( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', $produkt->get_price_including_tax() );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty ( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          if ( ! empty ( $custom_labels_array ) ) {
            $a = 0;
            foreach ( $custom_labels_array as $custom_label ) {
              $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
              if ( ! empty ( $custom_label_hodnota ) && $a <= 1 ) {
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
  if ( ! empty ( $progress ) ) {
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
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, $custom_labels_array );
  $pocet_produkt = 0;
  $prubezny_pocet = 0;
  
  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }

    $produkt = wc_get_product( $product_id );

    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '|' );
    $doplneny_nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, $doplneny_nazev_produkt, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, true );
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav_produkt, $kategorie_stav_produkt, false, false );

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), $variation['variation_id'], $varianta->get_sku() );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $variation['variation_id'], $varianta, '-1', false );

          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, $doplneny_nazev_produkt, $vlastnosti_varianta, $produkt->post->post_title );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, $varianta, $zkracene_zapisy );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $podpora_vyrobcu, true );

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $popis_varianta ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( $stav_produkt == 'hide' ) {
              $xmlWriter->writeElement( 'VISIBILITY', 0 );
            }
            if ( ! empty ( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'CATEGORYTEXT' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'URL', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
            $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba );
            $xmlWriter->writeElement( 'PRICE_VAT', $varianta->get_price_including_tax() );
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
            if ( ! empty ( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_varianta );
            }
            if ( ! empty ( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
            if ( ! empty ( $custom_labels_array ) ) {
              $a = 0;
              foreach ( $custom_labels_array as $custom_label ) {
                $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                if ( ! empty ( $custom_label_hodnota ) && $a <= 1 ) {
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
        $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), false, false );
        $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, '-1', false );

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( $nazev_produkt );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $popis_produkt ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( $popis_produkt );
            $xmlWriter->endElement();
          }
          if ( $stav_produkt == 'hide' ) {
            $xmlWriter->writeElement( 'VISIBILITY', 0 );
          }
          if ( ! empty ( $strom_kategorie ) ) {
            $xmlWriter->startElement( 'CATEGORYTEXT' );
              $xmlWriter->text( $strom_kategorie );
            $xmlWriter->endElement();
          }
          $xmlWriter->writeElement( 'URL', get_permalink( $product_id ) );
          $xmlWriter->writeElement( 'IMGURL', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
          $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
          $xmlWriter->writeElement( 'PRICE_VAT', $produkt->get_price_including_tax() );
          $viditelne_vlastnosti_produkt = ceske_sluzby_xml_ziskat_pouze_viditelne_vlastnosti( $vlastnosti_produkt );
          if ( $viditelne_vlastnosti_produkt ) {
            foreach ( $viditelne_vlastnosti_produkt as $vlastnost_produkt ) {
              $xmlWriter->startElement( 'PARAM' ); 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
              $xmlWriter->endElement();
            }
          }
          if ( ! empty ( $vyrobce_produkt ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce_produkt );
          }
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
          }
          if ( ! empty ( $custom_labels_array ) ) {
            $a = 0;
            foreach ( $custom_labels_array as $custom_label ) {
              $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
              if ( ! empty ( $custom_label_hodnota ) && $a <= 1 ) {
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
  if ( ! empty ( $progress ) ) {
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

  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $global_stav_produkt = get_option( 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, $custom_labels_array );
  $postovne = get_option( 'wc_ceske_sluzby_xml_feed_pricemania_postovne' );
  $nazev_webu = get_bloginfo();

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'channel' );
  $xmlWriter->writeAttribute( 'xmlns:g', 'http://base.google.com/ns/1.0' );
  $xmlWriter->writeElement( 'title', get_bloginfo() );
  $xmlWriter->writeElement( 'link', get_bloginfo( 'url' ) );
  $xmlWriter->writeElement( 'description', get_bloginfo( 'description' ) );

  foreach ( $products as $product_id ) {

    $produkt = wc_get_product( $product_id );

    $prirazene_kategorie = ceske_sluzby_xml_ziskat_prirazene_kategorie( $product_id );
    $kategorie_stav_produkt = ceske_sluzby_xml_ziskat_prirazene_hodnoty_kategorie( $prirazene_kategorie, 'ceske-sluzby-xml-stav-produktu' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '>' );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, false, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, $nazev_webu );
    $stav_produkt = ceske_sluzby_xml_ziskat_stav_produktu( $product_id, $global_stav_produkt, $kategorie_stav_produkt, 'new', 'value' );
    $sku_produkt = $produkt->get_sku();

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->variation_is_visible() ) {
          $sku_varianta = $varianta->get_sku();
          $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $sku_produkt, $variation['variation_id'], $sku_varianta );
          $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $variation['variation_id'], $varianta, 'preorder', 'out of stock' );

          $attributes_varianta = $varianta->get_variation_attributes();
          $vlastnosti_varianta = ceske_sluzby_xml_ziskat_vlastnosti_varianty( $attributes_varianta, $attributes_produkt );
          $nazev_varianta = ceske_sluzby_xml_ziskat_nazev_produktu( 'varianta', $product_id, false, $vlastnosti_varianta, $produkt->post->post_title );
          $popis_varianta = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, $varianta, $zkracene_zapisy );
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $vyrobce_varianta = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $podpora_vyrobcu, $nazev_webu );

          $xmlWriter->startElement( 'item' );
            $xmlWriter->writeElement( 'g:id', $varianta->variation_id );
            if ( ! empty ( $nazev_varianta ) ) {
              $xmlWriter->startElement( 'g:title' );
                $xmlWriter->text( $nazev_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $popis_varianta ) ) {
              $xmlWriter->startElement( 'g:description' );
                $xmlWriter->text( $popis_varianta );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $stav_produkt ) ) {
              $xmlWriter->writeElement( 'g:condition', $stav_produkt );
            }
            if ( ! empty ( $strom_kategorie ) ) {
              $xmlWriter->startElement( 'g:product_type' );
                $xmlWriter->text( $strom_kategorie );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'g:link', $varianta->get_permalink() );
            $xmlWriter->writeElement( 'g:image_link', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( $varianta->get_image_id() ) ) ) );
            if ( strtotime ( $dodaci_doba ) ) {
              $xmlWriter->writeElement( 'g:availability', 'preorder' );
              $xmlWriter->writeElement( 'g:availability_date', $dodaci_doba );
            } else {
              $xmlWriter->writeElement( 'g:availability', $dodaci_doba );
            }
            if ( $postovne != "" ) {
              $xmlWriter->startElement( 'g:shipping' );
                $xmlWriter->writeElement( 'g:price', $postovne . ' ' . GOOGLE_MENA );
              $xmlWriter->endElement();
            }
            $xmlWriter->writeElement( 'g:price', $varianta->get_price_including_tax() . ' ' . GOOGLE_MENA );
            if ( ! empty ( $vyrobce_varianta ) ) {
              $xmlWriter->writeElement( 'g:brand', $vyrobce_varianta );
            }
            if ( ! empty ( $ean ) ) {
              $xmlWriter->writeElement( 'g:gtin', $ean );
            }
            if ( $podpora_ean != 'SKU' && ! empty ( $sku_varianta ) ) {
              $xmlWriter->writeElement( 'g:mpn', $sku_varianta );
            }
            if ( ! empty ( $custom_labels_array ) ) {
              $a = 0;
              foreach ( $custom_labels_array as $custom_label ) {
                $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_varianta, $dostupna_postmeta, $custom_label, false );
                if ( ! empty ( $custom_label_hodnota ) && $a <= 4 ) {
                  $xmlWriter->writeElement( 'g:custom_label_' . $a, $custom_label_hodnota );
                }
                $a = $a + 1;
              }
            }
          $xmlWriter->endElement();
        }
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $sku_produkt, false, false );
      $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, 'preorder', 'out of stock' );

      $xmlWriter->startElement( 'item' );
        $xmlWriter->writeElement( 'g:id', $product_id );
        if ( ! empty ( $nazev_produkt ) ) {
          $xmlWriter->startElement( 'g:title' );
            $xmlWriter->text( $nazev_produkt );
          $xmlWriter->endElement();
        }
        if ( ! empty ( $popis_produkt ) ) {
          $xmlWriter->startElement( 'g:description' );
            $xmlWriter->text( $popis_produkt );
          $xmlWriter->endElement();
        }
        if ( ! empty ( $stav_produkt ) ) {
          $xmlWriter->writeElement( 'g:condition', $stav_produkt );
        }
        if ( ! empty ( $strom_kategorie ) ) {
          $xmlWriter->startElement( 'g:product_type' );
            $xmlWriter->text( $strom_kategorie );
          $xmlWriter->endElement();
        }
        $xmlWriter->writeElement( 'g:link', get_permalink( $product_id ) );
        $xmlWriter->writeElement( 'g:image_link', str_replace( array( '%3A', '%2F' ), array ( ':', '/' ), urlencode( wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) ) ) );
        if ( strtotime ( $dodaci_doba ) ) {
          $xmlWriter->writeElement( 'g:availability', 'preorder' );
          $xmlWriter->writeElement( 'g:availability_date', $dodaci_doba );
        } else {
          $xmlWriter->writeElement( 'g:availability', $dodaci_doba );
        }
        if ( $postovne != "" ) {
          $xmlWriter->startElement( 'g:shipping' );
            $xmlWriter->writeElement( 'g:price', $postovne . ' ' . GOOGLE_MENA );
          $xmlWriter->endElement();
        }
        $xmlWriter->writeElement( 'g:price', $produkt->get_price_including_tax() . ' ' . GOOGLE_MENA );
        if ( ! empty ( $vyrobce_produkt ) ) {
          $xmlWriter->writeElement( 'g:brand', $vyrobce_produkt );
        }
        if ( ! empty ( $ean ) ) {
          $xmlWriter->writeElement( 'g:gtin', $ean );
        }
        if ( $podpora_ean != 'SKU' && ! empty ( $sku_produkt ) ) {
          $xmlWriter->writeElement( 'g:mpn', $sku_produkt );
        }
        if ( ! empty ( $custom_labels_array ) ) {
          $a = 0;
          foreach ( $custom_labels_array as $custom_label ) {
            $custom_label_hodnota = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $custom_label, false );
            if ( ! empty ( $custom_label_hodnota ) && $a <= 4 ) {
              $xmlWriter->writeElement( 'g:custom_label_' . $a, $custom_label_hodnota );
            }
            $a = $a + 1;
          }
        }
      $xmlWriter->endElement();
    }
  }
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
  if ( ! empty ( $progress ) ) {
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

  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );
  $zkracene_zapisy = get_option( 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace' );
  $dostupna_postmeta = ceske_sluzby_xml_ziskat_dostupna_postmeta( $podpora_vyrobcu, false );
  $postovne = get_option( 'wc_ceske_sluzby_xml_feed_pricemania_postovne' );

  foreach ( $products as $product_id ) {

    $produkt = new WC_Product( $product_id );

    $ean = ceske_sluzby_xml_ziskat_ean_produktu( $podpora_ean, $product_id, $produkt->get_sku(), false, false );
    $dodaci_doba = ceske_sluzby_xml_ziskat_dodaci_dobu_produktu( $global_dodaci_doba, $dodaci_doba_vlastni_reseni, $product_id, $produkt, '50', '100' );
    $strom_kategorie = ceske_sluzby_xml_ziskat_kategorie_produktu( $product_id, false, false, '>' );
    $attributes_produkt = $produkt->get_attributes();
    $vlastnosti_produkt = ceske_sluzby_xml_ziskat_vlastnosti_produktu( $product_id, $attributes_produkt );
    $nazev_produkt = ceske_sluzby_xml_ziskat_nazev_produktu( 'produkt', $product_id, false, $vlastnosti_produkt, $produkt->post->post_title );
    $popis_produkt = ceske_sluzby_xml_ziskat_popis_produktu( $produkt->post->post_excerpt, $produkt->post->post_content, false, $zkracene_zapisy );
    $vyrobce_produkt = ceske_sluzby_xml_ziskat_hodnotu_dat( $product_id, $vlastnosti_produkt, $dostupna_postmeta, $podpora_vyrobcu, true );

    $xmlWriter->startElement( 'product' );
    $xmlWriter->writeElement( 'id', $product_id );
    if ( ! empty ( $nazev_produkt ) ) {    
      $xmlWriter->startElement( 'name' );
        $xmlWriter->text( $nazev_produkt );
      $xmlWriter->endElement();
    }
    if ( ! empty ( $popis_produkt ) ) {
      $xmlWriter->startElement( 'description' );
        $xmlWriter->text( $popis_produkt );
      $xmlWriter->endElement();
    }
    if ( ! empty ( $strom_kategorie ) ) {
      $xmlWriter->startElement( 'category' );
        $xmlWriter->text( $strom_kategorie );
      $xmlWriter->endElement();
    }
    if ( ! empty ( $vyrobce_produkt ) ) {
      $xmlWriter->writeElement( 'manufacturer', $vyrobce_produkt );
    }
    $xmlWriter->writeElement( 'url', get_permalink( $product_id ) );
    $xmlWriter->writeElement( 'picture', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
    $xmlWriter->writeElement( 'availability', $dodaci_doba );
    if ( $postovne != "" ) {
      $xmlWriter->writeElement( 'shipping', $postovne );
    }
    $xmlWriter->writeElement( 'price', $produkt->get_price_including_tax() );
    if ( ! empty ( $ean ) ) {
      $xmlWriter->writeElement( 'ean', $ean );
    }
    $xmlWriter->endElement();
  } 

  $output = $xmlWriter->outputMemory();
  if ( ! empty ( $progress ) ) {
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