<?php

class Orbis_Twinfield_Plugin extends Orbis_Plugin {
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->plugin_include( 'includes/post.php' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	public function loaded() {
		$this->load_textdomain( 'orbis_twinfield', '/languages/' );
	}

	public function orbis_twinfield_assets() {
		wp_register_script( 'orbis-twinfield-admin', $this->plugin_url( 'assets/orbis-twinfield-admin.js' ), array( 'jquery' ) );

		wp_enqueue_script( 'orbis-twinfield-admin' );
	}

}
