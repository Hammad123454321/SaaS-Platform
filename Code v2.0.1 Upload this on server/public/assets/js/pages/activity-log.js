
'use strict';
function queryParams(p) {
    return {
        "user_ids": $('#user_filter').val(),
        "client_ids": $('#client_filter').val(),
        "activities": $('#activity_filter').val(),
        "types": $('#type_filter').val(),
        "date_from": $('#activity_log_between_date_from').val(),
        "date_to": $('#activity_log_between_date_to').val(),
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




$('#activity_log_between_date').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#activity_log_between_date_from').val(startDate);
    $('#activity_log_between_date_to').val(endDate);

    $('#table').bootstrapTable('refresh');
});

$('#activity_log_between_date').on('cancel.daterangepicker', function (ev, picker) {
    $('#activity_log_between_date_from').val('');
    $('#activity_log_between_date_to').val('');
    $('#activity_log_between_date').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#table').bootstrapTable('refresh');
});


addDebouncedEventListener('#user_filter, #client_filter, #activity_filter, #type_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#activity_log_table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-activity-log-filters', function (e) {
    e.preventDefault();
    $('#activity_log_between_date_from').val('');
    $('#activity_log_between_date_to').val('');
    $('#activity_log_between_date').val('');
    $('#user_filter').val('').trigger('change', [0]);
    $('#client_filter').val('').trigger('change', [0]);
    $('#activity_filter').val('').trigger('change', [0]);
    $('#type_filter').val('').trigger('change', [0]);
    $('#activity_log_table').bootstrapTable('refresh');
})





