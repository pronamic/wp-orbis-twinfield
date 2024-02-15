<?php

add_filter(
	'orbis_invoice_url',
	function ( $url, $data ) {
		if ( '' === $data ) {
			return $url;
		}

		$info = json_decode( $data );

		if ( ! is_object( $info ) ) {
			return $url;
		}

		if ( ! property_exists( $info, 'host' ) ) {
			return $url;
		}

		if ( ! property_exists( $info, 'invoice_number' ) ) {
			return $url;
		}

		if ( 'accounting.twinfield.com' !== $info->host ) {
			return $url;
		}

		return home_url( sprintf(
			'/facturen/%s/',
			$info->invoice_number
		) );
	},
	10,
	2
);
