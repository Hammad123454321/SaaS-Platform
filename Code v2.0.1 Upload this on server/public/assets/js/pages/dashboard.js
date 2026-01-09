/**
 * Dashboard Analytics
 */
"use strict";
loadDashboardOrder();
(function () {
    let cardColor, headingColor, axisColor, borderColor;
    // Define colors from configuration
    cardColor = config.colors.white;
    headingColor = config.colors.headingColor;
    axisColor = config.colors.axisColor;
    borderColor = config.colors.borderColor;
    // Custom pastel colors for the charts
    const pastelColors = ["#64C7CC", "#A0D995", "#FFB677", "#D4A5FF"]; // Light cool colors
    const pastelSuccess = "#63ED7A"; // Light green for success
    const pastelDanger = "#FC544B"; // Light red for danger
    // Function to calculate percentage
    function calculatePercentage(data) {
        const total = data.reduce((a, b) => a + b, 0);
        return data.map((value) => ((value / total) * 100).toFixed(2) + "%");
    }
    // Projects Statistics Chart
    var projectOptions = {
        series: project_data, // Dynamic project data
        colors: bg_colors, // Dynamic colors
        labels: labels, // Dynamic labels
        chart: {
            type: "donut",
            height: 200, // Compact height
        },
        plotOptions: {
            pie: {
                donut: {
                    size: "80%", // Smaller donut thickness
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "Total",
                            fontSize: "16px",
                            fontWeight: 500,
                            formatter: function () {
                                return project_data.reduce((a, b) => a + b, 0); // Dynamic total sum
                            },
                        },
                    },
                },
            },
        },
        dataLabels: {
            enabled: false, // Disable external labels for cleaner design
        },
        responsive: [
            {
                breakpoint: 480,
                options: {
                    chart: {
                        width: 180,
                    },
                    legend: {
                        position: "bottom",
                        fontSize: "12px",
                    },
                },
            },
        ],
        legend: {
            position: "right",
            fontSize: "14px",
            markers: {
                radius: 12,
            },
        },
        tooltip: {
            y: {
                formatter: function (val, { seriesIndex }) {
                    const percentage =
                        calculatePercentage(project_data)[seriesIndex];
                    return `${val} (${percentage})`; // Show value and percentage in tooltip
                },
            },
        },
    };
    var projectChart = new ApexCharts(
        document.querySelector("#projectStatisticsChart"),
        projectOptions
    );
    projectChart.render();
    // Tasks Statistics Chart
    var taskOptions = {
        series: task_data, // Dynamic task data
        colors: bg_colors, // Dynamic colors
        labels: labels, // Dynamic labels
        chart: {
            type: "donut",
            height: 200,
        },
        plotOptions: {
            pie: {
                donut: {
                    size: "80%", // Smaller donut thickness
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "Total",
                            fontSize: "16px",
                            fontWeight: 500,
                            formatter: function () {
                                return task_data.reduce((a, b) => a + b, 0); // Dynamic total sum
                            },
                        },
                    },
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        responsive: [
            {
                breakpoint: 480,
                options: {
                    chart: {
                        width: 180,
                    },
                },
            },
        ],
        tooltip: {
            y: {
                formatter: function (val, { seriesIndex }) {
                    const percentage =
                        calculatePercentage(task_data)[seriesIndex];
                    return `${val} (${percentage})`; // Show value and percentage in tooltip
                },
            },
        },
    };
    var taskChart = new ApexCharts(
        document.querySelector("#taskStatisticsChart"),
        taskOptions
    );
    taskChart.render();
    // Todos Statistics Chart
    var todoOptions = {
        series: todo_data, // Dynamic todo data
        colors: [pastelSuccess, pastelDanger], // Light success and danger colors
        labels: [done, pending], // Dynamic labels for done/pending
        chart: {
            type: "donut",
            height: 200,
        },
        plotOptions: {
            pie: {
                donut: {
                    size: "80%", // Smaller donut thickness
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: "Total",
                            fontSize: "16px",
                            fontWeight: 500,
                            formatter: function () {
                                return todo_data.reduce((a, b) => a + b, 0); // Dynamic total sum
                            },
                        },
                    },
                },
            },
        },
        dataLabels: {
            enabled: false,
        },
        responsive: [
            {
                breakpoint: 480,
                options: {
                    chart: {
                        width: 180,
                    },
                },
            },
        ],
        tooltip: {
            y: {
                formatter: function (val, { seriesIndex }) {
                    const percentage =
                        calculatePercentage(todo_data)[seriesIndex];
                    return `${val} (${percentage})`; // Show value and percentage in tooltip
                },
            },
        },
    };
    var todoChart = new ApexCharts(
        document.querySelector("#todoStatisticsChart"),
        todoOptions
    );
    todoChart.render();
})();
window.icons = {
    refresh: "bx-refresh",
    toggleOn: "bx-toggle-right",
    toggleOff: "bx-toggle-left",
};
function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>';
}
function queryParamsUpcomingBirthdays(p) {
    return {
        upcoming_days: $("#upcoming_days_bd").val(),
        user_ids: $("#birthday_user_filter").val(),
        client_ids: $("#birthday_client_filter").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
$("#upcoming_days_birthday_filter").on("click", function (e) {
    e.preventDefault();
    $("#birthdays_table").bootstrapTable("refresh");
});
addDebouncedEventListener(
    "#birthday_user_filter, #birthday_client_filter",
    "change",
    function (e, refreshTable) {
        e.preventDefault();
        if (typeof refreshTable === "undefined" || refreshTable) {
            $("#birthdays_table").bootstrapTable("refresh");
        }
    }
);
$(document).on("click", ".clear-upcoming-bd-filters", function (e) {
    e.preventDefault();
    $("#upcoming_days_bd").val("");
    $("#birthday_user_filter").val("").trigger("change", [0]);
    $("#birthday_client_filter").val("").trigger("change", [0]);
    $("#birthdays_table").bootstrapTable("refresh");
});
function queryParamsUpcomingWa(p) {
    return {
        upcoming_days: $("#upcoming_days_wa").val(),
        user_ids: $("#wa_user_filter").val(),
        client_ids: $("#wa_client_filter").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
$("#upcoming_days_wa_filter").on("click", function (e) {
    e.preventDefault();
    $("#wa_table").bootstrapTable("refresh");
});
addDebouncedEventListener(
    "#wa_user_filter, #wa_client_filter",
    "change",
    function (e, refreshTable) {
        e.preventDefault();
        if (typeof refreshTable === "undefined" || refreshTable) {
            $("#wa_table").bootstrapTable("refresh");
        }
    }
);
$(document).on("click", ".clear-upcoming-wa-filters", function (e) {
    e.preventDefault();
    $("#upcoming_days_wa").val("");
    $("#wa_user_filter, #wa_client_filter").val("").trigger("change", [0]);
    $("#wa_table").bootstrapTable("refresh");
});
function queryParamsMol(p) {
    return {
        upcoming_days: $("#upcoming_days_mol").val(),
        user_ids: $("#mol_user_filter").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
$("#upcoming_days_mol_filter").on("click", function (e) {
    e.preventDefault();
    $("#mol_table").bootstrapTable("refresh");
});
addDebouncedEventListener(
    "#mol_user_filter",
    "change",
    function (e, refreshTable) {
        e.preventDefault();
        if (typeof refreshTable === "undefined" || refreshTable) {
            $("#mol_table").bootstrapTable("refresh");
        }
    }
);
$(document).on("click", ".clear-upcoming-mol-filters", function (e) {
    e.preventDefault();
    $("#upcoming_days_mol").val("");
    $("#mol_user_filter").val("").trigger("change", [0]);
    $("#mol_table").bootstrapTable("refresh");
});
// alert('here');
$(function () {
    let incomeExpenseChart = null; // Initialize the chart variable
    function getFilters() {
        // Get the values from hidden inputs
        var startDate = $("#filter_date_range_from").val();
        var endDate = $("#filter_date_range_to").val();
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
    function parseCurrencyValue(currencyString) {
        // Remove currency symbol and commas, then convert to float
        return parseFloat(currencyString.replace(/[^0-9.-]+/g, ""));
    }
    function groupByDate(data, type) {
        const grouped = {};
        if (!Array.isArray(data) || data.length === 0) {
            return grouped; // return empty if no data
        }
        data.forEach((item) => {
            const date =
                type === "invoice" ? item.from_date : item.expense_date;
            const amount = parseCurrencyValue(item.amount);
            if (!grouped[date]) {
                grouped[date] = 0;
            }
            grouped[date] += amount;
        });
        return grouped;
    }
    function transformData(response) {
        // Group invoices and expenses by date
        const invoicesByDate = groupByDate(response.invoices, "invoice");
        const expensesByDate = groupByDate(response.expenses, "expense");
        // Get all unique dates
        const allDates = [
            ...new Set([
                ...Object.keys(invoicesByDate),
                ...Object.keys(expensesByDate),
            ]),
        ].sort();
        // Prepare series data
        const categories = [];
        const incomeData = [];
        const expenseData = [];
        allDates.forEach((date) => {
            categories.push(date);
            incomeData.push(invoicesByDate[date] || 0);
            expenseData.push((expensesByDate[date] || 0)); // Make expenses negative
        });
        return {
            categories,
            incomeData,
            expenseData,
        };
    }
    function updateIEChart() {
        $.ajax({
            type: "GET",
            url: "/reports/income-vs-expense-report-data",
            dataType: "JSON",
            data: getFilters(),
            success: function (response) {
                const chartData = transformData(response);
                const options = {
                    series: [
                        {
                            name: "Income",
                            data: chartData.incomeData,
                        },
                        {
                            name: "Expenses",
                            data: chartData.expenseData,
                        },
                    ],
                    chart: {
                        height: 380,
                        type: "area",
                        stacked: false,
                        toolbar: {
                            show: false,
                        },
                    },
                    dataLabels: {
                        enabled: false,
                    },
                    stroke: {
                        curve: "smooth",
                        width: 2,
                    },
                    fill: {
                        type: "gradient",
                        gradient: {
                            opacityFrom: 0.6,
                            opacityTo: 0.1,
                        },
                    },
                    colors: ["#22C55E", "#EF4444"], // Green for income, Red for expenses
                    xaxis: {
                        categories: chartData.categories,
                        labels: {
                            rotate: -45,
                            style: {
                                colors: "#64748B",
                                fontSize: "12px",
                            },
                        },
                        axisBorder: {
                            show: false,
                        },
                        axisTicks: {
                            show: false,
                        },
                    },
                    yaxis: {
                        labels: {
                            formatter: function (val) {
                                return "$ " + Math.abs(val).toLocaleString();
                            },
                            style: {
                                colors: "#64748B",
                                fontSize: "12px",
                            },
                        },
                    },
                    grid: {
                        borderColor: "#E2E8F0",
                        strokeDashArray: 4,
                        xaxis: {
                            lines: {
                                show: true,
                            },
                        },
                        yaxis: {
                            lines: {
                                show: true,
                            },
                        },
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                        y: {
                            formatter: function (value) {
                                return "$ " + Math.abs(value).toLocaleString();
                            },
                        },
                    },
                    legend: {
                        position: "top",
                        horizontalAlign: "right",
                        fontSize: "14px",
                        markers: {
                            radius: 12,
                        },
                    },
                };
                if ($('#income-expense-chart').length) {
                    if (incomeExpenseChart) {
                        incomeExpenseChart.updateOptions(options);
                    } else {
                        incomeExpenseChart = new ApexCharts(
                            document.querySelector("#income-expense-chart"),
                            options
                        );
                        incomeExpenseChart.render();
                    }
                }
            },
            error: function (xhr, status, error) {
                console.error("Chart Error:", error);
            },
        });
    }
    $("#filter_date_range_income_expense").on("apply.daterangepicker", function (ev, picker) {
        // alert("Date range applied: " + picker.startDate.format("YYYY-MM-DD") + " to " + picker.endDate.format("YYYY-MM-DD"));
        // Set the values in hidden inputs
        $("#filter_date_range_from").val(picker.startDate.format("YYYY-MM-DD"));
        $("#filter_date_range_to").val(picker.endDate.format("YYYY-MM-DD"));
        updateIEChart(); // Update report when dates are applied
    });
    $("#filter_date_range_income_expense").on("cancel.daterangepicker", function (ev, picker) {
        $(this).val("");
        // Clear the hidden inputs
        $("#filter_date_range_from").val("");
        $("#filter_date_range_to").val("");
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        updateIEChart(); // Update report when dates are cleared
    });
    // Initial chart update
    updateIEChart();
});
$(document).ready(function () {
    if (typeof moment === 'undefined') {
        console.error("Moment.js is NOT loaded!");
        return;
    }
    $('#filter_date_range_income_expense').daterangepicker({
        width: '100%',
        "alwaysShowCalendars": true,
        startDate: moment().startOf('month'),
        endDate: moment().endOf('month'),
        locale: {
            format: 'YYYY-MM-DD',
            cancelLabel: 'Clear'
        },
        ranges: {
            'Today': [moment(), moment()],
            'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
            'Last 7 Days': [moment().subtract(6, 'days'), moment()],
            'Last 30 Days': [moment().subtract(29, 'days'), moment()],
            'This Month': [moment().startOf('month'), moment().endOf('month')],
            'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
        }
    });
});
$(document).ready(function () {
    const $dashboardContainer = $('#dashboard-items');
    // Load the saved layout from localStorage on page load
    // Initialize Sortable.js
    Sortable.create($dashboardContainer[0], {
        animation: 150,
        ghostClass: 'sortable-ghost',
        handle: '.draggable-item',
        onStart: function (evt) {
            // Dynamically set the placeholder size to match the dragged item
            const draggedItem = evt.item;
            const placeholder = evt.clone; // Clone of the dragged item
            $(placeholder).css({
                height: $(draggedItem).outerHeight(),
                width: $(draggedItem).outerWidth()
            });
        },
        onEnd: function (evt) {
            const oldIndex = evt.oldIndex;
            const newIndex = evt.newIndex;
            console.log(`Item moved from position ${oldIndex} to ${newIndex}`);
            // Save the new order and dimensions to localStorage
            saveDashboardOrder();
        }
    });
    /**
     * Save the new order and dimensions to localStorage
     */
    function saveDashboardOrder() {
        const order = [];
        // Iterate through each draggable item
        $('#dashboard-items .draggable-item').each(function (index) {
            const $this = $(this);
            const id = $this.data('id');
            const height = $this.outerHeight(); // Get height of the item
            const width = $this.outerWidth(); // Get width of the item
            const position = index + 1; // Current position in the order
            order.push({
                id: id,
                height: height,
                width: width,
                position: position
            });
        });
        // Save the order in localStorage
        localStorage.setItem('dashboardOrder', JSON.stringify(order));
        console.log('Dashboard order saved to localStorage:', order);
    }
});
/**
 * Load the saved layout from localStorage
 */
function loadDashboardOrder() {
    const savedOrder = localStorage.getItem('dashboardOrder');
    const $dashboardContainer = $('#dashboard-items');
    if (savedOrder) {
        const order = JSON.parse(savedOrder);
        console.log('Loaded dashboard order from localStorage:', order);
        // Reorder the items based on the saved order
        order.forEach(function (item) {
            const $item = $(`#dashboard-items .draggable-item[data-id="${item.id}"]`);
            // Set the height and width of the item
            $item.css({
                height: item.height + 'px',
                width: item.width + 'px'
            });
            // Append the item in the correct position
            $dashboardContainer.append($item);
        });
    }
}
$(document).ready(function () {
    // Loop through all draggable items
    $('.draggable-item').each(function () {
        // Make sure parent is relatively positioned for absolute icon
        $(this).addClass('position-relative');
        // Create the tooltip icon element
        const tooltipIcon = `
            <span class="drag-tooltip-icon end-0 fs-4 me-4 mt-2 position-absolute top-0" data-bs-toggle="tooltip" title="Drag to reorder">
                <i class="bx bx-move text-muted small"></i>
            </span>
        `;
        // Append it to the item
        $(this).append(tooltipIcon);
    });
    // Initialize Bootstrap tooltip
    $('[data-bs-toggle="tooltip"]').tooltip();
});
