/**
 *
 * @param {string} ajaxUrl
 * @return {undefined}
 */
function refreshCerasisCarriers(ajaxUrl)
{
    const parameters = {
        'action': 'getcarriers'
    };

    gtLtAjaxRequest(parameters, ajaxUrl, cerasisCarriersResultData);
}

/**
 *
 * @param {type} data
 * @return {undefined}
 */
function cerasisCarriersResultData(data)
{
    if (data.SUCCESS) {
        location.reload();
    }
}

/**
 *
 * @return {undefined}
 */
function selectAllCarriers()
{
    const selectAllServices = jQuery('.selectAllServices');
    if (selectAllServices.length === selectAllServices.filter(":checked").length) {
        jQuery('.cerasisQuoteServices').prop('checked', true);
    } else {
        jQuery('.cerasisQuoteServices').attr('checked', false);
    }
}

/**
 *
 * @return {undefined}
 */
function unselectAllCarrierSelectCheckbox()
{
    const intCheckboxes = jQuery('.cerasisQuoteServices:checked').size();
    const intUnChecked = jQuery('.cerasisQuoteServices').size();
    if (intCheckboxes === intUnChecked) {
        jQuery('.selectAllServices').attr('checked', true);
    } else {
        jQuery('.selectAllServices').attr('checked', false);
    }
}

function autoEnableNewCarriers(ajaxUrl)
{
    const parameters = {
        'action': 'saveAutoEnable',
        'autoEnable': (jQuery('.auto_enable').prop('checked')) ? 'yes' : 'no'
    };
    gtLtAjaxRequest(parameters, ajaxUrl, autoenableCarriersResult);
}

/**
 *
 * @param {type} data
 * @return {undefined}
 */
function autoenableCarriersResult(data)
{
//           if(data.SUCCESS){
//               location.reload();
//           }
}
