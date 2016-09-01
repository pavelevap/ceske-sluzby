<?php
function ceske_sluzby_zpracovat_dodaci_dobu_produktu() {
  $dodaci_doba_array = array();
  $dodaci_doba_hodnoty = get_option( 'wc_ceske_sluzby_dodaci_doba_hodnoty' );
  if ( ! empty ( $dodaci_doba_hodnoty ) ) {
    $dodaci_doba_tmp = array_values( array_filter( explode( PHP_EOL, $dodaci_doba_hodnoty ) ) );
    foreach ( $dodaci_doba_tmp as $dodaci_doba_hodnota ) {
      $rozdeleno = explode( "|", $dodaci_doba_hodnota );
      if ( count( $rozdeleno ) == 2 && is_numeric( $rozdeleno[0] ) ) {
        $dodaci_doba_array[ $rozdeleno[0] ] = $rozdeleno[1];
      }
    }
    if ( ! empty ( $dodaci_doba_array ) ) {
      ksort( $dodaci_doba_array );
    }
  }
  return $dodaci_doba_array;
}

function ceske_sluzby_ziskat_zadanou_dodaci_dobu( $dodaci_doba, $actual_dodaci_doba ) {
  $availability = array();
  if ( ! empty ( $actual_dodaci_doba ) || $actual_dodaci_doba === '0' ) {
    if ( array_key_exists( $actual_dodaci_doba, $dodaci_doba ) ) {
      $availability['value'] = $actual_dodaci_doba;
      $availability['text'] = $dodaci_doba[ $actual_dodaci_doba ];
    }
  }
  return $availability;
}

function ceske_sluzby_ziskat_format_dodaci_doby( $availability ) {
  $format = get_option( 'wc_ceske_sluzby_dodaci_doba_format_zobrazeni' );
  if ( ! empty ( $format ) ) {
    $variables = array( 'VALUE' => $availability['value'], 'TEXT' => $availability['text'] );
    foreach( $variables as $key => $value ) {
      $format = str_replace( '{' . $key . '}', $value, $format );
    }
  } else {
    $format = '<p class=dodaci-doba">' . $availability['text'] . '</p>';
  }
  return $format;
}

function ceske_sluzby_ziskat_format_predobjednavky( $availability ) {
  $format = get_option( 'wc_ceske_sluzby_preorder_format_zobrazeni' );
  $availability = date_i18n( 'j.n.Y', $availability );
  if ( ! empty ( $format ) ) {
    $variables = array( 'DATUM' => $availability );
    foreach( $variables as $key => $value ) {
      $format = str_replace( '{' . $key . '}', $value, $format );
    }
  } else {
    $format = '<p class=predobjednavka">Předobjednávka: ' . $availability . '</p>';
  }
  return $format;
}