
'use strict';
function queryParamsExpenseTypes(p) {
    return {
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}

function queryParams(p) {
    return {
        "user_ids": $('#user_filter').val(),
        "type_ids": $('#type_filter').val(),
        "date_from": $('#expense_date_from').val(),
        "date_to": $('#expense_date_to').val(),
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

$('#expense_from_date_between').on('apply.daterangepicker', function (ev, picker) {
    var fromDate = picker.startDate.format('YYYY-MM-DD');
    var toDate = picker.endDate.format('YYYY-MM-DD');

    $('#expense_date_from').val(fromDate);
    $('#expense_date_to').val(toDate);

    $('#table').bootstrapTable('refresh');
});

$('#expense_from_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#expense_date_from').val('');
    $('#expense_date_to').val('');
    $('#expense_from_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#table').bootstrapTable('refresh');
});

addDebouncedEventListener('#user_filter, #type_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-expenses-filters', function (e) {
    e.preventDefault();
    $('#expense_from_date_between').val('');
    $('#expense_date_from').val('');
    $('#expense_date_to').val('');
    $('#user_filter').val('').trigger('change', [0]);
    $('#type_filter').val('').trigger('change', [0]);
    $('#table').bootstrapTable('refresh');
})
