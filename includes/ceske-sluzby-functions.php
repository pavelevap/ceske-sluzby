<?php
function ceske_sluzby_zpracovat_dodaci_dobu_produktu() {
  $dodaci_doba_array = array();
  $dodaci_doba_intervaly = get_option( 'wc_ceske_sluzby_dodaci_doba_intervaly' );
  if ( ! empty ( $dodaci_doba_intervaly ) ) {
    $dodaci_doba_tmp = array_values( array_filter( explode( PHP_EOL, $dodaci_doba_intervaly ) ) );
    foreach ( $dodaci_doba_tmp as $dodaci_doba_interval ) {
      $rozdeleno = explode( "|", $dodaci_doba_interval );
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