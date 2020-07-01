<?php 

global $wpdb;

function orbis_twinfield_trim_free_text( $free_text ) {
	if ( strlen( $free_text ) > 36 ) {
		// opmerkingen mag maximaal 36 tekens bevatten wanneer het een vrije tekst betreft.
		$free_text = substr( $free_text, 0, 35 ) . '…';
	}

	return $free_text;
}

function orbis_twinfield_maybe_create_invoice() {
	global $wpdb;
	global $twinfield_plugin;

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( ! filter_has_var( INPUT_POST, 'orbis_twinfield_create_invoice' ) ) {
		return;
	}

	if ( ! filter_has_var( INPUT_POST, 'orbis_twinfield_create_invoice_nonce' ) ) {
		return;
	}

	$nonce  = filter_input( INPUT_POST, 'orbis_twinfield_create_invoice_nonce', FILTER_SANITIZE_STRING );
	$action = 'orbis_twinfield_create_invoice';

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
		'header_text' => FILTER_UNSAFE_RAW,
		'footer_text' => FILTER_UNSAFE_RAW,
	) );

	$projects = array();

	$sales_invoice_lines = array();

	foreach ( $sales_invoice_object->lines as $i => $line_data ) {
		$sales_invoice_line = (object) filter_var_array( $line_data, array(
			// Line.
			'article_code'    => FILTER_UNSAFE_RAW,
			'subarticle_code' => FILTER_UNSAFE_RAW,
			'quantity'        => FILTER_VALIDATE_INT,
			'value_excl'      => FILTER_VALIDATE_FLOAT,
			'vat_code'        => FILTER_UNSAFE_RAW,
			'free_text_1'     => FILTER_UNSAFE_RAW,
			'free_text_2'     => FILTER_UNSAFE_RAW,
			'free_text_3'     => FILTER_UNSAFE_RAW,
			// Orbis.
			'project_id'      => FILTER_UNSAFE_RAW,
			'seconds'         => FILTER_UNSAFE_RAW,
		) );

		$sales_invoice_lines[ $i ]  = $sales_invoice_line;

		if ( $sales_invoice_line->project_id ) {
			if ( ! array_key_exists( $sales_invoice_line->project_id, $projects ) ) {
				$projects[ $sales_invoice_line->project_id ] = (object) array(
					'project_id' => $sales_invoice_line->project_id,
					'value_excl' => 0,
					'seconds'    => 0,
				);
			}

			$projects[ $sales_invoice_line->project_id ]->value_excl += $sales_invoice_line->value_excl;
			$projects[ $sales_invoice_line->project_id ]->seconds    += $sales_invoice_line->seconds;
		}
	}

	$sales_invoice = new \Pronamic\WP\Twinfield\SalesInvoices\SalesInvoice();

	$header = $sales_invoice->get_header();

	$header->set_office( get_option( 'twinfield_default_office_code' ) );
	$header->set_type( get_option( 'twinfield_default_invoice_type' ) );
	$header->set_customer( $sales_invoice_header->customer_id );
	$header->set_status( \Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceStatus::STATUS_CONCEPT );
	$header->set_payment_method( \Pronamic\WP\Twinfield\PaymentMethods::BANK );
	$header->set_header_text( $sales_invoice_header->header_text );
	$header->set_footer_text( $sales_invoice_header->footer_text );

	foreach ( $sales_invoice_lines as $item ) {
		$line = $sales_invoice->new_line();

		$line->set_article( $item->article_code );
		$line->set_subarticle( $item->subarticle_code );
		$line->set_quantity( $item->quantity );

		/**
		 * Only valid for invoice types with VAT exclusive units prices.
		 * Only add this tag to an XML request if the setting 
		 * 'Prices can be changed' on an item is set to 'true'.
		 * Otherwise, the price will be determined by the system.
		 *
		 * @link https://accounting.twinfield.com/webservices/documentation/#/ApiReference/SalesInvoices
		 */
		if ( 'WEB_DEVELOPMENT' === $item->article_code && 'WEB_DEVELOPMENT' === $item->subarticle_code && 1 === $item->quantity ) {
			$line->set_units_price_excl( (float) $item->value_excl );
		}

		$line->set_vat_code( $item->vat_code );
		$line->set_free_text_1( $item->free_text_1 );
		$line->set_free_text_2( $item->free_text_2 );
		$line->set_free_text_3( $item->free_text_3 );

		if ( 'VHEE' === $item->vat_code ) {
			$line->set_performance_type( \Pronamic\WP\Twinfield\PerformanceTypes::SERVICES );
			$line->set_performance_date( new \DateTime() );
		}
	}

	/**
	 * Send invoice to Twinfield.
	 */
	$xml_processor = $twinfield_plugin->get_xml_processor();

	$service = new \Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceService( $xml_processor );

	$response = $service->insert_sales_invoice( $sales_invoice );

	if ( ! $response ) {
		esc_html_e( 'No response from Twinfield.', 'orbis_twinfield' );

		exit;
	}

	if ( ! $response->is_successful() ) {
		$xml = simplexml_load_string( $response->get_message()->asXML() );
		$xsl = simplexml_load_file( __DIR__ . '/../admin/twinfield-salesinvoices.xsl' );

		$proc = new XSLTProcessor();
		$proc->importStyleSheet( $xsl );

		echo $proc->transformToXML( $xml ); // WPCS: xss ok

		exit;
	}

	$sales_invoice = $response->get_sales_invoice();

	$date   = new \DateTimeImmutable();
	$number = $sales_invoice->get_header()->get_number();

	/**
	 * Register invoice to invoiced projects.
	 *
	 * @link https://github.com/wp-orbis/wp-orbis-projects/blob/develop/classes/class-admin-project-post-type.php
	 */
	foreach ( $projects as $item ) {
		$data = array(
			'project_id'     => $item->project_id,
			'user_id'        => \get_current_user_id(),
			'invoice_number' => $number,
			'amount'         => $item->value_excl,
			'seconds'        => $item->seconds,
			'create_date'    => $date->format( 'Y-m-d' ),
		);

		$format = array(
			'project_id'     => '%d',
			'user_id'        => '%d',
			'invoice_number' => '%s',
			'amount'         => '%f',
			'seconds'        => '%d',
			'create_date'    => '%s',
		);

		$result = $wpdb->insert( $wpdb->orbis_projects_invoices, $data, $format );

		if ( false === $result ) {
			throw new \Exception(
				sprintf(
					'Error register invoice to project: %s, row: %s.',
					$wpdb->last_error,
					\print_r( $row, true )
				)
			);
		}

		\update_post_meta( $item->project_post_id, '_orbis_project_invoice_number', $number );
	}

	// Redirect.
	if ( \headers_sent() ) {
		return $sales_invoice;
	}

	$url = add_query_arg(
		array(
			'orbis_twinfield_invoice_created' => $number,
			'company_id'                      => false,
			'project_id'                      => false,
		),
		wp_get_referer()
	);

	wp_safe_redirect( $url );

	exit;
}

