<?php

/**
 * Company column
 *
 * @param string $column
 */
function orbis_twinfield_company_column( $column, $post_id ) {
	switch ( $column ) {
		case 'orbis_company_administration':
			$id = get_post_meta( $post_id, '_twinfield_customer_id', true );

			$value = $id;

			if ( ! empty( $value ) ) {
				$url = home_url( sprintf( '/debiteuren/%s/', $id ) );

				$value = sprintf( '<a href="%s" target="_blank">%s</a>', $url, $id );
			}

			printf(
				'<br /><strong>%s</strong> %s',
				esc_html__( 'Twinfield ID:', 'orbis_twinfield' ),
				esc_html( $value )
			);

			break;
	}
}

add_action( 'manage_posts_custom_column' , 'orbis_twinfield_company_column', 20, 2 );
