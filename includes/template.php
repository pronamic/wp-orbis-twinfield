<?php

function orbis_twinfield_render_details() {
	if ( is_singular( 'orbis_company' ) ) {
		global $orbis_twinfield_plugin;

		$orbis_twinfield_plugin->plugin_include( 'templates/company-twinfield.php' );
	}
}

add_action( 'orbis_before_side_content', 'orbis_twinfield_render_details', 100 );
