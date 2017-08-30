/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

$(function () {

	/**
	 * Page Component Input Field plugin
	 *
	 * @author Fred Neumann <fred.neumann@fau.de>
	 * @version $Id$
	 */
	il.PCInputField = new function () {

		/**
		 * Self reference for usage in event handlers
		 * @type object
		 * @private
		 */
		var self = this;

		self.savings = 0;       // active saving operations
		self.changes = 0;		// unsaved changed in text fields or text areas
		self.maxChanges = 10;	// maximum unsaved changes

		/**
		 * Initialisation
		 */
		this.init = function () {
			var activeFields = $('.ilPCInputFieldActive');
			activeFields.change(self.fieldChange);
			activeFields.keydown(self.fieldKeydown);

			/*Click on send*/
			$('input[type="submit"]').click(self.send);
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

		this.send = function (send_button) {

			//Get name of input, and data to send it to the exercise
			var input = send_button.target.id;

			var i = input.indexOf('_');
			var input_name = input.substr(0, i);
			var send_info = input.substr(i + 1);

			var i2 = send_info.indexOf('_');
			var exercise_id = send_info.substr(0, i2);
			var assignment_id = send_info.substr(i2 + 1);

			//Get content and type of input
			var input_content = null;
			var input_type = null;
			var field = $("#" + input_name);


			switch (field.parent().attr('data-field-type')) {
				case 'text':
					input_content = field.find('input').val();
					input_type = 'text';
					break;

				case 'textarea':
					input_content = field.find('textarea').val();
					input_type = 'textarea';
					break;

				case 'select':
					input_content = [];
					field.find('input:checked').each(function (index) {
						input_content[index] = $(this).val();
					});
					input_type = 'select';
					break;
			}

			// show loader
			self.savings++;
			field.parent().find('.pcinfi-loader').css('visibility', 'visible');

			// POST data
			var url = field.parent().attr('data-service-url');
			var data = {
				cmd: 'sendInput',
				name: input_name,
				type: input_type,
				value: input_content,
				exercise: exercise_id,
				assignment: assignment_id
			};

			var text_submitted = $("#text_submitted_" + input_name).html();
			var button_resubmit = $("#button_resubmit_"+ input_name).html();

			$.ajax({
				type: 'POST',		// always use POST for the api
				url: url,			// sync api url
				data: data,			// request data as object
				dataType: 'json'	// expected response data type
			}).fail(function (jqXHR) {
					self.savings--;
					if (self.savings <= 0) {
						field.parent().find('.pcinfi-loader').css('visibility', 'hidden');
					}
					if (jqXHR.status !== 0) {
						alert('Saving Failed: (' + jqXHR.status + ') ' + jqXHR.responseText);
					}
				})

				.done(function () {
					self.savings--;
					if (self.savings <= 0) {
						field.parent().find('.pcinfi-loader').css('visibility', 'hidden');
					}

					//Change status to submitted
					$('#status_' + input_name).html(text_submitted);

					//Change send button to re-submit
					$('input#' + input_name + '_' + exercise_id + '_' + assignment_id).attr('value', button_resubmit);
				});
		}
	};

// initialize
	il.PCInputField.init();

});
