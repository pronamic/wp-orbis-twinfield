;(function($) {
	
	var OTA = {
		
		/**
		 * Holds global config for all modules, and
		 * the OTA object
		 */
		config: {},
		
		/**
		 * Autoloaded modules
		 * 
		 * @returns void
		 */
		ready: function() {
			
			// Autoloads the form_builder_metabox module
			OTA.form_builder_metabox.ready();
			
		},
				
		/**
		 * Holds all aspects associated with the
		 * custom meta box form builder.
		 */
		form_builder_metabox: {
			
			/**
			 * Holds the configuration options
			 * and DOM elements used in this
			 * object
			 */
			config:{
				dom: {}
			},
					
			/**
			 * Sets the DOM elements and starts 
			 * the binds method.
			 * 
			 * @returns void
			 */
			ready: function() {
				
				// Builds the DOM elements for the form_builder_metabox
				OTA.form_builder_metabox.config.dom.holder = $('.jFormBuilderBox');
				OTA.form_builder_metabox.config.dom.spinner = $('.jFormBuilderBox .spinner');
				OTA.form_builder_metabox.config.dom.message_holder = $('.jFormBuilderBoxMessages');
				OTA.form_builder_metabox.config.dom.inputs = OTA.form_builder_metabox.config.dom.holder.find('input[type=hidden]');
				OTA.form_builder_metabox.config.dom.submit = OTA.form_builder_metabox.config.dom.holder.find('input[type=submit]');
				OTA.form_builder_metabox.config.dom.customer_id_input = $('input[name=twinfield_customer_id]');
				
				// Start the element binds
				OTA.form_builder_metabox.binds();
				
			},
					
			/**
			 * Binds the elements from form_builder_metabox.config.dom
			 * to certain events
			 * 
			 * @returns void
			 */
			binds: function() {
				
				// Listens to a click on the submit button side the jFormBuilderBox
				OTA.form_builder_metabox.config.dom.submit.click(
					OTA.form_builder_metabox.submit
				);
			
			},
					
			/**
			 * Prevents the normal action of submit and makes an ajax
			 * request that will take the data into the formbuilder
			 * process
			 * 
			 * @returns void
			 */
			submit: function(event) {
				event.preventDefault();
				
				// Hold all data to be posted
				var post_data = {
					action:'orbis_twinfield_synchronize'
				};
				
				// Go over each input, store in post_data in a format ready for ajax submission
				$.each( OTA.form_builder_metabox.config.dom.inputs, function( index, dom_element ) {
					var _element = $(dom_element);
					post_data[_element.attr('name')] = _element.val();
				} );
				
				OTA.form_builder_metabox.config.dom.spinner.show();
				OTA.form_builder_metabox.config.dom.message_holder.empty()
				
				$.ajax( {
					type: "POST",
					url:ajaxurl,
					data:post_data,
					dataType:'json',
					success:OTA.form_builder_metabox.success,
					error: function(one, two, three) {
						alert(one + ' : ' + two + ' : ' + three);
					}
				} );
			},
			success: function(data) {
				OTA.form_builder_metabox.config.dom.spinner.hide();

				if ( ! data.resp ) {
					OTA.form_builder_metabox.config.dom.message_holder.empty().prepend(data.errors);
				} else {
					OTA.form_builder_metabox.config.dom.customer_id_input.val(data.id);
					OTA.form_builder_metabox.config.dom.message_holder.empty().prepend(data.message);
				}
			}
		}
	};
	
	// Start the OTA object when document is ready
	$(OTA.ready);
	
})(jQuery);