
'use strict';
$(document).ready(function() {
    function toggleFields() {
        if ($('#type_client').is(':checked')) {
            $('#companyDiv').removeClass('d-none');
            // $('#roleDiv').addClass('d-none');
        } else if ($('#type_member').is(':checked')) {
            $('#companyDiv').addClass('d-none');
            // $('#roleDiv').removeClass('d-none');
        }
    }

    $('#type_client, #type_member').change(toggleFields);

    // Initial call to set the correct state on page load
    toggleFields();
});