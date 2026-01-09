
'use strict';

function queryParams(p) {
    return {
        "user_id": $('#user_filter').val(),
        "invoice_id": $('#invoice_filter').val(),
        "pm_id": $('#payment_method_filter').val(),
        "date_from": $('#payment_date_from').val(),
        "date_to": $('#payment_date_to').val(),
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

$('#payment_date_between').on('apply.daterangepicker', function (ev, picker) {
    var fromDate = picker.startDate.format('YYYY-MM-DD');
    var toDate = picker.endDate.format('YYYY-MM-DD');

    $('#payment_date_from').val(fromDate);
    $('#payment_date_to').val(toDate);

    $('#table').bootstrapTable('refresh');
});

$('#payment_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#payment_date_from').val('');
    $('#payment_date_to').val('');
    $('#payment_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#table').bootstrapTable('refresh');
});

addDebouncedEventListener('#user_filter, #invoice_filter, #payment_method_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-payments-filters', function (e) {
    e.preventDefault();
    $('#payment_date_from').val('');
    $('#payment_date_to').val('');
    $('#payment_date_between').val('');
    $('#user_filter').val('').trigger('change', [0]);
    $('#invoice_filter').val('').trigger('change', [0]);
    $('#payment_method_filter').val('').trigger('change', [0]);    
    $('#table').bootstrapTable('refresh');
})