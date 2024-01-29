<?php

use Pronamic\WordPress\Money\Money;

global $wpdb;

/**
 * Projects.
 */
$query = "
	SELECT
		project.id ,
		project.name ,
		project.number_seconds AS available_seconds ,
		project.invoice_number AS invoice_number ,
		project.invoicable ,
		project.post_id AS project_post_id,
		project.start_date AS project_start_date,
		manager.ID AS project_manager_id,
		manager.display_name AS project_manager_name,
		principal.id AS principal_id ,
		principal.name AS principal_name ,
		principal.post_id AS principal_post_id
	FROM
		$wpdb->orbis_projects AS project
			LEFT JOIN
		$wpdb->posts AS post
				ON project.post_id = post.ID
			LEFT JOIN
		$wpdb->users AS manager
				ON post.post_author = manager.ID
			LEFT JOIN
		$wpdb->orbis_companies AS principal
				ON project.principal_id = principal.id
	WHERE
		project.invoice_number IS NULL
			AND
		project.invoicable
			AND
		NOT project.invoiced
			AND
		project.start_date > '2011-01-01'
			AND
		(
			project.finished
				OR
			project.name LIKE '%strippenkaart%'
				OR
			project.name LIKE '%adwords%'
				OR
			project.name LIKE '%marketing%'
		)
	GROUP BY
		project.id
	ORDER BY
		principal.name ASC, project.start_date ASC, project.id ASC
	;
";

$projects = $wpdb->get_results( $query, OBJECT_K ); // unprepared SQL

/**
 * Project invoices.
 */
foreach ( $projects as $project ) {
	$project->invoices = array();
}

$project_ids = \wp_list_pluck( $projects, 'id' );

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

/**
 * Companies.
 */
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

	$companies[ $company_id ]->projects[ $project->id ] = $project;
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
	global $twinfield_plugin;

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
		'header_text' => FILTER_UNSAFE_RAW,
		'footer_text' => FILTER_UNSAFE_RAW,
	) );

	$sales_invoice_lines = array();

	foreach ( $sales_invoice_object->lines as $i => $line_data ) {
		$sales_invoice_line = (object) filter_var_array( $line_data, array(
			// Orbis.
			'project_id'      => FILTER_UNSAFE_RAW,
			'project_post_id' => FILTER_UNSAFE_RAW,
			'action'          => FILTER_UNSAFE_RAW,
			'time_string'     => FILTER_UNSAFE_RAW,
			// Header.
			'header_text'     => FILTER_UNSAFE_RAW,
			'footer_text'     => FILTER_UNSAFE_RAW,
			// Line.
			'article_code'    => FILTER_UNSAFE_RAW,
			'subarticle_code' => FILTER_UNSAFE_RAW,
			'quantity'        => FILTER_VALIDATE_INT,
			'value_excl'      => FILTER_VALIDATE_FLOAT,
			'vat_code'        => FILTER_UNSAFE_RAW,
			'free_text_1'     => FILTER_UNSAFE_RAW,
			'free_text_2'     => FILTER_UNSAFE_RAW,
			'free_text_3'     => FILTER_UNSAFE_RAW,
		) );

		$sales_invoice_line->seconds = \orbis_parse_time( $sales_invoice_line->time_string );

		$sales_invoice_lines[ $i ]  = $sales_invoice_line;
	}

	$header_texts = \array_filter( \explode( "\r\n", $sales_invoice_header->header_text ) );
	$footer_texts = \array_filter( \explode( "\r\n", $sales_invoice_header->footer_text ) );

	$sales_invoice = new \Pronamic\WP\Twinfield\SalesInvoices\SalesInvoice();

	$header = $sales_invoice->get_header();

	$header->set_office( get_option( 'twinfield_default_office_code' ) );
	$header->set_type( get_option( 'twinfield_default_invoice_type' ) );
	$header->set_customer( $sales_invoice_header->customer_id );
	$header->set_status( Pronamic\WP\Twinfield\SalesInvoices\SalesInvoiceStatus::STATUS_CONCEPT );
	$header->set_payment_method( Pronamic\WP\Twinfield\PaymentMethods::BANK );

	$sales_invoice_lines_to_add = array_filter( $sales_invoice_lines, function( $sales_invoice_line ) {
		return ( 'add' === $sales_invoice_line->action );
	} );

	foreach ( $sales_invoice_lines_to_add as $item ) {
		$line = $sales_invoice->new_line();

		$header_texts[] = $item->header_text;
		$footer_texts[] = $item->footer_text;

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
		$line->set_free_text_1( orbis_twinfield_trim_free_text( $item->free_text_1 ) );
		$line->set_free_text_2( orbis_twinfield_trim_free_text( $item->free_text_2 ) );
		$line->set_free_text_3( orbis_twinfield_trim_free_text( $item->free_text_3 ) );

		if ( 'VHEE' === $item->vat_code ) {
			$line->set_performance_type( \Pronamic\WP\Twinfield\PerformanceTypes::SERVICES );
			$line->set_performance_date( new \DateTime() );
		}
	}

	$header->set_header_text( \implode( "\r\n\r\n", \array_unique( \array_filter( $header_texts ) ) ) );
	$header->set_footer_text( \implode( "\r\n\r\n", \array_unique( \array_filter( $footer_texts ) ) ) );

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
		$xsl = simplexml_load_file( __DIR__ . '/twinfield-salesinvoices.xsl' );

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
	foreach ( $sales_invoice_lines_to_add as $item ) {
		if ( empty( $item->project_id ) ) {
			continue;
		}

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
		),
		wp_get_referer()
	);

	wp_safe_redirect( $url );

	exit;
}

