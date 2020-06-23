<?php

use Pronamic\WordPress\Money\Money;

// Projects
$projects = $this->get_projects();

$companies = array();

foreach ( $projects as $project ) {
	$company_id = $project->principal_id;

	if ( ! isset( $companies[ $company_id ] ) ) {
		$company = new stdClass();

		$company->id       = $project->principal_id;
		$company->name     = $project->principal_name;
		$company->post_id  = $project->principal_post_id;
		$company->projects = array();

		$companies[ $company_id ] = $company;
	}

	$project->price = $project->available_seconds / HOUR_IN_SECONDS * 75;

	$companies[ $company_id ]->projects[] = $project;
}

function orbis_twinfield_trim_free_text( $free_text ) {
	if ( strlen( $free_text ) > 36 ) {
		// opmerkingen mag maximaal 36 tekens bevatten wanneer het een vrije tekst betreft.
		$free_text = substr( $free_text, 0, 35 ) . '…';
	}

	return $free_text;
}

function orbis_twinfield_maybe_create_invoice() {
	global $wpdb;

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! filter_has_var( INPUT_POST, 'orbis_twinfield_create_project_invoice' ) ) {
		return;
	}

	if ( ! filter_has_var( INPUT_POST, 'orbis_twinfield_create_project_invoice_nonce' ) ) {
		return;
	}

	$nonce  = filter_input( INPUT_POST, 'orbis_twinfield_create_project_invoice_nonce', FILTER_SANITIZE_STRING );
	$action = 'orbis_twinfield_create_project_invoice';

	if ( ! wp_verify_nonce( $nonce, $action ) ) {
		return;
	}

	// Ok.
	$sales_invoice_data = filter_input( INPUT_POST, 'sales_invoice', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

	$sales_invoice_object = (object) filter_var_array( $sales_invoice_data, array(
		'header' => array(
			'filter' => FILTER_UNSAFE_RAW,
			'flags'  => FILTER_REQUIRE_ARRAY,
		),
		'lines'  => array(
			'filter' => FILTER_UNSAFE_RAW,
			'flags'  => FILTER_REQUIRE_ARRAY,
		),
	) );

	$sales_invoice_header = (object) filter_var_array( $sales_invoice_object->header, array(
		'customer_id' => FILTER_UNSAFE_RAW,
	) );

	$sales_invoice_lines = array();

	foreach ( $sales_invoice_object->lines as $i => $line_data ) {
		$sales_invoice_line = (object) filter_var_array( $line_data, array(
			'project_id'      => FILTER_UNSAFE_RAW,
			'project_post_id' => FILTER_UNSAFE_RAW,
			'action'          => FILTER_UNSAFE_RAW,
			'article_code'    => FILTER_UNSAFE_RAW,
			'subarticle_code' => FILTER_UNSAFE_RAW,
			'quantity'        => FILTER_VALIDATE_INT,
			'value_excl'      => FILTER_VALIDATE_FLOAT,
			'free_text_1'     => FILTER_UNSAFE_RAW,
			'free_text_2'     => FILTER_UNSAFE_RAW,
			'free_text_3'     => FILTER_UNSAFE_RAW,
		) );

		$sales_invoice_lines[ $i ]  = $sales_invoice_line;
	}

	$sales_invoice = new \Pronamic\WP\Twinfield\SalesInvoices\SalesInvoice();

	$header = $sales_invoice->get_header();

	$header->set_office( get_option( 'twinfield_default_office_code' ) );
	$header->set_type( get_option( 'twinfield_default_invoice_type' ) );
	$header->set_customer( $sales_invoice_header->customer_id );
	$header->set_status( Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceStatus::STATUS_CONCEPT );
	$header->set_payment_method( Pronamic\WP\Twinfield\PaymentMethods::BANK );
	$header->set_footer_text( sprintf(
		/* translators: placeholder is the date */
		__( 'Invoice created by Orbis on %s.', 'orbis_twinfield' ),
		date_i18n( 'D j M Y @ H:i' )
	) );

	$sales_invoice_lines_to_add = array_filter( $sales_invoice_lines, function( $sales_invoice_line ) {
		return ( 'add' === $sales_invoice_line->action );
	} );

	foreach ( $sales_invoice_lines_to_add as $item ) {
		$line = $sales_invoice->new_line();
	
		$line->set_article( $item->article_code );
		$line->set_subarticle( $item->subarticle_code );
		$line->set_value_excl( (float) $item->value_excl );
		$line->set_free_text_1( orbis_twinfield_trim_free_text( $item->free_text_1 ) );
		$line->set_free_text_2( orbis_twinfield_trim_free_text( $item->free_text_2 ) );
		$line->set_free_text_3( orbis_twinfield_trim_free_text( $item->free_text_3 ) );
	}

	/**
	 * @todo https://github.com/wp-orbis/wp-orbis-projects/blob/develop/classes/class-admin-project-post-type.php
	 */

	echo '<pre>';
	var_dump( $sales_invoice );
	echo '</pre>';

	echo '<pre>';
	var_dump( $sales_invoice_lines_to_add );
	echo '</pre>';

	exit;
}

