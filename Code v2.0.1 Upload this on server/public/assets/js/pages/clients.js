'use strict';

function queryParams(p) {
    return {
        "statuses": $('#client_status_filter').val(),
        "clientTypes": $('#client_internal_purpose_filter').val(),
        "ev_statuses": $('#client_ev_status_filter').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}



window.icons = {
    refresh: 'bx-refresh'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

function nameFormatter(value, row, index) {
    return [row.first_name, row.last_name].join(' ')
}

addDebouncedEventListener('#client_status_filter, #client_internal_purpose_filter, #client_ev_status_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-clients-filters', function (e) {
    e.preventDefault();
    $('#client_status_filter').val('').trigger('change', [0]);
    $('#client_internal_purpose_filter').val('').trigger('change', [0]); 
    $('#client_ev_status_filter').val('').trigger('change', [0]); 
    $('#table').bootstrapTable('refresh');   
})
