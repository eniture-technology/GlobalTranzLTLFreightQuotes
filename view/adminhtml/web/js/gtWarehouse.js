const gtLtWhFormId = "#gtLt-wh-form";
let gtLtWhEditFormData = '';
require(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'domReady!'
    ],
    function ($, modal) {

        let addWhModal = $('#gtLt-wh-modal');
        let formId = gtLtWhFormId;
        let options = {
            type: 'popup',
            modalClass: 'gtLt-add-wh-modal',
            responsive: true,
            innerScroll: true,
            title: 'Warehouse',
            closeText: 'Close',
            focus: formId + ' #gtLt-wh-zip',
            buttons: [{
                text: $.mage.__('Save'),
                class: 'en-btn save-wh-ds',
                click: function (data) {
                    var $this = this;
                    var formData = gtLtGetFormData($, formId);
                    var ajaxUrl = gtLtAjaxUrl + 'SaveWarehouse/';

                    if ($(formId).valid() && gtLtZipMilesValid()) {
                        //If form data is unchanged then close the modal and show updated message
                        if (gtLtWhEditFormData !== '' && gtLtWhEditFormData === formData) {
                            gtLtResponseMessage('gtLt-wh-msg', 'success', 'Warehouse updated successfully.');
                            addWhModal.modal('closeModal');
                        } else {
                            $.ajax({
                                url: ajaxUrl,
                                type: 'POST',
                                data: formData,
                                showLoader: true,
                                success: function (data) {
                                    if (gtLtWarehouseSaveResSettings(data)) {
                                        addWhModal.modal('closeModal');
                                    }
                                },
                                error: function (result) {
                                    console.log('no response !');
                                }
                            });
                        }
                    }
                }
            }],
            keyEventHandlers: {
                tabKey: function () {
                },
                /**
                 * Escape key press handler,
                 * close modal window
                 */
                escapeKey: function () {
                    if (this.options.isOpen && this.modal.find(document.activeElement).length ||
                        this.options.isOpen && this.modal[0] === document.activeElement) {
                        this.closeModal();
                    }
                }
            },
            closed: function () {
                gtLtModalClose(formId, '#', $);
            }
        };

        //Add WH
        $('#gtLt-add-wh-btn').on('click', function () {
            const popup = modal(options, addWhModal);
            addWhModal.modal('openModal');
        });

        //Edit WH
        $('body').on('click', '.gtLt-edit-wh', function () {
            const whId = $(this).data("id");
            if (typeof whId !== 'undefined') {
                gtLtEditWarehouse(whId, gtLtAjaxUrl);
                setTimeout(function () {
                    var popup = modal(options, addWhModal);
                    addWhModal.modal('openModal');
                }, 500);
            }
        });

        //Delete WH
        $('body').on('click', '.gtLt-del-wh', function () {
            const whId = $(this).data("id");
            if (typeof whId !== 'undefined') {
                cerasisDeleteWarehouse(whId, gtLtAjaxUrl);
            }
        });

        //Add required to Local Delivery Fee if Local Delivery is enabled
        $(formId + ' #enable-local-delivery').on('change', function () {
            if ($(this).is(':checked')) {
                $(formId + ' #ld-fee').addClass('required');
            } else {
                $(formId + ' #ld-fee').removeClass('required');
            }
        });

        //Get data of Zip Code
        $(formId + ' #gtLt-wh-zip').on('change', function () {
            const ajaxUrl = gtLtAjaxUrl + 'GlobalTranzOriginAddress/';
            $(formId + ' #wh-origin-city').val('');
            $(formId + ' #wh-origin-state').val('');
            $(formId + ' #wh-origin-country').val('');
            gtLtGetAddressFromZip(ajaxUrl, this, gtLtGetAddressResSettings);
            $(formId).validation('clearError');
        });
    }
);


function gtLtGetAddressResSettings(data)
{
    let id = gtLtWhFormId;
    if (data.country === 'US' || data.country === 'CA') {
        if (data.postcode_localities === 1) {
            jQuery(id + ' .city-select').show();
            jQuery(id + ' #actname').replaceWith(data.city_option);
            jQuery(id + ' .city-multiselect').replaceWith(data.city_option);
            jQuery(id).on('change', '.city-multiselect', function () {
                var city = jQuery(this).val();
                jQuery(id + ' #wh-origin-city').val(city);
            });
            jQuery(id + " #wh-origin-city").val(data.first_city);
            jQuery(id + " #wh-origin-state").val(data.state);
            jQuery(id + " #wh-origin-country").val(data.country);
            jQuery(id + ' .city-input').hide();
        } else {
            jQuery(id + ' .city-input').show();
            jQuery(id + ' #wh-multi-city').removeAttr('value');
            jQuery(id + ' .city-select').hide();
            jQuery(id + " #wh-origin-city").val(data.city);
            jQuery(id + " #wh-origin-state").val(data.state);
            jQuery(id + " #wh-origin-country").val(data.country);
        }
    } else if (data.msg) {
        gtLtResponseMessage('gtLt-wh-modal-msg', 'error', data.msg);
    }
    return true;
}


