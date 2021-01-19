const gtLtDsFormId = "#gtLt-ds-form";
let gtLtDsEditFormData = '';

require(
    [
        'jquery',
        'Magento_Ui/js/modal/modal',
        'Magento_Ui/js/modal/confirm',
        'domReady!',
    ],
    function ($, modal, confirmation) {

        const addDsModal = $('#gtLt-ds-modal');
        const options = {
            type: 'popup',
            modalClass: 'gtLt-add-ds-modal',
            responsive: true,
            innerScroll: true,
            title: 'Drop Ship',
            closeText: 'Close',
            focus: gtLtDsFormId + ' #gtLt-ds-nickname',
            buttons: [{
                text: $.mage.__('Save'),
                class: 'en-btn save-ds-ds',
                click: function (data) {
                    var $this = this;
                    var form_data = gtLtGetFormData($, gtLtDsFormId);
                    var ajaxUrl = gtLtDsAjaxUrl + 'SaveDropship/';

                    if ($(gtLtDsFormId).valid() && gtLtDsZipMilesValid()) {
                        //If form data is unchanged then close the modal and show updated message
                        if (gtLtDsEditFormData !== '' && gtLtDsEditFormData === form_data) {
                            gtLtResponseMessage('gtLt-ds-msg', 'success', 'Drop ship updated successfully.');
                            addDsModal.modal('closeModal');
                        } else {
                            $.ajax({
                                url: ajaxUrl,
                                type: 'POST',
                                data: form_data,
                                showLoader: true,
                                success: function (data) {
                                    if (gtLtDropshipSaveResSettings(data)) {
                                        addDsModal.modal('closeModal');
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
                    return;
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
                gtLtModalClose(gtLtDsFormId, '#ds-', $);
            }
        };


        $('body').on('click', '.gtLt-del-ds', function (event) {
            event.preventDefault();
            confirmation({
                title: 'GlobalTranz LTL Freight Quotes',
                content: 'Warning! If you delete this location, Drop ship location settings will be disabled against products.',
                actions: {
                    always: function () {
                    },
                    confirm: function () {
                        var dataset = event.currentTarget.dataset;
                        gtLtDeleteDropship(dataset.id, gtLtDsAjaxUrl);
                    },
                    cancel: function () {
                    }
                }
            });
            return false;
        });


        //Add DS
        $('#gtLt-add-ds-btn').on('click', function () {
            const popup = modal(options, addDsModal);
            addDsModal.modal('openModal');
        });

        //Edit WH
        $('body').on('click', '.gtLt-edit-ds', function () {
            var dsId = $(this).data("id");
            if (typeof dsId !== 'undefined') {
                gtLtEditDropship(dsId, gtLtDsAjaxUrl);
                setTimeout(function () {
                    const popup = modal(options, addDsModal);
                    addDsModal.modal('openModal');
                }, 500);
            }
        });

        //Add required to Local Delivery Fee if Local Delivery is enabled
        $(gtLtDsFormId + ' #ds-enable-local-delivery').on('change', function () {
            if ($(this).is(':checked')) {
                $(gtLtDsFormId + ' #ds-ld-fee').addClass('required');
            } else {
                $(gtLtDsFormId + ' #ds-ld-fee').removeClass('required');
            }
        });

        //Get data of Zip Code
        $(gtLtDsFormId + ' #gtLt-ds-zip').on('change', function () {
            var ajaxUrl = gtLtAjaxUrl + 'GlobalTranzOriginAddress/';
            $(gtLtDsFormId + ' #ds-city').val('');
            $(gtLtDsFormId + ' #ds-state').val('');
            $(gtLtDsFormId + ' #ds-country').val('');
            gtLtGetAddressFromZip(ajaxUrl, this, gtLtGetDsAddressResSettings);
            $(gtLtDsFormId).validation('clearError');
        });
    }
);

/**
 * Set Address from zipCode
 * @param {type} data
 * @returns {Boolean}
 */
function gtLtGetDsAddressResSettings(data)
{
    let id = gtLtDsFormId;
    if (data.country === 'US' || data.country === 'CA') {
        var oldNick = jQuery('#gtLt-ds-nickname').val();
        var newNick = '';
        var zip = jQuery('#gtLt-ds-zip').val();
        if (data.postcode_localities === 1) {
            jQuery(id + ' .city-select').show();
            jQuery(id + ' #ds-actname').replaceWith(data.city_option);
            jQuery(id + ' .city-multiselect').replaceWith(data.city_option);
            jQuery(id).on('change', '.city-multiselect', function () {
                var city = jQuery(this).val();
                jQuery(id + ' #ds-city').val(city);
                jQuery(id + ' #gtLt-ds-nickname').val(gtLtSetDsNickname(oldNick, zip, city));
            });
            jQuery(id + " #ds-city").val(data.first_city);
            jQuery(id + ' #ds-state').val(data.state);
            jQuery(id + ' #ds-country').val(data.country);
            jQuery(id + ' .city-input').hide();
            newNick = gtLtSetDsNickname(oldNick, zip, data.first_city);
        } else {
            jQuery(id + ' .city-input').show();
            jQuery(id + ' #wh-multi-city').removeAttr('value');
            jQuery(id + ' .city-select').hide();
            jQuery(id + ' #ds-city').val(data.city);
            jQuery(id + ' #ds-state').val(data.state);
            jQuery(id + ' #ds-country').val(data.country);
            newNick = gtLtSetDsNickname(oldNick, zip, data.city);
        }
        jQuery(id + ' #gtLt-ds-nickname').val(newNick);
    } else if (data.msg) {
        gtLtResponseMessage('gtLt-ds-modal-msg', 'error', data.msg);
    }
    return true;
}


function gtLtDsZipMilesValid()
{
    let id = gtLtDsFormId;
    const enable_instore_pickup = jQuery(id + " #ds-enable-instore-pickup").is(':checked');
    const enable_local_delivery = jQuery(id + " #ds-enable-local-delivery").is(':checked');
    if (enable_instore_pickup || enable_local_delivery) {
        const instore_within_miles = jQuery(id + " #ds-within-miles").val();
        const instore_postal_code = jQuery(id + " #ds-postcode-match").val();
        const ld_within_miles = jQuery(id + " #ds-ld-within-miles").val();
        const ld_postal_code = jQuery(id + " #ds-ld-postcode-match").val();

        switch (true) {
            case (enable_instore_pickup && (instore_within_miles.length == 0 && instore_postal_code.length == 0)):
                jQuery(id + ' .ds-instore-miles-postal-err').show('slow');
                gtLtScrollHideMsg(2, '', id + ' #ds-is-heading-left', '.ds-instore-miles-postal-err');
                return false;

            case (enable_local_delivery && (ld_within_miles.length == 0 && ld_postal_code.length == 0)):
                jQuery(id + ' .ds-local-miles-postals-err').show('slow');
                gtLtScrollHideMsg(2, '', id + ' #ds-ld-heading-left', '.ds-local-miles-postals-err');
                return false;
        }
    }
    return true;
}


function gtLtDropshipSaveResSettings(data)
{
    let styleClass = '';
    if (data.insert_qry == 1) {
        jQuery('#append-dropship tr:last').after(
            '<tr id="row_' + data.id + '" data-id="' + data.id + '">' +
            '<td>' + data.nickname + '</td>' +
            gtLtGetRowData(data, 'ds') + '</tr>'
        );
    } else if (data.update_qry == 1) {
        jQuery('tr[id=row_' + data.id + ']').html('<td>' + data.nickname + '</td>' + gtLtGetRowData(data, 'ds'));
    } else {
        gtLtResponseMessage('gtLt-ds-modal-msg', 'error', data.msg);
        return false;
    }
    gtLtResponseMessage('gtLt-ds-msg', 'success', data.msg);
    return true;
}

function gtLtEditDropship(dataId, ajaxUrl)
{
    ajaxUrl = ajaxUrl + 'EditDropship/';
    const parameters = {
        'action': 'edit_dropship',
        'edit_id': dataId
    };

    gtLtAjaxRequest(parameters, ajaxUrl, gtLtDropshipEditResSettings);
    return false;
}

function gtLtDropshipEditResSettings(data)
{
    let id = gtLtDsFormId;
    if (data[0]) {
        jQuery(id + ' #ds-edit-form-id').val(data[0].warehouse_id);
        jQuery(id + ' #gtLt-ds-zip').val(data[0].zip);
        jQuery(id + ' #gtLt-ds-nickname').val(data[0].nickname);
        jQuery(id + ' .city-select').hide();
        jQuery(id + ' .city-input').show();
        jQuery(id + ' #ds-city').val(data[0].city);
        jQuery(id + ' #ds-state').val(data[0].state);
        jQuery(id + ' #ds-country').val(data[0].country);

        if (gtLtAdvancePlan) {
            // Load instore pickup and local delivery data
            if ((data[0].in_store != null && data[0].in_store != 'null')
                || (data[0].local_delivery != null && data[0].local_delivery != 'null')) {
                gtLtlSetInspAndLdData(data[0], '#ds-');
                //gtLtSetInspAndLdData(data[0], '#ds-');
            }
        }

        gtLtDsEditFormData = gtLtGetFormData(jQuery, gtLtDsFormId);
    }
    return true;
}

function gtLtDeleteDropship(deleteid, ajaxUrl)
{
    ajaxUrl = ajaxUrl + 'DeleteDropship/';
    let parameters = {
        'action': 'delete_dropship',
        'delete_id': deleteid
    };
    gtLtAjaxRequest(parameters, ajaxUrl, gtLtDropshipDeleteResSettings);
    return false;
}

function gtLtDropshipDeleteResSettings(data)
{
    if (data.qryResp == 1) {
        jQuery('#row_' + data.deleteID).remove();
    }
    gtLtResponseMessage('gtLt-ds-msg', 'success', data.msg);
    return true;
}

function gtLtSetDsNickname(oldNick, zip, city)
{
    let nickName = '';
    let curNick = 'DS_' + zip + '_' + city;
    let pattern = /DS_[0-9 a-z A-Z]+_[a-z A-Z]*/;
    let regex = new RegExp(pattern, 'g');
    if (oldNick !== '') {
        nickName = regex.test(oldNick) ? curNick : oldNick;
    }
    return nickName;
}
