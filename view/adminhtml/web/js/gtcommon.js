/**
 * Document load function
 * @type type
 */

 require([
    'jquery',
    'jquery/validate',
    'domReady!'], function ($) {
    // $('.close').click(function () {
    //     $('.cerasis_warehouse_overlay').hide();
    // });
    // $('.add_dropship_btn, .add_warehouse_btn').click(function () {
    //     $('.cerasis_warehouse_overlay').show();
    // });

    $('#gtQuoteSetting_fourth span, #gtConnSettings_first span').attr('data-config-scope', '');

    //$('#gtConnSettings_first-head').before('<div class="conn-setting-note">Note! You must have a GlobalTranz account to use this application. If you do not have one contact GlobalTranz at 800-734-5351 or <a href="http://cerasis.com/contact/transportation-management-consultation/" target="_blank">click here</a> to access the new account request form.</div>');

    $('#cerasisgtQuoteSetting_fourth_hndlngFee').attr('title', 'Handling Fee / Markup');

    // Set focus on first input field

    $(".cerasisQuoteServices").on('change load', function () {
        unselectAllCarrierSelectCheckbox();
    });

    $('#gtQuoteSetting_fourth_liftGate').on('change', function () {
        gtChangeLiftgateOption('#gtQuoteSetting_fourth_offerLiftGate', this.value);
        this.value == 1 ? $('#gtQuoteSetting_fourth_RADforLiftgate').val('0') : '';
    });

    $('#gtQuoteSetting_fourth_offerLiftGate').on('change', function () {
        gtChangeLiftgateOption('#gtQuoteSetting_fourth_liftGate', this.value);
    });

    $('#gtQuoteSetting_fourth_RADforLiftgate').on('change', function () {
        gtChangeLiftgateOption('#gtQuoteSetting_fourth_liftGate', (this.value == '1') ? '1' : '0');
    });

    $('#gtQuoteSetting_fourth_shippingService').on('change', function () {
        let option = $('#gtQuoteSetting_fourth_shippingService option:selected').val();
        if (option == 2){
            $('#row_gtQuoteSetting_fourth_enableCuttOff').val('0');
            $('#row_gtQuoteSetting_fourth_enableCuttOff').hide();
            $('#row_gtQuoteSetting_fourth_cutOffTime').hide();
            $('#row_gtQuoteSetting_fourth_offsetDays').hide();
            $('#row_gtQuoteSetting_fourth_shipDays').hide();
            if($('#suspend-rad-use').length)
            {
                $('#suspend-rad-use').prop('checked', true);
            }
        }else{
            $('#row_gtQuoteSetting_fourth_enableCuttOff').show()
            let cutOffTime = $('#gtQuoteSetting_fourth_enableCuttOff option:selected').val();
            if (cutOffTime == 1){
                $('#row_gtQuoteSetting_fourth_cutOffTime').show();
                $('#row_gtQuoteSetting_fourth_offsetDays').show();
                $('#row_gtQuoteSetting_fourth_shipDays').show();
            }else{
                $('#row_gtQuoteSetting_fourth_cutOffTime').hide();
                $('#row_gtQuoteSetting_fourth_offsetDays').hide();
                $('#row_gtQuoteSetting_fourth_shipDays').hide();
            }
        }
        //gtChangeLiftgateOption('#gtQuoteSetting_fourth_liftGate', (this.value == '1') ? '1' : '0');
    });

    $.validator.addMethod('validate-gtLt-decimal-limit-2', function (value) {
        return !!(gtLtValidateDecimal($, value, 2));
    }, 'Maximum 2 digits allowed after decimal point.');
});

function gtChangeLiftgateOption(selectId, optionVal) {
    if (optionVal == 1) {
        jQuery(selectId).val(0);
    }
}

/**
 * Add label to rating method
 */
function gtRatingMethodComment() {
    const ratingMethod = jQuery('#gtQuoteSetting_fourth_ratingMethod').val();
    if (ratingMethod == 3) {
        jQuery('#gtQuoteSetting_fourth_ratingMethod').next().text('Displays a single rate based on an average of a specified number of least expensive options.');
        jQuery('#gtQuoteSetting_fourth_options').next().text('Number of options to include in the calculation of the average.');
        jQuery('#gtQuoteSetting_fourth_labelAs').next().text('What the user sees during checkout, e.g. "Freight". If left blank will default to "Freight".');
    } else if (ratingMethod == 1) {
        jQuery('#gtQuoteSetting_fourth_ratingMethod').next().text('Displays a least expensive option.');
        jQuery('#gtQuoteSetting_fourth_labelAs').next().text('What the user sees during checkout, e.g. "Freight". Leave blank to display the carrier name.');
    } else {
        jQuery('#gtQuoteSetting_fourth_options').next().text('Number of options to display in the shopping cart.');
        jQuery('#gtQuoteSetting_fourth_ratingMethod').next().text('Displays a list of a specified number of least expensive options.');
    }
}

