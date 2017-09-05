/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

/**
 * Page Component Input Field plugin
 * NOTE: increase the plugin version with each change in this file to force a browser reload
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 */
il.PCInputField = new function () {

	/**
	 * Self reference for usage in event handlers
	 * @type object
	 * @private
	 */
	var self = this;

	/**
	 * Page is already initialized
	 * @type boolean
	 * @private
	 */
	var initialized = false;

	/**
	 * Texts to be dynamically rendered
	 * @type object
	 * @private
	 */
	var texts = {};

	self.savings = 0;       // active saving operations
	self.changes = 0;		// unsaved changed in text fields or text areas
	self.maxChanges = 10;	// maximum unsaved changes

	/**
	 * Initialize the page
	 * called from ilPCInputFieldPluginGUI::getElementHTML(),
	 * @param a_texts    texts to be dynamically rendered
	 */
	this.init = function (a_texts) {
		if (!initialized) {
			initialized = true;
			texts = a_texts;

			var activeFields = $('.ilPCInputFieldActive');
			activeFields.change(self.fieldChange);
			activeFields.keydown(self.fieldKeydown);

			/*Click on send*/
			$('input[type="submit"]').click(self.confirm);
			$('a#pcinfi_send_button').click(self.send);
			$('a#pcinfi_cancel_button').click(self.hideNavigationModal);
		}
	}


	/**
	 * Text is entered in a field
	 */
	this.fieldKeydown = function () {
		self.changes++;
		if (self.changes > self.maxChanges) {
			self.save($(this));
			self.changes = 0;
		}
	}


	/**
	 * Field value is changed
	 */
	this.fieldChange = function () {
		self.save($(this));
		self.changes = 0;
	};


	/**
	 * Save a changed input
	 * @param   field  jquery object of the field div
	 */
	this.save = function (field) {
		var value = null;
		switch (field.attr('data-field-type')) {
			case 'text':
				value = field.find('input').val();
				break;

			case 'textarea':
				value = field.find('textarea').val();
				break;

			case 'select':
				value = [];
				field.find('input:checked').each(function (index) {
					value[index] = $(this).val();
				});
				break;
		}

		// show loader
		self.savings++;
		field.find('.pcinfi-loader').css('visibility', 'visible');

		// POST data
		var url = field.attr('data-service-url');
		var data = {
			cmd: 'saveInput',
			value: value
		};

		$.ajax({
				type: 'POST',		// alwasy use POST for the api
				url: url,			// sync api url
				data: data,			// request data as object
				dataType: 'json'	// expected response data type
			})

			.fail(function (jqXHR) {
				self.savings--;
				if (self.savings <= 0) {
					field.find('.pcinfi-loader').css('visibility', 'hidden');
				}
				if (jqXHR.status !== 0) {
					alert('Saving Failed: (' + jqXHR.status + ') ' + jqXHR.responseText);
				}
			})

			.done(function (data) {
				self.savings--;
				if (self.savings <= 0) {
					field.find('.pcinfi-loader').css('visibility', 'hidden');
				}
			});
	}

	this.confirm = function (send_button) {
		//Get name of input, and data to send it to the exercise
		var input = send_button.target.id;

		var i = input.indexOf('_');
		window.input_name = input.substr(0, i);
		var send_info = input.substr(i + 1);

		var i2 = send_info.indexOf('_');
		window.exercise_id = send_info.substr(0, i2);
		window.assignment_id = send_info.substr(i2 + 1);

		//Get content and type of input
		window.input_content = null;
		window.input_type = null;
		window.field = $("#" + window.input_name);

		switch (field.parent().attr('data-field-type')) {
			case 'text':
				window.input_content = window.field.find('input').val();
				window.input_type = 'text';
				break;

			case 'textarea':
				window.input_content = window.field.find('textarea').val();
				window.input_type = 'textarea';
				break;

			case 'select':
				window.input_content = [];
				window.field.find('input:checked').each(function (index) {
					window.input_content[index] = $(this).val();
				});
				window.input_type = 'select';
				break;
		}

		$("#pcinfi_" + input_name + "_confirmation").modal('show');
	}

	this.send = function () {

		// show loader
		self.savings++;
		window.field.parent().find('.pcinfi-loader').css('visibility', 'visible');

		// POST data
		var url = window.field.parent().attr('data-service-url');
		var data = {
			cmd: 'sendInput',
			name: window.input_name,
			type: window.input_type,
			value: window.input_content,
			exercise: window.exercise_id,
			assignment: window.assignment_id
		};

		$.ajax({
				type: 'POST',		// always use POST for the api
				url: url,			// sync api url
				data: data,			// request data as object
				dataType: 'json'	// expected response data type
			})
			.fail(function (jqXHR) {
				self.savings--;
				if (self.savings <= 0) {
					window.field.parent().find('.pcinfi-loader').css('display', 'block');
				}
				if (jqXHR.status !== 0) {
					alert('Saving Failed: (' + jqXHR.status + ') ' + jqXHR.responseText);
				}
			})

			.done(function (data) {
				self.savings--;
				if (self.savings <= 0) {
					window.field.parent().find('.pcinfi-loader').css('visibility', 'hidden');
				}

				//Change status to submitted
				$('#status_' + window.input_name).html(texts.submitted + ' ' + data.submit_time_str);

				//Change send button to re-submit
				$('input#' + window.input_name + '_' + window.exercise_id + '_' + window.assignment_id).attr('value', texts.re_submit);
				$("#pcinfi_" + input_name + "_confirmation").modal('hide');
			});
	}

	/**
	 * Hide the navigation modal
	 */
	this.hideNavigationModal = function () {
		$('#pcinfi_' + window.input_name + '_confirmation').modal('hide');
	}
};
