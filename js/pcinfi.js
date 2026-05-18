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
			$('#pcinfi_send_button').click(self.send);
			$('#pcinfi_cancel_button').click(self.hideNavigationModal);
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
		
		console.log('[PCInputField] Button clicked, ID:', input);
		
		// FIXED: Parse from the END to avoid issues with underscores in field names
		// Format: fieldname_exerciseID_assignmentID
		// We need the last two underscore-separated parts
		var parts = input.split('_');
		if (parts.length < 3) {
			console.error('[PCInputField] Invalid button ID format:', input);
			alert('Fehler: Ungültige Button-ID. Bitte Plugin-Entwickler kontaktieren.');
			return;
		}
		
		window.assignment_id = parts.pop(); // Last part
		window.exercise_id = parts.pop();   // Second-to-last part  
		window.input_name = parts.join('_'); // Everything else (handles underscores in field name)
		
		console.log('[PCInputField] Parsed:', {
			field_name: window.input_name,
			exercise_id: window.exercise_id,
			assignment_id: window.assignment_id
		});

		//Get content and type of input
		window.input_content = null;
		window.input_type = null;
		window.field = $("#" + window.input_name);
		
		console.log('[PCInputField] Looking for field with ID:', window.input_name);
		console.log('[PCInputField] Found field element:', window.field.length > 0 ? 'YES' : 'NO');
		console.log('[PCInputField] Field HTML:', window.field.length > 0 ? window.field[0].outerHTML.substring(0, 200) : 'N/A');
		console.log('[PCInputField] Field parent data-field-type:', field.parent().attr('data-field-type'));
		
		if (window.field.length === 0) {
			console.error('[PCInputField] Field not found! ID:', window.input_name);
			alert('Fehler: Feld nicht gefunden. ID: ' + window.input_name);
			return;
		}

		switch (field.parent().attr('data-field-type')) {
			case 'text':
				// FIX: Get value directly from input element, not via .find()
				var inputElement = window.field.find('input').first();
				console.log('[PCInputField] Found input elements:', window.field.find('input').length);
				if (inputElement.length === 0) {
					// Fallback: maybe the field IS the input
					inputElement = window.field.filter('input').first();
					console.log('[PCInputField] Fallback - is field itself an input?', inputElement.length);
				}
				if (inputElement.length === 0) {
					// Last resort: find by name attribute
					inputElement = $('input[name="' + window.input_name + '"]').first();
					console.log('[PCInputField] Last resort - find by name:', inputElement.length);
				}
				window.input_content = inputElement.val() || '';
				window.input_type = 'text';
				console.log('[PCInputField] Text field value:', window.input_content, '(from', inputElement.length, 'element(s))');
				break;

			case 'textarea':
				var textareaElement = window.field.find('textarea').first();
				if (textareaElement.length === 0) {
					textareaElement = window.field.filter('textarea').first();
				}
				window.input_content = textareaElement.val() || '';
				window.input_type = 'textarea';
				console.log('[PCInputField] Textarea value:', window.input_content, '(from', textareaElement.length, 'element(s))');
				break;

			case 'select':
				window.input_content = [];
				window.field.find('input:checked').each(function (index) {
					window.input_content[index] = $(this).val();
				});
				window.input_type = 'select';
				console.log('[PCInputField] Select values:', window.input_content);
				break;
		}

		self.showModal("pcinfi_" + window.input_name + "_confirmation");
	}

	this.send = function () {
		console.log('[PCInputField] Sending to server:', {
			field: window.input_name,
			type: window.input_type,
			value: window.input_content,
			exercise: window.exercise_id,
			assignment: window.assignment_id
		});

		// Zeige Ladebalken im Modal
		$('#pcinfi_loading_indicator').show();
		$('#pcinfi_modal_buttons').hide();

		// show loader
		self.savings++;
		window.field.parent().find('.pcinfi-loader').css('visibility', 'visible');

		// POST data
		var url = window.field.parent().attr('data-service-url');

		// Falls der Wert leer ist, liefern wir bewusst '' statt undefined
		var postValue = (typeof window.input_content === 'undefined' || window.input_content === null)
			? ''
			: window.input_content;

		var data = {
			cmd: 'sendInput',
			name: window.input_name,
			type: window.input_type,
			value: postValue,
			exercise: window.exercise_id,
			assignment: window.assignment_id
		};

		$.ajax({
			type: 'POST',
			url: url,
			data: data,
			dataType: 'json'
		})
			.done(function (resp) {
				console.log('[PCInputField] Server response:', resp);

				self.savings--;
				if (self.savings <= 0) {
					window.field.parent().find('.pcinfi-loader').css('visibility', 'hidden');
				}

				// Verstecke Ladebalken und zeige Buttons wieder
				$('#pcinfi_loading_indicator').hide();
				$('#pcinfi_modal_buttons').show();

				var ts = (resp && resp.submit_time_str) ? resp.submit_time_str : '';
				$('#status_' + window.input_name).html(texts.submitted + ' ' + ts);
				$('input#' + window.input_name + '_' + window.exercise_id + '_' + window.assignment_id)
					.attr('value', texts.re_submit);
				self.hideModal("pcinfi_" + window.input_name + "_confirmation");
				
				console.log('[PCInputField] Success! Reloading page in 500ms...');
				
				// *** RELOAD PAGE to show updated status and AI feedback ***
				// Use hard reload to bypass form cache
				setTimeout(function() {
					// Add timestamp to force fresh load
					var url = window.location.href.split('?')[0];
					var params = new URLSearchParams(window.location.search);
					params.set('t', Date.now()); // Force cache bypass
					window.location.href = url + '?' + params.toString();
				}, 500); // Short delay to ensure modal closes smoothly
			})
			.fail(function (jqXHR, textStatus, errorThrown) {
				console.error('[PCInputField] AJAX Error:', {
					status: jqXHR.status,
					textStatus: textStatus,
					errorThrown: errorThrown,
					response: jqXHR.responseText
				});

				// Verstecke Ladebalken und zeige Buttons wieder bei Fehler
				$('#pcinfi_loading_indicator').hide();
				$('#pcinfi_modal_buttons').show();

				self.savings--;
				if (self.savings <= 0) {
					window.field.parent().find('.pcinfi-loader').css('visibility', 'hidden');
				}

				var msg = 'Fehler (' + jqXHR.status + '): ';
				if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
					msg += jqXHR.responseJSON.message;
				} else if (jqXHR.responseText) {
					msg += jqXHR.responseText;
				} else {
					msg += textStatus || 'Unbekannt';
				}
				console.error('pcinfi sendInput failed', { url, data, status: jqXHR.status, textStatus, errorThrown, responseText: jqXHR.responseText });
				alert(msg);
			});
	}


	/**
	 * Hide the navigation modal
	 */
	this.hideNavigationModal = function () {
		self.hideModal('pcinfi_' + window.input_name + '_confirmation');
	}

	/**
	 * Show a modal – native <dialog> (ILIAS 10) or Bootstrap 3/4 fallback (ILIAS 9)
	 */
	self.showModal = function (id) {
		var el = document.getElementById(id);
		if (!el) return;
		if (el.showModal) {
			el.showModal();
		} else if (typeof $.fn.modal !== 'undefined') {
			$(el).modal('show');
		}
	};

	/**
	 * Hide a modal – native <dialog> (ILIAS 10) or Bootstrap 3/4 fallback (ILIAS 9)
	 */
	self.hideModal = function (id) {
		var el = document.getElementById(id);
		if (!el) return;
		if (el.tagName === 'DIALOG') {
			el.close();
		} else if (typeof $.fn.modal !== 'undefined') {
			$(el).modal('hide');
		}
	};
};
