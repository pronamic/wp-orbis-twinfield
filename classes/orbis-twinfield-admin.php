<?php

/**
 * Title: Orbis Twinfield admin
 * Description:
 * Copyright: Copyright (c) 2005 - 2015
 * Company: Pronamic
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
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'manage_posts_custom_column' , array( $this, 'manage_posts_custom_column' ), 20, 2 );

	}

	//////////////////////////////////////////////////

	/**
	 * Admin initalize
	 */
	public function admin_init() {
		$this->maybe_register_subscription_invoices();
	}

	//////////////////////////////////////////////////

	/**
	 * Admin menu
	 */
	public function admin_menu() {
		add_submenu_page(
			'edit.php?post_type=orbis_subscription',
			__( 'Orbis Twinfield', 'orbis_twinfield' ),
			__( 'Twinfield', 'orbis_twinfield' ),
			'manage_options',
			'orbis_twinfield',
			array( $this, 'page_orbis_twinfield' )
		);
	}

	/**
	 * Page Orbis InfiniteWP
	 */
	public function page_orbis_twinfield() {
		include plugin_dir_path( $this->plugin->file ) . 'admin/page-orbis-twinfield.php';
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

	//////////////////////////////////////////////////

	/**
	 * Get the date.
	 *
	 * @return DateTime
	 */
	public function get_date() {
		$date_string = filter_input( INPUT_GET, 'date', FILTER_SANITIZE_STRING );

		$date = date_create( $date_string );

		if ( empty( $date_string ) || false === $date ) {
			$date = new DateTime( 'first day of this month' );
		}

		return $date;
	}

	/**
	 * Get the interval.
	 *
	 * @return string
	 */
	public function get_interval() {
		$interval = filter_input( INPUT_GET, 'interval', FILTER_SANITIZE_STRING );

		$interval = empty( $interval ) ? 'Y' : $interval;

		return $interval;
	}

	/**
	 * Get subscriptions
	 */
	public function get_subscriptions() {
		global $wpdb;
		global $orbis_subscriptions_plugin;

		// Date
		$date = $this->get_date();

		// Interval
		$interval = $this->get_interval();

		// Query
		switch ( $interval ) {
			case 'M' :
				$last_day_month = clone $date;
				$last_day_month->modify( 'last day of this month' );

				$day_function    = 'DAYOFMONTH';
				$join_condition  = $wpdb->prepare( '( YEAR( invoice.start_date ) = %d AND MONTH( invoice.start_date ) = %d )', $date->format( 'Y' ), $date->format( 'n' ) );
				$where_condition = $wpdb->prepare( 'subscription.activation_date <= %s', $last_day_month->format( 'Y-m-d' ) );

				break;
			case 'Y' :
			default:
				$ahead_limit = new DateTime( '+2 month' );

				$day_function    = 'DAYOFYEAR';
				$join_condition  = $wpdb->prepare( 'YEAR( invoice.start_date ) = %d', $date->format( 'Y' ) );
				$where_condition = $wpdb->prepare( 'DATE_FORMAT( subscription.activation_date, %s ) <= %s', $date->format( 'Y' ) . '-%m-%d', $ahead_limit->format( 'Y-m-d' ) );

				break;
		}

		$interval_condition = $wpdb->prepare( 'product.interval = %s', $interval );

		$query = "
			SELECT
				company.id AS company_id,
				company.name AS company_name,
				company.post_id AS company_post_id,
				product.name AS subscription_name,
				product.price,
				product.twinfield_article,
				product.interval,
				product.post_id AS product_post_id,
				subscription.id,
				subscription.type_id,
				subscription.post_id,
				subscription.name,
				subscription.activation_date,
				DAYOFYEAR( subscription.activation_date ) AS activation_dayofyear,
				invoice.invoice_number,
				invoice.start_date,
				(
					invoice.id IS NULL
						AND
					$day_function( subscription.activation_date ) < $day_function( NOW() )
				) AS too_late
			FROM
				$wpdb->orbis_subscriptions AS subscription
					LEFT JOIN
				$wpdb->orbis_companies AS company
						ON subscription.company_id = company.id
					LEFT JOIN
				$wpdb->orbis_subscription_products AS product
						ON subscription.type_id = product.id
					LEFT JOIN
				$wpdb->orbis_subscriptions_invoices AS invoice
						ON
							subscription.id = invoice.subscription_id
								AND
							$join_condition
			WHERE
				cancel_date IS NULL
					AND
				invoice_number IS NULL
					AND
				product.auto_renew
					AND
				$interval_condition
					AND
				$where_condition
			ORDER BY
				DAYOFYEAR( subscription.activation_date )
			;"
		;

		$subscriptions = $wpdb->get_results( $query ); //unprepared SQL

		return $subscriptions;
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
							$failed[]   = $post_id;
						} else {
							$inserted[] = $post_id;
						}
					}
				}

				$url = add_query_arg( array(
					'inserted' => empty( $inserted ) ? false : implode( $inserted, ',' ),
					'failed'   => empty( $failed ) ? false : implode( $failed, ',' ),
				) );

				wp_redirect( $url );

				exit;
			}
		}
	}
}
