$(function () {

    $('#invoices_report_table').on('load-success.bs.table', function (e, data) {
        $('#average-invoice-value').text(data.summary.average_invoice_value);
        $('#total-final').text(data.summary.total_final);
        $('#total-tax').text(data.summary.total_tax);
        $('#total-amount').text((data.summary.total_amount));
        $('#total-invoices').text(data.summary.total_invoices);
    });
});
$(document).ready(function () {
    $('#export_button').click(function () {
        var $exportButton = $(this);
        $exportButton.attr('disabled', true);
        // Prepare query parameters
        const queryParams = invoices_report_query_params({ offset: 0, limit: 1000, sort: 'id', order: 'desc', search: '' });
        // Construct the export URL
        const exportUrl = invoices_report_export_url + '?' + $.param(queryParams);
        // Open the export URL in a new tab or window
        $exportButton.attr('disabled', false);
        window.open(exportUrl, '_blank');
    });
    $('#filter_date_range').on('apply.daterangepicker', function (ev, picker) {
        $('#filter_date_range_from').val(picker.startDate.format('YYYY-MM-DD'));
        $('#filter_date_range_to').val(picker.endDate.format('YYYY-MM-DD'));
        $('#invoices_report_table').bootstrapTable('refresh');
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
        $('#invoices_report_table').bootstrapTable('refresh');
    });


    $('#report_start_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_start_date_from').val(startDate);
        $('#filter_start_date_to').val(endDate);

        $('#invoices_report_table').bootstrapTable('refresh');
    });

    $('#report_start_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_start_date_from').val('');
        $('#filter_start_date_to').val('');
        $('#report_start_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#invoices_report_table').bootstrapTable('refresh');
    });

    $('#report_end_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');

        $('#filter_end_date_from').val(startDate);
        $('#filter_end_date_to').val(endDate);

        $('#invoices_report_table').bootstrapTable('refresh');
    });
    $('#report_end_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#filter_end_date_from').val('');
        $('#filter_end_date_to').val('');
        $('#report_end_date_between').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#invoices_report_table').bootstrapTable('refresh');
    });
});
function invoices_report_query_params(p) {
    return {
        "types": $('#type_filter').val(),
        "statuses": $('#status_filter').val(),
        "client_ids": $('#client_filter').val(),
        "created_by_user_ids": $('#user_creators_filter').val(),
        "created_by_client_ids": $('#client_creators_filter').val(),
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

addDebouncedEventListener('#type_filter, #client_filter, #status_filter, #user_creators_filter, #client_creators_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#invoices_report_table').bootstrapTable('refresh');
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
    $('#type_filter').val('').trigger('change', [0]);
    $('#client_filter').val('').trigger('change', [0]);
    $('#status_filter').val('').trigger('change', [0]);
    $('#user_creators_filter').val('').trigger('change', [0]);
    $('#client_creators_filter').val('').trigger('change', [0]);
    $('#invoices_report_table').bootstrapTable('refresh');
})