'use strict';
function queryParams(p) {
    return {
        "statuses": $('#user_status_filter').val(),
        "role_ids": $('#user_roles_filter').val(),
        "ev_statuses": $('#user_ev_status_filter').val(),
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
    toggleOff: 'bx-toggle-left',
    toggleOn: 'bx-toggle-right'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

addDebouncedEventListener('#user_status_filter, #user_roles_filter, #user_ev_status_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-users-filters', function (e) {
    e.preventDefault();
    $('#user_status_filter').val('').trigger('change', [0]);
    $('#user_roles_filter').val('').trigger('change', [0]); 
    $('#user_ev_status_filter').val('').trigger('change', [0]); 
    $('#table').bootstrapTable('refresh');   
})
