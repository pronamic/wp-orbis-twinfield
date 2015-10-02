<?php

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

$interval = 'Y';

$date = array(
	'year'  => date( 'Y' ),
	'month' => date( 'm' )
);

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
							<th scope="col"><?php esc_html_e( 'Subscription', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Price', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Start Date', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'End Date', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Twinfield', 'orbis_twinfield' ); ?></th>
						</tr>
					</thead>

					<tbody>
			
						<?php foreach ( $company->subscriptions as $i => $result ) : ?>

							<?php

							$name = 'subscriptions[%d][%s]';

							$date_start = new DateTime( $result->activation_date );
							$date_end   = new DateTime( $result->activation_date );
							$day   = $date_start->format( 'd' );
							$month = $date_start->format( 'm' );
							if ( $result->interval === 'Y' ) {
								$date_start->setDate( $date['year'], $month, $day );
								$date_end_timestamp = strtotime( $date['year'] . '-' . $month . '-' . $day . ' + 1 year' );
							} else if ( $result->interval === 'M' ) {
								$date_start->setDate( $date['year'], $date['month'], $day );
								$date_end_timestamp = strtotime( $date['year'] . '-' . $date['month'] . '-' . $day . ' + 1 month' );
							} else {
								$date_end_timestamp = strtotime( $date_string );
							}
							$date_end->setDate( date( 'Y', $date_end_timestamp ), date( 'm', $date_end_timestamp ), $day );

							$twinfield_article_code    = get_post_meta( $result->product_post_id, '_twinfield_article_code', true );
							$twinfield_subarticle_code = get_post_meta( $result->product_post_id, '_twinfield_subarticle_code', true );

							$line = $sales_invoice->new_line();
							$line->set_article( $twinfield_article_code );
							$line->set_subarticle( $twinfield_subarticle_code );
							$line->set_description( $result->subscription_name );
							$line->set_value_excl( (float) $result->price );
							$line->set_free_text_1( $result->name );
							$line->set_free_text_2( sprintf(
								'%s - %s',
								date_i18n( 'D j M Y', $date_start->getTimestamp() ),
								date_i18n( 'D j M Y', $date_end->getTimestamp() )
							) );
							$line->set_free_text_3( sprintf(
								__( 'Orbis ID: %s', 'orbis_twinfield' ),
								$result->id
							) );

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
									<?php echo esc_html( $date_start->format( 'Y-m-d H:i:s' ) ); ?>
								</td>
								<td>
									<?php echo esc_html( $date_end->format( 'Y-m-d H:i:s' ) ); ?>
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

									if ( ! empty( $twinfield_article_code ) ) {
										printf(
											'<strong>%s</strong>: %s',
											esc_html__( 'Subarticle', 'orbis_twinfield' ),
											esc_html( $twinfield_subarticle_code )
										);
									}

									?>
								</td>
							</tr>

						<?php endforeach; ?>
			
					</tbody>
				</table>

				<div class="panel-footer">
					<?php

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

						if ( $response && $response->is_successful() ) {
							$sales_invoice = $response->get_sales_invoice();

							$number = $sales_invoice->get_header()->get_number();

							foreach ( $register_invoices as $object ) {
								$subscription = new Orbis_Subscription( $object->post_id );

								$result = $subscription->register_invoice( $number, $object->start_date, $object->end_date );
							}
						}
					}

					printf(
						'<input name="company" value="%s" type="hidden" />',
						esc_attr( $company->id )
					);

					submit_button(
						__( 'Create Invoice', 'orbis_twinfield' ),
						'secondary',
						'orbis_twinfield_create_invoice'
					);

					?>
				</div>
			</div>
		</form>

	<?php endforeach; ?>
</div>
