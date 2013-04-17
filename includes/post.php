<?php

function orbis_twinfield_init() {
	global $orbis_twinfield_plugin;

	add_post_type_support( 'orbis_company', 'twinfield_customer' );
}

add_action( 'init', 'orbis_twinfield_init' );

function orbis_twinfield_customer_meta_box() {
	echo 'hoi';
}

add_action( 'twinfield_customer_meta_box', 'orbis_twinfield_customer_meta_box' );