/**
 * Set empty values to warehouse and dropship fields and remove error class
 * @param {string} form_id
 */
function gtEmptyFieldsAndErr(form_id) {
    jQuery(form_id + " input[type='text']").each(function () {
        jQuery(this).val('');
        jQuery('.err').remove();
    });
    jQuery('.local-delivery-fee-err').remove();
    jQuery(form_id).find("input[type='checkbox']").prop('checked', false);
    jQuery('#instore-pickup-zipmatch .tag-i, #local-delivery-zipmatch .tag-i').trigger('click');
    jQuery('.city_select').hide();
    jQuery('.city_input').show();
    jQuery('#edit_form_id').val('');
    jQuery('#edit_dropship_form_id').val('');
}

/**
 * @param canAddWh
 */
function gtLtAddWarehouseRestriction(canAddWh) {
    switch (canAddWh) {
        case 0:
            jQuery("#append-warehouse").find("tr").removeClass('inactiveLink');
            jQuery('.add-wh-btn').addClass('inactiveLink');
            if (jQuery(".required-plan-msg").length == 0) {
                jQuery('.add-wh-btn').after('<a href="https://eniture.com/magento-2-globaltranz-ltl-freight-quotes/" target="_blank" class="required-plan-msg">Standard Plan required</a>');
            }
            jQuery("#append-warehouse").find("tr:gt(1)").addClass('inactiveLink');
            break;
        case 1:
            jQuery('#gtLt-add-wh-btn').removeClass('inactiveLink');
            jQuery('.required-plan-msg').remove();
            jQuery("#append-warehouse").find("tr").removeClass('inactiveLink');
            break;
        default:
            break;
    }

}

/**
 * call for warehouse ajax requests
 * @param {{password: (jQuery|string|undefined), shipperID: (jQuery|string|undefined), accessKey: (jQuery|string|undefined), pluginLicenceKey: (jQuery|string|undefined), username: (jQuery|string|undefined)}} parameters
 * @param {type} ajaxUrl
 * @param {string} responseFunction
 * @returns {function}
 */
function gtLtAjaxRequest(parameters, ajaxUrl, responseFunction) {
    new Ajax.Request(ajaxUrl, {
        method: 'POST',
        parameters: parameters,
        onSuccess: function (response) {
            const json = response.responseText;
            const data = JSON.parse(json);
            return responseFunction(data);

        }
    });
}

function gtLtGetRowData(data, loc) {
    return '<td>' + data.origin_city + '</td>' +
        '<td>' + data.origin_state + '</td>' +
        '<td>' + data.origin_zip + '</td>' +
        '<td>' + data.origin_country + '</td>' +
        '<td><a href="javascript:;" data-id="' + data.id + '" title="Edit" class="gtLt-edit-' + loc + '">Edit</a>' +
        ' | ' +
        '<a href="javascript:;" data-id="' + data.id + '" title="Delete" class="gtLt-del-' + loc + '">Delete</a>' +
        '</td>';
}

//This function serialize complete form data
function gtLtGetFormData($, formId) {
    // To initialize the Disabled inputs
    var disabled = $(formId).find(':input:disabled').removeAttr('disabled');
    var formData = $(formId).serialize();
    disabled.attr('disabled', 'disabled');
    var addData = '';
    $(formId + ' input[type=checkbox]').each(function () {
        if (!$(this).is(":checked")) {
            addData += '&' + $(this).attr('name') + '=';
        }
    });
    return formData + addData;
}


/*
* @identifierElem (will be the id or class name)
* @elemType (will be the type of identifier whether it an id or an class ) id = 1, class = 0
* @msgClass (magento style class) [success, error, info, warning]
* @msg (this will be the message which you want to print)
* */
function gtLtResponseMessage(identifierId, msgClass, msg) {
    identifierId = '#' + identifierId;
    let finalClass = 'message message-';
    switch (msgClass) {
        case 'success':
            finalClass += 'success success';
            break;
        case 'info':
            finalClass += 'info info';
            break;
        case 'error':
            finalClass += 'error error';
            break;
        default:
            finalClass += 'warning warning';
            break;
    }
    jQuery(identifierId).addClass(finalClass);
    jQuery(identifierId).text(msg).show();
    setTimeout(function () {
        jQuery(identifierId).hide();
        jQuery(identifierId).removeClass(finalClass);
    }, 5000);
}


function gtLtModalClose(formId, ele, $) {
    $(formId).validation('clearError');
    $(formId).trigger("reset");
    $($(formId + " .bootstrap-tagsinput").find("span[data-role=remove]")).trigger("click");
    $(formId + ' ' + ele + 'ld-fee').removeClass('required');
    $(ele + 'edit-form-id').val('');
    $('.city-select').hide();
    $('.city-input').show();
}

