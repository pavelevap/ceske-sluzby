<?php
class WC_Product_Tab_Ceske_Sluzby_Admin {

  public function __construct() {
    if ( is_admin() ) {
      add_filter( 'woocommerce_product_data_tabs', array( $this, 'ceske_sluzby_product_tab' ) );
      add_action( 'woocommerce_product_data_panels', array( $this, 'ceske_sluzby_product_tab_obsah' ) );
      add_action( 'woocommerce_process_product_meta', array( $this, 'ceske_sluzby_product_tab_ulozeni' ) );
      add_action( 'woocommerce_product_options_stock_status', array( $this, 'ceske_sluzby_zobrazit_nastaveni_dodaci_doby' ) );
      add_action( 'woocommerce_product_options_sku', array( $this, 'ceske_sluzby_zobrazit_nastaveni_ean' ) );
    }
  }

  public function ceske_sluzby_product_tab( $product_tabs ) {
    $product_tabs['ceske_sluzby'] = array(
      'label' => 'České služby',
      'target' => 'ceske_sluzby_tab_data',
      'class' => array()
    );
    return $product_tabs;
  }

  public function ceske_sluzby_product_tab_obsah() {
    // Zobrazit aktuální hodnoty v podobě ukázky XML
    // http://www.remicorson.com/mastering-woocommerce-products-custom-fields/
    global $post;
    $global_data = ceske_sluzby_xml_ziskat_globalni_hodnoty();
    $xml_feed_heureka = get_option( 'wc_ceske_sluzby_xml_feed_heureka-aktivace' );
    $xml_feed_zbozi = get_option( 'wc_ceske_sluzby_xml_feed_zbozi-aktivace' );
    if ( ! empty ( $global_data['stav_produktu'] ) ) {
      if ( $global_data['stav_produktu'] == 'used' ) {
        $global_stav_produktu_hodnota = 'Použité (bazar)';
      } else {
        $global_stav_produktu_hodnota = 'Repasované';
      }
    }
    echo '<div id="ceske_sluzby_tab_data" class="panel woocommerce_options_panel">';
    echo '<div class="options_group">';
    echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>XML feedy</strong> (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">hromadné nastavení</a>)</div>';

    $vynechane_kategorie = "";
    $stav_produktu_kategorie = "";
    $product_categories = wp_get_post_terms( $post->ID, 'product_cat' );
    foreach ( $product_categories as $kategorie_produktu ) {
      $vynechano = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-vynechano', true );
      $stav_produktu = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-stav-produktu', true );
      if ( ! empty ( $vynechano ) ) {
        if ( ! empty ( $vynechane_kategorie ) ) {
          $vynechane_kategorie .= ", ";
        }
        $vynechane_kategorie .= '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
      }
      if ( ! empty ( $stav_produktu ) ) {
        if ( $stav_produktu == 'used' ) {
          $stav_produktu_hodnota = 'Použité (bazar)';
        } else {
          $stav_produktu_hodnota = 'Repasované';
        }
        if ( ! empty ( $stav_produktu_kategorie ) ) {
          $stav_produktu_kategorie .= ", ";
        }
        $stav_produktu_kategorie .= '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>: <strong>' . $stav_produktu_hodnota . '</strong>';
      }
    }
    if ( ! empty ( $vynechane_kategorie ) ) {
      echo '<p class="form-field"><label for="ceske_sluzby_xml_vynechano">Odebrat z XML</label>Není potřeba nic zadávat, protože jsou zcela ignorovány některé kategorie: ' . $vynechane_kategorie . '</p>';
    } else {
      woocommerce_wp_checkbox( 
        array( 
          'id' => 'ceske_sluzby_xml_vynechano', 
          'wrapper_class' => '', // show_if_simple - pouze u jednoduchých produktů
          'label' => 'Odebrat z XML', 
          'description' => 'Po zaškrtnutí nebude produkt zahrnut do žádného z generovaných XML feedů'
        ) 
      );
    }

    if ( ! empty ( $global_data['stav_produktu'] ) ) {
      $stav_produktu_text = 'Není potřeba nic zadávat, protože je na úrovni celého webu <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> hodnota <strong>' . $global_stav_produktu_hodnota . '</strong>.';
      if ( ! empty ( $stav_produktu_kategorie ) ) {
        $stav_produktu_text .= ' Dále je nastaveno na úrovni kategorie ' . $stav_produktu_kategorie . '.';
      }
      $stav_produktu_text .= ' Případná změna na úrovni produktu však bude mít přednost.';
    }
    elseif ( ! empty ( $stav_produktu_kategorie ) ) {
      $stav_produktu_text = 'Není potřeba nic zadávat, protože je nastaveno na úrovni kategorie ' . $stav_produktu_kategorie . '. Případná změna na úrovni produktu však bude mít přednost.';
    } else {
      $stav_produktu_text = 'Zvolte stav produktu (pokud není nový).';
    }
    woocommerce_wp_select(
      array( 
        'id' => 'ceske_sluzby_xml_stav_produktu', 
        'label' => 'Stav produktu',
        'description' => $stav_produktu_text,
        'options' => array(
          '' => '- Vyberte -',
          'used' => 'Použité (bazar)',
          'refurbished' => 'Repasované'
        )
      )
    );
    echo '</div>';

    if ( $xml_feed_heureka == "yes" ) {
      echo '<div class="options_group">'; // hide_if_grouped - skrýt u seskupených produktů
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Heureka</strong> (<a href="http://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/" target="_blank">obecný manuál</a>)</div>';
      if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{PRODUCTNAME}' ) !== false ) {
        woocommerce_wp_text_input(
          array( 
            'id' => 'ceske_sluzby_xml_heureka_productname', 
            'label' => 'Přesný název (<a href="http://sluzby.' . HEUREKA_URL . '/napoveda/povinne-nazvy/" target="_blank">manuál</a>)', 
            'placeholder' => 'PRODUCTNAME',
            'desc_tip' => 'true',
            'description' => 'Zadejte přesný název produktu, pokud chcete aby byl odlišný od aktuálního názvu.' 
          )
        );
        ceske_sluzby_zobrazit_xml_hodnotu( 'ceske_sluzby_xml_heureka_productname', $post->ID, $post, $global_data, false );
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_heureka_product', 
          'label' => 'Doplněný název (<a href="http://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/#PRODUCT" target="_blank">manuál</a>)', 
          'placeholder' => 'PRODUCT',
          'desc_tip' => 'true',
          'description' => 'Zadejte doplněk názvu produktu, mezera je zobrazena automaticky (použito i pro feed Zboží.cz).' 
        )
      );
      $kategorie_heureka = "";
      foreach ( $product_categories as $kategorie_produktu ) {
        $kategorie = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
        if ( ! empty ( $kategorie ) ) {
          if ( empty ( $kategorie_heureka ) ) {
            $kategorie_heureka = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
            $nazev_kategorie_heureka = $kategorie;
          }
        }
      }
      if ( ! empty ( $kategorie_heureka ) ) {
        echo '<p class="form-field"><strong>Upozornění: </strong>Pokud nic nevyplníte, tak bude automaticky použita hodnota na úrovni kategorie ' . $kategorie_heureka . ': <code>' . $nazev_kategorie_heureka . '</code></p>';
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_heureka_kategorie', 
          'label' => 'Kategorie (<a href="http://www.' . HEUREKA_URL . '/direct/xml-export/shops/heureka-sekce.xml" target="_blank">přehled</a>)', 
          'placeholder' => 'CATEGORYTEXT',
          'desc_tip' => 'true',
          'description' => 'Příklad: Elektronika | Počítače a kancelář | Software | Multimediální software' 
        )
      );
      echo '</div>';
    }
    
    if ( $xml_feed_zbozi == "yes" ) {
      echo '<div class="options_group">';
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Zbozi.cz</strong> (<a href="http://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/specifikace-xml-feedu/" target="_blank">obecný manuál</a>)</div>';
      $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
      if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{PRODUCTNAME}' ) !== false ) {
        woocommerce_wp_text_input(
          array( 
            'id' => 'ceske_sluzby_xml_zbozi_productname', 
            'label' => 'Přesný název (<a href="http://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/pravidla-pojmenovani-nabidek/" target="_blank">manuál</a>)', 
            'placeholder' => 'PRODUCTNAME',
            'desc_tip' => 'true',
            'description' => 'Zadejte přesný název produktu, pokud chcete aby byl odlišný od aktuálního názvu.' 
          )
        );
        ceske_sluzby_zobrazit_xml_hodnotu( 'ceske_sluzby_xml_zbozi_productname', $post->ID, $post, $global_data, $custom_labels_array );
      }
      if ( $xml_feed_heureka != "yes" ) {
        woocommerce_wp_text_input(
          array( 
            'id' => 'ceske_sluzby_xml_heureka_product', 
            'label' => 'Doplněný název (<a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/specifikace-xml-feedu/#PRODUCT" target="_blank">manuál</a>)', 
            'placeholder' => 'PRODUCT',
            'desc_tip' => 'true',
            'description' => 'Zadejte doplněk názvu produktu, mezera je zobrazena automaticky..' 
          )
        );
      }
      $kategorie_zbozi = "";
      foreach ( $product_categories as $kategorie_produktu ) {
        $kategorie = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-zbozi-kategorie', true );
        if ( ! empty ( $kategorie ) ) {
          if ( empty ( $kategorie_zbozi ) ) {
            $kategorie_zbozi = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
            $nazev_kategorie_zbozi = $kategorie;
          }
        }
      }
      if ( ! empty ( $kategorie_zbozi ) ) {
        echo '<p class="form-field"><strong>Upozornění: </strong>Pokud nic nevyplníte, tak bude automaticky použita hodnota na úrovni kategorie ' . $kategorie_zbozi . ': <code>' . $nazev_kategorie_zbozi . '</code></p>';
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_zbozi_kategorie', 
          'label' => 'Kategorie (<a href="http://www.zbozi.cz/static/categories.csv" target="_blank">přehled</a>)', 
          'placeholder' => 'CATEGORYTEXT',
          'desc_tip' => 'true',
          'description' => 'Příklad: Počítače | Software | Grafický a video software' 
        )
      );
      echo '</div>';
    }
    echo '</div>';
  }
  
  public function ceske_sluzby_zobrazit_nastaveni_dodaci_doby() {
    global $thepostid;
    $global_dodaci_doba_text = '';
    $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
    $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( false, true );
    $predobjednavka = get_option( 'wc_ceske_sluzby_preorder-aktivace' );

    if ( $aktivace_dodaci_doby == "yes" ) {
      if ( ! empty ( $dodaci_doba ) || $predobjednavka == "yes" ) {
        echo '</div>';
        echo '<div class="options_group">';
        echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>České služby</strong> (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=dodaci-doba">nastavení dodací doby</a>)</div>';
      }

      if ( ! empty ( $dodaci_doba ) ) {
        $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
        if ( ! empty ( $global_dodaci_doba ) || $global_dodaci_doba === '0' ) {
          if ( array_key_exists( $global_dodaci_doba, $dodaci_doba ) ) {
            $global_dodaci_doba_text = ' Globálně máte <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=dodaci-doba">nastaveno</a>: <strong>' . $dodaci_doba[ $global_dodaci_doba ] . '</strong> (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">hodnota</a>: '.$global_dodaci_doba.').';
          } else {
            $global_dodaci_doba_text = ' Pro globální dodací dobu (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">hodnota</a>: '.$global_dodaci_doba.') nemáte <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=dodaci-doba">nastaven</a> žádný speciální text.';
          }
        }
        woocommerce_wp_select(
          array( 
            'id' => 'ceske_sluzby_dodaci_doba', 
            'label' => 'Dodací doba',
            'description' => 'Zvolte dodací dobu pro konkrétní produkt.' . $global_dodaci_doba_text,
            'options' => $dodaci_doba
          )
        );
      }

      if ( $predobjednavka == "yes" ) {
        $datum_predobjednavky = "";
        $datum = get_post_meta( $thepostid, 'ceske_sluzby_xml_preorder_datum', true );
        if ( ! empty ( $datum ) ) {
          $datum_predobjednavky = date_i18n( 'Y-m-d', $datum );
        }
        echo '<p class="form-field ceske_sluzby_xml_preorder_datum_field">
                <label for="ceske_sluzby_xml_preorder_datum">Předobjednávka</label>
                <input type="text" class="short" name="ceske_sluzby_xml_preorder_datum" id="ceske_sluzby_xml_preorder_datum" value="' . esc_attr( $datum_predobjednavky ) . '" placeholder="Požadovaný formát: YYYY-MM-DD" maxlength="10" pattern="[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])" />
                <a href="#" class="cancel_preorder">Zrušit</a>' . wc_help_tip( 'Zadejte datum, kdy bude možné dodat produkt zákazníkovi.' ) . '
              </p>';
      }
    }
  }

  public function ceske_sluzby_zobrazit_nastaveni_ean() {
    global $thepostid;
    $podpora_ean = get_option( 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean' );
    if ( empty ( $podpora_ean ) ) {
      $value = get_post_meta( $thepostid, 'ceske_sluzby_hodnota_ean', true );
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_hodnota_ean', 
          'label' => 'EAN kód',
          'value' => $value, 
          'placeholder' => 'EAN',
          'desc_tip' => 'true',
          'description' => 'Zadejte hodnotu EAN.' 
        )
      );
    }
  }

  public function ceske_sluzby_product_tab_ulozeni( $post_id ) {
    $hodnota_ean_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_hodnota_ean', true );
    if ( isset( $_POST['ceske_sluzby_hodnota_ean'] ) ) {
      $hodnota_ean = $_POST['ceske_sluzby_hodnota_ean'];
      if ( ! empty( $hodnota_ean ) ) {
        if ( ! is_array( $hodnota_ean ) ) {
          update_post_meta( $post_id, 'ceske_sluzby_hodnota_ean', esc_attr( $hodnota_ean ) );
        }
      } elseif ( ! empty( $hodnota_ean_ulozeno ) ) {
        delete_post_meta( $post_id, 'ceske_sluzby_hodnota_ean' );
      }
    }

    $ukladana_data = array(
      'ceske_sluzby_xml_heureka_productname',
      'ceske_sluzby_xml_heureka_product',
      'ceske_sluzby_xml_heureka_kategorie',
      'ceske_sluzby_xml_zbozi_productname',
      'ceske_sluzby_xml_zbozi_kategorie'
    );
    foreach ( $ukladana_data as $key ) {
      $value = $_POST[ $key ];
      if ( isset( $value ) ) {
        $ulozeno = get_post_meta( $post_id, $key, true );
        if ( ! empty( $value ) ) {
          update_post_meta( $post_id, $key, esc_attr( $value ) );
        } elseif ( ! empty( $ulozeno ) ) {
          delete_post_meta( $post_id, $key );
        }
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

    $dodaci_doba = $_POST['ceske_sluzby_dodaci_doba'];
    $dodaci_doba_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_dodaci_doba', true );
    if ( is_array( $dodaci_doba_ulozeno ) ) {
      delete_post_meta( $post_id, 'ceske_sluzby_dodaci_doba' );
    }
    if ( ! empty ( $dodaci_doba ) || (string)$dodaci_doba === '0' ) {
      if ( ! is_array( $dodaci_doba ) ) {
        update_post_meta( $post_id, 'ceske_sluzby_dodaci_doba', $dodaci_doba );
      }
    } elseif ( ! empty( $dodaci_doba_ulozeno ) || (string)$dodaci_doba_ulozeno === '0' ) {
      delete_post_meta( $post_id, 'ceske_sluzby_dodaci_doba' );
    }

    $datum_predobjednavky_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_xml_preorder_datum', true );
    if ( isset( $_POST['ceske_sluzby_xml_preorder_datum'] ) ) {
      $datum_predobjednavky = $_POST['ceske_sluzby_xml_preorder_datum'];
      if ( ! empty( $datum_predobjednavky ) ) {
        update_post_meta( $post_id, 'ceske_sluzby_xml_preorder_datum', strtotime( $datum_predobjednavky ) );
      } elseif ( ! empty( $datum_predobjednavky_ulozeno ) ) {
        delete_post_meta( $post_id, 'ceske_sluzby_xml_preorder_datum' );
      }
    }

    $stav_produktu = $_POST['ceske_sluzby_xml_stav_produktu'];
    $stav_produktu_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_xml_stav_produktu', true );
    if ( ! empty ( $stav_produktu ) ) {
      update_post_meta( $post_id, 'ceske_sluzby_xml_stav_produktu', $stav_produktu );
    } elseif ( ! empty ( $stav_produktu_ulozeno ) ) {
      delete_post_meta( $post_id, 'ceske_sluzby_xml_stav_produktu' );
    }
  }
}