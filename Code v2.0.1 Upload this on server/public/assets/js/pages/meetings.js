
'use strict';
function queryParams(p) {
    return {
        "statuses": $('#status_filter').val(),
        "user_ids": $('#meeting_user_filter').val(),
        "client_ids": $('#meeting_client_filter').val(),
        "date_between_from": $('#meeting_date_between_from').val(),
        "date_between_to": $('#meeting_date_between_to').val(),
        "start_date_from": $('#meeting_start_date_from').val(),
        "start_date_to": $('#meeting_start_date_to').val(),
        "end_date_from": $('#meeting_end_date_from').val(),
        "end_date_to": $('#meeting_end_date_to').val(),
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

addDebouncedEventListener('#status_filter, #meeting_user_filter, #meeting_client_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#meetings_table').bootstrapTable('refresh');
    }
});

$(document).ready(function () {
    $('#meeting_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');
        $('#meeting_date_between_from').val(startDate);
        $('#meeting_date_between_to').val(endDate);
        $('#meetings_table').bootstrapTable('refresh');
    });

    // Cancel event to clear values
    $('#meeting_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#meeting_date_between_from').val('');
        $('#meeting_date_between_to').val('');
        $(this).val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#meetings_table').bootstrapTable('refresh');
    });
});

$(document).on('click', '.clear-meetings-filters', function (e) {
    e.preventDefault();
    $('#meeting_date_between').val('');
    $('#meeting_date_between_from').val('');
    $('#meeting_date_between_to').val('');
    $('#meeting_start_date_between').val('');
    $('#meeting_end_date_between').val('');
    $('#meeting_start_date_from').val('');
    $('#meeting_start_date_to').val('');
    $('#meeting_end_date_from').val('');
    $('#meeting_end_date_to').val('');
    $('#status_filter').val('').trigger('change', [0]);
    $('#meeting_user_filter').val('').trigger('change', [0]);
    $('#meeting_client_filter').val('').trigger('change', [0]);
    $('#meetings_table').bootstrapTable('refresh');
})


