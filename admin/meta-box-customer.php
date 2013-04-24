<?php

global $post;

$id      = get_post_meta( $post->ID, '_twinfield_customer_id', true );
$title   = $post->post_title;
$post_id = $post->ID;

// Perhaps move all this into a class to represent orbis_company
$kvk_number = get_post_meta( $post->ID, '_orbis_company_kvk_number', true );
$email      = get_post_meta( $post->ID, '_orbis_company_email', true );
$website    = get_post_meta( $post->ID, '_orbis_company_website', true );

$address    = get_post_meta( $post->ID, '_orbis_company_address', true );
$postcode   = get_post_meta( $post->ID, '_orbis_company_postcode', true );
$city       = get_post_meta( $post->ID, '_orbis_company_city', true );
$country    = get_post_meta( $post->ID, '_orbis_company_country', true );
$ebilling   = get_post_meta( $post->ID, '_orbis_company_ebilling', true );

$fields = array(
	'post_id'                => $post_id,
	'id'                     => $id,
	'name'                   => $title,
	'website'                => $website,
	'duedays'                => 30,
	'vatcode'                => 'VH',
	'ebilling'               => $ebilling,
	'ebillmail'              => $email,
	'addresses[1][default]'  => 'true',
	'addresses[1][type]'     => 'invoice',
	'addresses[1][name]'     => $title,
	'addresses[1][field2]'   => $address,
	'addresses[1][field5]'   => $kvk_number,
	'addresses[1][postcode]' => $postcode,
	'addresses[1][city]'     => $city,
	'addresses[1][country]'  => $country,
	'addresses[1][email]'    => $email
);

?>
<div class="jFormBuilderBox">
	<div class="jFormBuilderBoxMessages"></div>

	<?php 
	
	foreach ( $fields as $name => $value ) {
		printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $name ), esc_attr( $value ) );
	}
	
	?>

	<div>
		<input type="submit" value="<?php _e( 'Synchronize', 'orbis_twinfield' ); ?>" class="button" />
	</div>
	<span class="spinner" style="float: left;"></span>
</div>