<?php
class WC_Product_Tab_Ceske_Sluzby_Admin {

  public function __construct() {
    if ( is_admin() ) {
      add_filter( 'woocommerce_product_data_tabs', array( $this, 'ceske_sluzby_product_tab' ) );
      add_action( 'woocommerce_product_data_panels', array( $this, 'ceske_sluzby_product_tab_obsah' ) );
      add_action( 'woocommerce_process_product_meta', array( $this, 'ceske_sluzby_product_tab_ulozeni' ) );
    }
  }

  public function ceske_sluzby_product_tab( $product_tabs ) {
    $product_tabs['ceske_sluzby'] = array(
      'label' => 'České služby',
      'target' => 'ceske_sluzby_tab_data',
      'class' => array( 'show_if_simple' ),
    );
    return $product_tabs;
  }

  public function ceske_sluzby_product_tab_obsah() {
    // Zobrazit aktuální hodnoty v podobě ukázky XML
    // http://www.remicorson.com/mastering-woocommerce-products-custom-fields/
    $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
    echo '<div id="ceske_sluzby_tab_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group show_if_simple">';
    echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>XML feedy</strong> (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">hromadné nastavení</a>)</div>';

    woocommerce_wp_checkbox( 
      array( 
        'id' => 'ceske_sluzby_xml_vynechano', 
        'wrapper_class' => '', // show_if_simple - pouze u jednoduchých produktů
        'label' => 'Odebrat z XML', 
        'description' => 'Po zaškrtnutí nebude produkt zahrnut do žádného z generovaných XML feedů'
      ) 
    );

    echo '</div>';

    if ( $xml_feed_heureka == "yes" ) {
      echo '<div class="options_group show_if_simple">'; // hide_if_grouped - skrýt u seskupených produktů
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Heureka</strong> (<a href="http://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/" target="_blank">obecný manuál</a>)</div>';
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_heureka_productname', 
          'label' => 'Přesný název (<a href="http://sluzby.' . HEUREKA_URL . '/napoveda/povinne-nazvy/" target="_blank">manuál</a>)', 
          'placeholder' => 'PRODUCTNAME',
          'desc_tip' => 'true',
          'description' => 'Zadejte přesný název produktu, pokud chcete aby byl odlišný od aktuálního názvu.' 
        )
      );
      echo '</div>';
    }

    echo '</div>';
  }

  public function ceske_sluzby_product_tab_ulozeni( $post_id ) {
    if ( isset( $_POST['ceske_sluzby_xml_heureka_productname'] ) ) {
      $heureka_productname = $_POST['ceske_sluzby_xml_heureka_productname'];
      if( ! empty( $heureka_productname ) ) {
        update_post_meta( $post_id, 'ceske_sluzby_xml_heureka_productname', esc_attr( $heureka_productname ) );
      }
    }

    $xml_vynechano_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_xml_vynechano', true );
    if ( isset( $_POST['ceske_sluzby_xml_vynechano'] ) ) {
      $xml_vynechano = $_POST['ceske_sluzby_xml_vynechano'];
      if ( ! empty( $xml_vynechano ) ) {
        update_post_meta( $post_id, 'ceske_sluzby_xml_vynechano', $xml_vynechano );  
      }
    } elseif ( ! empty( $xml_vynechano_ulozeno ) ) {
        delete_post_meta( $post_id, 'ceske_sluzby_xml_vynechano' );  
    }
  }
}
// Variace: http://www.remicorson.com/woocommerce-custom-fields-for-variations/