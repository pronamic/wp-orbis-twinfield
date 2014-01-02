<?php
/*
Plugin Name: Orbis Twinfield
Plugin URI: http://www.orbiswp.com/
Description: The Orbis Twinfield plugin allows you to synchronize Orbis information to Twinfield.

Version: 1.0.0
Requires at least: 3.5

Author: Pronamic
Author URI: http://www.pronamic.eu/

Text Domain: orbis_twinfield
Domain Path: /languages/

License: Copyright (c) Pronamic

GitHub URI: https://github.com/pronamic/wp-orbis-twinfield
*/

function orbis_twinfield_bootstrap() {
	include 'classes/orbis-twinfield-plugin.php';

	global $orbis_twinfield_plugin;
	
	$orbis_twinfield_plugin = new Orbis_Twinfield_Plugin( __FILE__ );
}

add_action( 'orbis_bootstrap', 'orbis_twinfield_bootstrap' );
