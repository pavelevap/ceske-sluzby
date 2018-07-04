<?php
class WC_Product_Tab_Ceske_Sluzby_Admin {

  public function __construct() {
    if ( is_admin() ) {
      $xml_feed = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
      if ( $xml_feed == "yes" ) {
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'ceske_sluzby_product_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'ceske_sluzby_product_tab_obsah' ) );
      }
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
    $xml_feed_glami = get_option( 'wc_ceske_sluzby_xml_feed_glami-aktivace' );
    if ( ! empty( $global_data['stav_produktu'] ) ) {
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
    $extra_message_kategorie_odkaz = "";
    $extra_message_kategorie_array = array();
    $product_categories = wp_get_post_terms( $post->ID, 'product_cat' );
    foreach ( $product_categories as $kategorie_produktu ) {
      $vynechano = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-vynechano', true );
      $stav_produktu = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-stav-produktu', true );
      $kategorie_extra_message_ulozeno = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-zbozi-extra-message', true );
      if ( ! empty( $vynechano ) ) {
        if ( ! empty( $vynechane_kategorie ) ) {
          $vynechane_kategorie .= ", ";
        }
        $vynechane_kategorie .= '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
      }
      if ( ! empty( $stav_produktu ) ) {
        if ( $stav_produktu == 'used' ) {
          $stav_produktu_hodnota = 'Použité (bazar)';
        } else {
          $stav_produktu_hodnota = 'Repasované';
        }
        if ( ! empty( $stav_produktu_kategorie ) ) {
          $stav_produktu_kategorie .= ", ";
        }
        $stav_produktu_kategorie .= '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>: <strong>' . $stav_produktu_hodnota . '</strong>';
      }
      if ( ! empty( $kategorie_extra_message_ulozeno ) ) {
        if ( ! empty( $extra_message_kategorie_odkaz ) ) {
          $extra_message_kategorie_odkaz .= ", ";
        }
        $extra_message_kategorie_odkaz .= '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
        foreach ( $kategorie_extra_message_ulozeno as $key => $value ) {
          $extra_message_kategorie_array[] = $key;
        }
      }
    }
    if ( ! empty( $vynechane_kategorie ) ) {
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

    if ( ! empty( $global_data['stav_produktu'] ) ) {
      $stav_produktu_text = 'Není potřeba nic zadávat, protože je na úrovni celého webu <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> hodnota <strong>' . $global_stav_produktu_hodnota . '</strong>.';
      if ( ! empty( $stav_produktu_kategorie ) ) {
        $stav_produktu_text .= ' Dále je nastaveno na úrovni kategorie ' . $stav_produktu_kategorie . '.';
      }
      $stav_produktu_text .= ' Případná změna na úrovni produktu však bude mít přednost.';
    }
    elseif ( ! empty( $stav_produktu_kategorie ) ) {
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
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Heureka</strong> (<a href="https://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/" target="_blank">obecný manuál</a>)</div>';
      if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{PRODUCTNAME}' ) !== false ) {
        woocommerce_wp_text_input(
          array( 
            'id' => 'ceske_sluzby_xml_heureka_productname', 
            'label' => 'Přesný název (<a href="https://sluzby.' . HEUREKA_URL . '/napoveda/povinne-nazvy/" target="_blank">manuál</a>)', 
            'placeholder' => 'PRODUCTNAME',
            'desc_tip' => 'true',
            'description' => 'Zadejte přesný název produktu, pokud chcete aby byl odlišný od aktuálního názvu.' 
          )
        );
        ceske_sluzby_zobrazit_xml_hodnotu( 'ceske_sluzby_xml_heureka_productname', $post->ID, $post, 'ceske-sluzby-xml-heureka-productname', $global_data, false );
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_heureka_product', 
          'label' => 'Doplněný název (<a href="https://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/#PRODUCT" target="_blank">manuál</a>)', 
          'placeholder' => 'PRODUCT',
          'desc_tip' => 'true',
          'description' => 'Zadejte doplněk názvu produktu, mezera je zobrazena automaticky (použito i pro feed Zboží.cz).' 
        )
      );
      $kategorie_heureka = "";
      foreach ( $product_categories as $kategorie_produktu ) {
        $kategorie = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-heureka-kategorie', true );
        if ( ! empty( $kategorie ) ) {
          if ( empty( $kategorie_heureka ) ) {
            $kategorie_heureka = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
            $nazev_kategorie_heureka = $kategorie;
          }
        }
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_heureka_kategorie', 
          'label' => 'Kategorie (<a href="https://www.' . HEUREKA_URL . '/direct/xml-export/shops/heureka-sekce.xml" target="_blank">přehled</a>)', 
          'placeholder' => 'CATEGORYTEXT',
          'desc_tip' => 'true',
          'description' => 'Příklad: Elektronika | Počítače a kancelář | Software | Multimediální software' 
        )
      );
      if ( ! empty( $kategorie_heureka ) ) {
        echo '<p class="form-field"><strong>Upozornění: </strong>Pokud nic nevyplníte, tak bude automaticky použita hodnota na úrovni kategorie ' . $kategorie_heureka . ': <code>' . $nazev_kategorie_heureka . '</code></p>';
      }
      echo '</div>';
    }

    if ( $xml_feed_zbozi == "yes" ) {
      echo '<div class="options_group">';
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Zbozi.cz</strong> (<a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/specifikace-xml-feedu/" target="_blank">obecný manuál</a>)</div>';
      $custom_labels_array = ceske_sluzby_xml_ziskat_dodatecna_oznaceni_nabidky();
      if ( empty( $global_data['nazev_produktu'] ) || strpos( $global_data['nazev_produktu'], '{PRODUCTNAME}' ) !== false ) {
        woocommerce_wp_text_input(
          array( 
            'id' => 'ceske_sluzby_xml_zbozi_productname', 
            'label' => 'Přesný název (<a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/pravidla-pojmenovani-nabidek/" target="_blank">manuál</a>)', 
            'placeholder' => 'PRODUCTNAME',
            'desc_tip' => 'true',
            'description' => 'Zadejte přesný název produktu, pokud chcete aby byl odlišný od aktuálního názvu.' 
          )
        );
        ceske_sluzby_zobrazit_xml_hodnotu( 'ceske_sluzby_xml_zbozi_productname', $post->ID, $post, 'ceske-sluzby-xml-zbozi-productname', $global_data, $custom_labels_array );
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
        if ( ! empty( $kategorie ) ) {
          if ( empty( $kategorie_zbozi ) ) {
            $kategorie_zbozi = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
            $nazev_kategorie_zbozi = $kategorie;
          }
        }
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_zbozi_kategorie', 
          'label' => 'Kategorie (<a href="https://www.zbozi.cz/static/categories.csv" target="_blank">přehled</a>)', 
          'placeholder' => 'CATEGORYTEXT',
          'desc_tip' => 'true',
          'description' => 'Příklad: Počítače | Software | Grafický a video software' 
        )
      );
      if ( ! empty( $kategorie_zbozi ) ) {
        echo '<p class="form-field"><strong>Upozornění: </strong>Pokud nic nevyplníte, tak bude automaticky použita hodnota na úrovni kategorie ' . $kategorie_zbozi . ': <code>' . $nazev_kategorie_zbozi . '</code></p>';
      }
      $extra_message_aktivace = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
      if ( ! empty( $extra_message_aktivace ) ) {
        $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
        foreach ( $extra_message_aktivace as $extra_message ) {
          if ( ! empty( $extra_message_kategorie_odkaz ) && ! empty( $extra_message_kategorie_array ) && in_array( $extra_message, $extra_message_kategorie_array ) ) {
            if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) {
              echo '<p class="form-field"><label for="ceske_sluzby_xml_zbozi_extra_message[' . $extra_message . ']">' . $extra_message_array[ $extra_message ] . '</label>Není potřeba nic zadávat, protože na úrovni kategorie ' . $extra_message_kategorie_odkaz . ' i eshopu je tato informace <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.';
            } else {
              echo '<p class="form-field"><label for="ceske_sluzby_xml_zbozi_extra_message[' . $extra_message . ']">' . $extra_message_array[ $extra_message ] . '</label>Není potřeba nic zadávat, protože je tato informace nastavena na úrovni kategorie ' . $extra_message_kategorie_odkaz . '.';
            }
          } else {
            if ( array_key_exists( $extra_message, $global_data['extra_message'] ) ) {
              echo '<p class="form-field"><label for="ceske_sluzby_xml_zbozi_extra_message[' . $extra_message . ']">' . $extra_message_array[ $extra_message ] . '</label>Není potřeba nic zadávat, protože na úrovni eshopu je tato informace <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastavena</a> globálně pro všechny produkty.';
            } else {
              $extra_message_text = 'Po zaškrtnutí bude produkt označen příslušnou doplňkovou informací. Na úrovni eshopu ani kategorie zatím není nic <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">nastaveno</a>.';
              $value = "";
              $produkt_extra_message_ulozeno = get_post_meta( $post->ID, 'ceske_sluzby_xml_zbozi_extra_message', true );
              if ( ! empty( $produkt_extra_message_ulozeno ) && array_key_exists( $extra_message, $produkt_extra_message_ulozeno ) ) {
                $value = $produkt_extra_message_ulozeno[ $extra_message ];
              }
              woocommerce_wp_checkbox( 
                array( 
                  'id' => 'ceske_sluzby_xml_zbozi_extra_message[' . $extra_message . ']',
                  'value' => $value,
                  'cbvalue' => 'yes', 
                  'wrapper_class' => '',
                  'label' => $extra_message_array[ $extra_message ], 
                  'description' => $extra_message_text
                ) 
              );
            }
          }
        }
      }
      echo '</div>';
    }