orbis_twinfield_maybe_create_invoice();

if ( filter_has_var( INPUT_POST, 'orbis_twinfield_create_invoice' ) ) {
	$posted_company = filter_input( INPUT_POST, 'company', FILTER_SANITIZE_STRING );

	if ( $company->id === $posted_company ) {
		$client = new Pronamic\WP\Twinfield\Client();

		$user         = get_option( 'twinfield_username' );
		$password     = get_option( 'twinfield_password' );
		$organisation = get_option( 'twinfield_organisation' );
		$office       = get_option( 'twinfield_default_office_code' );
		$type         = get_option( 'twinfield_default_invoice_type' );

		$credentials = new Pronamic\WP\Twinfield\Credentials( $user, $password, $organisation );

		$logon_response = $client->logon( $credentials );

		$session = $client->get_session( $logon_response );

		$xml_processor = new Pronamic\WP\Twinfield\XMLProcessor( $session );

		$service = new Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceService( $xml_processor );

		$response = $service->insert_sales_invoice( $sales_invoice );

		if ( $response ) {
			if ( $response->is_successful() ) {
				$sales_invoice = $response->get_sales_invoice();

				$number = $sales_invoice->get_header()->get_number();

				foreach ( $register_invoices as $object ) {
					$project = new Orbis_Project( $object->post_id );

					$result = $project->register_invoice( $number, $object->price );
				}

				esc_html_e( 'Twinfield invoice created.', 'orbis_twinfield' );
			} else {
				$xml = simplexml_load_string( $response->get_message()->asXML() );
				$xsl = simplexml_load_file( plugin_dir_path( $this->plugin->file ) . '/admin/twinfield-salesinvoices.xsl' );

				$proc = new XSLTProcessor();
				$proc->importStyleSheet( $xsl );

				echo $proc->transformToXML( $xml ); // WPCS: xss ok
			}
		} else {
			esc_html_e( 'No response from Twinfield.', 'orbis_twinfield' );
		}
	}
}

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<datalist id="twinfield_articles">
		<option value="MARKETING">
		<option value="STRIPPENKAART">
		<option value="WEB_DEVELOPMENT">
	</datalist>

	<datalist id="twinfield_subarticles">
		<option value="HOUR_1">
		<option value="HOURS_2">
		<option value="WEB_DEVELOPMENT">
	</datalist>

	<?php foreach ( $companies as $company ) : ?>

		<?php

		$twinfield_customer = get_post_meta( $company->post_id, '_twinfield_customer_id', true );

		$sales_invoice = new Pronamic\WP\Twinfield\SalesInvoices\SalesInvoice();

		$header = $sales_invoice->get_header();

		$header->set_office( get_option( 'twinfield_default_office_code' ) );
		$header->set_type( get_option( 'twinfield_default_invoice_type' ) );
		$header->set_customer( $twinfield_customer );
		$header->set_status( Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceStatus::STATUS_CONCEPT );
		$header->set_payment_method( Pronamic\WP\Twinfield\PaymentMethods::BANK );
		$header->set_footer_text( sprintf(
			/* translators: placeholder is the date */
			__( 'Invoice created by Orbis on %s.', 'orbis_twinfield' ),
			date_i18n( 'D j M Y @ H:i' )
		) );

		$register_invoices = array();

		/**
		 * Sales invoice.
		 *
		 * sales_invoice[header][office_id]
		 * sales_invoice[header][type]
		 * sales_invoice[header][customer_id]
		 * sales_invoice[header][status]
		 * sales_invoice[header][payment_method]
		 * sales_invoice[header][header_text] 
		 * sales_invoice[header][footer_text]
		 * sales_invoice[lines][0][checked]
		 * sales_invoice[lines][0][project_id]
		 * sales_invoice[lines][0][project_post_id]
		 * sales_invoice[lines][0][article_code]
		 * sales_invoice[lines][0][subarticle_code]
		 * sales_invoice[lines][0][value_excl]
		 * sales_invoice[lines][0][free_text_1]
		 * sales_invoice[lines][0][free_text_2]
		 * sales_invoice[lines][0][free_text_3]
		 */
		$name = 'sales_invoice[office_id]';

		?>

		<form method="post" action="">
			<div class="panel panel-default">
				<div class="panel-heading">
					<h3 class="panel-title">
						<a href="<?php echo esc_attr( get_permalink( $company->post_id ) ); ?>"><?php echo esc_html( $company->name ); ?></a>
					</h3>
				</div>

				<div class="panel-body">
					<dl class="dl-horizontal">
						<dt><?php esc_html_e( 'Customer', 'orbis_twinfield' ); ?></dt>
						<dd><?php echo esc_html( $twinfield_customer ); ?></dd>
					</dl>
				</div>

				<!-- Table -->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'ID', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Project', 'orbis_twinfield' ); ?></th>
							<th scope="col" colspan="4"><?php esc_html_e( 'Twinfield', 'orbis_twinfield' ); ?></th>
						</tr>
					</thead>

					<tbody>

						<?php foreach ( $company->projects as $i => $result ) : ?>

							<?php

							$project_hours = ( $result->available_seconds / HOUR_IN_SECONDS );

							$name = \sprintf(
								'sales_invoice[lines][%d]',
								$i
							);

							$article_code    = '';
							$subarticle_code = '';
							$quantity        = 1;

							if ( false !== \strpos( $result->name, 'Online marketing' ) ) {
								$article_code    = 'MARKETING';
								$subarticle_code = 'HOUR_1';
								$quantity        = $project_hours;
							}

							if ( false !== \strpos( $result->name, 'Strippenkaart' ) ) {
								$article_code    = 'STRIPPENKAART';
								
								switch ( $project_hours ) {
									case 2:
										$subarticle_code = 'HOURS_2';
										break;
									case 4:
										$subarticle_code = 'HOURS_4';
										break;
									case 5:
										$subarticle_code = 'HOURS_5';
										break;
									case 10:
										$subarticle_code = 'HOURS_10';
										break;
									case 20:
										$subarticle_code = 'HOURS_20';
										break;
								}
							}

							$free_text_1 = $result->id;
							$free_text_2 = $result->name;

							?>
							<tr>
								<td>
									<?php echo esc_html( $result->id ); ?>
								</td>
								<td>
									<a href="<?php echo esc_attr( get_permalink( $result->project_post_id ) ); ?>">
										<?php echo esc_html( get_the_title( $result->project_post_id ) ); ?>
									</a>
								</td>
								<td>
									<?php

									printf(
										'<input name="%s" value="%s" type="hidden" />',
										esc_attr( $name . '[project_id]' ),
										esc_attr( $result->id )
									);

									printf(
										'<input name="%s" value="%s" type="hidden" />',
										esc_attr( $name . '[project_post_id]' ),
										esc_attr( $result->project_post_id )
									);

									?>

									<div>
										<strong><?php \esc_html_e( 'Action', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<select name="%s">',
											\esc_attr( $name . '[action]' )
										);

										\printf(
											'<option value="%s">%s</option>',
											\esc_attr( '' ),
											\esc_html__( 'No action', 'orbis_twinfield' )
										);

										\printf(
											'<option value="%s">%s</option>',
											\esc_attr( 'add' ),
											\esc_html__( 'Add to invoice', 'orbis_twinfield' )
										);

										echo '</select>';

										?>
									</div>
								</td>
								<td>
									<div>
										<strong><?php \esc_html_e( 'Article', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" list="twinfield_articles" />',
											\esc_attr( $name . '[article_code]' ),
											\esc_attr( $article_code )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Subarticle', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" list="twinfield_subarticles" />',
											\esc_attr( $name . '[subarticle_code]' ),
											\esc_attr( $subarticle_code )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Quantity', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="number" />',
											\esc_attr( $name . '[quantity]' ),
											\esc_attr( $quantity )
										);

										?>
									</div>
								</td>
								<td>
									<div>
										<strong><?php \esc_html_e( 'Price excl.', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="number" step="0.01" />',
											\esc_attr( $name . '[value_excl]' ),
											\esc_attr( $result->price )
										);

										?>
									</div>
								</td>
								<td>
									<div>
										<strong><?php \esc_html_e( 'Free Text 1', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" />',
											\esc_attr( $name . '[free_text_1]' ),
											\esc_attr( $free_text_1 )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Free Text 2', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" />',
											\esc_attr( $name . '[free_text_2]' ),
											\esc_attr( $free_text_2 )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Free Text 3', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" />',
											\esc_attr( $name . '[free_text_3]' ),
											\esc_attr( $free_text_3 )
										);

										?>
									</div>
								</td>
							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

				<div class="panel-footer">
					<?php

					wp_nonce_field( 'orbis_twinfield_create_project_invoice', 'orbis_twinfield_create_project_invoice_nonce' );

					printf(
						'<input name="sales_invoice[company_id]" value="%s" type="hidden" />',
						esc_attr( $company->id )
					);

					printf(
						'<input name="sales_invoice[header][customer_id]" value="%s" type="hidden" />',
						esc_attr( $twinfield_customer )
					);

					submit_button(
						__( 'Create Invoice', 'orbis_twinfield' ),
						'secondary',
						'orbis_twinfield_create_project_invoice',
						false
					);

					?>
				</div>
			</div>
		</form>

	<?php endforeach; ?>
</div>
