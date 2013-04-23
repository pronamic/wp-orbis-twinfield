<?php

function orbis_twinfield_init() {
	global $orbis_twinfield_plugin;

	add_post_type_support( 'orbis_company', 'twinfield_customer' );
}

add_action( 'init', 'orbis_twinfield_init' );

function orbis_twinfield_customer_meta_box() {
	global $orbis_twinfield_plugin;

	$orbis_twinfield_plugin->plugin_include( 'admin/meta-box-customer.php' );
}

add_action( 'twinfield_customer_meta_box', 'orbis_twinfield_customer_meta_box' );


/**
 * Company column
 *
 * @param string $column
 */
function orbis_twinfield_company_column( $column, $post_id ) {
	switch ( $column ) {
		case 'orbis_company_administration':
			$id   = get_post_meta( $post_id, '_twinfield_customer_id', true );

			printf(
				'<br /><strong>%s</strong> %s',
				__( 'Twinfield ID:', 'orbis' ),
				$id
			);

			break;
	}
}

add_action( 'manage_posts_custom_column' , 'orbis_twinfield_company_column', 20, 2 );