    if ( $xml_feed_glami == "yes" ) {
      echo '<div class="options_group">'; // hide_if_grouped - skrýt u seskupených produktů
      echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>Glami</strong> (<a href="https://info.' . GLAMI_URL . '/feed/" target="_blank">obecný manuál</a>)</div>';
      $kategorie_glami = "";
      foreach ( $product_categories as $kategorie_produktu ) {
        $kategorie = get_woocommerce_term_meta( $kategorie_produktu->term_id, 'ceske-sluzby-xml-glami-kategorie', true );
        if ( ! empty( $kategorie ) ) {
          if ( empty( $kategorie_glami ) ) {
            $kategorie_glami = '<a href="' . admin_url(). 'edit-tags.php?action=edit&taxonomy=product_cat&tag_ID=' . $kategorie_produktu->term_id . '">' . $kategorie_produktu->name . '</a>';
            $nazev_kategorie_glami = $kategorie;
          }
        }
      }
      woocommerce_wp_text_input(
        array( 
          'id' => 'ceske_sluzby_xml_glami_kategorie', 
          'label' => 'Kategorie (<a href="https://www.' . GLAMI_URL . '/category-xml/" target="_blank">přehled</a>)', 
          'placeholder' => 'CATEGORYTEXT',
          'desc_tip' => 'true',
          'description' => 'Příklad: Dámské oblečení a obuv | Dámské boty | Dámské outdoorové boty' 
        )
      );
      if ( ! empty( $kategorie_glami ) ) {
        echo '<p class="form-field"><strong>Upozornění: </strong>Pokud nic nevyplníte, tak bude automaticky použita hodnota na úrovni kategorie ' . $kategorie_glami . ': <code>' . $nazev_kategorie_glami . '</code></p>';
      }
      echo '</div>';
    }
    echo '</div>';
  }

  public function ceske_sluzby_zobrazit_nastaveni_dodaci_doby() {
    global $thepostid;
    $global_dodaci_doba_text = '';
    $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );

    if ( $aktivace_dodaci_doby == "yes" ) {
      $dodaci_doba = ceske_sluzby_zpracovat_dodaci_dobu_produktu( false, true );
      $predobjednavka = get_option( 'wc_ceske_sluzby_preorder-aktivace' );
      if ( ! empty( $dodaci_doba ) || $predobjednavka == "yes" ) {
        echo '</div>';
        echo '<div class="options_group">';
        echo '<div class="nadpis" style="margin-left: 12px; margin-top: 10px;"><strong>České služby</strong> (<a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=dodaci-doba">nastavení dodací doby</a>)</div>';
      }

      if ( ! empty( $dodaci_doba ) ) {
        $global_dodaci_doba = get_option( 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba' );
        if ( ! empty( $global_dodaci_doba ) || $global_dodaci_doba === '0' ) {
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
        if ( ! empty( $datum ) ) {
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
    if ( empty( $podpora_ean ) ) {
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

    $ukladana_data_text = array(
      'ceske_sluzby_xml_heureka_productname',
      'ceske_sluzby_xml_heureka_product',
      'ceske_sluzby_xml_heureka_kategorie',
      'ceske_sluzby_xml_zbozi_productname',
      'ceske_sluzby_xml_zbozi_kategorie',
      'ceske_sluzby_xml_glami_kategorie',
      'ceske_sluzby_xml_stav_produktu',
      'ceske_sluzby_xml_preorder_datum'
    );
    foreach ( $ukladana_data_text as $key ) {
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        $ulozeno_text = get_post_meta( $post_id, $key, true );
        if ( ! empty( $value ) ) {
          if ( $key == 'ceske_sluzby_xml_preorder_datum' ) {
            $value = strtotime( $value );
          }
          update_post_meta( $post_id, $key, esc_attr( $value ) );
        } elseif ( ! empty( $ulozeno_text ) ) {
          delete_post_meta( $post_id, $key );
        }
      }
    }

    $ukladana_data_checkbox = array(
      'ceske_sluzby_xml_vynechano',
      'ceske_sluzby_xml_zbozi_extra_message'
    );
    foreach ( $ukladana_data_checkbox as $key ) {
      $ulozeno_checkbox = get_post_meta( $post_id, $key, true );
      if ( isset( $_POST[ $key ] ) ) {
        $value = $_POST[ $key ];
        if ( ! empty( $value ) ) {
          update_post_meta( $post_id, $key, $value );
        }
      } elseif ( ! empty( $ulozeno_checkbox ) ) {
        delete_post_meta( $post_id, $key );
      }
    }

    if ( isset( $_POST['ceske_sluzby_dodaci_doba'] ) ) {
      $dodaci_doba = $_POST['ceske_sluzby_dodaci_doba'];
      $dodaci_doba_ulozeno = get_post_meta( $post_id, 'ceske_sluzby_dodaci_doba', true );
      if ( is_array( $dodaci_doba_ulozeno ) ) {
        delete_post_meta( $post_id, 'ceske_sluzby_dodaci_doba' );
      }
      if ( ! empty( $dodaci_doba ) || (string)$dodaci_doba === '0' ) {
        if ( ! is_array( $dodaci_doba ) ) {
          update_post_meta( $post_id, 'ceske_sluzby_dodaci_doba', $dodaci_doba );
        }
      } elseif ( ! empty( $dodaci_doba_ulozeno ) || (string)$dodaci_doba_ulozeno === '0' ) {
        delete_post_meta( $post_id, 'ceske_sluzby_dodaci_doba' );
      }
    }
  }
}