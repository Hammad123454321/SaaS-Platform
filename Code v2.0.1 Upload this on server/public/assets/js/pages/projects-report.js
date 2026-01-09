
$(function () {
    $('#projects_report_table').on('load-success.bs.table', function (e, data) {
        $('#total-projects').text(data.summary.total_projects);
        $('#on-time-projects').text(data.summary.on_time_projects);
        $('#projects-with-due-tasks').text(data.summary.projects_with_due_tasks);
        $('#average-days-remaining').text((data.summary.average_days_remaining || 0).toFixed(2));
        $('#average-task-progress').text((data.summary.average_task_progress || 0).toFixed(2) + '%');
        $('#average-overdue-days-per-project').text((data.summary.average_overdue_days_per_project || 0).toFixed(2));
        $('#total-tasks').text(data.summary.total_tasks);
        $('#average-task-duration').text((data.summary.average_task_duration || 0).toFixed(2) + ' days');
        $('#total-overdue-days').text(data.summary.total_overdue_days);
        $('#overdue-projects-percentage').text(
            `${data.summary.overdue_projects || 0} (${(data.summary.overdue_projects_percentage || 0).toFixed(2)}%)`
        );
        $('#due-projects-percentage').text(
            `${data.summary.due_projects || 0} (${(data.summary.due_projects_percentage || 0).toFixed(2)}%)`
        );        
        $('#average-budget-utilization').text((data.summary.average_budget_utilization || 0).toFixed(2) + '%');
        $('#total-team-members').text(data.summary.total_team_members);
    });


});
$(document).ready(function () {
    $('#export_button').click(function () {
        var $exportButton = $(this);
        $exportButton.attr('disabled', true);
        // Prepare query parameters
        const queryParams = project_report_query_params({ offset: 0, limit: 1000, sort: 'id', order: 'desc', search: '' });
        // Construct the export URL
        const exportUrl = projects_report_export_url + '?' + $.param(queryParams);
        // Open the export URL in a new tab or window
        $exportButton.attr('disabled', false);
        window.open(exportUrl, '_blank');
    });
    $('#filter_date_range').on('apply.daterangepicker', function (ev, picker) {
        $('#filter_date_range_from').val(picker.startDate.format('YYYY-MM-DD'));
        $('#filter_date_range_to').val(picker.endDate.format('YYYY-MM-DD'));
        $('#projects_report_table').bootstrapTable('refresh');
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
        $('#projects_report_table').bootstrapTable('refresh');
    });


    $('#report_start_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_start_date_from').val(startDate);
        $('#filter_start_date_to').val(endDate);

        $('#projects_report_table').bootstrapTable('refresh');
    });

    $('#report_start_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_start_date_from').val('');
        $('#filter_start_date_to').val('');
        $('#report_start_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#projects_report_table').bootstrapTable('refresh');
    });

    $('#report_end_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_end_date_from').val(startDate);
        $('#filter_end_date_to').val(endDate);

        $('#projects_report_table').bootstrapTable('refresh');
    });
    $('#report_end_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_end_date_from').val('');
        $('#filter_end_date_to').val('');
        $('#report_end_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#projects_report_table').bootstrapTable('refresh');
    });
});
function project_report_query_params(p) {
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
        $('#projects_report_table').bootstrapTable('refresh');
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
    $('#projects_report_table').bootstrapTable('refresh');
})
