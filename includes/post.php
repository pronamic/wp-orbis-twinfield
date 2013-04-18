<?php

function orbis_twinfield_init() {
	global $orbis_twinfield_plugin;

	add_post_type_support( 'orbis_company', 'twinfield_customer' );
}

add_action( 'init', 'orbis_twinfield_init' );

function orbis_twinfield_customer_meta_box() {
	global $post;

	$id         = get_post_meta( $post->ID, '_twinfield_customer_id', true );
	$title      = $post->post_title;
	$post_id    = $post->ID;

	// Perhaps move all this into a class to represent orbis_company
	$kvk_number = get_post_meta( $post->ID, '_orbis_company_kvk_number', true );
	$email      = get_post_meta( $post->ID, '_orbis_company_email', true );
	$website    = get_post_meta( $post->ID, '_orbis_company_website', true );

	$address    = get_post_meta( $post->ID, '_orbis_company_address', true );
	$postcode   = get_post_meta( $post->ID, '_orbis_company_postcode', true );
	$city       = get_post_meta( $post->ID, '_orbis_company_city', true );
	$country    = get_post_meta( $post->ID, '_orbis_company_country', true );

	include ORBIS_TWINFIELD_FOLDER . '/views/orbis_twinfield_customer_meta_box.php';
}

add_action( 'twinfield_customer_meta_box', 'orbis_twinfield_customer_meta_box' );
