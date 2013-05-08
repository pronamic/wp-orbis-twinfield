<?php

function orbis_twinfield_render_details() {
	if ( is_singular( 'orbis_company' ) ) {
		global $orbis_twinfield_plugin;

		$orbis_twinfield_plugin->plugin_include( 'templates/company-twinfield.php' );
	}
}

add_action( 'orbis_before_side_content', 'orbis_twinfield_render_details', 100 );


function orbis_twinfield_invoice_link( $link, $invoice_number ) {
	$link = home_url( sprintf( '/facturen/%s/', $invoice_number ) );

	return $link;
} 

add_filter( 'orbis_invoice_link', 'orbis_twinfield_invoice_link', 10, 2 );
