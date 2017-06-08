<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Ceske_Sluzby_Sledovani_Zasilek_Email' ) ) :

class WC_Email_Ceske_Sluzby_Sledovani_Zasilek extends WC_Email {
  // https://www.skyverge.com/blog/how-to-add-a-custom-woocommerce-email/
  function __construct() {
    $this->id = 'wc_email_ceske_sluzby_sledovani_zasilek';
    $this->customer_email = true;
    $this->title = 'Sledování zásilek';
    $this->description = 'Pokud necháte email neaktivní, tak bude odkaz na aktuální stav zásilky přidáván do existujících emailů (např. při označení dokončené zásilky). Samostatné emaily jsou odesílány ve chvíli, kdy dojde k uložení čísla zásilky u objednávky a zároveň zaškrtnete políčko pro odeslání. V tomto případě je možná vhodné deaktivovat klasické emailové notifikace zasílané po dokončení objednávky.';
    $this->subject = '{site_title}: Odeslaná objednávka';
    $this->heading = 'Objednávka #{order_number} byla odeslána!';
    $this->template_html  = 'sledovani-zasilek.php';
    $this->template_plain = 'sledovani-zasilek-plain.php';

    add_action( 'woocommerce_ceske_sluzby_sledovani_zasilek_email_akce_notification', array( $this, 'trigger' ) );
    add_filter( 'woocommerce_locate_core_template', array( $this, 'ceske_sluzby_locate_template' ), 10, 3 );
    parent::__construct();
  }

  public function ceske_sluzby_locate_template( $template, $template_name, $template_path ) {
    // http://rahuljalavadiya.blogspot.cz/2015/06/how-to-add-custom-email-functionality.html
    if ( $template_name == $this->template_html || $template_name == $this->template_plain ) {
      $template = plugin_dir_path( __FILE__ ) . 'emails/' . $template_name;
    }
    return $template;
  }

  public function trigger( $order_id ) {
    if ( ! $order_id ) {
      return;
    }
    $this->object = wc_get_order( $order_id );
    $this->recipient = is_callable( array( $this->object, 'get_billing_email' ) ) ? $this->object->get_billing_email() : $this->object->billing_email;
    $this->find[] = '{order_number}';
    $this->replace[] = $this->object->get_order_number();
    if ( ! $this->is_enabled() || ! $this->get_recipient() ) {
      return;
    }
    $this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
	}

  function get_content_html() {
    ob_start();
    wc_get_template( $this->template_html, array(
      'order' => $this->object,
      'email_heading' => $this->get_heading(),
      'sent_to_admin' => false,
      'plain_text' => false
    ), '', plugin_dir_path( __FILE__ ) . 'emails/' );
    return ob_get_clean();
  }

  function get_content_plain() {
    ob_start();
    wc_get_template( $this->template_plain, array(
      'order' => $this->object,
      'email_heading' => $this->get_heading(),
      'sent_to_admin' => false,
      'plain_text' => true
    ), '', plugin_dir_path( __FILE__ ) . 'emails/' );
    return ob_get_clean();
  }

  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title' => 'Aktivovat',
        'type' => 'checkbox',
        'label' => 'Aktivovat zasílání speciální emailové notifikace o odeslané zásilce',
        'default' => 'no'
      ),
      'subject' => array(
        'title' => 'Předmět',
        'type' => 'text',
        'description' => sprintf( 'Předmět emailu. Základní nastavení: <code>%s</code>.', $this->subject ),
        'placeholder' => '',
        'default' => ''
      ),
      'heading' => array(
        'title' => 'Záhlaví',
        'type' => 'text',
        'description' => sprintf( __( 'Text v záhlaví emailu. Základní nastavení: <code>%s</code>.' ), $this->heading ),
        'placeholder' => '',
        'default' => ''
      ),
      'email_type' => array(
        'title' => 'Formát',
        'type' => 'select',
        'description' => 'Zvolte formát odesílaného emailu.',
        'default' => 'html',
        'class' => 'email_type',
        'options' => array(
          'plain' => 'Text',
          'html' => 'HTML',
          'multipart' => 'Multipart',
        )
      )
    );
  }
}
endif;