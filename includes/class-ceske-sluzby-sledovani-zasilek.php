<?php
function spustit_Ceske_Sluzby_Sledovani_Zasilek() {
  new Ceske_Sluzby_Sledovani_Zasilek();
}

if ( is_admin() ) {
  $sledovani = get_option( 'wc_ceske_sluzby_dalsi_nastaveni_sledovani-zasilek' );
  if ( $sledovani == "yes" ) {
    add_action( 'load-post.php', 'spustit_Ceske_Sluzby_Sledovani_Zasilek' );
  }
}

function ceske_sluzby_sledovani_zasilek_dostupni_dopravci() {
  $dostupni_dopravci = array(
    'CPOST' => array (
      'nazev' => 'Česká pošta',
      'url' => 'http://www.postaonline.cz/cs/trackandtrace/-/zasilka/cislo?parcelNumbers=%ID%'
    ),
    'DPD' => array (
      'nazev' => 'DPD',
      'url' => 'https://tracking.dpd.de/parcelstatus?query=%ID%&locale=cs_CZ'
    )
  );
  return $dostupni_dopravci;
}

class Ceske_Sluzby_Sledovani_Zasilek {

  public function __construct() {
    add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
    add_action( 'save_post', array( $this, 'save' ) );
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

  public function save( $post_id ) {
    if ( ! isset( $_POST['ceske_sluzby_sledovani_zasilek_box_nonce'] ) )
      return $post_id;

    $nonce = $_POST['ceske_sluzby_sledovani_zasilek_box_nonce'];
    if ( ! wp_verify_nonce( $nonce, 'ceske_sluzby_sledovani_zasilek_box' ) )
      return $post_id;

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
      return $post_id;

    if ( ! current_user_can( 'edit_post', $post_id ) )
      return $post_id;

    if ( isset( $_POST['ceske_sluzby_sledovani_zasilek_dopravce'] ) && isset( $_POST['ceske_sluzby_sledovani_zasilek_id_zasilky'] ) ) {
      $id_zasilky = sanitize_text_field( $_POST['ceske_sluzby_sledovani_zasilek_id_zasilky'] );
      $dopravce = sanitize_text_field( $_POST['ceske_sluzby_sledovani_zasilek_dopravce'] );
      $odeslat_ulozeno = get_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_odeslat', true );
      if ( isset( $_POST['ceske_sluzby_sledovani_zasilek_odeslat'] ) ) {
        $odeslat = $_POST['ceske_sluzby_sledovani_zasilek_odeslat'];
          if ( ! empty( $odeslat ) ) {
            update_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_odeslat', esc_attr( $odeslat ) );
            if ( $odeslat != $odeslat_ulozeno ) {
              do_action( 'woocommerce_ceske_sluzby_sledovani_zasilek_email_akce', $post_id );
            }
          }
      } elseif ( ! empty( $odeslat_ulozeno ) ) {
        delete_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_odeslat' );   
      }
      update_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_id_zasilky', $id_zasilky );
      update_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_dopravce', $dopravce);
    }
  }

  public function render_meta_box_content( $post ) {
    wp_nonce_field( 'ceske_sluzby_sledovani_zasilek_box', 'ceske_sluzby_sledovani_zasilek_box_nonce' );

    $id_zasilky = get_post_meta( $post->ID, '_ceske_sluzby_sledovani_zasilek_id_zasilky', true );
    $dopravce = get_post_meta( $post->ID, '_ceske_sluzby_sledovani_zasilek_dopravce', true);
    $odeslat = get_post_meta( $post->ID, '_ceske_sluzby_sledovani_zasilek_odeslat', true );
    $checked = '';
    if ( ! empty ( $odeslat ) ) {
      $checked = 'checked="checked"';
    }

    $dostupni_dopravci = ceske_sluzby_sledovani_zasilek_dostupni_dopravci();

    if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
      $odkaz = str_replace( '%ID%', $id_zasilky , $dostupni_dopravci[$dopravce]['url'] );
    } ?>

    <label for="ceske_sluzby_sledovani_zasilek_id_zasilky">ID zásilky: </label>
    <input type="text" id="ceske_sluzby_sledovani_zasilek_id_zasilky" name="ceske_sluzby_sledovani_zasilek_id_zasilky" value="<?php esc_attr_e( $id_zasilky ); ?>" size="20" />
    <br />
    <br />
    <label for="ceske_sluzby_sledovani_zasilek_dopravce">Dopravce: </label>
    <select name="ceske_sluzby_sledovani_zasilek_dopravce" id="ceske_sluzby_sledovani_zasilek_dopravce">
    <option value="">Vyberte dopravce</option>
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
    $aktivace = get_option( 'woocommerce_wc_email_ceske_sluzby_sledovani_zasilek_settings' );
    if ( isset ( $aktivace['enabled'] ) && $aktivace['enabled'] == "yes" ) {
      $order = wc_get_order( $post->ID );
      if ( $order->has_status( 'on-hold' ) ) {
        echo '<p>Asi není moc dobrý nápad odesílat nezaplacenou objednávku.';
      } else {
    ?>
    <br />
    <br />
    <label for="ceske_sluzby_sledovani_zasilek_odeslat">Odeslat? </label>
    <input name="ceske_sluzby_sledovani_zasilek_odeslat" id="ceske_sluzby_sledovani_zasilek_odeslat" type="checkbox" value="yes" <?php echo $checked; ?>/>
    <?php
      }
    }
    else {
      echo '<p>Kontrolní odkaz nebude odeslán jako samostatný email, protože zatím nebyl <a href="' . admin_url(). 'admin.php?page=wc-settings&tab=email&section=wc_email_ceske_sluzby_sledovani_zasilek">aktivován</a>.';
    }
    if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
      echo '<p>Kontrolní odkaz: <a href="' . $odkaz . '" target="_blank">' . $dostupni_dopravci[$dopravce]['nazev'] . '</a></p>';
    }
    else {
      echo '<p>Obě zadané hodnoty musíte nejdříve uložit, potom se zobrazí kontrolní odkaz.</p>';
    }
  }
}