function gtLtlSetInspAndLdData(data, eleid) {
    const inStore = JSON.parse(data.in_store);
    const localdel = JSON.parse(data.local_delivery);
    //Filling form data
    if (inStore != null && inStore != 'null') {
        inStore.enable_store_pickup == 1 ? jQuery(eleid + 'enable-instore-pickup').prop('checked', true) : '';
        jQuery(eleid + 'within-miles').val(inStore.miles_store_pickup);
        jQuery(eleid + 'postcode-match').tagsinput('add', inStore.match_postal_store_pickup);
        jQuery(eleid + 'checkout-descp').val(inStore.checkout_desc_store_pickup);
        if (inStore.suppress_other == 1) {
            jQuery(eleid + 'ld-sup-rates').prop('checked', true);
        }
    }
    if (localdel != null && localdel != 'null') {
        if (localdel.enable_local_delivery == 1) {
            jQuery(eleid + 'enable-local-delivery').prop('checked', true);
            jQuery(eleid + 'ld-fee').addClass('required');
        }
        jQuery(eleid + 'ld-within-miles').val(localdel.miles_local_delivery);
        jQuery(eleid + 'ld-postcode-match').tagsinput('add', localdel.match_postal_local_delivery);
        jQuery(eleid + 'ld-checkout-descp').val(localdel.checkout_desc_local_delivery);
        jQuery(eleid + 'ld-fee').val(localdel.fee_local_delivery);
        if (localdel.suppress_other == 1) {
            jQuery(eleid + 'ld-sup-rates').prop('checked', true);
        }
    }
}

function gtLtCurrentPlanNote($, planMsg, carrierDiv) {
    let divAfter = '<div class="message message-notice notice gtLt-plan-note"><div data-ui-id="messages-message-notice">' + planMsg + '</div></div>';
    gtLtNotesToggleHandling($, divAfter, '.gtLt-plan-note', carrierDiv);
}

function gtLtNotesToggleHandling($, divAfter, className, carrierDiv) {
    setTimeout(function () {
        if ($(carrierDiv).attr('class') === 'open') {
            $(carrierDiv).after(divAfter);
        }
    }, 1000);
    $(carrierDiv).click(function () {
        if ($(carrierDiv).attr('class') === 'open') {
            $(carrierDiv).after(divAfter);
        } else if ($(className).length) {
            $(className).remove();
        }
    });
}

/**
 * Restrict Quote Settings Fields
 * @param {string} qRestriction
 */
function gtLtPlanQuoteRestriction(qRestriction) {
    var quoteSecRowID = "#row_gtQuoteSetting_fourth_";
    var quoteSecID = "#gtQuoteSetting_fourth_";
    var parsedData = JSON.parse(qRestriction);

    if (parsedData['standard']) {
        jQuery('' + quoteSecRowID + 'enableCuttOff').before(
            '<tr>' +
            '<td>' +
            '<label>' +
            '<span data-config-scope="">' +
            '</span>' +
            '</label>' +
            '</td>' +
            '<td class="value">' +
            '<a href="https://eniture.com/magento-2-globaltranz-ltl-freight-quotes/" target="_blank" class="required-plan-msg">Standard Plan required</a>' +
            '</td><td class="">' +
            '</td>' +
            '</tr>');
        gtLtDisabledFieldsLoop(parsedData['standard'], quoteSecID);
    }

}

function gtLtDisabledFieldsLoop(dataArr, quoteSecID) {
    jQuery.each(dataArr, function (index, value) {
        jQuery(quoteSecID + value).attr('disabled', 'disabled');
    });
}

/**
 * Get address against zipCode from smart street api
 * @param {string} ajaxUrl
 * @param $this
 * @param callFunction
 * @returns {Boolean}
 */
function gtLtGetAddressFromZip(ajaxUrl, $this, callFunction) {
    const zipCode = $this.value;
    if (zipCode === '') {
        return false;
    }
    const parameters = {'origin_zip': zipCode};

    gtLtAjaxRequest(parameters, ajaxUrl, callFunction);
}

/*
* Hide message
 */
function gtLtScrollHideMsg(scrollType, scrollEle, scrollTo, hideEle) {

    if (scrollType == 1) {
        jQuery(scrollEle).animate({scrollTop: jQuery(scrollTo).offset().top - 170});
    } else if (scrollType == 2) {
        jQuery(scrollTo)[0].scrollIntoView({behavior: "smooth"});
    }
    setTimeout(function () {
        jQuery(hideEle).hide('slow');
    }, 5000);
}

function gtLtValidateDecimal($, value, limit) {
    let pattern;
    switch (limit) {
        case 4:
            pattern = /^-?\d*(\.\d{0,4})?$/;
            break;
        case 3:
            pattern = /^-?\d*(\.\d{0,3})?$/;
            break;
        default:
            pattern = /^-?\d*(\.\d{0,2})?$/;
            break;
    }
    const regex = new RegExp(pattern, 'g');
    return regex.test(value);
}
