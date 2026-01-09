
'use strict';
function queryParamsLr(p) {
    return {
        "statuses": $('#lr_status_filter').val(),
        "user_ids": $('#lr_user_filter').val(),
        "action_by_ids": $('#lr_action_by_filter').val(),
        "date_between_from": $('#lr_date_between_from').val(),
        "date_between_to": $('#lr_date_between_to').val(),
        "start_date_from": $('#lr_start_date_from').val(),
        "start_date_to": $('#lr_start_date_to').val(),
        "end_date_from": $('#lr_end_date_from').val(),
        "end_date_to": $('#lr_end_date_to').val(),
        "types": $('#lr_type_filter').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}
function debounce(func, delay) {
    let timer;
    return function (...args) {
        clearTimeout(timer);
        timer = setTimeout(() => func.apply(this, args), delay);
    };
}
// Attach change event with debounce
addDebouncedEventListener('#lr_status_filter, #lr_user_filter, #lr_action_by_filter, #lr_type_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#lr_table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-leave-requests-filters', function (e) {
    e.preventDefault();
    $('#lr_date_between').val('');
    $('#lr_date_between_from').val('');
    $('#lr_date_between_to').val('');
    $('#lr_start_date_between').val('');
    $('#lr_end_date_between').val('');
    $('#lr_start_date_from').val('');
    $('#lr_start_date_to').val('');
    $('#lr_end_date_from').val('');
    $('#lr_end_date_to').val('');
    $('#lr_status_filter').val('').trigger('change', [0]);
    $('#lr_user_filter').val('').trigger('change', [0]);
    $('#lr_action_by_filter').val('').trigger('change', [0]);
    $('#lr_type_filter').val('').trigger('change', [0]);
    $('#lr_table').bootstrapTable('refresh');
})

$('#lr_start_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#lr_start_date_from').val(startDate);
    $('#lr_start_date_to').val(endDate);

    $('#lr_table').bootstrapTable('refresh');
});

$('#lr_start_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#lr_start_date_from').val('');
    $('#lr_start_date_to').val('');
    $('#lr_start_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#lr_table').bootstrapTable('refresh');
});

$('#lr_end_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#lr_end_date_from').val(startDate);
    $('#lr_end_date_to').val(endDate);

    $('#lr_table').bootstrapTable('refresh');
});
$('#lr_end_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#lr_end_date_from').val('');
    $('#lr_end_date_to').val('');
    $('#lr_end_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#lr_table').bootstrapTable('refresh');
});

$(document).ready(function () {
    $('#lr_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');
        $('#lr_date_between_from').val(startDate);
        $('#lr_date_between_to').val(endDate);
        $('#lr_table').bootstrapTable('refresh');
    });

    // Cancel event to clear values
    $('#lr_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#lr_date_between_from').val('');
        $('#lr_date_between_to').val('');
        $(this).val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#lr_table').bootstrapTable('refresh');
    });
});


window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}


$(document).ready(function () {
    if(!isAdminOrLe){
        $('.delete-selected ').addClass('d-none');
    }

});