<div class="jFormBuilderBox">
	<div class="jFormBuilderBoxMessages"></div>
	<input type="hidden" name="post_id" value="<?php echo $post_id; ?>"/>
	<input type="hidden" name="id" value="<?php echo $id; ?>"/>
	<input type="hidden" name="name" value="<?php echo $title; ?>"/>
	<input type="hidden" name="website" value="<?php echo $website; ?>"/>
	<input type="hidden" name="duedays" value="30"/>
	<input type="hidden" name="vatcode" value="VH"/>
	<input type="hidden" name="ebilling" value="true"/>
	<input type="hidden" name="ebillmail" value="<?php echo $email; ?>"/>
	<input type="hidden" name="addresses[1][default]" value="true" />
	<input type="hidden" name="addresses[1][type]" value="invoice"/>
	<input type="hidden" name="addresses[1][field2]" value="<?php echo $address; ?>"/>
	<input type="hidden" name="addresses[1][field5]" value="<?php echo $kvk_number; ?>"/>
	<input type="hidden" name="addresses[1][postcode]" value="<?php echo $postcode; ?>"/>
	<input type="hidden" name="addresses[1][city]" value="<?php echo $city; ?>"/>
	<input type="hidden" name="addresses[1][country]" value="<?php echo $country; ?>"/>
	<input type="hidden" name="addresses[1][email]" value="<?php echo $email; ?>"/>
	<input type="submit" value="<?php _e( 'Send to form builder' ); ?>"/>
</div>