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

