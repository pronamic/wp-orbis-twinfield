<?php

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

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

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
							<th scope="col"><?php esc_html_e( 'Price', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Twinfield', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Manual Invoice', 'orbis_twinfield' ); ?></th>
						</tr>
					</thead>

					<tfoot>
						<tr>
							<td colspan="2">

							</td>
							<td>
								<?php

								$total = 0;
								foreach ( $company->projects as $i => $result ) {
									$total += $result->price;
								}

								echo esc_html( orbis_price( $total ) );

								?>
							</td>
							<td colspan="1">

							</td>
							<td>
								<?php

								printf(
									'<input name="company" value="%s" type="hidden" />',
									esc_attr( $company->id )
								);

								submit_button(
									__( 'Create Invoice', 'orbis_twinfield' ),
									'secondary',
									'orbis_twinfield_create_invoice',
									false
								);

								?>
							</td>
							<td>
								<?php

								submit_button(
									__( 'Register Invoices', 'orbis_twinfield' ),
									'secondary',
									'orbis_twinfield_register_invoices',
									false
								);

								?>
							</td>
						</tr>
					</tfoot>

					<tbody>

						<?php foreach ( $company->projects as $i => $result ) : ?>

							<?php

							$name = 'projects[%d][%s]';

							$twinfield_article_code    = get_option( 'twinfield_default_article_code' );
							$twinfield_subarticle_code = get_option( 'twinfield_default_subarticle_code' );

							$line = $sales_invoice->new_line();
							$line->set_article( $twinfield_article_code );
							$line->set_subarticle( $twinfield_subarticle_code );
							$line->set_value_excl( (float) $result->price );

							$free_text_1 = $result->name;
							if ( strlen( $free_text_1 ) > 36 ) {
								// opmerkingen mag maximaal 36 tekens bevatten wanneer het een vrije tekst betreft.
								$free_text_1 = substr( $free_text_1, 0, 35 ) . '…';
							}
							$line->set_free_text_1( $free_text_1 );

							$line->set_free_text_2( '' );

							$line->set_free_text_3( $result->id );

							$register_invoices[] = (object) array(
								'post_id' => $result->project_post_id,
								'price'   => $result->price,
							);

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
									<?php echo esc_html( orbis_price( $result->price ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $result->name ); ?>
								</td>
								<td>
									<?php

									if ( ! empty( $twinfield_article_code ) ) {
										printf(
											'<strong>%s</strong>: %s',
											esc_html__( 'Article', 'orbis_twinfield' ),
											esc_html( $twinfield_article_code )
										);
									}

									echo '<br />';

									if ( ! empty( $twinfield_subarticle_code ) ) {
										printf(
											'<strong>%s</strong>: %s',
											esc_html__( 'Subarticle', 'orbis_twinfield' ),
											esc_html( $twinfield_subarticle_code )
										);
									}

									?>
								</td>
								<td>
									<?php

									$name = 'subscriptions[%d][%s]';

									?>
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'id' ) ); ?>" value="<?php echo esc_attr( $result->id ); ?>" type="hidden" />
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'post_id' ) ); ?>" value="<?php echo esc_attr( $result->project_post_id ); ?>" type="hidden" />
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'invoice_number' ) ); ?>" value="" type="text" />
								</td>
							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

				<div class="panel-footer">
					<?php

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
				</div>
			</div>
		</form>

	<?php endforeach; ?>
</div>