$created_sales_invoice = orbis_twinfield_maybe_create_invoice();

/**
 * Setup.
 */
$sales_invoice = (object) array(
	'header' => (object) array(
		'customer_id' => null,
		'header_text' => null,
		'footer_text' => null,
	),
	'lines'  => array(),
);

$header_texts = array();
$footer_texts = array();

$vies_countries = array(
	'AT' => 'Oostenrijk',
	'BE' => 'België',
	'BG' => 'Bulgarije',
	'CY' => 'Cyprus',
	'CZ' => 'Tsjechië',
	'DE' => 'Duitsland',
	'DK' => 'Denemarken',
	'EE' => 'Estland',
	'EL' => 'Griekenland',
	'ES' => 'Spanje',
	'FI' => 'Finland',
	'FR' => 'Frankrijk',
	'GB' => 'Verenigd Koninkrijk',
	'HR' => 'Kroatië',
	'HU' => 'Hongarije',
	'IE' => 'Ierland',
	'IT' => 'Italy',
	'LT' => 'Litouwen',
	'LU' => 'Luxemburg',
	'LV' => 'Letland',
	'MT' => 'Malta',
	'NL' => 'Nederland',
	'PL' => 'Polen',
	'PT' => 'Portugal',
	'RO' => 'Roemenië',
	'SE' => 'Zweden',
	'SI' => 'Slovenië',
	'SK' => 'Slowakije',
);

