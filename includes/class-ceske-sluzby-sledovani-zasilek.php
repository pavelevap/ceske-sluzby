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
      update_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_id_zasilky', $id_zasilky );
      update_post_meta( $post_id, '_ceske_sluzby_sledovani_zasilek_dopravce', $dopravce);
    }
  }

  public function render_meta_box_content( $post ) {
    wp_nonce_field( 'ceske_sluzby_sledovani_zasilek_box', 'ceske_sluzby_sledovani_zasilek_box_nonce' );
    $id_zasilky = get_post_meta( $post->ID, '_ceske_sluzby_sledovani_zasilek_id_zasilky', true );
    $dopravce = get_post_meta( $post->ID, '_ceske_sluzby_sledovani_zasilek_dopravce', true);
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
    if ( ! empty( $id_zasilky ) && ! empty( $dopravce ) ) {
      echo '<p>Kontrolní odkaz: <a href="' . $odkaz . '">' . $dostupni_dopravci[$dopravce]['nazev'] . '</a></p>';
    }
    else {
      echo '<p>Obě zadané hodnoty musíte nejdříve uložit, potom se zobrazí kontrolní odkaz.</p>';
    }
  }
}