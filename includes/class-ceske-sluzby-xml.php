<?php
function heureka_xml_feed_zobrazeni() {
  // http://codeinthehole.com/writing/creating-large-xml-files-with-php/
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
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  
  foreach ( $products as $product_id ) {
    
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $skladem = false;
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );
    $sku = $produkt->get_sku();
    if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
      $ean = $sku;
    }
    
    $skladem = $produkt->is_in_stock();
    if ( $skladem && isset( $global_dodaci_doba ) ) {
      $dodaci_doba = $global_dodaci_doba;
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

    $xmlWriter->startElement( 'SHOPITEM' );
    $xmlWriter->writeElement( 'ITEM_ID', $product_id );
    $xmlWriter->startElement( 'PRODUCTNAME' );
      $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
    $xmlWriter->endElement();
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
    if ( $dodaci_doba != "" ) {
      $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
    }
    $xmlWriter->writeElement( 'PRICE_VAT', $produkt->price );
    if ( ! empty ( $ean ) ) {
      $xmlWriter->writeElement( 'EAN', $ean );
    }
    $xmlWriter->endElement();
    // http://narhinen.net/2011/01/15/Serving-large-xml-files.html
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
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'SHOP' );
  
  foreach ( $products as $product_id ) {
    
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $skladem = false;
    $strom_kategorie = "";

    $produkt = new WC_Product( $product_id );
    
    $sku = $produkt->get_sku();
    if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
      $ean = $sku;
    }
    
    $skladem = $produkt->is_in_stock();
    if ( $skladem && isset( $global_dodaci_doba ) ) {
      $dodaci_doba = $global_dodaci_doba;
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

    $xmlWriter->startElement( 'SHOPITEM' );
    $xmlWriter->startElement( 'PRODUCT' );
      $xmlWriter->text( wp_strip_all_tags( $produkt->post->post_title ) );
    $xmlWriter->endElement();
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
    if ( $dodaci_doba != "" ) {
      $xmlWriter->writeElement( 'DELIVERY_DATE', $dodaci_doba ); // Doplnit nastavení produktů...
    }
    $xmlWriter->writeElement( 'PRICE_VAT', $produkt->price );
    if ( ! empty ( $ean ) ) {
      $xmlWriter->writeElement( 'EAN', $ean );
    }
    $xmlWriter->endElement();
  }

  $xmlWriter->endElement();
  
  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  echo $xmlWriter->outputMemory();
}

function pricemania_xml_feed_aktualizace() {
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
  $postovne = get_option( 'wc_ceske_sluzby_xml_feed_pricemania_postovne' );

  $xmlWriter = new XMLWriter();
  $xmlWriter->openMemory();
  $xmlWriter->startDocument( '1.0', 'utf-8' );
  $xmlWriter->startElement( 'products' );
  
  $i = 0;
  unlink( WP_CONTENT_DIR . '/pricemania.xml' );
  
  foreach ( $products as $product_id ) {
    
    $ean = "";
    $dodaci_doba = "";
    $description = "";
    $skladem = false;
    $strom_kategorie = "";
    
    $i = $i + 1;

    $produkt = new WC_Product( $product_id );
    
    $sku = $produkt->get_sku();
    if ( ! empty ( $podpora_ean ) && ( $podpora_ean == "SKU" ) ) {
      $ean = $sku;
    }
    
    $skladem = $produkt->is_in_stock();
    if ( $skladem && isset( $global_dodaci_doba ) ) {
      $dodaci_doba = $global_dodaci_doba;
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
    if ( $dodaci_doba != "" ) {
      $xmlWriter->writeElement( 'availability', $dodaci_doba );
    }
    if ( $postovne != "" ) {
      $xmlWriter->writeElement( 'shipping', $postovne );
    }
    $xmlWriter->writeElement( 'price', $produkt->price );
    if ( ! empty ( $ean ) ) {
      $xmlWriter->writeElement( 'ean', $ean );
    }
    $xmlWriter->endElement();
    
    if ( 0 == $i%1000 ) {
      file_put_contents( WP_CONTENT_DIR . '/pricemania.xml', $xmlWriter->flush( true ), FILE_APPEND );
    }
  }

  $xmlWriter->endElement();
  
  $xmlWriter->endDocument();
  header( 'Content-type: text/xml' );
  file_put_contents( WP_CONTENT_DIR . '/pricemania.xml', $xmlWriter->flush( true ), FILE_APPEND );
}