unset( $vies_countries['NL'] );

$vat_code = 'VH';

$payment_method_term = null;

/**
 * Company.
 */
$company_id = filter_input( INPUT_GET, 'company_id', FILTER_VALIDATE_INT );

if ( $company_id ) {
	$query = $wpdb->prepare(
		"
		SELECT
			company.*
		FROM
			$wpdb->orbis_companies AS company
		WHERE
			company.id = %d
		LIMIT
			1
		;
		",
		$company_id
	);

	$company = $wpdb->get_row( $query );

	if ( $company ) {
		$sales_invoice->header->customer_id = \get_post_meta( $company->post_id, '_twinfield_customer_id', true );

		$header_texts[] = \get_post_meta( $company->post_id, '_orbis_invoice_header_text', true );
		$footer_texts[] = \get_post_meta( $company->post_id, '_orbis_invoice_footer_text', true );

		// VAT Code.
		$country = \get_post_meta( $company->post_id, '_orbis_country', true );

		if ( isset( $vies_countries[ $country ] ) ) {
			$vat_code = 'VHEE'; // or perhaps 'VHV'

			$header_texts[] = 'Btw verlegd.';
		} elseif ( 'NL' !== $country ) {
			$vat_code = 'VHEW';
		}

		// Payment method.
		$terms = wp_get_post_terms( $company->post_id, 'orbis_payment_method' );

		$payment_method_term = array_shift( $terms );
	}
}

/**
 * Projects.
 */
$project_id = filter_input( INPUT_GET, 'project_id', FILTER_VALIDATE_INT );

