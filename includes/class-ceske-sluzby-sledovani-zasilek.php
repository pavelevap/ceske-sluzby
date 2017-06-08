<?php
function spustit_Ceske_Sluzby_Sledovani_Zasilek() {
  $screen = get_current_screen();
  if ( $screen->post_type == 'shop_order' ) {
    new Ceske_Sluzby_Sledovani_Zasilek();
  }
}

if ( is_admin() ) {
  $sledovani_zasilek = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_sledovani-zasilek' );
  if ( $sledovani_zasilek == "yes" ) {
    add_action( 'load-post.php', 'spustit_Ceske_Sluzby_Sledovani_Zasilek' );
  }
}

function ceske_sluzby_sledovani_zasilek_dostupni_dopravci( $lang ) {
  $dopravci = array(
    'CPOST' => array(
      'nazev' => 'Česká pošta',
      'url' => 'https://www.postaonline.cz/cs/trackandtrace/-/zasilka/cislo?parcelNumbers=%ID%',
      'lang' => 'CZ'
    ),
    'SPOST' => array(
      'nazev' => 'Slovenská pošta',
      'url' => 'http://tandt.posta.sk/zasielky/%ID%',
      'lang' => 'SK'
    ),
    'DPD' => array(
      'nazev' => 'DPD',
      'lang' => array(
                  'CZ' => 'https://tracking.dpd.de/parcelstatus?query=%ID%&locale=cs_CZ',
                  'SK' => 'https://tracking.dpd.de/parcelstatus?query=%ID%&locale=sk_SK'
                )
    ),
    'INTIME' => array(
      'nazev' => 'Intime',
      'url' => 'http://trace.intime.cz/index.php?orderNumber=%ID%',
      'lang' => 'CZ'
    ),
    'PPL' => array(
      'nazev' => 'PPL',
      'url' => 'http://www.ppl.cz/main2.aspx?cls=Package&idSearch=%ID%',
      'lang' => 'CZ'
    ),
    'DHL' => array(
      'nazev' => 'DHL',
      'lang' => array(
                  'CZ' => 'http://www.dhl.cz/content/cz/cs/express/sledovani_zasilek.shtml?brand=DHL&AWB=%ID%',
                  'SK' => 'http://www.dhl.sk/content/sk/sk/express/sledovanie_zasielky.shtml?brand=DHL&AWB=%ID%'
                )
    ),
    'GEIS' => array(
      'nazev' => 'Geis',
      'lang' => array(
                  'CZ' => 'http://tt.geis.cz/TrackAndTrace/ZasilkaDetailCargo.aspx?id=%ID%&lang=cs&country=cs',
                  'SK' => 'http://tt.geis.cz/TrackAndTrace/ZasilkaDetailCargo.aspx?id=%ID%&lang=sk&country=sk'
                )
    ),
    'GLS' => array(
      'nazev' => 'GLS',
      'lang' => array(
                  'CZ' => 'https://gls-group.eu/CZ/cs/sledovani-zasilek?match=%ID%',
                  'SK' => 'https://gls-group.eu/SK/sk/sledovanie-zasielok?match=%ID%'
                )
    )
  );
  foreach ( $dopravci as $key => $dopravce ) {
    if ( is_string( $dopravce['lang'] ) ) {
        $dostupni_dopravci[$key]['nazev'] = $dopravce['nazev'];
        $dostupni_dopravci[$key]['url'] = $dopravce['url'];
    } elseif ( is_array( $dopravce['lang'] ) ) {
      if ( array_key_exists( $lang, $dopravce['lang'] ) ) {
        $dostupni_dopravci[$key]['nazev'] = $dopravce['nazev'];
        $dostupni_dopravci[$key]['url'] = $dopravce['lang'][$lang];
      } else {
        $dostupni_dopravci[$key]['nazev'] = $dopravce['nazev'];
        $dostupni_dopravci[$key]['url'] = $dopravce['lang']['CZ'];
      }
    }
  }
  return $dostupni_dopravci;
}

class Ceske_Sluzby_Sledovani_Zasilek {

