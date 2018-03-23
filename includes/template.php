<?php

function orbis_twinfield_invoice_link( $link, $invoice_number ) {
	if ( ! empty( $invoice_number ) ) {
		$link = home_url( sprintf( '/facturen/%s/', $invoice_number ) );
	}

	return $link;
}

add_filter( 'orbis_invoice_link', 'orbis_twinfield_invoice_link', 10, 2 );
