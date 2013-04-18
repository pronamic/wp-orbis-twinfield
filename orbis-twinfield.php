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

function orbis_twinfield_bootstrap() {
	include 'classes/orbis-twinfield-plugin.php';

	global $orbis_twinfield_plugin;
	
	$orbis_twinfield_plugin = new Orbis_Twinfield_Plugin( __FILE__ );
}

add_action( 'orbis_bootstrap', 'orbis_twinfield_bootstrap' );
