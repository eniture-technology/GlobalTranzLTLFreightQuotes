/**
 *
 * @return {undefined}
 */
function cerasisBOLAddFieldHtml(senderInfo)
{
    if (senderInfo === undefined || senderInfo === null) {
        senderName = "";
        senderAddress = "";
    } else {
        senderName = (senderInfo.senderName === undefined || senderInfo.senderName === null) ? '' : senderInfo.senderName;
        senderAddress = (senderInfo.senderAddress === undefined || senderInfo.senderAddress === null) ? '' : senderInfo.senderAddress;
    }

    var inputFieldHtml = '<div id="popupAddField" class="cerasis-bol-overlay">\
            <div class="cerasis-bol-field-popup" id="bol-sender-input">\
                <h2 class="del-hdng">\
                    GlobalTranz BOL Sender Details\
                </h2>\
                <div class="bol-input">\
                    <span>Sender Name</span>\
                    <input type="text" maxlength="50" title="Sender Name" name="cerasis_bol_senderName" placeholder="Sender Name" id="cerasis_bol_senderName" value="' + senderName + '">\
                </div>\
                <div class="bol-input">\
                    <span>Sender Address</span>\
                    <input type="text" maxlength="50" title="Sender Address" name="cerasis_bol_senderAddress" placeholder="Sender Address" id="cerasis_bol_senderAddress" value="' + senderAddress + '">\
                </div>\
                <div class="bol-btns">\
                    <a href="#" class="cancel-bol" onclick="return closeBOLPopup();">Cancel</a>\
                    <a href="#" class="confirm-bol" >OK</a>\
                </div>\
            </div>\
        </div>';

    jQuery('body').append(inputFieldHtml);
}

/**
 *
 * @param {type} orderId
 * @param {type} getBolAjaxURL
 * @param {type} printBolAjaxURL
 * @return {undefined}
 */
function cerasisBOLNameField(orderId, getBolAjaxURL, printBolAjaxURL, senderInfo)
{
    cerasisBOLAddFieldHtml(senderInfo);
    jQuery(".cerasis-bol-overlay, .cerasis-bol-field-popup").addClass("active");
    jQuery(".confirm-bol").on("click", function () {

        var senderName = jQuery('#cerasis_bol_senderName').val();
        var senderAddress = jQuery('#cerasis_bol_senderAddress').val();
        var validationErr = cerasisBolFieldValidation('#bol-sender-input', senderName, senderAddress);

        if (!validationErr) {
            closeBOLPopup();
            printBOLAjaxRequest({
                orderId: orderId,
                senderName: senderName,
                senderAddress: senderAddress,
                printBolAjaxUrl: printBolAjaxURL
            }, getBolAjaxURL, cerasisBOLResSeting);
        }
        return false;
    });

}


/**
 * Varify connection credentials
 * @returns {Boolean}
 */
function cerasisBolFieldValidation(formId, senderName, senderAddress)
{
    jQuery('.err').remove();
    nameID = '#cerasis_bol_senderName';
    addressID = '#cerasis_bol_senderAddress';

    if (senderName === "" && senderAddress === "") {
        jQuery(nameID).after('<span class="err">Sender Name is required.</span>');
        jQuery(addressID).after('<span class="err">Sender Address is required.</span>');
        return true;
    }

    if (senderName === "") {
        jQuery(nameID).after('<span class="err">Sender Name is required.</span>');
        return true;
    }

    if (senderAddress === "") {
        jQuery(addressID).after('<span class="err">Sender Address is required.</span>');
        return true;
    }
}


/**
 *
 * @return {undefined}
 */
function closeBOLPopup()
{
    jQuery('#popupAddField').remove();
    jQuery(".cerasis-bol-overlay, .cerasis-bol-field-popup").removeClass("active");
}

/**
 * Request For Bill Of Ladding
 * @param orderId
 * @param ajaxURL
 * @returns {BOL}
 */
function cerasisBillOfLadding(orderId, getBolAjaxURL, printBolAjaxURL)
{
    printBOLAjaxRequest({orderId: orderId, printBolAjaxUrl: printBolAjaxURL}, getBolAjaxURL, cerasisBOLResSeting);
}

function cerasisBOLResSeting(data)
{
    if (data.ERROR) {
        jQuery('#sales_order_view').prepend('<div class=bol-error>' + data.ERROR + '</div>');
        setTimeout(function () {
            jQuery('.bol-error').hide('slow');
        }, 5000);
        return false;
    }
    var bolBase64 = data.bolJson;
    var fileName = "cerasis-bol.pdf";
    if (window.navigator && window.navigator.msSaveOrOpenBlob) { // IE workaround
        var byteCharacters = atob(bolBase64);
        var byteNumbers = new Array(byteCharacters.length);
        for (var i = 0; i < byteCharacters.length; i++) {
            byteNumbers[i] = byteCharacters.charCodeAt(i);
        }
        var byteArray = new Uint8Array(byteNumbers);
        var blob = new Blob([byteArray], {type: 'application/pdf'});
        window.navigator.msSaveOrOpenBlob(blob, fileName);
    } else { // much easier if not IE
        jQuery("#sales_order_view_tabs_order_info_content").prepend("<div id='cerasis_bol_overlay'><a class='cancel-print' onclick='return cerasisCancelBOL();'>&#10006;</a><div class='cerasis-bol-popup'><iframe id='imgBol' class='imgBol'></iframe></div>");
        imgBol.setAttribute('src', "data:application/pdf;base64," + bolBase64);
//            imgBol.setHeader('Content-Disposition','inline;filename="TYPE_HERE_REQUIRED_TITLE.pdf"');
    }

}

function cerasisCancelBOL()
{
    jQuery('#cerasis_bol_overlay').remove();
}

/**
 * call for warehouse ajax requests
 * @param {type} parameters
 * @param {type} ajaxUrl
 * @param {type} responseFunction
 * @returns {ajax response}
 */
function printBOLAjaxRequest(parameters, ajaxUrl, responseFunction)
{

    new Ajax.Request(ajaxUrl, {
        method: 'POST',
        parameters: parameters,
        onSuccess: function (response) {
            var json = response.responseText;
            var data = JSON.parse(json);
            var callbackRes = responseFunction(data);
            return callbackRes;

        }
    });
}

function checkTrackingStatus(bolNumber, bolTrackingUrl, ajaxURL)
{
    if (bolTrackingUrl.length > 0) {
        window.open(bolTrackingUrl, '_blank');
    } else {
        printBOLAjaxRequest({action: 'getTrackingStatus', bolNumber: bolNumber}, ajaxURL, cerasisTrackResSeting);
    }
}

function cerasisTrackResSeting(data)
{
    if (data.SUCCESS == 'SUCCESS') {
        window.open(data.trackingUrl, '_blank');
    }
}

