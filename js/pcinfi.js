/**
 * Copyright (c) 2015 Institut fuer Lern-Innovation, Friedrich-Alexander-Universitaet Erlangen-Nuernberg
 * GPLv3, see docs/LICENSE
 */

$(function() {

/**
 * Page Component Input Field plugin
 *
 * @author Fred Neumann <fred.neumann@fau.de>
 * @version $Id$
 */
il.PCInputField = new function() {

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
    this.init = function()
    {
        var activeFields = $('.ilPCInputFieldActive');
        activeFields.change(self.fieldChange);
        activeFields.keydown(self.fieldKeydown);
    }


    /**
     * Text is entered in a field
     */
    this.fieldKeydown = function()
    {
        self.changes++;
        if (self.changes > self.maxChanges) {
            self.save($(this));
            self.changes = 0;
        }
    }


    /**
     * Field value is changed
     */
    this.fieldChange = function()
    {
       self.save($(this));
       self.changes = 0;
    };


    /**
     * Save a changed input
     * @param   field  jquery object of the field div
     */
    this.save = function(field)
    {
        var value = null;
        switch(field.attr('data-field-type'))
        {
            case 'text':
                value = field.find('input').val();
                break;

            case 'textarea':
                value = field.find('textarea').val();
                break;

            case 'select':
                value = [];
                field.find('input:checked').each(function(index) {
                    value[index]=$(this).val();
                });
                break;
        }

        // show loader
        self.savings++;
        field.find('.pcinfi-loader').css('visibility','visible');

        // POST data
        var url = field.attr('data-service-url');
        var data = {
            cmd:   'saveInput',
            value: value
        };

        $.ajax({
            type: 'POST',		// alwasy use POST for the api
            url: url,			// sync api url
            data: data,			// request data as object
            dataType: 'json'	// expected response data type
        })

        .fail(function(jqXHR) {
            self.savings--;
            if (self.savings <= 0) {
                field.find('.pcinfi-loader').css('visibility','hidden');
            }
            if (jqXHR.status !== 0)
            {
                alert('Saving Failed: (' + jqXHR.status + ') ' + jqXHR.responseText);
            }
        })

        .done(function(data) {
            self.savings--;
            if (self.savings <= 0) {
                field.find('.pcinfi-loader').css('visibility','hidden');
            }
        });
    }
};

// initialize
il.PCInputField.init();

});
