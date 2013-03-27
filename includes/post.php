<?php

function orbis_twinfield_init() {
	global $orbis_twinfield_plugin;

	add_post_type_support( 'orbis_company', 'twinfield_customer' );
}

add_action( 'init', 'orbis_twinfield_init' );