  public function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
    add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save' ) );
    $aktivace_email = get_option( 'woocommerce_wc_email_ceske_sluzby_sledovani_zasilek_settings' );
    if ( isset ( $aktivace_email['enabled'] ) && $aktivace_email['enabled'] == "yes" ) {
      add_filter( 'woocommerce_resend_order_emails_available', array( $this, 'moznost_odesilat_email_sledovani_zasilek' ) );
    }
    add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'skryt_zobrazeni_hodnot' ) );
  }

  public function add_meta_box( $post_type ) {
    $post_types = array( 'shop_order' );
    if ( in_array( $post_type, $post_types )) {
      add_meta_box(
        'ceske_sluzby_sledovani_zasilek',
        'Sledování zásilek',
        array( $this, 'render_meta_box_content' ),
        $post_type,
        'side',
        'default'
      );
    }
  }
  
  public function moznost_odesilat_email_sledovani_zasilek( $available_emails ) {
    $available_emails[] = 'wc_email_ceske_sluzby_sledovani_zasilek';
    return $available_emails;
  }

  public function skryt_zobrazeni_hodnot( $keys ) {
    $keys[] = 'ceske_sluzby_sledovani_zasilek_id_zasilky';
    $keys[] = 'ceske_sluzby_sledovani_zasilek_dopravce';
    return $keys;
  }

  public function save( $post_id ) {
    $item_id = $post_id;
    if ( ! isset( $_POST['ceske_sluzby_sledovani_zasilek_box_nonce'] ) )
      return $post_id;

    $nonce = $_POST['ceske_sluzby_sledovani_zasilek_box_nonce'];
    if ( ! wp_verify_nonce( $nonce, 'ceske_sluzby_sledovani_zasilek_box' ) )
      return $post_id;

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

    if ( ! current_user_can( 'edit_post', $post_id ) )
      return $post_id;

    $order = wc_get_order( $post_id );
    $shipping = $order->get_shipping_methods();
    if ( ! empty ( $shipping ) && is_array ( $shipping ) ) {
      $shipping_item_id = key( $shipping );
      $item_id = $shipping_item_id;
    }

    if ( isset( $_POST['ceske_sluzby_sledovani_zasilek_dopravce'] ) && isset( $_POST['ceske_sluzby_sledovani_zasilek_id_zasilky'] ) ) {
      $id_zasilky = sanitize_text_field( $_POST['ceske_sluzby_sledovani_zasilek_id_zasilky'] );
      $dopravce = sanitize_text_field( $_POST['ceske_sluzby_sledovani_zasilek_dopravce'] );
      $id_zasilky_ulozeno = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_id_zasilky', true );
      $dopravce_ulozeno = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_dopravce', true );
      if ( ! empty ( $id_zasilky ) ) {
        if ( $id_zasilky != $id_zasilky_ulozeno ) {
          wc_update_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_id_zasilky', $id_zasilky );
        }
      } else {
        wc_delete_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_id_zasilky' );
      }
      if ( ! empty ( $dopravce ) ) {
        if ( $dopravce != $dopravce_ulozeno ) {
          wc_update_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_dopravce', $dopravce );
        }
      } else {
        wc_delete_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_dopravce' );
      }
    }
  }

  public function render_meta_box_content( $post ) {
    $item_id = $post->ID;
    wp_nonce_field( 'ceske_sluzby_sledovani_zasilek_box', 'ceske_sluzby_sledovani_zasilek_box_nonce' );

    $order = wc_get_order( $post->ID );
    $shipping = $order->get_shipping_methods();
    if ( ! empty ( $shipping ) && is_array ( $shipping ) ) {
      $shipping_item_id = key( $shipping );
      $item_id = $shipping_item_id;
    }
    $id_zasilky = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_id_zasilky', true );
    $dopravce = wc_get_order_item_meta( $item_id, 'ceske_sluzby_sledovani_zasilek_dopravce', true );
    $zeme_doruceni = is_callable( array( $order, 'get_shipping_country' ) ) ? $order->get_shipping_country() : $order->shipping_country;
    $dostupni_dopravci = ceske_sluzby_sledovani_zasilek_dostupni_dopravci( $zeme_doruceni );

    if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
      $odkaz = str_replace( '%ID%', $id_zasilky , $dostupni_dopravci[$dopravce]['url'] );
    } ?>

    <label for="ceske_sluzby_sledovani_zasilek_id_zasilky">ID zásilky: </label>
    <input type="text" id="ceske_sluzby_sledovani_zasilek_id_zasilky" name="ceske_sluzby_sledovani_zasilek_id_zasilky" value="<?php esc_attr_e( $id_zasilky ); ?>" size="20" />
    <br />
    <br />
    <label for="ceske_sluzby_sledovani_zasilek_dopravce">Dopravce: </label>
    <select name="ceske_sluzby_sledovani_zasilek_dopravce" id="ceske_sluzby_sledovani_zasilek_dopravce">
    <option value="">- Vyberte dopravce -</option>
    <?php
    foreach ( $dostupni_dopravci as $id => $dostupny_dopravce ) {
      if ( $id == $dopravce ) {
        $selected = ' selected="selected"';
      } else {
        $selected = "";
      }
      echo '<option value="' . $id . '"' . $selected . '>' . $dostupny_dopravce['nazev'] . '</option>';
    } ?>
    </select>
    <?php
    $aktivace_email = get_option( 'woocommerce_wc_email_ceske_sluzby_sledovani_zasilek_settings' );
    if ( isset ( $aktivace_email['enabled'] ) && $aktivace_email['enabled'] == "yes" ) {
      if ( $order->has_status( 'on-hold' ) ) {
        echo '<p>Asi není moc dobrý nápad odesílat nezaplacenou objednávku?';
      } else {
    ?>
    <br />
    <?php
      }
    }
    else {
      echo '<p>Kontrolní odkaz zatím není možné odesílat jako samostatný email, protože ještě nebyl <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=email&section=wc_email_ceske_sluzby_sledovani_zasilek">aktivován</a>.';
    }
    if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
      echo '<p>Kontrolní odkaz: <a href="' . $odkaz . '" target="_blank">' . $dostupni_dopravci[$dopravce]['nazev'] . '</a></p>';
    }
    else {
      echo '<p>Nejdříve musíte doplnit obě hodnoty, aby se zobrazil kontrolní odkaz a mohl být ručně odeslán notifikační email.</p>';
    }
  }
}