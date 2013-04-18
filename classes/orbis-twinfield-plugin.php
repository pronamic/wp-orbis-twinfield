<?php

class Orbis_Twinfield_Plugin extends Orbis_Plugin {
	public function __construct( $file ) {
		parent::__construct( $file );

		$this->plugin_include( 'includes/post.php' );

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'wp_ajax_form_builder_submit', array( $this, 'form_builder_submit' ) );
	}

	public function loaded() {
		$this->load_textdomain( 'orbis_twinfield', '/languages/' );
	}

	public function admin_enqueue_scripts() {
		wp_register_script( 'orbis-twinfield-admin', $this->plugin_url( 'assets/orbis-twinfield-admin.js' ), array( 'jquery' ) );

		wp_enqueue_script( 'orbis-twinfield-admin' );
	}

	function form_builder_submit() {
	
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
}
