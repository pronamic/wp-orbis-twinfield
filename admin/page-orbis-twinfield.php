<?php

// Date
$date = $this->get_date();

// Interval
$interval = $this->get_interval();

// Action URL
$action_url = add_query_arg( array(
	'post_type' => 'orbis_subscription',
	'page'      => 'orbis_twinfield',
	'date'      => $date->format( 'd-m-Y' ),
	'interval'  => $interval,
), admin_url( 'edit.php' ) );

// Subscriptions
$subscriptions = $this->get_subscriptions();

$companies = array();

foreach ( $subscriptions as $subscription ) {
	$company_id = $subscription->company_id;

	if ( ! isset( $companies[ $company_id ] ) ) {
		$company = new stdClass();
		$company->id            = $subscription->company_id;
		$company->name          = $subscription->company_name;
		$company->post_id       = $subscription->company_post_id;
		$company->subscriptions = array();

		$companies[ $company_id ] = $company;
	}

	$companies[ $company_id ]->subscriptions[] = $subscription;
}

?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php

	$statuses = array(
		'inserted' => __( 'Inserted', 'orbis_twinfield' ),
		'failed'   => __( 'Failed', 'orbis_twinfield' ),
	);

	foreach ( $statuses as $status => $label ) {
		if ( filter_has_var( INPUT_GET, $status ) ) {
			$ids = filter_input( INPUT_GET, $status, FILTER_SANITIZE_STRING );
			$ids = explode( ',', $ids );

			if ( ! empty( $ids ) ) {
				echo '<h3>', esc_html( $label ), '</h3>';

				$subscriptions = get_posts( array(
					'post_type'      => 'any',
					'post__in'       => $ids,
					'posts_per_page' => 50,
				) );

				if ( ! empty( $subscriptions ) ) {
					echo '<ul>';

					foreach ( $subscriptions as $subscription ) {
						echo '<li>';
						printf(
							'<a href="%s" target="_blank">%s</a>',
							esc_attr( get_permalink( $subscription->ID ) ),
							esc_html( get_the_title( $subscription->ID ) )
						);
						echo '</li>';
					}

					echo '</ul>';
				}
			}
		}
	}

	?>

	<ul class="subsubsub">
		<li>
			<?php echo esc_html( date_i18n( 'M Y', $date->getTimestamp() ) ); ?> |
		</li>
		<li>
			<a href="<?php echo esc_attr( remove_query_arg( 'date' ) ); ?>" class="btn btn-default">
				<?php esc_html_e( 'This month', 'orbis_twinfield' ); ?>
			</a>
		</li>
	</ul>

	<form method="get">
		<div class="tablenav top">
			<div class="alignleft actions bulkactions">
				<select name="interval">
					<option value="-1" selected="selected"><?php esc_html_e( 'Interval', 'orbis_twinfield' ); ?></option>
					<option value="Y" <?php selected( $interval, 'Y' ); ?>><?php esc_html_e( 'Yearly', 'orbis_twinfield' ); ?></option>
					<option value="Q" <?php selected( $interval, 'Q' ); ?>><?php esc_html_e( 'Quarterly', 'orbis_twinfield' ); ?></option>
					<option value="M" <?php selected( $interval, 'M' ); ?>><?php esc_html_e( 'Monthly', 'orbis_twinfield' ); ?></option>
				</select>

				<input type="hidden" name="post_type" value="orbis_subscription" />
				<input type="hidden" name="page" value="orbis_twinfield" />

				<input type="submit" class="button action" name="action" value="<?php esc_attr_e( 'Execute', 'orbis_twinfield' ); ?>" />
			</div>

			<div class="tablenav-pages">
				<span class="pagination-links">
					<?php

					$date_prev = clone $date;
					$date_prev->modify( '-1 month' );

					$link_prev = add_query_arg( 'date', $date_prev->format( 'd-m-Y' ) );

					$date_next = clone $date;
					$date_next->modify( '+1 month' );

					$link_next = add_query_arg( 'date', $date_next->format( 'd-m-Y' ) );

					?>
					<a class="prev-page" href="<?php echo esc_attr( $link_prev ); ?>">
						<span class="screen-reader-text">Vorige pagina</span><span aria-hidden="true">‹</span>
					</a>

					<a class="next-page" href="<?php echo esc_attr( $link_next ); ?>">
						<span class="screen-reader-text">Volgende pagina</span><span aria-hidden="true">›</span>
					</a>
				</span>

				
			</div>
		</div>
	</form>

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
			__( 'Invoice created by Orbis on %s.', 'orbis_twinfield' ),
			date_i18n( 'D j M Y @ H:i' )
		) );

		$register_invoices = array();

		?>

		<form method="post" action="<?php echo esc_attr( $action_url ); ?>">
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
							<th scope="col"><?php esc_html_e( 'Subscription', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Start Date', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'End Date', 'orbis_twinfield' ); ?></th>
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
								foreach ( $company->subscriptions as $i => $result ) {
									$total += $result->price;
								}

								echo esc_html( orbis_price( $total ) );

								?>
							</td>
							<td colspan="3">

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
			
						<?php foreach ( $company->subscriptions as $i => $result ) : ?>

							<?php

							$name = 'subscriptions[%d][%s]';

							$date_start = new DateTime( $result->activation_date );
							$date_end   = new DateTime( $result->activation_date );

							$day   = $date_start->format( 'd' );
							$month = $date_start->format( 'n' );

							switch ( $result->interval ) {
								// Month
								case 'M' : 
									$date_start->setDate( $date->format( 'Y' ), $date->format( 'n' ), $day );

									$date_end = clone $date_start;
									$date_end->modify( '+1 month' );

									break;
								// Quarter
								case 'Q' :
									$date_start = new DateTime( $result->expiration_date );
									
									$date_end   = new DateTime( $result->expiration_date );
									$date_end->modify( '+3 month' );

									break;
								// Year
								case 'Y' :
								default : 
									$date_start->setDate( $date->format( 'Y' ), $month, $day );

									$date_end = clone $date_start;
									$date_end->modify( '+1 year' );

									break;
							}

							$twinfield_article_code    = get_post_meta( $result->product_post_id, '_twinfield_article_code', true );
							$twinfield_subarticle_code = get_post_meta( $result->product_post_id, '_twinfield_subarticle_code', true );

							$line = $sales_invoice->new_line();
							$line->set_article( $twinfield_article_code );
							$line->set_subarticle( $twinfield_subarticle_code );
							//$line->set_description( $result->subscription_name );
							$line->set_value_excl( (float) $result->price );

							$free_text_1 = $result->name;
							if ( strlen( $free_text_1 ) > 36 ) {
								// opmerkingen mag maximaal 36 tekens bevatten wanneer het een vrije tekst betreft.
								$free_text_1 = substr( $free_text_1, 0, 35 ) . '…';
							}
							$line->set_free_text_1( $free_text_1 );

							$line->set_free_text_2( sprintf(
								'%s - %s',
								date_i18n( 'D j M Y', $date_start->getTimestamp() ),
								date_i18n( 'D j M Y', $date_end->getTimestamp() )
							) );
							$line->set_free_text_3( $result->id );

							$register_invoices[] = (object) array(
								'post_id'    => $result->post_id,
								'start_date' => $date_start,
								'end_date'   => $date_end,
							);

							?>
							<tr>
								<td>
									<?php echo esc_html( $result->id ); ?>
								</td>
								<td>
									<a href="<?php echo esc_attr( get_permalink( $result->post_id ) ); ?>">
										<?php echo esc_html( $result->subscription_name ); ?>
									</a>
								</td>
								<td>
									<?php echo esc_html( orbis_price( $result->price ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $result->name ); ?>
								</td>
								<td>
									<?php echo esc_html( date_i18n( 'D j M Y', $date_start->getTimestamp() ) ); ?>
								</td>
								<td>
									<?php echo esc_html( date_i18n( 'D j M Y', $date_end->getTimestamp() ) ); ?>
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
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'post_id' ) ); ?>" value="<?php echo esc_attr( $result->post_id ); ?>" type="hidden" />
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'invoice_number' ) ); ?>" value="" type="text" />
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'date_start' ) ); ?>" value="<?php echo esc_attr( $date_start->format( DATE_W3C ) ); ?>" type="hidden" />
									<input name="<?php echo esc_attr( sprintf( $name, $i, 'date_end' ) ); ?>" value="<?php echo esc_attr( $date_end->format( DATE_W3C ) ); ?>" type="hidden" />
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
										$subscription = new Orbis_Subscription( $object->post_id );

										$result = $subscription->register_invoice( $number, $object->start_date, $object->end_date );
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
