
'use strict';
function queryParams(p) {
    return {
        "statuses": $('#status_filter').val(),
        "types": $('#type_filter').val(),
        "notification_types": $('#noti_types_filter').val(),
        "user_ids": $('#user_filter').val(),
        "client_ids": $('#client_filter').val(),
        "date_from": $('#notification_between_date_from').val(),
        "date_to": $('#notification_between_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}

$('#notification_between_date').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#notification_between_date_from').val(startDate);
    $('#notification_between_date_to').val(endDate);

    $('#table').bootstrapTable('refresh');
});

$('#notification_between_date').on('cancel.daterangepicker', function (ev, picker) {
    $('#notification_between_date_from').val('');
    $('#notification_between_date_to').val('');
    $('#notification_between_date').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
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

function ClientFormatter(value, row, index) {
    var clients = Array.isArray(row.clients) && row.clients.length ? row.clients : '<span class="badge bg-primary">' + label_not_assigned + '</span>';
    if (Array.isArray(clients)) {
        clients = clients.map(client => '<li>' + client + '</li>');
        return '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">' + clients.join('') + '</ul>';
    } else {
        return clients;
    }
}


function UserFormatter(value, row, index) {
    var users = Array.isArray(row.users) && row.users.length ? row.users : '<span class="badge bg-primary">' + label_not_assigned + '</span>';
    if (Array.isArray(users)) {
        users = users.map(user => '<li>' + user + '</li>');
        return '<ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">' + users.join('') + '</ul>';
    } else {
        return users;
    }
}

addDebouncedEventListener('#status_filter, #user_filter, #client_filter, #type_filter, #noti_types_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});
$(document).on('click', '.clear-notifications-filters', function (e) {
    e.preventDefault();  
    $('#notification_between_date_from').val('');
    $('#notification_between_date_to').val('');
    $('#notification_between_date').val('');
    $('#status_filter').val('').trigger('change', [0]);
    $('#user_filter').val('').trigger('change', [0]);
    $('#client_filter').val('').trigger('change', [0]);
    $('#type_filter').val('').trigger('change', [0]);
    $('#noti_types_filter').val('').trigger('change', [0]);
    $('#table').bootstrapTable('refresh');
})
