
'use strict';
function queryParams(p) {
    return {
        "unit_ids": $('#unit_filter').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}

$('#unit_filter').on('change', function (e) {
    e.preventDefault();
    $('#table').bootstrapTable('refresh');
});


window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

$(document).on('click', '.edit-item', function () {
    var id = $(this).data('id');
    $('#edit_item_modal').modal('show');
    $.ajax({
        url: baseUrl + '/items/get/' + id,
        type: 'get',
        headers: {
            'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
        },
        dataType: 'json',
        success: function (response) {
            $('#item_id').val(response.item.id);
            $('#item_title').val(response.item.title);
            $('#item_price').val(response.item.price);
            $('#item_unit_id').val(response.item.unit_id).trigger('change');
            $('#item_description').val(response.item.description);
        },

    });
});