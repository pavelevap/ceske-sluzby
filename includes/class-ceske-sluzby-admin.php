<?php
// https://docs.woothemes.com/document/adding-a-section-to-a-settings-tab/
class WC_Settings_Tab_Ceske_Sluzby_Admin {

  public static function init() {
    add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 100 );
    add_action( 'woocommerce_settings_tabs_ceske-sluzby', __CLASS__ . '::settings_tab' );
    add_action( 'woocommerce_update_options_ceske-sluzby', __CLASS__ . '::update_settings' );
    add_action( 'woocommerce_sections_ceske-sluzby', __CLASS__ . '::output_sections' );
    add_filter( 'woocommerce_admin_settings_sanitize_option', __CLASS__ . '::admin_settings_sanitize_option', 10, 3 );
    add_action( 'woocommerce_admin_field_upload', __CLASS__ . '::ceske_sluzby_upload_button_settings_field' );
    add_action( 'woocommerce_update_options_checkout', __CLASS__ . '::ceske_sluzby_nastaveni_plateb' );
    add_action( 'wp_loaded', __CLASS__ . '::ceske_sluzby_nastaveni_plateb', 100 );
    add_action( 'woocommerce_sections_shipping', __CLASS__ . '::ceske_sluzby_nastaveni_pokladna_doprava' );
    add_action( 'wp_loaded', __CLASS__ . '::ceske_sluzby_nastaveni_pokladna_doprava', 100 );
    add_filter( 'woocommerce_get_settings_checkout', __CLASS__ . '::ceske_sluzby_nastaveni_pokladna' );
    add_action( 'woocommerce_settings_tabs_shipping', __CLASS__ . '::settings_tab_shipping' );
    add_action( 'woocommerce_update_options_shipping', __CLASS__ . '::update_settings_shipping' );
  }

  public static function ceske_sluzby_ziskat_aktivovane_platebni_metody() {
    $available_gateways = array();
    if ( ! is_null( WC()->payment_gateways ) ) {
      $gateways = WC()->payment_gateways->payment_gateways();
      if ( ! empty( $gateways ) ) {
        foreach ( $gateways as $gateway ) {
          if ( $gateway->is_available() || ( isset( $gateway->enabled ) && $gateway->enabled == 'yes' ) ) {
            $available_gateways[ $gateway->id ] = $gateway;
          }
        }
      }
    }
    return $available_gateways;
  }

  public static function ceske_sluzby_ziskat_aktualni_dopravu( $aktualni_filtr ) {
    $shipping_method = '';
    if ( ! empty( $aktualni_filtr ) ) {
      if ( strpos( $aktualni_filtr, 'woocommerce_shipping_instance_form_fields_' ) !== false ) {
        $shipping_method = str_replace( 'woocommerce_shipping_instance_form_fields_', '', $aktualni_filtr );
      } elseif ( strpos( $aktualni_filtr, 'woocommerce_settings_api_form_fields_' ) !== false ) {
        $shipping_method = str_replace( 'woocommerce_settings_api_form_fields_', '', $aktualni_filtr );
      }
    }
    return $shipping_method;
  }

  public static function ceske_sluzby_ziskat_povolenou_dopravu_cod() {
    $cod_shipping = array();
    $available_gateways = self::ceske_sluzby_ziskat_aktivovane_platebni_metody();
    if ( array_key_exists( 'cod', $available_gateways ) && isset( $available_gateways['cod']->enable_for_methods ) ) {
      $cod_shipping = $available_gateways['cod']->enable_for_methods;
    }
    return $cod_shipping;
  }

  public static function ceske_sluzby_zobrazeni_pokladna_doprava( $form_fields ) {
    $available_gateways = self::ceske_sluzby_ziskat_aktivovane_platebni_metody();
    $moznosti_nastaveni = get_option( 'wc_ceske_sluzby_nastaveni_doprava' );
    $options = self::dostupne_nastaveni( 'doprava' );
    $zobrazeny_uvod = false;
    $aktualni_filtr = current_filter();
    if ( ! empty( $moznosti_nastaveni ) && is_array( $moznosti_nastaveni ) ) {
      $form_fields['wc_ceske_sluzby_nastaveni_pokladna_doprava_title'] = array(
        'title' => 'České služby',
        'type' => 'title',
        'default' => ''
      );
      $zobrazeny_uvod = true;
      $settings = array();
      $shipping_method = self::ceske_sluzby_ziskat_aktualni_dopravu( $aktualni_filtr );
      $cod_shipping = self::ceske_sluzby_ziskat_povolenou_dopravu_cod();
      foreach ( $available_gateways as $gateway_id => $gateway ) {
        if ( $gateway_id != 'cod' || empty( $cod_shipping ) || ( $gateway_id == 'cod' && ! empty( $cod_shipping ) && in_array( $shipping_method, $cod_shipping ) ) ) {
          $settings[$gateway_id] = $gateway->title;
        }
      }
      if ( in_array( 'platebni_metody', $moznosti_nastaveni ) && array_key_exists( 'platebni_metody', $options ) ) {
        $form_fields['ceske_sluzby_platebni_metody'] = array(
          'title' => 'Odstranit platební metody',
          'type' => 'multiselect',
          'options' => $settings,
          'class' => 'wc-enhanced-select',
          'default' => 'no'
        );
        if ( empty( $settings ) ) {
          $form_fields['ceske_sluzby_platebni_metody']['description'] = 'Žádné platební metody nejsou dostupné.';
        } else {
          $form_fields['ceske_sluzby_platebni_metody']['description'] = 'Zvolte platební metody, které nebudou nadále dostupné.';
        }
      }
    }
    $moznosti_nastaveni = get_option( 'wc_ceske_sluzby_nastaveni_pokladna_doprava' );
    $options = self::dostupne_nastaveni( 'pokladna_doprava' );
    if ( ! empty( $moznosti_nastaveni ) && is_array( $moznosti_nastaveni ) ) {
      if ( ! $zobrazeny_uvod ) {
        $form_fields['wc_ceske_sluzby_nastaveni_pokladna_doprava_title'] = array(
          'title' => 'České služby',
          'type' => 'title',
          'default' => ''
        );
        $shipping_method = self::ceske_sluzby_ziskat_aktualni_dopravu( $aktualni_filtr );
        $cod_shipping = self::ceske_sluzby_ziskat_povolenou_dopravu_cod();
      }
      foreach ( $moznosti_nastaveni as $moznost_nastaveni ) {
        foreach ( $available_gateways as $gateway_id => $gateway ) {
          if ( $gateway_id != 'cod' || empty( $cod_shipping ) || ( $gateway_id == 'cod' && ! empty( $cod_shipping ) && in_array( $shipping_method, $cod_shipping ) ) ) {
            if ( $moznost_nastaveni == 'poplatek_platba' && array_key_exists( 'poplatek_platba', $options ) ) {
              $form_fields['ceske_sluzby_poplatek_platba_' . $gateway_id ] = array(
                'title' => 'Poplatek za platbu (' . $gateway->title . ')',
                'type' => 'text',
                'description' => 'Doplňte cenu poplatku za použitou platební metodu. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_doprava_poplatek_platba' ),
              );
            }
            if ( $moznost_nastaveni == 'eet_format' && array_key_exists( 'eet_format', $options ) ) {
              $form_fields['ceske_sluzby_eet_format_' . $gateway_id ] = array(
                'title' => 'EET: Formát účtenky (' . $gateway->title . ')',
                'type' => 'select',
                'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_format' ),
                'css' => 'width: 300px',
                'default' => 'no',
                'description' => 'Formát elektronické účtenky. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_eet_format' ),
              );
            }
            if ( in_array( 'eet_podminka', $moznosti_nastaveni ) && array_key_exists( 'eet_podminka', $options ) ) {
              $form_fields['ceske_sluzby_eet_podminka_' . $gateway_id ] = array(
                'title' => 'EET: Podmínka odeslání (' . $gateway->title . ')',
                'type' => 'select',
                'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_podminka' ),
                'css' => 'width: 300px',
                'default' => 'no',
                'description' => 'Podmínka pro automatické odeslání elektronické účtenky. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_eet_podminka' ),
              );
            }
          }
        }
      }
    }
    return $form_fields;
  }

  public static function output_sections() {
    // Neduplikovat do budoucna tuto funkci...
    global $current_section;
    $aktivace_xml = get_option( 'wc_ceske_sluzby_heureka_xml_feed-aktivace' );
    $aktivace_certifikatu = get_option( 'wc_ceske_sluzby_heureka_certifikat_spokojenosti-aktivace' );
    $aktivace_dodaci_doby = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace' );
    $aktivace_eet = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
    $sections = array(
      '' => 'Základní nastavení'
    );
    if ( $aktivace_xml == "yes" ) {
      $sections['xml-feed'] = 'XML feed';
    }
    if ( $aktivace_certifikatu == "yes" ) {
      $sections['certifikat-spokojenosti'] = 'Certifikát spokojenosti';
    }
    if ( $aktivace_dodaci_doby == "yes" ) {
      $sections['dodaci-doba'] = 'Dodací doba';
    }
    if ( $aktivace_eet == "yes" ) {
      $sections['eet'] = 'EET';
    }
    if ( empty( $sections ) ) {
      return;
    }
    echo '<ul class="subsubsub">';
    $array_keys = array_keys( $sections );
    foreach ( $sections as $id => $label ) {
      echo '<li><a href="' . admin_url( 'admin.php?page=wc-settings&tab=ceske-sluzby&section=' . sanitize_title( $id ) ) . '" class="' . ( $current_section == $id ? 'current' : '' ) . '">' . $label . '</a> ' . ( end( $array_keys ) == $id ? '' : '|' ) . ' </li>';
    }
    echo '</ul><br class="clear" />';
  }

  public static function add_settings_tab( $settings_tabs ) {
    $settings_tabs['ceske-sluzby'] = 'České služby';
    return $settings_tabs;
  }

  public static function settings_tab() {
    global $current_section;
    $settings = self::get_settings( $current_section );
    woocommerce_admin_fields( $settings );
  }

  public static function settings_tab_shipping() {
    global $current_section;
    $settings = self::get_settings_shipping( $current_section );
    if ( ! empty( $settings ) ) {
      woocommerce_admin_fields( $settings );
    }
  }

  public static function update_settings() {
    global $current_section;
    woocommerce_update_options( self::get_settings( $current_section ) );
  }

  public static function update_settings_shipping() {
    global $current_section;
    woocommerce_update_options( self::get_settings_shipping( $current_section ) );
  }

  public static function admin_settings_sanitize_option( $value, $option, $raw_value ) {
    if ( 'wc_ceske_sluzby_dodaci_doba_format_zobrazeni' == $option['id'] || 'wc_ceske_sluzby_preorder_format_zobrazeni' == $option['id'] || 'wc_ceske_sluzby_dodatecne_produkty_format_zobrazeni' == $option['id'] ) {
      $value = wp_kses( $raw_value, wp_kses_allowed_html( 'post' ) );
    }
    return $value; 
  }

  public static function get_settings_shipping( $current_section = '' ) {
    global $current_section, $hide_save_button;
    $settings = array();
    if ( '' == $current_section && ! isset( $_GET['zone_id'] ) && ! isset( $_GET['instance_id'] ) ) {
      $hide_save_button = false;
      $settings[] = array(
        'title' => 'České služby',
        'type' => 'title',
        'id' => 'wc_ceske_sluzby_nastaveni_pokladna_doprava_title',
      );
      $options = self::dostupne_nastaveni( 'doprava' );
      if ( ! empty( $options ) ) {
        $settings[] = array(
          'title' => 'Dopravní metody',
          'type' => 'multiselect',
          'desc' => 'Zvolte podporované funkce, které můžete následně nastavit <strong>na úrovni jednotlivých dopravních metod</strong>.',
          'id' => 'wc_ceske_sluzby_nastaveni_doprava',
          'class' => 'wc-enhanced-select',
          'options' => $options,
          'custom_attributes' => array(
            'data-placeholder' => 'Nastavení na úrovni dopravních metod'
          )
        );
      }
      $options = self::dostupne_nastaveni( 'pokladna_doprava' );
      if ( ! empty( $options ) ) {
        $settings[] = array(
          'title' => 'Kombinace dopravních a platebních metod',
          'type' => 'multiselect',
          'desc' => 'Zvolte podporované funkce, které můžete následně nastavit <strong>v kombinaci jednotlivých platebních a dopravních metod</strong>.',
          'id' => 'wc_ceske_sluzby_nastaveni_pokladna_doprava',
          'class' => 'wc-enhanced-select',
          'options' => $options,
          'custom_attributes' => array(
            'data-placeholder' => 'Nastavení kombinace platebních a dopravních metod'
          )
        );
      }
      $settings[] = array(
        'type' => 'sectionend',
        'id' => 'wc_ceske_sluzby_nastaveni_pokladna_doprava_title'
      );
    }
    return $settings;
  }

  public static function moznosti_nastaveni( $settings ) {
    $options = array();
    if ( $settings == 'wc_ceske_sluzby_eet_format' ) {
      $options = array(
        '' => '- Vyberte -',
        'email-completed' => 'Doplnit do emailu (dokončená objednávka)',
        'email-processing' => 'Doplnit do emailu (zaplacená objednávka)',
        'email-faktura' => 'Doplnit do emailu (faktura)',
      );
      // WooCommerce PDF Invoices & Packing Slips
      if ( class_exists( 'WooCommerce_PDF_Invoices' ) ) {
        $options['faktura-plugin'] = 'Doplnit v rámci faktury (externí plugin)';
      }
    }
    if ( $settings == 'wc_ceske_sluzby_eet_podminka' ) {
      $options = array(
        '' => '- Vyberte -',
        'manual' => 'Odesílat pouze ručně',
        'dokonceno' => 'Dokončená objednávka',
        'platba' => 'Provedená platba'
      );
    }
    if ( $settings == 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni' ) {
      $options = array(
        '' => '- Vyberte -',
        'nahoru' => 'Zaokrouhlit nahoru'
      );
    }
    return $options;
  }

  public static function dostupne_nastaveni( $type ) {
    $options = array();
    $reverse_type = '';
    $aktivace_eet = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace' );
    if ( $type == 'pokladna' || $type == 'pokladna_doprava' ) {
      if ( $aktivace_eet == "yes" ) {
        $options = array(
          'eet_format' => 'EET: Formát účtenky',
          'eet_podminka' => 'EET: Podmínka odeslání'
        );
      }
      $options = array(
        'poplatek_platba' => 'Poplatek za platbu',
      );
    }
    if ( $type == 'pokladna' ) {
      $options['zaokrouhlovani'] = 'Zaokrouhlování celkové ceny objednávky';
    }
    if ( $type == 'doprava' ) {
      $options['platebni_metody'] = 'Dostupné platební metody';
    }
    if ( $type == 'pokladna' ) {
      $reverse_type = 'pokladna_doprava';
    }
    if ( $type == 'pokladna_doprava' ) {
      $reverse_type = 'pokladna';
    }
    if ( ! empty( $reverse_type ) ) {
      $dalsi_moznosti = get_option( 'wc_ceske_sluzby_nastaveni_' . $reverse_type );
      if ( ! empty( $dalsi_moznosti ) && is_array( $dalsi_moznosti ) ) {
        foreach ( $dalsi_moznosti as $id ) {
          if ( array_key_exists( $id, $options ) ) {
            unset( $options[$id] );
          }
        }
      }
    }
    return $options;
  }

  public static function zobrazit_zvolene_nastaveni( $settings ) {
    $description = 'Na úrovni eshopu zatím není nic nastaveno.';
    $zvolene_nastaveni = get_option( $settings );
    if ( ! empty( $zvolene_nastaveni ) ) {
      $mozne_nastaveni_array = self::moznosti_nastaveni( $settings );
      if ( ! empty( $mozne_nastaveni_array ) ) {
        if ( array_key_exists( $zvolene_nastaveni, $mozne_nastaveni_array ) ) {
          $nastaveni = $mozne_nastaveni_array[$zvolene_nastaveni];
        }
      } else {
        $nastaveni = $zvolene_nastaveni;
      }
      $description = 'Na úrovni eshopu je nastaveno: <strong>' . $nastaveni . '</strong>.';
    }
    return $description;
  }

  public static function ceske_sluzby_nastaveni_pokladna( $settings ) {
    $options = self::dostupne_nastaveni( 'pokladna' );
    if ( ! empty( $options ) ) {
      $settings[] = array(
        'title' => 'České služby',
        'type' => 'title',
        'id' => 'wc_ceske_sluzby_nastaveni_pokladna_title',
      );
      $settings[] = array(
        'title' => 'Platební metody',
        'type' => 'multiselect',
        'desc' => 'Zvolte podporované funkce, které můžete následně nastavit <strong>na úrovni jednotlivých platebních metod</strong>.',
        'id' => 'wc_ceske_sluzby_nastaveni_pokladna',
        'class' => 'wc-enhanced-select',
        'options' => $options,
        'custom_attributes' => array(
          'data-placeholder' => 'Nastavení na úrovni platebních metod'
        )
      );
      $settings[] = array(
        'type' => 'sectionend',
        'id' => 'wc_ceske_sluzby_nastaveni_pokladna_title',
      );
    }
    return $settings;
  }

  public static function ceske_sluzby_nastaveni_plateb() {
    $form_fields = array();
    $moznosti_nastaveni = get_option( 'wc_ceske_sluzby_nastaveni_pokladna' );
    $options = self::dostupne_nastaveni( 'pokladna' );
    if ( ! empty( $moznosti_nastaveni ) && is_array( $moznosti_nastaveni ) ) {
      $available_gateways = self::ceske_sluzby_ziskat_aktivovane_platebni_metody();
      foreach ( $available_gateways as $gateway_id => $gateway ) {
        $form_fields['wc_ceske_sluzby_nastaveni_pokladna_doprava_title'] = array(
          'title' => 'České služby',
          'type' => 'title',
        );
        if ( in_array( 'poplatek_platba', $moznosti_nastaveni ) ) {
          $form_fields['ceske_sluzby_poplatek_platba'] = array(
            'title' => 'Poplatek za platbu',
            'type' => 'text',
            'description' => 'Doplňte cenu poplatku za použitou platební metodu. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_doprava_poplatek_platba' ),
          );
        }
        if ( in_array( 'zaokrouhlovani', $moznosti_nastaveni ) ) {
          $form_fields['ceske_sluzby_zaokrouhleni'] = array(
            'title' => 'Zaokrouhlování',
            'type' => 'select',
            'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni' ),
            'default' => 'no',
            'description' => 'Automatické zaokrouhlení celkové částky objednávky. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni' ),
          );
        }
        if ( in_array( 'eet_format', $moznosti_nastaveni ) && array_key_exists( 'eet_format', $options ) ) {
          $form_fields['ceske_sluzby_eet_format'] = array(
            'title' => 'EET: Formát účtenky',
            'type' => 'select',
            'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_format' ),
            'css' => 'width: 300px',
            'default' => 'no',
            'description' => 'Formát elektronické účtenky. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_eet_format' ),
          );
        }
        if ( in_array( 'eet_podminka', $moznosti_nastaveni ) && array_key_exists( 'eet_podminka', $options ) ) {
          $form_fields['ceske_sluzby_eet_podminka'] = array(
            'title' => 'EET: Podmínka odeslání',
            'type' => 'select',
            'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_podminka' ),
            'css' => 'width: 300px',
            'default' => 'no',
            'description' => 'Podmínka pro automatické odeslání elektronické účtenky. ' . self::zobrazit_zvolene_nastaveni( 'wc_ceske_sluzby_eet_podminka' ),
          );
        }
        $gateway->form_fields += $form_fields;
      }
    }
  }

  public static function ceske_sluzby_nastaveni_pokladna_doprava() {
    $available_shipping = WC()->shipping->load_shipping_methods();
    $available_gateways = self::ceske_sluzby_ziskat_aktivovane_platebni_metody();
    foreach ( $available_shipping as $shipping ) {
      if ( isset( $shipping->supports ) && is_array( $shipping->supports ) && ! empty( $shipping->supports ) ) {
        if ( in_array( 'shipping-zones', $shipping->supports ) && in_array( 'instance-settings-modal', $shipping->supports ) ) {
          add_filter( 'woocommerce_shipping_instance_form_fields_' . $shipping->id, __CLASS__ . '::ceske_sluzby_zobrazeni_pokladna_doprava' );
        } elseif ( in_array( 'settings', $shipping->supports ) ) {
          add_filter( 'woocommerce_settings_api_form_fields_' . $shipping->id, __CLASS__ . '::ceske_sluzby_zobrazeni_pokladna_doprava' );
        }
      }
    }
  }

  public static function ceske_sluzby_upload_button_settings_field( $value ) {
    if ( array_key_exists( 'upload_button', $value ) && ! empty( $value['upload_button'] ) ) {
      $upload_button = $value['upload_button'];
    } else {
      $upload_button = 'Nahrát';
    }
    if ( array_key_exists( 'remove_button', $value ) && ! empty( $value['remove_button'] ) ) {
      $remove_button = $value['remove_button'];
    } else {
      $remove_button = 'Odstranit';
    }
    $description = '';
    $nazev_souboru = '';
    if ( array_key_exists( 'desc', $value ) && ! empty( $value['desc'] ) ) {
      $description = '<span class="description">' . $value['desc'] . '</span>';
    }  
    $selected_value = get_option( $value['id'] );
    $display = 'inline-block';
    $display_remove = 'none';
    if ( ! empty( $selected_value ) ) {
      $display_remove = 'inline-block';
      $display = 'none';
    }
    $button = ' button">' . $upload_button;
    $nazev_souboru = basename( get_attached_file( $selected_value ) ); ?>
    <tr valign="top">
      <th scope="row" class="titledesc">
        <label for="<?php echo esc_attr( $value['id'] ); ?>"><?php echo esc_html( $value['title'] ); ?></label>
      </th>
      <td class="forminp forminp-<?php echo sanitize_title( $value['type'] ) ?>">
        <div>
          <?php if ( ! empty( $nazev_souboru ) ) { ?>
            <span class="nazev-souboru" style="padding-right:10px;"><strong><?php echo $nazev_souboru; ?></strong></span>
          <?php } ?>
          <a href="#" style="display:<?php echo $display; ?>" class="ceske_sluzby_upload_button<?php echo $button; ?></a>
          <input type="hidden" name="<?php echo $value['id']; ?>" id="<?php echo $value['id']; ?>" value="<?php echo $selected_value; ?>" />
          <a href="#" class="ceske_sluzby_remove_button" style="font-size:13px;display:<?php echo $display_remove; ?>"><?php echo $remove_button; ?></a>
        </div>
        <?php echo $description; ?>
      </td>
    </tr><?php
  }

  public static function get_settings( $current_section = '' ) {
    global $current_section;

    if ( '' == $current_section ) {
      $settings = array(
        array(
          'title' => 'Služby pro WordPress',
          'type' => 'title',
          'desc' => 'Pokud nebude konkrétní hodnota vyplněna, tak se nebude příslušná služba vůbec spouštět.',
          'id' => 'wc_ceske_sluzby_title'
        ),
        array(
          'title' => 'Heureka.cz (.sk)',
          'type' => 'title',
          'desc' => '',
          'id' => 'wc_ceske_sluzby_heureka_title'
        ),
        array(
          'title' => 'API klíč: Ověřeno zákazníky',
          'type' => 'text',
          'desc' => 'API klíč pro službu Ověřeno zákazníky naleznete <a href="https://sluzby.' . HEUREKA_URL . '/sluzby/certifikat-spokojenosti/">zde</a>.',
          'id' => 'wc_ceske_sluzby_heureka_overeno-api',
          'css' => 'width: 300px'
        ),
        array(
          'title' => 'API klíč: Měření konverzí',
          'type' => 'text',
          'desc' => 'API klíč pro službu Měření konverzí naleznete <a href="https://sluzby.' . HEUREKA_URL . '/obchody/mereni-konverzi/">zde</a>. Heureka může ještě nějaký čas hlásit, že nebyla služba zprovozněna (dokud neproběhne nějaká objednávka zákazníka z Heureky).',
          'id'   => 'wc_ceske_sluzby_heureka_konverze-api',
          'css'   => 'width: 300px'
        ),
        array(
          'title' => 'Aktivovat certifikát',
          'type' => 'checkbox',
          'desc' => 'Nastavení pro zobrazení certifikátu spokojenosti bude po aktivaci dostupné <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=certifikat-spokojenosti">zde</a>. Obchod musí certifikát nejdříve získat, což snadno ověříte <a href="https://sluzby.' . HEUREKA_URL . '/sluzby/certifikat-spokojenosti/">zde</a>',
          'id' => 'wc_ceske_sluzby_heureka_certifikat_spokojenosti-aktivace'
        ),
        array(
          'title' => 'Aktivovat XML feed',
          'type' => 'checkbox',
          'desc' => 'Nastavení pro XML feed bude po aktivaci dostupné <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=xml-feed">zde</a>.',
          'id' => 'wc_ceske_sluzby_heureka_xml_feed-aktivace'
        ),
        array(
          'title' => 'Aktivovat zobrazení recenzí',
          'type' => 'checkbox',
          'desc' => 'Po aktivaci můžete zobrazit aktuální recenze pomocí zkráceného zápisu (shortcode): <code>[heureka-recenze-obchodu]</code>.
                     Zobrazovat se budou všechny, pokud neomezíte jejich počet pomocí parametru <code>limit</code>, např. <code>[heureka-recenze-obchodu limit="10"]</code>.
                     Pozor, musí být zadán platný API klíč pro službu Ověřeno zákazníky.',
          'id' => 'wc_ceske_sluzby_heureka_recenze_obchodu-aktivace'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_heureka_title'
        ),
        array(
          'title' => 'Zboží.cz',
          'type' => 'title',
          'desc' => '',
          'id' => 'wc_ceske_sluzby_zbozi_title'
        ),
        array(
          'title' => 'ID obchodu',
          'type' => 'text',
          'desc' => 'Identifikační číslo obchodu pro měření konverzí naleznete <a href="https://admin.zbozi.cz/premiseListScreen">zde</a>.',
          'id'   => 'wc_ceske_sluzby_zbozi_konverze_id-obchodu',                                                             
          'css'   => 'width: 300px'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_zbozi_title'
        ),
        array(
          'title' => 'Sklik.cz',
          'type' => 'title',
          'desc' => '',
          'id' => 'wc_ceske_sluzby_sklik_title'
        ),
        array(
          'title' => 'ID konverzního kódu',
          'type' => 'text',
          'desc' => 'ID získaného kódu pro měření konverzí naleznete <a href="https://www.sklik.cz/seznam-konverzi">zde</a>. Je třeba vytvořit konverzní kód typu "vytvoření objednávky" a z něho získat potřebné ID.',
          'id' => 'wc_ceske_sluzby_sklik_konverze-objednavky'
        ),
        array(
          'title' => 'ID pro retargeting',
          'type' => 'text',
          'desc' => 'ID získaného kódu pro retargeting naleznete <a href="https://www.sklik.cz/retargeting">zde</a>. Je třeba kliknout na odkaz "Zobrazit retargetingový kód" a z něho získat potřebné ID. Manuál pro použití této služby naleznete <a href="https://napoveda.sklik.cz/typy-cileni/retargeting/">zde</a>.',
          'id' => 'wc_ceske_sluzby_sklik_retargeting'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_sklik_title'
        ),
        array(
          'title' => 'Srovnáme.cz',
          'type' => 'title',
          'desc' => '',
          'id' => 'wc_ceske_sluzby_srovname_title'
        ),
        array(
          'title' => 'Identifikační klíč',
          'type' => 'text',
          'desc' => 'Identifikační klíč pro měření konverzí naleznete <a href="https://www.srovname.cz/muj-obchod">zde</a>.',
          'id' => 'wc_ceske_sluzby_srovname_konverze-objednavky'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_srovname_title'
        ),
        array(
          'title' => 'Další nastavení',
          'type' => 'title',
          'desc' => '',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_title'
        ),
        array(
          'title' => 'Sledování zásilek',
          'type' => 'checkbox',
          'desc' => 'Aktivovat možnost zadávání informací pro sledování zásilek u každé objednávky. Speciální notifikační email můžete nastavit <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=email&section=wc_email_ceske_sluzby_sledovani_zasilek">zde</a>.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_sledovani-zasilek'
        ),
        array(
          'title' => 'Dodací doba',
          'type' => 'checkbox',
          'desc' => 'Aktivovat možnost podrobného nastavení dodací doby, které bude dostupné <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=dodaci-doba">zde</a>.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_dodaci_doba-aktivace'
        ),
        array(
          'title' => 'EET',
          'type' => 'checkbox',
          'desc' => 'Aktivovat možnost použití elektronické evidence tržeb, podrobné nastavení bude dostupné <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=ceske-sluzby&section=eet">zde</a>.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_eet-aktivace'
        ),
        array(
          'title' => 'Možnost změny objednávek pro dobírku',
          'type' => 'checkbox',
          'desc' => 'Povolí možnost změny objednávek, které jsou provedené prostřednictvím dobírky.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_dobirka-zmena'
        ),
        array(
          'title' => 'Pouze doprava zdarma',
          'type' => 'checkbox',
          'desc' => 'Pokud je dostupná doprava zdarma, tak zobrazit pouze tuto možnost (+ nabídku vyzvednutí na pobočce).',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_doprava-pouze-zdarma'
        ),
        array(
          'title' => 'Poplatek za platbu',
          'type' => 'text',
          'desc' => 'Doplňkový poplatek k objednávce je možné upřesnit podle konkrétní platební metody či v kombinaci se způsobem dopravy.',
          'id' => 'wc_ceske_sluzby_doprava_poplatek_platba'
        ),
        array(
          'title' => 'Název poplatku za platbu',
          'type' => 'text',
          'desc' => 'Název poplatku za způsob platby.',
          'id' => 'wc_ceske_sluzby_doprava_poplatek_platba_nazev'
        ),
        array(
          'title' => 'Zakrouhlování',
          'type' => 'select',
          'desc_tip' => 'Možnosti zaokrouhlení celkové částky objednávky.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni',
          'class' => 'wc-enhanced-select',
          'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_dalsi_nastaveni_zaokrouhleni' ),
          'css' => 'width: 300px',
        ),
        array(
          'title' => 'Nepřesné zaokrouhlení',
          'type' => 'checkbox',
          'desc' => 'Pokud se v košíku a objednávkách objevují nepřesně vypočtené daně a součty, tak můžete zkusit aktivovat tuto experimentální opravnou funkci.',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_nepresne-zaokrouhleni'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_dalsi_nastaveni_title'
        )
      );
    }

    if ( 'xml-feed' == $current_section ) {
      $settings_before = array(
        array(
          'title' => 'XML feed',
          'type' => 'title',
          'desc' => 'Zde budou postupně přidávána další nastavení.',
          'id' => 'wc_ceske_sluzby_xml_feed_title'
        ),
        array(
          'title' => 'Aktivovat shortcodes',
          'type' => 'checkbox',
          'desc' => 'Možnost spouštění zkrácených zápisů (shortcode). V základním nastavení jsou zcela ignorovány, ale pokud obsahují informace potřebné pro popis produktů, tak je můžete nechat zobrazovat.',
          'id' => 'wc_ceske_sluzby_xml_feed_shortcodes-aktivace'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_title'
        ),
        array(
          'title' => 'Heureka.cz (.sk)',
          'type' => 'title',
          'desc' => 'Průběžně generovaný feed je dostupný <a href="' . site_url() . '/?feed=heureka">zde</a>. Pro větší eshopy je ale vhodná spíše varianta v podobě <a href="' . WP_CONTENT_URL . '/heureka.xml">souboru</a>, který je aktualizován automaticky jednou denně a v případě velkého množství produktů postupně po částech (1000 produktů). Podrobný manuál naleznete <a href="https://sluzby.' . HEUREKA_URL . '/napoveda/xml-feed/">zde</a>.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_title'
        ),
        array(
          'title' => 'Aktivovat feed',
          'type' => 'checkbox',
          'desc' => 'Povolí možnost postupného generování .xml souboru pro Heureka.cz (.sk) a zobrazí příslušná nastavení v administraci.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka-aktivace'
        ),
        array(
          'title' => 'Dodací doba',
          'type' => 'number',
          'desc' => 'Zboží může být skladem (0), dostupné do tří dnů (1 - 3), do týdne (4 - 7), do dvou týdnů (8 - 14), do měsíce (15 - 30) či více než měsíc (31 a více).',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_dodaci_doba',
          'css' => 'width: 50px',
          'custom_attributes' => array(
            'min' => 0,
            'step' => 1
          )
        ),
        array(
          'title' => 'Podpora EAN',
          'type' => 'text',
          'desc' => 'Pokud doplňujete EAN kód do pole pro katalogové číslo, tak zadejte hodnotu SKU. Pokud máte své vlastní řešení pro doplňování EAN kódů, tak zadejte název příslušného uživatelského pole (pozor na malá a velká písmena). Pokud zůstane pole prázdné, tak bude automaticky zapnuta možnost nastavit EAN u každého produktu či varianty.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_podpora_ean',
          'css' => 'width: 250px',
        ),
        array(
          'title' => 'Podpora výrobců',
          'type' => 'text',
          'desc' => 'Zadat můžete název příslušné taxonomie (např. na základě používaného pluginu), vlastnosti (jednoduchá textová nebo v podobě taxonomie), uživatelského pole nebo libovolný text pro element <code>MANUFACTURER</code>. Další podrobnosti (a dostupné taxonomie) naleznete dole u nastavení dodatečného označení produktů.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_podpora_vyrobcu',
          'css' => 'width: 250px',
        ),
        array(
          'title' => 'Stav produktů',
          'type' => 'select',
          'desc_tip' => 'Zvolte stav produktů, který bude hromadně použit pro celý eshop (můžete měnit na úrovni kategorie či produktu).',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_stav_produktu',
          'class' => 'wc-enhanced-select',
          'options' => array(
            '' => '- Vyberte -',
            'used' => 'Použité (bazar)',
            'refurbished' => 'Repasované'
          ),
        ),
        array(
          'title' => 'Název produktů',
          'type' => 'text',
          'desc' => 'Zvolte obecný název produktů (<code>PRODUCTNAME</code>), který bude hromadně použit pro celý eshop (můžete měnit na úrovni kategorie či produktu). Ve výchozím nastavení je automaticky použita hodnota <code>{PRODUCTNAME} | {KATEGORIE} | {NAZEV} {VLASTAXVID}</code>, což je název doplněný o přiřazené (viditelné) vlastnosti v podobě taxonomií, pokud není vyplněna hodnota <code>PRODUCTNAME</code> na úrovni produktu či kategorie. Dále je možné použít hodnoty některých elementů, např. <code>{MANUFACTURER}</code>, nebo konkrétních vlastností, např. <code>{pa_barva}</code>.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_nazev_produktu',
          'css' => 'width: 400px',
        ),
        array(
          'title' => 'Název variant',
          'type' => 'text',
          'desc' => 'Zvolte obecný název variant (<code>PRODUCTNAME</code>), který bude hromadně použit pro celý eshop (můžete měnit na úrovni kategorie či produktu). Ve výchozím nastavení je automaticky použita hodnota <code>{PRODUCTNAME} {VLASVAR} | {KATEGORIE} | {NAZEV} {VLASVAR}</code>, což je název doplněný o přiřazené vlastnosti variant, pokud není vyplněna hodnota <code>PRODUCTNAME</code> na úrovni produktu či kategorie. Dále je možné použít hodnoty některých elementů, např. <code>{MANUFACTURER}</code>, nebo konkrétních vlastností, např. <code>{pa_barva}</code>.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_nazev_variant',
          'css' => 'width: 400px',
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_title'
        ),
        array(
          'title' => 'Zbozi.cz',
          'type' => 'title',
          'desc' => 'Průběžně generovaný feed je dostupný <a href="' . site_url() . '/?feed=zbozi">zde</a>. Pro větší eshopy je ale vhodná spíše varianta v podobě <a href="' . WP_CONTENT_URL . '/zbozi.xml">souboru</a>, který je aktualizován automaticky jednou denně a v případě velkého množství produktů postupně po částech (1000 produktů). Podrobný manuál naleznete <a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/specifikace-xml-feedu/">zde</a>. Základní nastavení je stejné jako pro Heureka.cz.',
          'id' => 'wc_ceske_sluzby_xml_feed_zbozi_title'
        ),
        array(
          'title' => 'Aktivovat feed',
          'type' => 'checkbox',
          'desc' => 'Povolí možnost postupného generování .xml souboru pro Zbozi.cz a zobrazí příslušná nastavení v administraci.',
          'id' => 'wc_ceske_sluzby_xml_feed_zbozi-aktivace'
        ),
        array(
          'title' => 'Doplňkové informace',
          'type' => 'multiselect',
          'desc' => 'Zvolte položky, které budete chtít používat jako doplňkové informace k produktu (element <code>EXTRA_MESSAGE</code>). Jednotlivé hodnoty bude po uložení možné nastavit na úrovni produktu, kategorie a eshopu.',
          'id' => 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace',
          'class' => 'wc-enhanced-select',
          'options' => ceske_sluzby_ziskat_nastaveni_zbozi_extra_message(),
          'custom_attributes' => array(
            'data-placeholder' => 'EXTRA_MESSAGE'
          )
        )
      );

      $global_extra_message = get_option( 'wc_ceske_sluzby_xml_feed_zbozi_extra_message-aktivace' );
      if ( ! empty( $global_extra_message ) ) {
        $extra_message_array = ceske_sluzby_ziskat_nastaveni_zbozi_extra_message();
        foreach ( $global_extra_message as $extra_message ) {
          $extra_message_desc = 'Po zaškrtnutí budou všechny produkty v eshopu označeny příslušnou doplňkovou informací.';
          if ( $extra_message == "free_delivery" ) {
            $extra_message_desc = 'Po zaškrtnutí bude na všechny produkty v eshopu aplikováno nastavení dopravy zdarma.';
          }
          $settings_before[] =
          array(
            'title' => $extra_message_array[ $extra_message ],
            'type' => 'checkbox',
            'desc' => $extra_message_desc,
            'id' => 'wc_ceske_sluzby_xml_feed_zbozi_extra_message[' . $extra_message . ']'
          );
        }
      }

      $settings_after = array(
        array(
          'title' => 'Erotický obsah',
          'type' => 'checkbox',
          'desc' => 'Označit všechny produkty jako erotické. Pokud chcete označit pouze některé kategorie, tak to můžete nastavit přímo tam.',
          'id' => 'wc_ceske_sluzby_xml_feed_heureka_erotika'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_zbozi_title'
        ),
        array(
          'title' => 'Pricemania.cz (.sk)',
          'type' => 'title',
          'desc' => 'Generovaný feed je dostupný v podobě .xml <a href="' . WP_CONTENT_URL . '/pricemania.xml">souboru</a>. Aktualizace probíhá automaticky jednou denně a v případě velkého množství produktů postupně po částech (1000 produktů). Podrobný manuál naleznete <a href="http://files.pricemania.sk/pricemania-struktura-xml-feedu.pdf">zde</a>. Základní nastavení je stejné jako pro Heureka.cz.',
          'id' => 'wc_ceske_sluzby_xml_feed_pricemania_title'
        ),
        array(
          'title' => 'Aktivovat feed',
          'type' => 'checkbox',
          'desc' => 'Povolí možnost postupného generování .xml souboru pro Pricemania.cz (.sk) a zobrazí příslušná nastavení v administraci.',
          'id' => 'wc_ceske_sluzby_xml_feed_pricemania-aktivace'
        ),
        array(
          'title' => 'Poštovné',
          'type' => 'number',
          'desc' => 'Uvedeno může být nejnižší základní poštovné (zadávejte konkrétní číslo, pokud je poštovné zdarma tak nulu).',
          'id' => 'wc_ceske_sluzby_xml_feed_pricemania_postovne',
          'css' => 'width: 50px',
          'custom_attributes' => array(
            'min' => 0,
            'step' => 1
          )
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_pricemania_title'
        ),
        array(
          'title' => 'Glami.cz (.sk)',
          'type' => 'title',
          'desc' => 'Průběžně generovaný feed je dostupný <a href="' . site_url() . '/?feed=glami">zde</a>. Podrobný manuál naleznete <a href="https://info.' . GLAMI_URL . '/feed/" target="_blank">zde</a>.
                     Automaticky je použito nastavení z ostatních feedů.',
          'id' => 'wc_ceske_sluzby_xml_feed_glami_title'
        ),
        array(
          'title' => 'Aktivovat feed',
          'type' => 'checkbox',
          'desc' => 'Zobrazí příslušná nastavení v administraci.',
          'id' => 'wc_ceske_sluzby_xml_feed_glami-aktivace'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_glami_title'
        ),
        array(
          'title' => 'Google.cz (.sk)',
          'type' => 'title',
          'desc' => 'Průběžně generovaný feed je dostupný <a href="' . site_url() . '/?feed=google">zde</a>. Podrobný manuál naleznete <a href="https://support.google.com/merchants/answer/7052112">zde</a>.
                     Automaticky je použito nastavení z ostatních feedů.',
          'id' => 'wc_ceske_sluzby_xml_feed_google_title'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_google_title'
        ),
        array(
          'title' => 'Dodatečné označení produktů',
          'type' => 'title',
          'desc' => 'Produkty je možné rozdělit do speciálních skupin, např. podle prodejnosti, marže, atd (manuál pro <a href="https://napoveda.seznam.cz/cz/zbozi/specifikace-xml-pro-obchody/specifikace-xml-feedu/#CUSTOM_LABEL">Zbozi.cz</a> a <a href="https://support.google.com/merchants/answer/188494?hl=cs#customlabel">Google</a>).
                     Dostupné taxonomie: ' . ceske_sluzby_zobrazit_dostupne_taxonomie( 'obecne', false ) . '
                     Dostupné vlastnosti v podobě taxonomií: ' . ceske_sluzby_zobrazit_dostupne_taxonomie( 'vlastnosti', false ) . '
                     Podporovány jsou také názvy jednoduchých textových vlastností nebo uživatelských polí.',
          'id' => 'wc_ceske_sluzby_xml_feed_dodatecne_oznaceni_title'
        ),
        array(
          'title' => 'Definice skupin',
          'type' => 'textarea',
          'desc_tip' => 'Na každém řádku musí být samostatné uveden konkrétní název, kterým bude skupina definována.',
          'css' => 'width: 30%; height: 105px;',
          'id' => 'wc_ceske_sluzby_xml_feed_dodatecne_oznaceni'
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_xml_feed_dodatecne_oznaceni_title'
        ),
      );
      $settings = array_merge( $settings_before, $settings_after );
    }

    if ( 'certifikat-spokojenosti' == $current_section ) {
      $settings = array(
        array(
          'title' => 'Ověřeno zákazníky: Certifikát spokojenosti',
          'type' => 'title',
          'desc' => 'Nastavení pro zobrazování certifikátu spokojenosti na webu.',
          'id' => 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_title'
        ),
        array(
          'title' => 'Základní umístění',
          'type' => 'radio',
          'default' => 'vlevo',
          'options' => array(
            'vlevo' => 'Vlevo',
            'vpravo' => 'Vpravo',
          ),
          'id' => 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_umisteni'
        ),
        array(
          'title' => 'Odsazení shora (px)',
          'type' => 'number',
          'default' => 60,
          'desc' => 'Zadávejte hodnotu pro odsazení shora v pixelech.',
          'id' => 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_odsazeni',
          'css' => 'width: 50px',
          'custom_attributes' => array(
            'min' => 0,
            'step' => 10
          )
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_heureka_certifikat_spokojenosti_title'
        )
      );
    }

    if ( 'dodaci-doba' == $current_section ) {
      $settings = array(
        array(
          'title' => 'Dodací doba',
          'type' => 'title',
          'desc' => 'Možnost nastavení dodací doby u jednotlivých produktů. Zvolené hodnoty se budou automaticky zobrazovat v XML feedech.',
          'id' => 'wc_ceske_sluzby_dodaci_doba_title'
        ),
        array(
          'title' => 'Hodnoty pro dodací dobu',
          'type' => 'textarea',
          'desc' => 'Na každém řádku musí být uvedena číselná hodnota (počet dnů) oddělená pomocí znaku <code>|</code> od zobrazovaného textu.',
          'default' => sprintf( '1|Skladem zítra%1$s3|Dostupné do 3 dnů%1$s7|Na objednávku do týdne', PHP_EOL ),
          'css' => 'width: 40%; height: 85px;',
          'id' => 'wc_ceske_sluzby_dodaci_doba_hodnoty'
        ),
        array(
          'title' => 'Zobrazování na webu',
          'type' => 'multiselect',
          'desc' => 'Dodací dobu (případně datum předobjednávky) je možné zobrazovat na různých místech webu.',
          'id' => 'wc_ceske_sluzby_dodaci_doba_zobrazovani',
          'class' => 'wc-enhanced-select',
          'options' => array(
            'get_availability_text' => 'Detail produktu (náhrada textu pro sklad)',
            'before_add_to_cart_form' => 'Detail produktu (pod textem pro sklad)',
            'after_shop_loop_item' => 'Archiv'
          ),
          'custom_attributes' => array(
            'data-placeholder' => 'Zobrazování dodací doby'
          )
        ),
        array(
          'title' => 'Formát zobrazení',
          'type' => 'text',
          'desc' => 'Na webu můžete přesně definovat libovolný text (včetně HTML) s použitím výše zadaných hodnot <code>{VALUE}</code> (počet dní) nebo <code>{TEXT}</code> (příslušný text). Pokud není nic vyplněno, tak je použit jednoduchý odstavec s třídou <code>dodaci-doba</code>.',
          'id' => 'wc_ceske_sluzby_dodaci_doba_format_zobrazeni',
          'css' => 'width: 500px'
        ),
        array(
          'title' => 'Dodatečné produkty',
          'type' => 'text',
          'desc' => 'Na webu můžete přesně definovat libovolný text (včetně HTML) s použitím výše zadaných hodnot <code>{VALUE}</code> (počet dní) nebo <code>{TEXT}</code> (doplňující text).',
          'id' => 'wc_ceske_sluzby_dodatecne_produkty_format_zobrazeni',
          'css' => 'width: 500px'
        ),
        array(
          'title' => 'Intervaly počtu produktů',
          'type' => 'textarea',
          'desc' => 'Na každém řádku musí být uvedena číselná hodnota (dolní hranice počtu produktů) oddělená pomocí znaku <code>|</code> od zobrazovaného textu.
                     Použít můžete také hodnotu <code>{VALUE}</code>, která zobrazí přesný počet produktů skladem.
                     Automaticky je také generována CSS třída ve formátu <code>skladem-{VALUE}</code>.',
          'default' => sprintf( '0|Skladem: {VALUE}%1$s5|Skladem 5+%1$s10|Skladem 10+', PHP_EOL ),
          'css' => 'width: 40%; height: 85px;',
          'id' => 'wc_ceske_sluzby_dodaci_doba_intervaly'
        ),
        array(
          'title' => 'Předobjednávky',
          'type' => 'checkbox',
          'desc' => 'Povolí možnost zadávat a zobrazovat datum předobjednávky u jednotlivých produktů.',
          'id' => 'wc_ceske_sluzby_preorder-aktivace'
        ),
        array(
          'title' => 'Formát zobrazení',
          'type' => 'text',
          'desc' => 'Na webu můžete přesně definovat libovolný text (včetně HTML) s použitím zadaného data pro předobjednávku <code>{DATUM}</code>. Pokud není nic vyplněno, tak je použit jednoduchý odstavec s třídou <code>predobjednavka</code>.',
          'id' => 'wc_ceske_sluzby_preorder_format_zobrazeni',
          'css' => 'width: 500px'
        ),
        array(
          'title' => 'Vlastní řešení',
          'type' => 'text',
          'desc' => 'Pokud používáte své vlastní řešení pro nastavení dodací doby (např. nějaký plugin), tak zadejte název příslušného uživatelského pole (pozor na malá a velká písmena), odkud se budou načítat data pro XML feed.',
          'id' => 'wc_ceske_sluzby_dodaci_doba_vlastni_reseni',
          'css' => 'width: 250px',
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_dodaci_doba_title'
        )
      );
    }

    if ( 'eet' == $current_section ) {
      $settings = array(
        array(
          'title' => 'Elektronická evidence tržeb (EET)',
          'type' => 'title',
          'desc' => 'Nastavení potřebných informací pro odesílání elektronických účtenek.
                     Součástí pluginu je i <strong>testovací certifikát</strong>, který bude automaticky použit pokud bude zvoleno <strong>testovací prostředí</strong>.
                     Pokud chcete pouze testovat, tak nemusíte vyplňovat žádné údaje, protože budou použity automaticky (např. DIČ <code>CZ1212121218</code>).
                     V rámci testovacího režimu je možné účtenky následně mazat (nejsou oficiálně evidovány) a všechny mají stejné pořadové číslo (1).',
          'id' => 'wc_ceske_sluzby_eet_title'
        ),
        array(
          'title' => 'DIČ',
          'type' => 'text',
          'desc' => 'DIČ provozovatele obchodu.',
          'id' => 'wc_ceske_sluzby_eet_dic',
          'css' => 'width: 150px',
        ),
        array(
          'title' => 'ID provozovny',
          'type' => 'text',
          'desc' => 'Identifikace provozovny (získáte v rámci registrace provozovny pro EET).',
          'id' => 'wc_ceske_sluzby_eet_id_provozovna',
          'css' => 'width: 100px',
        ),
        array(
          'title' => 'ID pokladny',
          'type' => 'text',
          'desc' => 'Identifikace pokladního zařízení (můžete nastavit libovolně).',
          'id' => 'wc_ceske_sluzby_eet_id_pokladna',
          'css' => 'width: 100px',
        ),
        array(
          'title' => 'Certifikát',
          'type' => 'upload',
          'upload_button' => 'Nahrát certifikát',
          'remove_button' => 'Odstranit certifikát',
          'desc' => 'Nastavení získaného certifikátu (soubor ve formátu .p12).',
          'id' => 'wc_ceske_sluzby_eet_certifikat',
        ),
        array(
          'title' => 'Heslo',
          'type' => 'password',
          'desc' => 'Heslo k certifikátu.',
          'id' => 'wc_ceske_sluzby_eet_heslo',
          'css' => 'width: 150px',
        ),
        array(
          'title' => 'Prostředí',
          'type' => 'select',
          'desc_tip' => 'Nejdříve je vhodné celou funkci vyzkoušet na nějaké testovací objednávce.',
          'id' => 'wc_ceske_sluzby_eet_prostredi',
          'class' => 'wc-enhanced-select',
          'options' => array(
            'test' => 'Testovací',
            'produkce' => 'Produkční',
          ),
          'default' => 'test',
          'css' => 'width: 150px',
        ),
        array(
          'title' => 'Formát účtenky',
          'type' => 'select',
          'id' => 'wc_ceske_sluzby_eet_format',
          'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_format' ),
          'css' => 'width: 300px',
          'default' => 'no',
          'description' => 'Formát elektronické účtenky.',
        ),
        array(
          'title' => 'Podmínka odeslání',
          'type' => 'select',
          'id' => 'wc_ceske_sluzby_eet_podminka',
          'options' => self::moznosti_nastaveni( 'wc_ceske_sluzby_eet_podminka' ),
          'css' => 'width: 300px',
          'default' => 'no',
          'description' => 'Podmínka pro automatické odeslání elektronické účtenky.',
        ),
        array(
          'type' => 'sectionend',
          'id' => 'wc_ceske_sluzby_eet_title'
        )
      );
    }
    return $settings;
  }
}