if ( $project_id  ) {
	$where = '1 = 1';

	$where .= $wpdb->prepare( ' AND project.id = %d', $project_id );

	if ( $company ) {
		$where .= $wpdb->prepare( ' AND project.principal_id = %d', $company->id );
	}

	$query = "
		SELECT
			project.id AS project_id,
			project.name AS project_name,
			project.billable_amount AS project_billable_amount,
			project.number_seconds AS project_billable_time,
			project.invoice_number AS project_invoice_number,
			project.post_id AS project_post_id,
			project.start_date AS project_start_date,
			manager.ID AS project_manager_id,
			manager.display_name AS project_manager_name,
			principal.id AS principal_id ,
			principal.name AS principal_name ,
			principal.post_id AS principal_post_id,
			project_invoice_totals.project_billed_time,
			project_invoice_totals.project_billed_amount,
			project_invoice_totals.project_invoice_numbers,
			project_timesheet_totals.project_timesheet_time
		FROM
			orbis_projects AS project
				INNER JOIN
			wp_posts AS project_post
					ON project.post_id = project_post.ID
				INNER JOIN
			wp_users AS manager
					ON project_post.post_author = manager.ID
				INNER JOIN
			orbis_companies AS principal
					ON project.principal_id = principal.id
				LEFT JOIN
			(
				SELECT
					project_invoice.project_id,
					SUM( project_invoice.seconds ) AS project_billed_time,
					SUM( project_invoice.amount ) AS project_billed_amount,
					GROUP_CONCAT( DISTINCT project_invoice.invoice_number ) AS project_invoice_numbers
				FROM
					orbis_projects_invoices AS project_invoice
				GROUP BY
					project_invoice.project_id
			) AS project_invoice_totals ON project_invoice_totals.project_id = project.id
				LEFT JOIN
			(
				SELECT
					project_timesheet.project_id,
					SUM( project_timesheet.number_seconds ) AS project_timesheet_time
				FROM
					orbis_hours_registration AS project_timesheet
				GROUP BY
					project_timesheet.project_id
			) AS project_timesheet_totals ON project_timesheet_totals.project_id = project.id
		WHERE
			$where
		GROUP BY
			project.id
		ORDER BY
			principal.name
		;
	";

	$projects = $wpdb->get_results( $query, OBJECT_K );

	/**
	 * Project invoices.
	 */
	foreach ( $projects as $project ) {
		$project->invoices = array();
	}

	$project_ids = \wp_list_pluck( $projects, 'project_id' );

	$where = $wpdb->prepare(
		sprintf(
			'project_id IN ( %s )',
			implode( ', ', array_fill( 0, count( $project_ids ), '%d' ) )
		),
		$project_ids
	);

	$query = "
		SELECT
			*
		FROM
			$wpdb->orbis_projects_invoices
		WHERE
			$where
		;
	";

	$project_invoices = $wpdb->get_results( $query );

	foreach ( $project_invoices as $project_invoice ) {
		$project_id = \intval( $project_invoice->project_id );

		$project = $projects[ $project_id ];

		$project->invoices[] = $project_invoice;
	}

	foreach ( $projects as $item ) {
		/**
		 * We never invoice more time then available.
		 */
		$project_start_date = new \DateTimeImmutable( $item->project_start_date );

		$project_hours = ( $item->project_billable_time / HOUR_IN_SECONDS );

		$seconds    = \min( \intval( $item->project_timesheet_time ), \intval( $item->project_billable_time ) );
		$value_excl = ( 85 * ( $seconds / HOUR_IN_SECONDS ) );

		if ( $item->project_timesheet_time >= $item->project_billable_time ) {
			$seconds    = $item->project_billable_time;
			$value_excl = $item->project_billable_amount;
		}

		$line = (object) array(
			'article_code'    => '',
			'subarticle_code' => '',
			'quantity'        => 1,
			'value_excl'      => $value_excl,
			'vat_code'        => $vat_code,
			'free_text_1'     => '',
			'free_text_2'     => '',
			'free_text_3'     => '',
			'project_id'      => $item->project_id,
			'seconds'         => $seconds,
		);

		$free_texts_1 = array(
			$item->project_id,
		);

		$free_texts_2 = array(
			$item->project_name,
		);

		$free_texts_3 = array();

		if ( false !== \strpos( $item->project_name, 'Ontwikkelen' ) ) {
			$line->article_code    = 'WEB_DEVELOPMENT';
			$line->subarticle_code = 'WEB_DEVELOPMENT';
		}

		if ( false !== \strpos( $item->project_name, 'Online marketing' ) ) {
			$line->article_code    = 'MARKETING';
			$line->subarticle_code = 'HOUR_1';
			$line->quantity        = $project_hours;
			$line->value_excl      = $item->project_billable_amount;
			$line->seconds         = $item->project_billable_time;
		}

		if ( false !== \strpos( $item->project_name, 'Strippenkaart' ) ) {
			$line->article_code = 'STRIPPENKAART';
			$line->value_excl   = $item->project_billable_amount;
			$line->seconds      = $item->project_billable_time;
								
			switch ( $project_hours ) {
				case 2:
					$line->subarticle_code = 'HOURS_2';
					break;
				case 4:
					$line->subarticle_code = 'HOURS_4';
					break;
				case 5:
					$line->subarticle_code = 'HOURS_5';
					break;
				case 10:
					$line->subarticle_code = 'HOURS_10';
					break;
				case 20:
					$line->subarticle_code = 'HOURS_20';
					break;
			}
		}

		$free_texts_3[] = \get_post_meta( $item->project_post_id, '_orbis_invoice_line_description', true );
		$free_texts_3[] = $project_start_date->format( 'd-m-Y' );
		$free_texts_3[] = \orbis_time( $line->seconds );

		$line->free_text_1 = orbis_twinfield_trim_free_text( \implode( ' - ', \array_unique( \array_filter( $free_texts_1 ) ) ) ); 
		$line->free_text_2 = orbis_twinfield_trim_free_text( \implode( ' - ', \array_unique( \array_filter( $free_texts_2 ) ) ) );
		$line->free_text_3 = orbis_twinfield_trim_free_text( \implode( ' - ', \array_unique( \array_filter( $free_texts_3 ) ) ) );

		$sales_invoice->lines[] = $line;

		/**
		 * Previous project invoices.
		 */
		foreach ( $item->invoices as $invoice ) {
			$date = new \DateTimeImmutable( $invoice->create_date );

			$invoice_line = (object) array(
				'article_code'    => $line->article_code,
				'subarticle_code' => $line->subarticle_code,
				'quantity'        => 1,
				'value_excl'      => -$invoice->amount,
				'vat_code'        => $vat_code,
				'free_text_1'     => 'Factuur ' . $invoice->invoice_number,
				'free_text_2'     => $date->format( 'd-m-Y' ),
				'free_text_3'     => \orbis_time( $invoice->seconds ),
				'project_id'      => $item->project_id,
				'seconds'         => -$invoice->seconds,
				'invoice_number'  => $invoice->invoice_number,
			);

			$sales_invoice->lines[] = $invoice_line;
		}
	}
}

