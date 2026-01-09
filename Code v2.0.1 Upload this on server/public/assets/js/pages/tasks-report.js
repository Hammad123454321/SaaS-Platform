
$(function () {
    $('#tasks_report_table').on('load-success.bs.table', function (e, data) {
        $('#total-tasks').text(data.summary.total_tasks);
        $('#due-tasks').text(
            `${data.summary.due_tasks || 0} (${(data.summary.due_tasks_percentage || 0).toFixed(2)}%)`
        );        
        $('#overdue-tasks').text(
            `${data.summary.overdue_tasks || 0} (${(data.summary.overdue_tasks_percentage || 0).toFixed(2)}%)`
        );        
        $('#average-task-completion-time').text(data.summary.average_task_duration);
        $('#urgent-tasks').text(
            `${data.summary.urgent_tasks || 0} (${(data.summary.urgent_tasks_percentage || 0).toFixed(2)}%)`
        );             
        $('#total-tasks').text(data.summary.total_tasks);
    });


});
$(document).ready(function () {
    $('#export_button').click(function () {
        var $exportButton = $(this);
        $exportButton.attr('disabled', true);
        // Prepare query parameters
        const queryParams = tasks_report_query_params({ offset: 0, limit: 1000, sort: 'id', order: 'desc', search: '' });
        // Construct the export URL
        const exportUrl = tasks_report_export_url + '?' + $.param(queryParams);
        // Open the export URL in a new tab or window
        $exportButton.attr('disabled', false);
        window.open(exportUrl, '_blank');
    });
    $('#filter_date_range').on('apply.daterangepicker', function (ev, picker) {
        $('#filter_date_range_from').val(picker.startDate.format('YYYY-MM-DD'));
        $('#filter_date_range_to').val(picker.endDate.format('YYYY-MM-DD'));
        $('#tasks_report_table').bootstrapTable('refresh');
    });
    $('#filter_date_range').on('cancel.daterangepicker', function (ev, picker) {
        // Clear the input field and hidden fields
        $(this).val('');
        // Clear the hidden inputs
        $('#filter_date_range_from').val('');
        $('#filter_date_range_to').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#tasks_report_table').bootstrapTable('refresh');
    });


    $('#report_start_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_start_date_from').val(startDate);
        $('#filter_start_date_to').val(endDate);

        $('#tasks_report_table').bootstrapTable('refresh');
    });

    $('#report_start_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_start_date_from').val('');
        $('#filter_start_date_to').val('');
        $('#report_start_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#tasks_report_table').bootstrapTable('refresh');
    });

    $('#report_end_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_end_date_from').val(startDate);
        $('#filter_end_date_to').val(endDate);

        $('#tasks_report_table').bootstrapTable('refresh');
    });
    $('#report_end_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_end_date_from').val('');
        $('#filter_end_date_to').val('');
        $('#report_end_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#tasks_report_table').bootstrapTable('refresh');
    });
});
function tasks_report_query_params(p) {
    return {
        project_ids: $('#project_filter').val(),
        user_ids: $('#user_filter').val(),
        client_ids: $('#client_filter').val(),
        status_ids: $('#status_filter').val(),
        priority_ids: $('#priority_filter').val(),
        date_between_from: $('#filter_date_range_from').val(),
        date_between_to: $('#filter_date_range_to').val(),
        start_date_from: $('#filter_start_date_from').val(),
        start_date_to: $('#filter_start_date_to').val(),
        end_date_from: $('#filter_end_date_from').val(),
        end_date_to: $('#filter_end_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}
addDebouncedEventListener('#project_filter,#user_filter,#client_filter,#status_filter,#priority_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#tasks_report_table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-report-filters', function (e) {
    e.preventDefault();
    $('#filter_date_range').val('');
    $('#filter_date_range_from').val('');
    $('#filter_date_range_to').val('');
    $('#report_start_date_between').val('');
    $('#filter_start_date_from').val('');
    $('#filter_start_date_to').val('');
    $('#report_end_date_between').val('');
    $('#filter_end_date_from').val('');
    $('#filter_end_date_to').val('');
    $('#project_filter').val('').trigger('change', [0]);
    $('#user_filter').val('').trigger('change', [0]);
    $('#client_filter').val('').trigger('change', [0]);
    $('#status_filter').val('').trigger('change', [0]);
    $('#priority_filter').val('').trigger('change', [0]);
    $('#tasks_report_table').bootstrapTable('refresh');
})
