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

?>
<div class="jFormBuilderBox">
	<div class="jFormBuilderBoxMessages"></div>

	<input type="hidden" name="post_id" value="<?php echo $post_id; ?>" />
	<input type="hidden" name="id" value="<?php echo $id; ?>" />
	<input type="hidden" name="name" value="<?php echo $title; ?>" />
	<input type="hidden" name="website" value="<?php echo $website; ?>" />
	<input type="hidden" name="duedays" value="30" />
	<input type="hidden" name="vatcode" value="VH" />
	<input type="hidden" name="ebilling" value="<?php echo $ebilling; ?>" />
	<input type="hidden" name="ebillmail" value="<?php echo $email; ?>" />
	<input type="hidden" name="addresses[1][default]" value="true" />
	<input type="hidden" name="addresses[1][type]" value="invoice" />
	<input type="hidden" name="addresses[1][field2]" value="<?php echo $address; ?>" />
	<input type="hidden" name="addresses[1][field5]" value="<?php echo $kvk_number; ?>" />
	<input type="hidden" name="addresses[1][postcode]" value="<?php echo $postcode; ?>" />
	<input type="hidden" name="addresses[1][city]" value="<?php echo $city; ?>" />
	<input type="hidden" name="addresses[1][country]" value="<?php echo $country; ?>" />
	<input type="hidden" name="addresses[1][email]" value="<?php echo $email; ?>" />
	<div>
		<input type="submit" value="<?php _e( 'Synchronize', 'orbis_twinfield' ); ?>" class="button" />
	</div>
	<span class="spinner" style="float: left;"></span>
</div>