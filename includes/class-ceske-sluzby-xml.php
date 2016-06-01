<?php
function ceske_sluzby_xml_ziskat_vynechane_kategorie() {
  $vynechane_kategorie = array();
  $product_categories = get_terms( 'product_cat' ); // Do budoucna použít parametr meta_query?
  foreach ( $product_categories as $kategorie_produktu ) {
    $vynechano = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-vynechano' );
    if ( ! empty ( $vynechano ) ) {
      $vynechane_kategorie[] = $kategorie_produktu->term_id;
    }
  }
  return $vynechane_kategorie;
}

function heureka_xml_feed_zobrazeni() {
  // http://codeinthehole.com/writing/creating-large-xml-files-with-php/
  $args = array(
    'nopaging' => true,
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
  $products = get_posts( $args );
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  
  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = $global_dodaci_doba;
    $description = "";
    $strom_kategorie = "";
    $nazev_produkt_vlastnosti = "";
    $vlastnosti_produkt = array();
    $terms = array();

    $produkt = wc_get_product( $product_id );
    
    $kategorie_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_kategorie', true );
    if ( $kategorie_produkt ) {
      $strom_kategorie = $kategorie_produkt;
    } else {
      $kategorie = get_the_terms( $product_id, 'product_cat' );
      if ( $kategorie && ! is_wp_error( $kategorie ) ) {
        $heureka_kategorie = get_woocommerce_term_meta( $kategorie[0]->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
        if ( $heureka_kategorie ) {
          $strom_kategorie = $heureka_kategorie;
        }
        else {
          $rodice_kategorie = get_ancestors( $kategorie[0]->term_id, 'product_cat' );
          if ( ! empty ( $rodice_kategorie ) ) {
            foreach ( $rodice_kategorie as $rodic ) {
              $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
              $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
            }
          }
          $strom_kategorie .= $kategorie[0]->name;
        }
      }
    }
 
    $nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );

    $attributes_produkt = $produkt->get_attributes();
    if ( $attributes_produkt ) {
      $i = 0;
      foreach ( $attributes_produkt as $attribute ) {
        if ( $attribute['is_taxonomy'] ) {
          if ( ! $attribute['is_variation'] ) {
            $terms = wc_get_product_terms( $product_id, $attribute['name'] );
            if ( $terms ) {
              foreach ( $terms as $term ) {
                $vlastnosti_produkt[$i]['nazev'] = wc_attribute_label( $attribute['name'] );
                $vlastnosti_produkt[$i]['hodnota'] = $term;
                if ( count( $terms ) == 1 ) {
                  $nazev_produkt_vlastnosti .= " " . $term;
                }
                $i = $i + 1; 
              }
            }
          }
        } else {
          if ( ! $attribute['is_variation'] ) {
            $vlastnosti_produkt[$i]['nazev'] = $attribute['name'];
            $vlastnosti_produkt[$i]['hodnota'] = $attribute['value'];
            $i = $i + 1;
          }
        }
      }
    }

    if ( ! empty ( $podpora_vyrobcu ) ) {
      $vyrobce = wp_get_post_terms( $product_id, $podpora_vyrobcu, array( 'fields' => 'names' ) );
    }

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = "";
          $dodaci_doba = $global_dodaci_doba;
          $description = "";
          $nazev_varianta = "";
          $vlastnosti_varianta = array();

          if ( ! empty ( $podpora_ean ) ) {
            if ( $podpora_ean == "SKU" ) {
              $ean = $varianta->get_sku();
              if ( empty ( $ean ) ) {
                $ean = $produkt->get_sku();
              }
            } else {
              $ean = get_post_meta( $variation['variation_id'], $podpora_ean, true );
              if ( empty ( $ean ) ) {
                $ean = get_post_meta( $product_id, $podpora_ean, true );
              }
            }
          }

          if ( $varianta->managing_stock() && $varianta->backorders_allowed() ) {
            $dodaci_doba = "";
          }
          if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
            $vlastni_dodaci_doba = get_post_meta( $variation['variation_id'], $dodaci_doba_vlastni_reseni, true );
            if ( is_numeric( $vlastni_dodaci_doba ) ) {
              $dodaci_doba = $vlastni_dodaci_doba;
            }
          }

          $varianta_description = $varianta->get_variation_description();
          if ( empty ( $varianta_description ) ) {
            if ( ! empty ( $produkt->post->post_excerpt ) ) {
            $description = $produkt->post->post_excerpt;
            } else {
              $description = $produkt->post->post_content;
            }
          } else {
            $description = $varianta->get_variation_description();
          }
       
          $attributes_varianta = $varianta->get_variation_attributes();
          if ( $attributes_varianta ) {
            $i = 0;
            foreach ( $attributes_varianta as $nazev => $hodnota ) {
              if ( strpos( $nazev, '_pa_' ) !== false ) {
                $term = get_term_by( 'slug', $hodnota, esc_attr( str_replace( 'attribute_', '', $nazev ) ) );
                if ( $term ) {
                  $vlastnosti_varianta[$i]['nazev'] = wc_attribute_label( str_replace( 'attribute_', '', $nazev ) ); 
                  $vlastnosti_varianta[$i]['hodnota'] = $term->name;
                  $nazev_varianta .= " " . $term->name;
                }
              } else {
                $vlastnosti_varianta[$i]['nazev'] = $attributes_produkt[str_replace( 'attribute_', '', $nazev )]['name'];
                $vlastnosti_varianta[$i]['hodnota'] = $hodnota;
                $nazev_varianta .= " " . $hodnota;
              }
              $i = $i + 1;
            }
          }
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }

          $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt . $nazev_varianta ) );
          } else {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_varianta ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $description ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( wp_strip_all_tags( $description ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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

          if ( $vlastnosti_varianta ) {
            $xmlWriter->startElement( 'PARAM' );
            foreach ( $vlastnosti_varianta as $vlastnost ) { 
              $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost['nazev'] );
              $xmlWriter->writeElement( 'VAL', $vlastnost['hodnota'] );
            }
            $xmlWriter->endElement();
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

        if ( ! empty ( $podpora_ean ) ) {
          if ( $podpora_ean == "SKU" ) {
            $ean = $produkt->get_sku();
          } else {
            $ean = get_post_meta( $product_id, $podpora_ean, true );
          }
        }

        if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
          $dodaci_doba = "";
        }
        if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
          $vlastni_dodaci_doba = get_post_meta( $product_id, $dodaci_doba_vlastni_reseni, true );
          if ( is_numeric( $vlastni_dodaci_doba ) ) {
            $dodaci_doba = $vlastni_dodaci_doba;
          }
        }

        if ( ! empty ( $produkt->post->post_excerpt ) ) {
          $description = $produkt->post->post_excerpt;
        } else {
          $description = $produkt->post->post_content;
        }

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt ) ); // Potřebujeme wp_strip_all_tags()?
          } else {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_produkt_vlastnosti ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $description ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( wp_strip_all_tags( $description ) ); // Může být omezeno...
            $xmlWriter->endElement();
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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

          if ( $vlastnosti_produkt ) {
            $xmlWriter->startElement( 'PARAM' );
            foreach ( $vlastnosti_produkt as $vlastnost_produkt ) { 
              $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
              $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
            }
            $xmlWriter->endElement();
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
    'fields' => 'ids',
    'posts_per_page' => $limit,
    'offset' => $offset
  );
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
  $pocet_produkt = 0;
  $prubezny_pocet = 0;
  
  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }
    
    $ean = "";
    $dodaci_doba = $global_dodaci_doba;
    $description = "";
    $strom_kategorie = "";
    $nazev_produkt_vlastnosti = "";
    $vlastnosti_produkt = array();
    $terms = array();

    $produkt = wc_get_product( $product_id );
    
    $kategorie_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_kategorie', true );
    if ( $kategorie_produkt ) {
      $strom_kategorie = $kategorie_produkt;
    } else {
      $kategorie = get_the_terms( $product_id, 'product_cat' );
      if ( $kategorie && ! is_wp_error( $kategorie ) ) {
        $heureka_kategorie = get_woocommerce_term_meta( $kategorie[0]->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
        if ( $heureka_kategorie ) {
          $strom_kategorie = $heureka_kategorie;
        }
        else {
          $rodice_kategorie = get_ancestors( $kategorie[0]->term_id, 'product_cat' );
          if ( ! empty ( $rodice_kategorie ) ) {
            foreach ( $rodice_kategorie as $rodic ) {
              $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
              $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
            }
          }
          $strom_kategorie .= $kategorie[0]->name;
        }
      }
    }
      
    $nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );

    $attributes_produkt = $produkt->get_attributes();
    if ( $attributes_produkt ) {
      $i = 0;
      foreach ( $attributes_produkt as $attribute ) {
        if ( $attribute['is_taxonomy'] ) {
          if ( ! $attribute['is_variation'] ) {
            $terms = wc_get_product_terms( $product_id, $attribute['name'] );
            if ( $terms ) {
              foreach ( $terms as $term ) {
                $vlastnosti_produkt[$i]['nazev'] = wc_attribute_label( $attribute['name'] );
                $vlastnosti_produkt[$i]['hodnota'] = $term;
                if ( count( $terms ) == 1 ) {
                  $nazev_produkt_vlastnosti .= " " . $term;
                }
                $i = $i + 1; 
              }
            }
          }
        } else {
          if ( ! $attribute['is_variation'] ) {
            $vlastnosti_produkt[$i]['nazev'] = $attribute['name'];
            $vlastnosti_produkt[$i]['hodnota'] = $attribute['value'];
            $i = $i + 1;
          }
        }
      }
    }

    if ( ! empty ( $podpora_vyrobcu ) ) {
      $vyrobce = wp_get_post_terms( $product_id, $podpora_vyrobcu, array( 'fields' => 'names' ) );
    }

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = "";
          $dodaci_doba = $global_dodaci_doba;
          $description = "";
          $nazev_varianta = "";
          $vlastnosti_varianta = array();

          if ( ! empty ( $podpora_ean ) ) {
            if ( $podpora_ean == "SKU" ) {
              $ean = $varianta->get_sku();
              if ( empty ( $ean ) ) {
                $ean = $produkt->get_sku();
              }
            } else {
              $ean = get_post_meta( $variation['variation_id'], $podpora_ean, true );
              if ( empty ( $ean ) ) {
                $ean = get_post_meta( $product_id, $podpora_ean, true );
              }
            }
          }

          if ( $varianta->managing_stock() && $varianta->backorders_allowed() ) {
            $dodaci_doba = "";
          }
          if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
            $vlastni_dodaci_doba = get_post_meta( $variation['variation_id'], $dodaci_doba_vlastni_reseni, true );
            if ( is_numeric( $vlastni_dodaci_doba ) ) {
              $dodaci_doba = $vlastni_dodaci_doba;
            }
          }

          $varianta_description = $varianta->get_variation_description();
          if ( empty ( $varianta_description ) ) {
            if ( ! empty ( $produkt->post->post_excerpt ) ) {
            $description = $produkt->post->post_excerpt;
            } else {
              $description = $produkt->post->post_content;
            }
          } else {
            $description = $varianta->get_variation_description();
          }
       
          $attributes_varianta = $varianta->get_variation_attributes();
          if ( $attributes_varianta ) {
            $i = 0;
            foreach ( $attributes_varianta as $nazev => $hodnota ) {
              if ( strpos( $nazev, '_pa_' ) !== false ) {
                $term = get_term_by( 'slug', $hodnota, esc_attr( str_replace( 'attribute_', '', $nazev ) ) );
                if ( $term ) {
                  $vlastnosti_varianta[$i]['nazev'] = wc_attribute_label( str_replace( 'attribute_', '', $nazev ) ); 
                  $vlastnosti_varianta[$i]['hodnota'] = $term->name;
                  $nazev_varianta .= " " . $term->name;
                }
              } else {
                $vlastnosti_varianta[$i]['nazev'] = $attributes_produkt[str_replace( 'attribute_', '', $nazev )]['name'];
                $vlastnosti_varianta[$i]['hodnota'] = $hodnota;
                $nazev_varianta .= " " . $hodnota;
              }
              $i = $i + 1;
            }
          }
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_produkt ) ) {
              $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt . $nazev_varianta ) );
            } else {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_varianta ) );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $description ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( wp_strip_all_tags( $description ) );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $vyrobce ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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
            if ( $vlastnosti_varianta ) {
              $xmlWriter->startElement( 'PARAM' );
              foreach ( $vlastnosti_varianta as $vlastnost ) { 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost['hodnota'] );
              }
              $xmlWriter->endElement();
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

        if ( ! empty ( $podpora_ean ) ) {
          if ( $podpora_ean == "SKU" ) {
            $ean = $produkt->get_sku();
          } else {
            $ean = get_post_meta( $product_id, $podpora_ean, true );
          }
        }

        if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
          $dodaci_doba = "";
        }
        if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
          $vlastni_dodaci_doba = get_post_meta( $product_id, $dodaci_doba_vlastni_reseni, true );
          if ( is_numeric( $vlastni_dodaci_doba ) ) {
            $dodaci_doba = $vlastni_dodaci_doba;
          }
        }

        if ( ! empty ( $produkt->post->post_excerpt ) ) {
          $description = $produkt->post->post_excerpt;
        } else {
          $description = $produkt->post->post_content;
        }

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt ) ); // Potřebujeme wp_strip_all_tags()?
          } else {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_produkt_vlastnosti ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $description ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( wp_strip_all_tags( $description ) ); // Může být omezeno...
            $xmlWriter->endElement();
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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
          if ( $vlastnosti_produkt ) {
            $xmlWriter->startElement( 'PARAM' );
            foreach ( $vlastnosti_produkt as $vlastnost_produkt ) { 
              $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
              $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
            }
            $xmlWriter->endElement();
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
  $args = array(
    'nopaging' => true,
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
  $products = get_posts( $args );
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $dodaci_doba_vlastni_reseni = get_option( 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $podpora_vyrobcu = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu' );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );
  
  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = $global_dodaci_doba;
    $description = "";
    $strom_kategorie = "";
    $nazev_produkt_vlastnosti = "";
    $vlastnosti_produkt = array();
    $terms = array();

    $produkt = wc_get_product( $product_id );

    $kategorie = get_the_terms( $product_id, 'product_cat' );
    if ( $kategorie && ! is_wp_error( $kategorie ) ) { 
      $rodice_kategorie = get_ancestors( $kategorie[0]->term_id, 'product_cat' );
      if ( ! empty ( $rodice_kategorie ) ) {
        foreach ( $rodice_kategorie as $rodic ) {
          $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
          $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
        }
      }
      $strom_kategorie .= $kategorie[0]->name;
    }

    $nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );

    $attributes_produkt = $produkt->get_attributes();
    if ( $attributes_produkt ) {
      $i = 0;
      foreach ( $attributes_produkt as $attribute ) {
        if ( $attribute['is_taxonomy'] ) {
          if ( ! $attribute['is_variation'] ) {
            $terms = wc_get_product_terms( $product_id, $attribute['name'] );
            if ( $terms ) {
              foreach ( $terms as $term ) {
                $vlastnosti_produkt[$i]['nazev'] = wc_attribute_label( $attribute['name'] );
                $vlastnosti_produkt[$i]['hodnota'] = $term;
                if ( count( $terms ) == 1 ) {
                  $nazev_produkt_vlastnosti .= " " . $term;
                }
                $i = $i + 1; 
              }
            }
          }
        } else {
          if ( ! $attribute['is_variation'] ) {
            $vlastnosti_produkt[$i]['nazev'] = $attribute['name'];
            $vlastnosti_produkt[$i]['hodnota'] = $attribute['value'];
            $i = $i + 1;
          }
        }
      }
    }

    if ( ! empty ( $podpora_vyrobcu ) ) {
      $vyrobce = wp_get_post_terms( $product_id, $podpora_vyrobcu, array( 'fields' => 'names' ) );
    }

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = "";
          $dodaci_doba = $global_dodaci_doba;
          $description = "";
          $nazev_varianta = "";
          $vlastnosti_varianta = array();

          if ( ! empty ( $podpora_ean ) ) {
            if ( $podpora_ean == "SKU" ) {
              $ean = $varianta->get_sku();
              if ( empty ( $ean ) ) {
                $ean = $produkt->get_sku();
              }
            } else {
              $ean = get_post_meta( $variation['variation_id'], $podpora_ean, true );
              if ( empty ( $ean ) ) {
                $ean = get_post_meta( $product_id, $podpora_ean, true );
              }
            }
          }

          if ( $varianta->managing_stock() && $varianta->backorders_allowed() ) {
            $dodaci_doba = "-1";
          }
          if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
            $vlastni_dodaci_doba = get_post_meta( $variation['variation_id'], $dodaci_doba_vlastni_reseni, true );
            if ( is_numeric( $vlastni_dodaci_doba ) ) {
              $dodaci_doba = $vlastni_dodaci_doba;
            }
          }

          $varianta_description = $varianta->get_variation_description();
          if ( empty ( $varianta_description ) ) {
            if ( ! empty ( $produkt->post->post_excerpt ) ) {
            $description = $produkt->post->post_excerpt;
            } else {
              $description = $produkt->post->post_content;
            }
          } else {
            $description = $varianta->get_variation_description();
          }
          
          $attributes_varianta = $varianta->get_variation_attributes();
          if ( $attributes_varianta ) {
            $i = 0;
            foreach ( $attributes_varianta as $nazev => $hodnota ) {
              if ( strpos( $nazev, '_pa_' ) !== false ) {
                $term = get_term_by( 'slug', $hodnota, esc_attr( str_replace( 'attribute_', '', $nazev ) ) );
                if ( $term ) {
                  $vlastnosti_varianta[$i]['nazev'] = wc_attribute_label( str_replace( 'attribute_', '', $nazev ) ); 
                  $vlastnosti_varianta[$i]['hodnota'] = $term->name;
                  $nazev_varianta .= " " . $term->name;
                }
              } else {
                $vlastnosti_varianta[$i]['nazev'] = $attributes_produkt[str_replace( 'attribute_', '', $nazev )]['name'];
                $vlastnosti_varianta[$i]['hodnota'] = $hodnota;
                $nazev_varianta .= " " . $hodnota;
              }
              $i = $i + 1;
            }
          }
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }

          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_produkt ) ) {
              $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt . $nazev_varianta ) );
            } else {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_varianta ) );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $description ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( wp_strip_all_tags( $description ) );
              $xmlWriter->endElement();
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
            if ( $vlastnosti_varianta ) {
              $xmlWriter->startElement( 'PARAM' );
              foreach ( $vlastnosti_varianta as $vlastnost ) { 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost['hodnota'] );
              }
              $xmlWriter->endElement();
              $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
            }
            if ( ! empty ( $vyrobce ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
            }
            if ( ! empty ( $ean ) ) {
              $xmlWriter->writeElement( 'EAN', $ean );
            }
          $xmlWriter->endElement();
        }
      }
    } elseif ( $produkt->is_type( 'simple' ) ) {
      if ( $produkt->is_in_stock() ) {    

        if ( ! empty ( $podpora_ean ) ) {
          if ( $podpora_ean == "SKU" ) {
            $ean = $produkt->get_sku();
          } else {
            $ean = get_post_meta( $product_id, $podpora_ean, true );
          }
        }

        if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
          $dodaci_doba = "-1";
        }
        if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
          $vlastni_dodaci_doba = get_post_meta( $product_id, $dodaci_doba_vlastni_reseni, true );
          if ( is_numeric( $vlastni_dodaci_doba ) ) {
            $dodaci_doba = $vlastni_dodaci_doba;
          }
        }

        if ( ! empty ( $produkt->post->post_excerpt ) ) {
          $description = $produkt->post->post_excerpt;
        } else {
          $description = $produkt->post->post_content;
        }

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt ) );
          } else {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_produkt_vlastnosti ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $description ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( wp_strip_all_tags( $description ) ); // Může být omezeno...
            $xmlWriter->endElement();
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
          if ( $vlastnosti_produkt ) {
            $xmlWriter->startElement( 'PARAM' );
            foreach ( $vlastnosti_produkt as $vlastnost_produkt ) { 
              $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
              $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
            }
            $xmlWriter->endElement();
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
          }
          if ( ! empty ( $ean ) ) {
            $xmlWriter->writeElement( 'EAN', $ean );
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
    'fields' => 'ids',
    'posts_per_page' => $limit,
    'offset' => $offset
  );
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
  $pocet_produkt = 0;
  $prubezny_pocet = 0;
  
  foreach ( $products as $product_id ) {
    if ( $prubezny_pocet > $limit ) {
      break;
    }
    
    $ean = "";
    $dodaci_doba = $global_dodaci_doba;
    $description = "";
    $strom_kategorie = "";
    $nazev_produkt_vlastnosti = "";
    $vlastnosti_produkt = array();
    $terms = array();

    $produkt = wc_get_product( $product_id );

    $kategorie = get_the_terms( $product_id, 'product_cat' );
    if ( $kategorie && ! is_wp_error( $kategorie ) ) { 
      $rodice_kategorie = get_ancestors( $kategorie[0]->term_id, 'product_cat' );
      if ( ! empty ( $rodice_kategorie ) ) {
        foreach ( $rodice_kategorie as $rodic ) {
          $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
          $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
        }
      }
      $strom_kategorie .= $kategorie[0]->name;
    }

    $nazev_produkt = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );

    $attributes_produkt = $produkt->get_attributes();
    if ( $attributes_produkt ) {
      $i = 0;
      foreach ( $attributes_produkt as $attribute ) {
        if ( $attribute['is_taxonomy'] ) {
          if ( ! $attribute['is_variation'] ) {
            $terms = wc_get_product_terms( $product_id, $attribute['name'] );
            if ( $terms ) {
              foreach ( $terms as $term ) {
                $vlastnosti_produkt[$i]['nazev'] = wc_attribute_label( $attribute['name'] );
                $vlastnosti_produkt[$i]['hodnota'] = $term;
                if ( count( $terms ) == 1 ) {
                  $nazev_produkt_vlastnosti .= " " . $term;
                }
                $i = $i + 1; 
              }
            }
          }
        } else {
          if ( ! $attribute['is_variation'] ) {
            $vlastnosti_produkt[$i]['nazev'] = $attribute['name'];
            $vlastnosti_produkt[$i]['hodnota'] = $attribute['value'];
            $i = $i + 1;
          }
        }
      }
    }

    if ( ! empty ( $podpora_vyrobcu ) ) {
      $vyrobce = wp_get_post_terms( $product_id, $podpora_vyrobcu, array( 'fields' => 'names' ) );
    }

    if ( $produkt->is_type( 'variable' ) ) {
      foreach( $produkt->get_available_variations() as $variation ) {
        $varianta = new WC_Product_Variation( $variation['variation_id'] );
        if ( $varianta->is_in_stock() && $varianta->variation_is_visible() ) {
          $ean = "";
          $dodaci_doba = $global_dodaci_doba;
          $description = "";
          $nazev_varianta = "";
          $vlastnosti_varianta = array();

          if ( ! empty ( $podpora_ean ) ) {
            if ( $podpora_ean == "SKU" ) {
              $ean = $varianta->get_sku();
              if ( empty ( $ean ) ) {
                $ean = $produkt->get_sku();
              }
            } else {
              $ean = get_post_meta( $variation['variation_id'], $podpora_ean, true );
              if ( empty ( $ean ) ) {
                $ean = get_post_meta( $product_id, $podpora_ean, true );
              }
            }
          }

          if ( $varianta->managing_stock() && $varianta->backorders_allowed() ) {
            $dodaci_doba = "-1";
          }
          if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
            $vlastni_dodaci_doba = get_post_meta( $variation['variation_id'], $dodaci_doba_vlastni_reseni, true );
            if ( is_numeric( $vlastni_dodaci_doba ) ) {
              $dodaci_doba = $vlastni_dodaci_doba;
            }
          }

          $varianta_description = $varianta->get_variation_description();
          if ( empty ( $varianta_description ) ) {
            if ( ! empty ( $produkt->post->post_excerpt ) ) {
            $description = $produkt->post->post_excerpt;
            } else {
              $description = $produkt->post->post_content;
            }
          } else {
            $description = $varianta->get_variation_description();
          }
       
          $attributes_varianta = $varianta->get_variation_attributes();
          if ( $attributes_varianta ) {
            $i = 0;
            foreach ( $attributes_varianta as $nazev => $hodnota ) {
              if ( strpos( $nazev, '_pa_' ) !== false ) {
                $term = get_term_by( 'slug', $hodnota, esc_attr( str_replace( 'attribute_', '', $nazev ) ) );
                if ( $term ) {
                  $vlastnosti_varianta[$i]['nazev'] = wc_attribute_label( str_replace( 'attribute_', '', $nazev ) ); 
                  $vlastnosti_varianta[$i]['hodnota'] = $term->name;
                  $nazev_varianta .= " " . $term->name;
                }
              } else {
                $vlastnosti_varianta[$i]['nazev'] = $attributes_produkt[str_replace( 'attribute_', '', $nazev )]['name'];
                $vlastnosti_varianta[$i]['hodnota'] = $hodnota;
                $nazev_varianta .= " " . $hodnota;
              }
              $i = $i + 1;
            }
          }
          if ( $vlastnosti_produkt ) {
            $vlastnosti_varianta = array_merge( $vlastnosti_varianta, $vlastnosti_produkt );
          }
          $xmlWriter->startElement( 'SHOPITEM' );
            $xmlWriter->writeElement( 'ITEM_ID', $varianta->variation_id );
            if ( ! empty ( $nazev_produkt ) ) {
              $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt . $nazev_varianta ) );
            } else {
              $xmlWriter->startElement( 'PRODUCTNAME' );
                $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_varianta ) );
              $xmlWriter->endElement();
            }
            if ( ! empty ( $description ) ) {
              $xmlWriter->startElement( 'DESCRIPTION' );
                $xmlWriter->text( wp_strip_all_tags( $description ) );
              $xmlWriter->endElement();
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
            if ( $vlastnosti_varianta ) {
              $xmlWriter->startElement( 'PARAM' );
              foreach ( $vlastnosti_varianta as $vlastnost ) { 
                $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost['nazev'] );
                $xmlWriter->writeElement( 'VAL', $vlastnost['hodnota'] );
              }
              $xmlWriter->endElement();
              $xmlWriter->writeElement( 'ITEMGROUP_ID', $product_id );
            }
            if ( ! empty ( $vyrobce ) ) {
              $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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
   
        if ( ! empty ( $podpora_ean ) ) {
          if ( $podpora_ean == "SKU" ) {
            $ean = $produkt->get_sku();
          } else {
            $ean = get_post_meta( $product_id, $podpora_ean, true );
          }
        }

        if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
          $dodaci_doba = "-1";
        }
        if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
          $vlastni_dodaci_doba = get_post_meta( $product_id, $dodaci_doba_vlastni_reseni, true );
          if ( is_numeric( $vlastni_dodaci_doba ) ) {
            $dodaci_doba = $vlastni_dodaci_doba;
          }
        }

        if ( ! empty ( $produkt->post->post_excerpt ) ) {
          $description = $produkt->post->post_excerpt;
        } else {
          $description = $produkt->post->post_content;
        }

        $xmlWriter->startElement( 'SHOPITEM' );
          $xmlWriter->writeElement( 'ITEM_ID', $product_id );
          if ( ! empty ( $nazev_produkt ) ) {
            $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev_produkt ) );
          } else {
            $xmlWriter->startElement( 'PRODUCTNAME' );
              $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title . $nazev_produkt_vlastnosti ) );
            $xmlWriter->endElement();
          }
          if ( ! empty ( $description ) ) {
            $xmlWriter->startElement( 'DESCRIPTION' );
              $xmlWriter->text( wp_strip_all_tags( $description ) ); // Může být omezeno...
            $xmlWriter->endElement();
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
          if ( $vlastnosti_produkt ) {
            $xmlWriter->startElement( 'PARAM' );
            foreach ( $vlastnosti_produkt as $vlastnost_produkt ) { 
              $xmlWriter->writeElement( 'PARAM_NAME', $vlastnost_produkt['nazev'] );
              $xmlWriter->writeElement( 'VAL', $vlastnost_produkt['hodnota'] );
            }
            $xmlWriter->endElement();
          }
          if ( ! empty ( $vyrobce ) ) {
            $xmlWriter->writeElement( 'MANUFACTURER', $vyrobce[0] );
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

  file_put_contents( WP_CONTENT_DIR . '/zbozi-tmp.xml', $output, FILE_APPEND );
  $xmlWriter->flush( true );
  
  $offset = $offset + $pocet_produkt;
  update_option( 'zbozi_xml_progress', $offset );
  delete_option( $lock_name );
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
    'fields' => 'ids',
    'posts_per_page' => $limit,
    'offset' => $offset
  );
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
  $postovne = get_option( 'wc_ceske_sluzby_xml_feed_pricemania_postovne' );

  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = $global_dodaci_doba;
    $description = "";
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );

    if ( ! empty ( $podpora_ean ) ) {
      if ( $podpora_ean == "SKU" ) {
        $ean = $produkt->get_sku();
      } else {
        $ean = get_post_meta( $product_id, $podpora_ean, true );
      }
    }

    if ( ! empty ( $podpora_vyrobcu ) ) {
      $vyrobce = wp_get_post_terms( $product_id, $podpora_vyrobcu, array( 'fields' => 'names' ) );
    }

    if ( $produkt->is_in_stock() ) {
      if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
        $dodaci_doba = 50;
      }
      if ( ! empty( $dodaci_doba_vlastni_reseni ) ) {
        $vlastni_dodaci_doba = get_post_meta( $product_id, $dodaci_doba_vlastni_reseni, true );
        if ( is_numeric( $vlastni_dodaci_doba ) ) {
          $dodaci_doba = $vlastni_dodaci_doba;
        }
      }
    }
    else {
      $dodaci_doba = 100;
    }

    if ( ! empty ( $produkt->post->post_excerpt ) ) {
      $description = $produkt->post->post_excerpt;
    } else {
      $description = $produkt->post->post_content;
    }

    $kategorie = get_the_terms( $product_id, 'product_cat' );
    if ( $kategorie && ! is_wp_error( $kategorie ) ) { 
      $rodice_kategorie = get_ancestors( $kategorie[0]->term_id, 'product_cat' );
      if ( ! empty ( $rodice_kategorie ) ) {
        foreach ( $rodice_kategorie as $rodic ) {
          $nazev_kategorie = get_term_by( 'ID', $rodic, 'product_cat' );
          $strom_kategorie = $nazev_kategorie->name . ' > ' . $strom_kategorie;
        }
      }
      $strom_kategorie .= $kategorie[0]->name;
    }

    $xmlWriter->startElement( 'product' );
    $xmlWriter->writeElement( 'id', $product_id );
    $xmlWriter->startElement( 'name' );
      $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
    $xmlWriter->endElement();
    if ( ! empty ( $description ) ) {
      $xmlWriter->startElement( 'description' );
        $xmlWriter->text( wp_strip_all_tags( $description ) );
      $xmlWriter->endElement();
    }
    if ( ! empty ( $strom_kategorie ) ) {
      $xmlWriter->startElement( 'category' );
        $xmlWriter->text( $strom_kategorie );
      $xmlWriter->endElement();
    }

    if ( ! empty ( $vyrobce ) ) {
      $xmlWriter->writeElement( 'manufacturer', $vyrobce[0] );
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