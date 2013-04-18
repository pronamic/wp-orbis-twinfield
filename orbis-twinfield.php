<?php
/*
Plugin Name: Orbis Twinfield
Plugin URI: http://orbiswp.com/
Description: 

Version: 0.1
Requires at least: 3.5

Author: Pronamic
Author URI: http://pronamic.eu/

Text Domain: orbis
Domain Path: /languages/

License: GPL

GitHub URI: https://github.com/pronamic/wp-orbis-twinfield
*/

define( 'ORBIS_TWINFIELD_FILE', __FILE__ );
define( 'ORBIS_TWINFIELD_FOLDER', dirname( __FILE__ ) );

function orbis_twinfield_bootstrap() {
	include 'classes/orbis-twinfield-plugin.php';

	global $orbis_twinfield_plugin;
	
	$orbis_twinfield_plugin = new Orbis_Twinfield_Plugin( __FILE__ );
}

add_action( 'orbis_bootstrap', 'orbis_twinfield_bootstrap' );

add_action( 'admin_enqueue_scripts', 'orbis_twinfield_assets' );
function orbis_twinfield_assets() {
	wp_register_script( 'orbis-twinfield-admin', plugins_url( '/assets/orbis-twinfield-admin.js', ORBIS_TWINFIELD_FILE ), array( 'jquery' ) );
	wp_enqueue_script( 'orbis-twinfield-admin' );
}

add_action( 'wp_ajax_form_builder_submit', 'orbis_twinfield_form_builder_submit' );
function orbis_twinfield_form_builder_submit() {
	
	$customer = new \Pronamic\WP\Twinfield\FormBuilder\Form\Customer();
	
	$data = $_POST;
	if ( empty( $data['id'] ) ) {
		$extra = $customer->extra_variables();
		$data['id'] = $extra['latest_customer_id'];
	}
	
	$notice = new \ZFramework\Util\Notice();
	
	if ( $customer->submit( $data ) ) {
		
		$customer_response = Pronamic\Twinfield\Customer\Mapper\CustomerMapper::map($customer->get_response());
		
		update_post_meta( $data['post_id'], '_twinfield_customer_id', $customer_response->getID() );
		
		$notice->updated( 'Successful!' );
		
		echo json_encode( array( 'resp' => true, 'id' => $customer_response->getID(), 'message' => $notice->retrieve() ) );
		exit;
	} else {
		$errors = $customer->get_response()->getErrorMessages();
		
		foreach ( $errors as $error ) {
			$notice->error( $error );
		}
		
		echo json_encode( array( 'resp' => false, 'errors' => $notice->retrieve() ) );
		exit;
	}
	
	
}