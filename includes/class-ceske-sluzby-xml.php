<?php
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
    'fields' => 'ids'
  );
  $products = get_posts( $args );
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  
  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );

    if ( $produkt->is_in_stock() ) {
      $sku = $produkt->get_sku();
      if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
        $ean = $sku;
      }
    
      if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
        $dodaci_doba = "";
      }
      elseif ( isset( $global_dodaci_doba ) ) {
        $dodaci_doba = $global_dodaci_doba;
      }
      else {
        $dodaci_doba = "";
      }

      if ( ! empty ( $produkt->post->post_excerpt ) ) {
        $description = $produkt->post->post_excerpt;
      } else {
        $description = $produkt->post->post_content;
      }

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
      
      $nazev = get_post_meta( $product_id, 'ceske_sluzby_xml_heureka_productname', true );

      $xmlWriter->startElement( 'SHOPITEM' );
      $xmlWriter->writeElement( 'ITEM_ID', $product_id );
      if ( ! empty ( $nazev ) ) {
        $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev ) ); // Potřebujeme wp_strip_all_tags()?
      } else {
        $xmlWriter->startElement( 'PRODUCTNAME' );
          $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
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
      $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
      $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
      $xmlWriter->writeElement( 'PRICE_VAT', $produkt->price );
      if ( ! empty ( $ean ) ) {
        $xmlWriter->writeElement( 'EAN', $ean );
      }
      $xmlWriter->endElement();
      // http://narhinen.net/2011/01/15/Serving-large-xml-files.html
    }
  }

  $xmlWriter->endElement();

  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  echo $xmlWriter->outputMemory();
}

function zbozi_xml_feed_zobrazeni() {
  $args = array(
    'nopaging' => true,
    'post_type' => 'product',
    'post_status' => 'publish',
    'meta_key' => '_visibility',
    'meta_value' => 'hidden',
    'meta_compare' => '!=',
    'fields' => 'ids'
  );
  $products = get_posts( $args );
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->setIndent( true );
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  
  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );

    if ( $produkt->is_in_stock() ) {    
      $sku = $produkt->get_sku();
      if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
        $ean = $sku;
      }

      if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
        $dodaci_doba = -1;
      }
      elseif ( isset( $global_dodaci_doba ) ) {
        $dodaci_doba = $global_dodaci_doba;
      }
      else {
        $dodaci_doba = -1;
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
            $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
          }
        }
        $strom_kategorie .= $kategorie[0]->name;
      }

      $nazev = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
      
      $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );
      $xmlWriter->startElement( 'SHOPITEM' );
      if ( ! empty ( $nazev ) ) {
        $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev ) );
      } else {
        $xmlWriter->startElement( 'PRODUCTNAME' );
          $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
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
      $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
      $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
      $xmlWriter->writeElement( 'PRICE_VAT', $produkt->price );
      if ( ! empty ( $ean ) ) {
        $xmlWriter->writeElement( 'EAN', $ean );
      }
      $xmlWriter->endElement();
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
    'meta_key' => '_visibility',
    'meta_value' => 'hidden',
    'meta_compare' => '!=',
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
  
  $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  
  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );

    if ( $produkt->is_in_stock() ) {    
      $sku = $produkt->get_sku();
      if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
        $ean = $sku;
      }

      if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
        $dodaci_doba = -1;
      }
      elseif ( isset( $global_dodaci_doba ) ) {
        $dodaci_doba = $global_dodaci_doba;
      }
      else {
        $dodaci_doba = -1;
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
            $strom_kategorie = $nazev_kategorie->name . ' | ' . $strom_kategorie;
          }
        }
        $strom_kategorie .= $kategorie[0]->name;
      }

      $nazev = get_post_meta( $product_id, 'ceske_sluzby_xml_zbozi_productname', true );
      
      $xmlWriter->writeAttribute( 'xmlns', 'http://www.zbozi.cz/ns/offer/1.0' );
      $xmlWriter->startElement( 'SHOPITEM' );
      if ( ! empty ( $nazev ) ) {
        $xmlWriter->writeElement( 'PRODUCTNAME', wp_strip_all_tags ( $nazev ) );
      } else {
        $xmlWriter->startElement( 'PRODUCTNAME' );
          $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
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
      $xmlWriter->writeElement( 'IMGURL', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
      $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
      $xmlWriter->writeElement( 'PRICE_VAT', $produkt->price );
      if ( ! empty ( $ean ) ) {
        $xmlWriter->writeElement( 'EAN', $ean );
      }
      $xmlWriter->endElement();
    }
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
  
  $offset = $offset + $limit;
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
    'meta_key' => '_visibility',
    'meta_value' => 'hidden',
    'meta_compare' => '!=',
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
  $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
  $postovne = get_option( 'wc_ceske_sluzby_xml_feed_pricemania_postovne' );

  foreach ( $products as $product_id ) {
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );
 
    $sku = $produkt->get_sku();
    if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
      $ean = $sku;
    }
    
    if ( $produkt->is_in_stock() ) {
      if ( $produkt->managing_stock() && $produkt->backorders_allowed() ) {
        $dodaci_doba = 50;
      }
      elseif ( isset( $global_dodaci_doba ) ) {
        $dodaci_doba = $global_dodaci_doba;
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

    $xmlWriter->writeElement( 'manufacturer', '' ); // https://wordpress.org/plugins/woocommerce-brand/
    $xmlWriter->writeElement( 'url', get_permalink( $product_id ) );
    $xmlWriter->writeElement( 'picture', wp_get_attachment_url( get_post_thumbnail_id( $product_id ) ) );
    $xmlWriter->writeElement( 'availability', $dodaci_doba );
    if ( $postovne != "" ) {
      $xmlWriter->writeElement( 'shipping', $postovne );
    }
    $xmlWriter->writeElement( 'price', $produkt->price );
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