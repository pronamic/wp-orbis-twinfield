<?php

global $wpdb, $post;

$twinfield_customer_id = get_post_meta( $post->ID, '_twinfield_customer_id', true );

if ( ! empty( $twinfield_customer_id ) ) : ?>
	
	<div class="card">
		<div class="card-body">
			<h3 class="card-title"><?php esc_html_e( 'Twinfield', 'orbis_twinfield' ); ?></h3>
		
			<div class="content">
				<dl>
					<dt><?php esc_html_e( 'Twinfield ID', 'orbis_twinfield' ); ?></dt>
					<dd>
						<?php

						$url = home_url( sprintf( '/debiteuren/%s/', $twinfield_customer_id ) );

						printf(
							'<a href="%s" target="_blank">%s</a>',
							esc_attr( $url ),
							esc_html( $twinfield_customer_id )
						);

						?>
					</dd>
				</dl>
			</div>
		</div>
		
	</div>

<?php endif; ?>