$created_sales_invoice = orbis_twinfield_maybe_create_invoice();

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

	<style type="text/css">
		.orbis-dl {
			margin: 0;
		}

		.orbis-dl dt {
			font-weight: bold
		}
	</style>

	<?php if ( null !== $created_sales_invoice ) : ?>

		<div id="message" class="updated notice is-dismissible">
			<p>
				<?php

				$twinfield_sales_invoice_number = $created_sales_invoice->get_header()->get_number();

				$twinfield_sales_invoice_url = \home_url( '/twinfield/facturen/' . $twinfield_sales_invoice_number . '/' );

				\printf(
					'<a href="%s">%s</a>',
					\esc_url( $twinfield_sales_invoice_url ),
					\esc_html(
						\sprintf(
							__( 'Invoice %s created.', 'orbis_twinfield' ),
							\esc_html( $twinfield_sales_invoice_number )
						)
					)
				);

				?>
			</p>
		</div>

	<?php endif; ?>

	<?php foreach ( $companies as $company ) : ?>

		<?php

		$twinfield_customer = get_post_meta( $company->post_id, '_twinfield_customer_id', true );
		$country            = get_post_meta( $company->post_id, '_orbis_country', true );

		$header_texts = array(
			get_post_meta( $company->post_id, '_orbis_invoice_header_text', true ),
		);

		$footer_texts = array(
			get_post_meta( $company->post_id, '_orbis_invoice_footer_text', true ),
		);

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

		if ( isset( $vies_countries[ $country ] ) ) {
			$vat_code = 'VHEE'; // or perhaps 'VHV'

			$header_texts[] = 'Btw verlegd.';
		} elseif ( 'NL' !== $country ) {
			$vat_code = 'VHEW';
		}

		$terms = wp_get_post_terms( $company->post_id, 'orbis_payment_method' );

		$payment_method_term = array_shift( $terms );

		foreach ( $company->projects as $i => $project ) {
			$terms = wp_get_post_terms( $project->post_id, 'orbis_payment_method' );

			$term = array_shift( $terms );

			if ( is_object( $term ) ) {
				$payment_method_term = $term;
			}

			$header_texts[] = get_post_meta( $project->post_id, '_orbis_invoice_header_text', true );
			$footer_texts[] = get_post_meta( $project->post_id, '_orbis_invoice_footer_text', true );
		}

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

		$header_text = implode( "\r\n\r\n", $header_texts );
		$footer_text = implode( "\r\n\r\n", $footer_texts );

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
						<dd>
							<?php

							printf(
								'<input name="sales_invoice[header][customer_id]" value="%s" type="text" />',
								esc_attr( $twinfield_customer )
							);

							?>
						</dd>

						<dt><?php esc_html_e( 'Header Text', 'orbis_twinfield' ); ?></dt>
						<dd>
							<?php

							\printf(
								'<textarea name="sales_invoice[header][header_text]" cols="60" rows="3">%s</textarea>',
								\esc_textarea( $header_text )
							);

							?>
						</dd>

						<dt><?php esc_html_e( 'Footer Text', 'orbis_twinfield' ); ?></dt>
						<dd>
							<?php

							\printf(
								'<textarea name="sales_invoice[header][footer_text]" cols="60" rows="3">%s</textarea>',
								\esc_textarea( $footer_text )
							);

							?>
						</dd>
					</dl>
				</div>

				<!-- Table -->
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Project', 'orbis_twinfield' ); ?></th>
							<th scope="col" colspan="5"><?php esc_html_e( 'Twinfield', 'orbis_twinfield' ); ?></th>
						</tr>
					</thead>

					<tbody>

						<?php foreach ( $company->projects as $i => $result ) : ?>

							<?php

							$project_start_date = new \DateTimeImmutable( $result->project_start_date );

							$project_hours = ( $result->available_seconds / HOUR_IN_SECONDS );

							$invoice_number = \get_post_meta( $result->project_post_id, '_orbis_project_invoice_number', true );

							$name = \sprintf(
								'sales_invoice[lines][%d]',
								$i
							);

							$article_code    = '';
							$subarticle_code = '';
							$quantity        = 1;
							$price           = \get_post_meta( $result->project_post_id, '_orbis_price', true );

							$free_texts_1 = array(
								$result->id,
							);

							$free_texts_2 = array(
								$result->name,
							);

							$free_texts_3 = array(
								\get_post_meta( $result->project_post_id, '_orbis_invoice_line_description', true )
							);

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

								$free_texts_3[] = $project_start_date->format( 'd-m-Y' );
							}

							$free_texts_3[] = \orbis_time( $result->available_seconds );

							$free_text_1 = \implode( ' - ', \array_unique( \array_filter( $free_texts_1 ) ) ); 
							$free_text_2 = \implode( ' - ', \array_unique( \array_filter( $free_texts_2 ) ) );
							$free_text_3 = \implode( ' - ', \array_unique( \array_filter( $free_texts_3 ) ) );

							?>
							<tr>
								<td>
									<dl class="orbis-dl">
										<dt><?php \esc_html_e( 'ID', 'orbis_twinfield' ); ?></dt>
										<dd><?php echo \esc_html( $result->id ); ?></dd>

										<dt><?php \esc_html_e( 'Name', 'orbis_twinfield' ); ?></dt>
										<dd>
											<a href="<?php echo esc_attr( get_permalink( $result->project_post_id ) ); ?>">
												<?php echo esc_html( get_the_title( $result->project_post_id ) ); ?>
											</a>
										</dd>

										<dt><?php \esc_html_e( 'Invoice', 'orbis_twinfield' ); ?></dt>
										<dd><?php echo \esc_html( $invoice_number ); ?></dd>
									</dl>
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
											'<select name="%s" %s>',
											\esc_attr( $name . '[action]' ),
											empty( $invoice_number ) ? '' : 'disabled'
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
										<strong><?php \esc_html_e( 'Header Text', 'orbis_twinfield' ); ?></strong><br />

										<?php

										$header_text = get_post_meta( $result->project_post_id, '_orbis_invoice_header_text', true );

										\printf(
											'<textarea name="%s">%s</textarea>',
											\esc_attr( $name . '[header_text]' ),
											\esc_attr( $header_text )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Footer Text', 'orbis_twinfield' ); ?></strong><br />

										<?php

										$footer_text = get_post_meta( $result->project_post_id, '_orbis_invoice_footer_text', true );

										\printf(
											'<textarea name="%s">%s</textarea>',
											\esc_attr( $name . '[footer_text]' ),
											\esc_attr( $footer_text )
										);

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
										<strong><?php \esc_html_e( 'Time', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" />',
											\esc_attr( $name . '[time_string]' ),
											\esc_attr( \orbis_time( $result->available_seconds ) )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'Price excl.', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="number" step="0.01" />',
											\esc_attr( $name . '[value_excl]' ),
											\esc_attr( $price )
										);

										?>
									</div>

									<div>
										<strong><?php \esc_html_e( 'VAT Code', 'orbis_twinfield' ); ?></strong><br />

										<?php

										\printf(
											'<input name="%s" value="%s" type="text" />',
											\esc_attr( $name . '[vat_code]' ),
											\esc_attr( $vat_code )
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

							<?php foreach ( $result->invoices as $j => $project_invoice ) : ?>

								<tr>
									<?php

									$name = \sprintf(
										'sales_invoice[lines][%d.%d]',
										$i,
										$j
									);

									$date = new \DateTimeImmutable( $project_invoice->create_date );

									$free_text_1 = $result->id;
									$free_text_2 = 'Factuur ' . $project_invoice->invoice_number;
									$free_text_3 = \sprintf(
										'%s - %s',
										$date->format( 'd-m-Y' ),
										\orbis_time( $project_invoice->seconds )
									);

									?>
									<td>
										<?php echo \esc_html( $project_invoice->invoice_number ); ?>
									</td>
									<td>
										<div>
											<strong><?php \esc_html_e( 'Action', 'orbis_twinfield' ); ?></strong><br />

											<?php

											\printf(
												'<select name="%s" %s>',
												\esc_attr( $name . '[action]' ),
												empty( $invoice_number ) ? '' : 'disabled'
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
											<strong><?php \esc_html_e( 'Time', 'orbis_twinfield' ); ?></strong><br />

											<?php

											\printf(
												'<input name="%s" value="%s" type="text" />',
												\esc_attr( $name . '[time_string]' ),
												\esc_attr( \orbis_time( $project_invoice->seconds ) )
											);

											?>
										</div>

										<div>
											<strong><?php \esc_html_e( 'Price excl.', 'orbis_twinfield' ); ?></strong><br />

											<?php

											\printf(
												'<input name="%s" value="%s" type="number" step="0.01" />',
												\esc_attr( $name . '[value_excl]' ),
												\esc_attr( '-' . $project_invoice->amount )
											);

											?>
										</div>

										<div>
											<strong><?php \esc_html_e( 'VAT Code', 'orbis_twinfield' ); ?></strong><br />

											<?php

											\printf(
												'<input name="%s" value="%s" type="text" />',
												\esc_attr( $name . '[vat_code]' ),
												\esc_attr( $vat_code )
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