function gtLtZipMilesValid()
{
    let id = gtLtWhFormId;
    const enable_instore_pickup = jQuery(id + " #enable-instore-pickup").is(':checked');
    const enable_local_delivery = jQuery(id + " #enable-local-delivery").is(':checked');
    if (enable_instore_pickup || enable_local_delivery) {
        var instore_within_miles = jQuery(id + " #within-miles").val();
        var instore_postal_code = jQuery(id + " #postcode-match").val();
        var ld_within_miles = jQuery(id + " #ld-within-miles").val();
        var ld_postal_code = jQuery(id + " #ld-postcode-match").val();

        switch (true) {
            case (enable_instore_pickup && (instore_within_miles.length == 0 && instore_postal_code.length == 0)):
                jQuery(id + ' .wh-instore-miles-postal-err').show('slow');
                gtLtScrollHideMsg(2, '', id + ' #wh-is-heading-left', '.wh-instore-miles-postal-err');
                return false;

            case (enable_local_delivery && (ld_within_miles.length == 0 && ld_postal_code.length == 0)):
                jQuery(id + ' .wh-local-miles-postals-err').show('slow');
                gtLtScrollHideMsg(2, '', id + ' #wh-ld-heading-left', '.wh-local-miles-postals-err');
                return false;
        }
    }
    return true;
}

function gtLtWarehouseSaveResSettings(data)
{
    console.log(data);
    if (data.insert_qry == 1) {
        jQuery('#append-warehouse tr:last').after(
            '<tr id="row_' + data.id + '" data-id="' + data.id + '">' + gtLtGetRowData(data, 'wh') + '</tr>'
        );
    } else if (data.update_qry == 1) {
        jQuery('tr[id=row_' + data.id + ']').html(gtLtGetRowData(data, 'wh'));
    } else {
        //to be changed
        gtLtResponseMessage('gtLt-wh-modal-msg', 'error', data.msg);
        return false;
    }
    gtLtAddWarehouseRestriction(data.canAddWh);
    gtLtResponseMessage('gtLt-wh-msg', 'success', data.msg);
    return true;
}

/**
 * Edit warehouse
 * @param {type} dataId
 * @param {type} ajaxUrl
 * @returns {Boolean}
 */
function gtLtEditWarehouse(dataId, ajaxUrl)
{
    ajaxUrl = ajaxUrl + 'EditWarehouse/';
    let parameters = {
        'action': 'edit_warehouse',
        'edit_id': dataId
    };
    gtLtAjaxRequest(parameters, ajaxUrl, gtLtWarehouseEditResSettings);
}

function gtLtWarehouseEditResSettings(data)
{
    if (data.error == 1) {
        gtLtResponseMessage('gtLt-wh-msg', 'error', data.msg);
        jQuery('#gtLt-wh-modal').modal('closeModal');
        return false
    }
    let id = gtLtWhFormId;
    if (data[0]) {
        jQuery(id + ' #edit-form-id').val(data[0].warehouse_id);
        jQuery(id + ' #gtLt-wh-zip').val(data[0].zip);
        jQuery(id + ' .city-select').hide();
        jQuery(id + ' .city-input').show();
        jQuery(id + ' #wh-origin-city').val(data[0].city);
        jQuery(id + ' #wh-origin-state').val(data[0].state);
        jQuery(id + ' #wh-origin-country').val(data[0].country);

        if (gtLtAdvancePlan) {
            // Load instorepikup and local delivery data
            if ((data[0].in_store != null && data[0].in_store != 'null')
                || (data[0].local_delivery != null && data[0].local_delivery != 'null')) {
                gtLtlSetInspAndLdData(data[0], '#');
            }
        }
        gtLtWhEditFormData = gtLtGetFormData(jQuery, gtLtWhFormId);
    }
    return true;
}

/**
 * Delete selected Warehouse
 * @param {int} dataId
 * @param {string} ajaxUrl
 * @returns {boolean}
 */
function cerasisDeleteWarehouse(dataId, ajaxUrl)
{
    ajaxUrl = ajaxUrl + 'DeleteWarehouse/';
    let parameters = {
        'action': 'delete_warehouse',
        'delete_id': dataId
    };
    gtLtAjaxRequest(parameters, ajaxUrl, cerasisLtWarehouseDeleteResSettings);
    return false;
}

function cerasisLtWarehouseDeleteResSettings(data)
{

    if (data.qryResp == 1) {
        jQuery('#row_' + data.deleteID).remove();
        gtLtAddWarehouseRestriction(data.canAddWh);
    }
    gtLtResponseMessage('gtLt-wh-msg', 'success', data.msg);
    //gtLtScrollHideMsg(1, 'html,body', '.wh-text', '.gtLt-wh-msg');
    return true;
}
