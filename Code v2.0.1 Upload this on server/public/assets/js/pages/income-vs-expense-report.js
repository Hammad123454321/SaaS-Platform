document.addEventListener('DOMContentLoaded', function () {
    // Function to get current filters
    function getFilters() {
        // Get the values from hidden inputs
        var startDate = $('#filter_date_range_from').val();
        console.log(startDate);
        var endDate = $('#filter_date_range_to').val();

        // Check if the input values are not empty
        if (startDate && endDate) {
            return {
                start_date: startDate,
                end_date: endDate,
            };
        }

        // If dates are not set or input is empty, return null
        return {
            start_date: null,
            end_date: null,
        };
    }

    // Function to fetch and update the report data
    function updateReport() {
        $.ajax({
            url: baseUrl + '/reports/income-vs-expense-report-data',
            method: 'GET',
            data: getFilters(),
            success: function (data) {
                // Update total income and expenses
                $('#total_income').text(data.total_income || '0');
                $('#total_expenses').text(data.total_expenses || '0');
                $('#profit_or_loss').text(data.profit_or_loss || '0');

                // Update invoice details
                var invoicesHtml = '';
                if (data.invoices.length > 0) {
                    data.invoices.forEach(function (invoice) {
                        invoicesHtml += `
                        <tr>
                            <td><a href="${invoice.view_route}">${label_invoice_id_prefix}${invoice.id}</a></td>
                            <td>${invoice.from_date} ${label_to} ${invoice.to_date}</td>
                            <td>${invoice.amount}</td>
                        </tr>
                    `;
                    });
                } else {
                    invoicesHtml = `
                    <tr>
                        <td colspan="3" class="text-center">${label_no_data_available}</td>
                    </tr>
                `;
                }
                $('#invoices_table tbody').html(invoicesHtml);

                // Update expense details
                var expensesHtml = '';
                if (data.expenses.length > 0) {
                    data.expenses.forEach(function (expense) {
                        expensesHtml += `
                        <tr>
                            <td>${expense.id}</td>
                            <td>${expense.title}</td>
                            <td>${expense.amount}</td>
                            <td>${expense.expense_date}</td>
                        </tr>
                    `;
                    });
                } else {
                    expensesHtml = `
                    <tr>
                        <td colspan="4" class="text-center">${label_no_data_available}</td>
                    </tr>
                `;
                }
                $('#expenses_table tbody').html(expensesHtml);
            },
            error: function () {
                // Handle errors
                toastr.error(label_something_went_wrong);
            }
        });
    }

    // Handle filter changes on apply or cancel
    $('#filter_date_range').on('apply.daterangepicker', function (ev, picker) {
        // Set the values in hidden inputs
        $('#filter_date_range_from').val(picker.startDate.format('YYYY-MM-DD'));
        $('#filter_date_range_to').val(picker.endDate.format('YYYY-MM-DD'));
        updateReport(); // Update report when dates are applied
    });

    $('#filter_date_range').on('cancel.daterangepicker', function (ev, picker) {
        $(this).val('');
        // Clear the hidden inputs
        $('#filter_date_range_from').val('');
        $('#filter_date_range_to').val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        updateReport(); // Update report when dates are cleared
    });

    // Initialize report with default filters
    updateReport();
});

var isExporting = false; // Concurrency lock

$('#export_button').on('click', async function () {
    if (isExporting) {
        return; // Prevent concurrent exports
    }

    isExporting = true; // Lock to prevent concurrent execution

    // Disable the button
    var $exportButton = $(this);
    $exportButton.attr('disabled', true);

    try {
        var startDate = $('#filter_date_range_from').val();
        var endDate = $('#filter_date_range_to').val();

        // Build the URL conditionally
        var exportUrl = export_income_vs_expense_url;
        var params = [];

        if (startDate) {
            params.push(`start_date=${startDate}`);
        }
        if (endDate) {
            params.push(`end_date=${endDate}`);
        }

        if (params.length > 0) {
            exportUrl += `?${params.join('&')}`;
        }

        await performExport(exportUrl); // Simulate async export operation
    } catch (error) {
        console.error('Export failed', error);
        toastr.error('Something went wrong during the export.');
    } finally {
        // Re-enable the button
        $exportButton.attr('disabled', false);
        isExporting = false; // Release lock
    }
});

async function performExport(url) {
    // Simulate a delay to represent the export process
    return new Promise((resolve, reject) => {
        setTimeout(() => {
            window.open(url, '_blank');
            resolve(); // Mark the export as complete
        }, 2000); // Simulate a 2-second export delay
    });
}
