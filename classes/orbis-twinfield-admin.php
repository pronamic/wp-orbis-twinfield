<?php

/**
 * Title: Orbis Twinfield admin
 * Description:
 * Copyright: Copyright (c) 2005 - 2015
 * Company: Pronamic
 *
 * @author Remco Tolsma
 * @version 1.0.0
 */
class Orbis_Twinfield_Admin {
	/**
	 * Plugin
	 *
	 * @var Orbis_InfiniteWP_Plugin
	 */
	private $plugin;

	//////////////////////////////////////////////////

	/**
	 * Constructs and initialize an Orbis core admin
	 *
	 * @param Orbis_Plugin $plugin
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;

		// Actions
		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'manage_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 20, 2 );

	}

	//////////////////////////////////////////////////

	/**
	 * Admin initalize
	 */
	public function admin_init() {
		$this->maybe_register_subscription_invoices();
	}

	/**
	 * Manage posts custom column
	 */
	public function manage_posts_custom_column( $column, $post_id ) {
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

	/**
	 * Maybe register subscription invoices.
	 */
	private function maybe_register_subscription_invoices() {
		if ( filter_has_var( INPUT_POST, 'orbis_twinfield_register_invoices' ) ) {
			$subscriptions = filter_input( INPUT_POST, 'subscriptions', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY );

			if ( ! empty( $subscriptions ) ) {
				$failed   = array();
				$inserted = array();

				foreach ( $subscriptions as $subscription ) {
					$id             = $subscription['id'];
					$post_id        = $subscription['post_id'];
					$invoice_number = $subscription['invoice_number'];
					$date_start     = new DateTime( $subscription['date_start'] );
					$date_end       = new DateTime( $subscription['date_end'] );

					if ( ! empty( $invoice_number ) ) {
						$subscription_object = new Orbis_Subscription( $post_id );

						$result = $subscription_object->register_invoice( $invoice_number, $date_start, $date_end );

						if ( false === $result ) {
							$failed[] = $post_id;
						} else {
							$inserted[] = $post_id;
						}
					}
				}

				$url = add_query_arg( array(
					'inserted' => empty( $inserted ) ? false : implode( $inserted, ',' ),
					'failed'   => empty( $failed ) ? false : implode( $failed, ',' ),
				) );

				wp_safe_redirect( $url );

				exit;
			}
		}
	}
}
