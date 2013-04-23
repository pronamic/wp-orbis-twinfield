<?php

global $wpdb, $post;

$twinfield_customer_id = get_post_meta( $post->ID, '_twinfield_customer_id', true );

if ( ! empty( $twinfield_customer_id ) ) : ?>
	
	<div class="panel">
		<header>
			<h3><?php _e( 'Twinfield', 'orbis_subscriptions' ); ?></h3>
		</header>
	
		<div class="content">
			<dl>
				<dt><?php _e( 'Twinfield ID', 'orbis_subscriptions' ); ?></dt>
				<dd>
					<?php 
					
					$url = home_url( sprintf('/debiteuren/%s/', $twinfield_customer_id ) );
					
					printf( '<a href="%s" target="_blank">%s</a>', $url, $twinfield_customer_id );
	
					?>
				</dd>
			</dl>
		</div>
	</div>

<?php endif; ?>