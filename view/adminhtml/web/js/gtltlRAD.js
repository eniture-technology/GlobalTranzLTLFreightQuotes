/**
 * Document load function
 * @type type
 */

require([
    'jquery',
    'domReady!'
], function ($) {
    if ($("#suspend-rad-use").length > 0 && $("#suspend-rad-use").is(":disabled") == false) {
        if (($('#suspend-rad-use:checkbox:checked').length) > 0) {
            $("#gtQuoteSetting_fourth_residentialDlvry").prop({disabled: false});
            $("#gtQuoteSetting_fourth_RADforLiftgate").prop({disabled: true});
        } else {
            $("#gtQuoteSetting_fourth_residentialDlvry").prop({disabled: true});
            $("#gtQuoteSetting_fourth_RADforLiftgate").prop({disabled: false});
        }
    }
    jQuery("#suspend-rad-use").on('click', function () {
        if (this.checked) {
            jQuery("#gtQuoteSetting_fourth_residentialDlvry").prop({disabled: false});
            jQuery("#gtQuoteSetting_fourth_RADforLiftgate").prop({disabled: true});
        } else {
            jQuery("#gtQuoteSetting_fourth_residentialDlvry").prop({disabled: true});
            jQuery("#gtQuoteSetting_fourth_RADforLiftgate").prop({disabled: false});
        }
    });
});
