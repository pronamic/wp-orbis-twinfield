<?php

/**
 * Title: Orbis Twinfield plugin
 * Description:
 * Copyright: Copyright (c) 2013
 * Company: Pronamic
 *
 * @author Remco Tolsma
 * @version 1.0
 */
class Orbis_Twinfield_Plugin extends Orbis_Plugin {
	/**
	 * Construct and initialize the plugin
	 *
	 * @param string $file plugin main file
	 */
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->set_name( 'orbis_twinfield' );
		$this->set_db_version( '1.0.0' );

		// Admin
		if ( is_admin() ) {
			$this->admin = new Orbis_Twinfield_Admin( $this );
		}

		// Includes
		$this->plugin_include( 'includes/template.php' );

		// Actions
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'twinfield_post_customer', array( $this, 'twinfield_post_customer' ), 20, 2 );
	}

	//////////////////////////////////////////////////

	/**
	 * Loaded
	 */
	public function loaded() {
		$this->load_textdomain( 'orbis_twinfield', '/languages/' );
	}

	/**
	 * Initialize.
	 */
	public function init() {
		add_post_type_support( 'orbis_company', 'twinfield_customer' );
		add_post_type_support( 'orbis_subs_product', 'twinfield_article' );
		add_post_type_support( 'orbis_subscription', 'twinfield_invoiceable' );
	}

	//////////////////////////////////////////////////

	/**
	 * Twinfield post customer
	 *
	 * @param Customer $customer
	 * @param int      $post_id
	 */
	public function twinfield_post_customer( $customer, $post_id ) {
		if ( 'orbis_company' === get_post_type( $post_id ) ) {
			$customer->set_name( get_the_title( $post_id ) );

			$address = $customer->new_address();
			$address->set_default( true );
			$address->set_type( \Pronamic\WP\Twinfield\AddressTypes::INVOICE );
			$address->set_name( get_the_title( $post_id ) );
			$address->set_field_2( get_post_meta( $post_id, '_orbis_address', true ) );
			$address->set_postcode( get_post_meta( $post_id, '_orbis_postcode', true ) );
			$address->set_city( get_post_meta( $post_id, '_orbis_city', true ) );
			$address->set_country( get_post_meta( $post_id, '_orbis_country', true ) );
			$address->set_email( get_post_meta( $post_id, '_orbis_email', true ) );
			$address->set_field_4( get_post_meta( $post_id, '_orbis_vat_number', true ) );
			$address->set_field_5( get_post_meta( $post_id, '_orbis_kvk_number', true ) );

			$financials = $customer->get_financials();
			$financials->set_due_days( 14 );

			$invoice_email = get_post_meta( $post_id, '_orbis_invoice_email', true );

			if ( ! empty( $invoice_email ) ) {
				$financials->set_ebilling( true );
				$financials->set_ebillmail( $invoice_email );

				$credit_management = $customer->get_credit_management();
				$credit_management->set_send_reminder( 'email' );
				$credit_management->set_reminder_email( $invoice_email );
			}
		}

		return $customer;
	}
}