/**
 * Header and footers texts.
 */
if ( is_object( $payment_method_term ) ) {
	$header_texts[] = $payment_method_term->description;
}

$header_texts[] = get_option( 'orbis_invoice_header_text' );
$footer_texts[] = get_option( 'orbis_invoice_footer_text' );

$footer_texts[] = sprintf(
	__( 'Invoice created by Orbis on %s.', 'orbis_twinfield' ),
	date_i18n( 'D j M Y @ H:i' )
);

$header_texts = array_filter( $header_texts );
$header_texts = array_unique( $header_texts );

$footer_texts = array_filter( $footer_texts );
$footer_texts = array_unique( $footer_texts );

$sales_invoice->header->header_text = implode( "\r\n\r\n", $header_texts );
$sales_invoice->header->footer_text = implode( "\r\n\r\n", $footer_texts );

/**
 * Action URL.
 */
$action_url = \home_url( \user_trailingslashit( 'twinfield/invoicer' ) );

/**
 * Display.
 */
get_header();

?>

<div>
	<?php if ( \filter_has_var( INPUT_GET, 'orbis_twinfield_invoice_created' ) ) : ?>

		<div class="alert alert-success" role="alert">
			<?php

			$invoice_number = filter_input( INPUT_GET, 'orbis_twinfield_invoice_created', FILTER_SANITIZE_STRING );

			$invoice_url = \home_url( \user_trailingslashit( 'twinfield/facturen/' . $invoice_number ) );

			\printf(
				__( 'Invoice %s created.', 'orbis_twinfield' ),
				\sprintf(
					'<a href="%s">%s</a>',
					\esc_url( $invoice_url ),
					\esc_html( $invoice_number )
				)
			);

			?>
		</div>

	<?php endif; ?>

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

	<form method="post" action="<?php echo \esc_url( $action_url ); ?>">
		<div class="card">
			<div class="card-header">
				<?php esc_html_e( 'Header', 'orbis_twinfield' ); ?>
			</div>

			<div class="card-body">
				<div class="form-group row">
					<label for="twinfield-sales-invoice-customer-id" class="col-sm-2 col-form-label"><?php esc_html_e( 'Customer', 'orbis_twinfield' ); ?></label>

					<div class="col-sm-10">
						<?php

						printf(
							'<input name="sales_invoice[header][customer_id]" value="%s" type="text" class="form-control" />',
							esc_attr( $sales_invoice->header->customer_id )
						);

						if ( $company ) {
							printf( 
								'<small id="%d" class="%s">%s</small>',
								\esc_attr( '' ),
								\esc_attr( 'form-text text-muted' ),
								\esc_html( $company->name )
							);
						}

						?>
					</div>
				</div>

				<div class="form-group row">
					<label for="twinfield-sales-invoice-header-text" class="col-sm-2 col-form-label"><?php esc_html_e( 'Header Text', 'orbis_twinfield' ); ?></label>

					<div class="col-sm-10">
						<?php

						\printf(
							'<textarea class="form-control" id="twinfield-sales-invoice-header-text" name="sales_invoice[header][header_text]" cols="60" rows="3">%s</textarea>',
							\esc_textarea( $sales_invoice->header->header_text )
						);

						?>
					</div>
				</div>

				<div class="form-group row">
					<label for="twinfield-sales-invoice-footer-text" class="col-sm-2 col-form-label"><?php esc_html_e( 'Footer Text', 'orbis_twinfield' ); ?></label>

					<div class="col-sm-10">
						<?php

						\printf(
							'<textarea class="form-control" id="twinfield-sales-invoice-footer-text" name="sales_invoice[header][footer_text]" cols="60" rows="3">%s</textarea>',
							\esc_textarea( $sales_invoice->header->footer_text )
						);

						?>
					</div>
				</div>
			</div>
		</div>

		<?php if ( $projects ) : ?>
		
			<div class="card mt-4">
				<div class="card-header">
					<?php esc_html_e( 'Projects', 'orbis_twinfield' ); ?>
				</div>

				<div class="card-body">

					<table class="table table-striped">
						<thead>
							<tr>
								<th scope="col"><?php \esc_html_e( 'Orbis ID', 'orbis_twinfield' ); ?></th>
								<th scope="col"><?php \esc_html_e( 'Post ID', 'orbis_twinfield' ); ?></th>
								<th scope="col"><?php \esc_html_e( 'Name', 'orbis_twinfield' ); ?></th>
								<th scope="col"><?php \esc_html_e( 'Billable Time', 'orbis_twinfield' ); ?></th>
								<th scope="col"><?php \esc_html_e( 'Billable Amount', 'orbis_twinfield' ); ?></th>
							</tr>
						</thead>

						<tbody>
							
							<?php foreach ( $projects as $item ) : ?>

								<tr>
									<td>
										<code class="text-body"><?php echo \esc_html( $item->project_id ); ?></code>
									</td>
									<td>
										<code class="text-body"><?php echo \esc_html( $item->project_post_id ); ?></code>
									</td>
									<td>
										<?php

										\printf(
											'<a href="%s">%s</a>',
											\esc_url( \get_permalink( $item->project_post_id ) ),
											\esc_html( $item->project_name )
										);

										?>
									</td>
									<td>
										<?php echo \orbis_time( $item->project_billable_time ); ?>
									</td>
									<td>
										<?php echo \orbis_price( $item->project_billable_amount ); ?>
									</td>
								</tr>

							<?php endforeach; ?>

						</tbody>
					</table>

				</div>
			</div>

		<?php endif; ?>

		<div class="card mt-4">
			<div class="card-header">
				<?php esc_html_e( 'Lines', 'orbis_twinfield' ); ?>
			</div>

			<div class="card-body">

				<table class="table table-striped">
					<thead>
						<tr>
							<th scope="col"><?php \esc_html_e( 'Article', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Price', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Free Texts', 'orbis_twinfield' ); ?></th>
							<th scope="col"><?php \esc_html_e( 'Project', 'orbis_twinfield' ); ?></th>
						</tr>
					</thead>

					<tbody>
						
						<?php foreach ( $sales_invoice->lines as $i => $line ) : ?>

							<tr>
								<?php

								$name = \sprintf(
									'sales_invoice[lines][%d]',
									$i
								);

								?>
								<td>
									<div class="form-group">
										<label for="exampleInputEmail1"><?php \esc_html_e( 'Article', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" list="twinfield_articles" class="form-control" />',
											\esc_attr( $name . '[article_code]' ),
											\esc_attr( $line->article_code )
										);

										?>
									</div>

									<div class="form-group">
										<label for="exampleInputEmail1"><?php \esc_html_e( 'Subarticle', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" list="twinfield_subarticles" class="form-control" />',
											\esc_attr( $name . '[subarticle_code]' ),
											\esc_attr( $line->subarticle_code )
										);

										?>
									</div>

									<div class="form-group">
										<label for="exampleInputEmail1"><?php \esc_html_e( 'Quantity', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="number" class="form-control" />',
											\esc_attr( $name . '[quantity]' ),
											\esc_attr( $line->quantity )
										);

										?>
									</div>
								</td>
								<td>
									<div class="form-group">
										<label><?php \esc_html_e( 'Price excl.', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" />',
											\esc_attr( $name . '[value_excl]' ),
											\esc_attr( $line->value_excl )
										);

										?>
									</div>

									<div class="form-group">
										<label><?php \esc_html_e( 'VAT Code', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" />',
											\esc_attr( $name . '[vat_code]' ),
											\esc_attr( $line->vat_code )
										);

										?>
									</div>
								</td>
								<td>
									<div class="form-group">
										<label><?php \esc_html_e( 'Free Text 1', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" maxlength="36" />',
											\esc_attr( $name . '[free_text_1]' ),
											\esc_attr( $line->free_text_1 )
										);

										?>
									</div>

									<div class="form-group">
										<label><?php \esc_html_e( 'Free Text 2', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" maxlength="36" />',
											\esc_attr( $name . '[free_text_2]' ),
											\esc_attr( $line->free_text_2 )
										);

										?>
									</div>

									<div class="form-group">
										<label><?php \esc_html_e( 'Free Text 3', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" maxlength="36" />',
											\esc_attr( $name . '[free_text_3]' ),
											\esc_attr( $line->free_text_3 )
										);

										?>
									</div>
								</td>
								<td>
									<div class="form-group">
										<label><?php \esc_html_e( 'Project ID', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" />',
											\esc_attr( $name . '[project_id]' ),
											\esc_attr( $line->project_id )
										);

										?>
									</div>

									<div class="form-group">
										<label><?php \esc_html_e( 'Time', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" />',
											\esc_attr( $name . '[seconds]' ),
											\esc_attr( $line->seconds )
										);

										?>
									</div>

									<div class="form-group">
										<label><?php \esc_html_e( 'Invoice Number', 'orbis_twinfield' ); ?></label>

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" class="form-control" />',
											\esc_attr( $name . '[invoice_number]' ),
											\esc_attr( $line->invoice_number )
										);

										?>
									</div>
								</td>
							</tr>

						<?php endforeach; ?>

					</tbody>
				</table>

			</div>
		</div>

		<div class="card mt-4">
			<div class="card-header">
				<?php esc_html_e( 'Totals', 'orbis_twinfield' ); ?>
			</div>

			<div class="card-body">
				<div class="form-group row">
					<label for="twinfield-sales-invoice-total" class="col-sm-2 col-form-label"><?php esc_html_e( 'Total', 'orbis_twinfield' ); ?></label>

					<div class="col-sm-10">
						<?php

						$values = \wp_list_pluck( $sales_invoice->lines, 'value_excl' );

						$total_value_excl = array_sum( $values );

						printf(
							'<input name="%s" value="%s" type="text" class="form-control" disabled="disabled" />',
							'sales_invoice[totals][value_excl]',
							$total_value_excl
						);

						?>
					</div>
				</div>
				<div class="form-group row">
					<label for="twinfield-sales-invoice-total-time" class="col-sm-2 col-form-label"><?php esc_html_e( 'Time', 'orbis_twinfield' ); ?></label>

					<div class="col-sm-10">
						<?php

						$values = \wp_list_pluck( $sales_invoice->lines, 'seconds' );

						$total_seconds = array_sum( $values );

						printf(
							'<input name="%s" value="%s" type="text" class="form-control" disabled="disabled" />',
							'sales_invoice[totals][time_string]',
							\esc_attr( \orbis_time( $total_seconds ) )
						);

						?>
					</div>
				</div>
			</div>
		</div>

		<div class="mt-4">
			<?php

			wp_nonce_field( 'orbis_twinfield_create_invoice', 'orbis_twinfield_create_invoice_nonce' );

			printf(
				'<button name="orbis_twinfield_create_invoice" value="true" type="submit" class="btn btn-primary">%s</button>',
				__( 'Create Invoice', 'orbis_twinfield' )
			); 

			?>
		</div>
	</form>
</div>

<?php

get_footer();
