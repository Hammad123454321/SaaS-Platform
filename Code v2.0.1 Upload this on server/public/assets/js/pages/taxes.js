
'use strict';
function queryParams(p) {
    return {
        "types": $('#types_filter').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}


window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

$(document).on('click', '.edit-tax', function () {
    var id = $(this).data('id');
    $('#edit_tax_modal').modal('show');
    $.ajax({
        url: baseUrl + '/taxes/get/' + id,
        type: 'get',
        headers: {
            'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
        },
        dataType: 'json',
        success: function (response) {
            $('#tax_id').val(response.tax.id);
            $('#tax_title').val(response.tax.title);
            $('#update_tax_type').val(response.tax.type);
            if (response.tax.type == 'amount') {
                $('#update_amount_div').removeClass('d-none');
                $('#update_percentage_div').addClass('d-none');
                $('#tax_amount').val(response.tax.amount);
            } else {
                $('#update_amount_div').addClass('d-none');
                $('#update_percentage_div').removeClass('d-none');
                $('#tax_percentage').val(response.tax.percentage);
            }
        },

    });
});

$('#types_filter').on('change', function (e) {
    e.preventDefault();
    $('#table').bootstrapTable('refresh');
});