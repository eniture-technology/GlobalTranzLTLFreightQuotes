require(['jquery', 'domReady!'], function ($) {
    /* Test Connection Validation */
    $('#gtLtlTestConnBtn').click(function () {
        if ($('#config-edit-form').valid()) {
            const ajaxURL = $(this).attr('gtConnAjaxUrl');
            gtTestConnectionAjaxCall($, ajaxURL);
        }
        return false;
    });
});

/**
 * Test connection ajax call
 * @param {type} ajaxURL
 * @returns {Success or Error}
 */
function gtTestConnectionAjaxCall($, ajaxURL) {
    let common = '#gtConnSettings_first_';
    let endPoint = $(common + 'endPoint').val();
    let credentials;

    if (endPoint === '1') {   //For Cerasis
        credentials = {
            carrierName : 'cerasis',
            shipperID: $(common + 'cerasisltlshipperID').val(),
            username: $(common + 'cerasisltlusername').val(),
            password: $(common + 'cerasisltlPassword').val(),
            accessKey: $(common + 'cerasisltlAccessKey').val(),
            pluginLicenceKey: $(common + 'licnsKey').val()
    };
    } else if (endPoint === '2') {    //For GlobalTranz
        credentials = {
            carrierName : 'globalTranz',
            username: $(common + 'gtLtlUsername').val(),
            password: $(common + 'gtLtlPassword').val(),
            accessKey: $(common + 'gtLtlAuthKey').val(),
            customerID: $(common + 'gtLtlCustomerId').val(),
            pluginLicenceKey: $(common + 'licnsKey').val()
        };
    }
    gtLtAjaxRequest(credentials, ajaxURL, gtConnectSuccessFunction);
}

/**
 *
 * @param {type} data
 * @returns {undefined}
 */
function gtConnectSuccessFunction(data) {
    if (data.Success) {
        gtLtResponseMessage('gt-response-box', 'success', data.Success);
    } else if (data.Error) {
        gtLtResponseMessage('gt-response-box', 'error', data.Error);
    } else {
        let errorText = 'The credentials entered did not result in a successful test. Confirm your credentials and try again.';
        gtLtResponseMessage('gt-response-box', 'error', errorText);
    }
}
