
$(document).ready(function () {
    var viewMode = 'Day'; // Default view mode
    var currentDate = new Date();
    var gantt;
    var initialStartDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
    var initialEndDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
    var previousTaskState = {};
    var manualClose = false; // Flag to track manual closure

    // Initialize Gantt chart
    function initGanttChart(data) {        
        if (!gantt) {
            gantt = new Gantt("#gantt", data, {
                view_mode: viewMode,
                date_format: 'YYYY-MM-DD',
                start_date: initialStartDate,
                end_date: initialEndDate,
                year_view_pixel_per_day: 0.5,
                custom_popup_html: null,
                on_click: function (task) {
                    redirectToInfoPage(task);
                },
                on_date_change: function (task, start, end) {
                    previousTaskState[task.id] = {
                        start: task.start,
                        end: task.end
                    };
                    if (task.id == 'no_tasks') {
                        toastr.error(label_change_date_not_allowed);
                        revertTaskDates(task); // Revert the change immediately
                        return; // Stop further processing
                    }
                    debouncedUpdateModuleDates(task, start, end);
                },
                on_progress_change: function (task, progress) {
                    // Handle progress change if needed
                },
                on_view_change: function (mode) {
                    // Handle view mode change if needed
                }
            });
        } else {
            gantt.refresh(data); // Refresh existing gantt instance
        }
    }

    function redirectToInfoPage(data) {

        if (data && data.type && data.id) {
            let url;
            switch (data.type) {
                case 'project':
                    url = baseUrl + '/projects/information/' + data.id;
                    break;
                case 'task':
                    url = baseUrl + '/tasks/information/' + data.id;
                    break;
                default:
                    console.error('Unknown module type:', data.type);
                    return; // Exit the function early if the type is unknown
            }

            // Open the URL in a new tab
            window.open(url, '_blank');
        } else {
            console.error('Invalid data provided. Make sure it has "type" and "id" properties.');
        }
    }

    function validateDateRange(start, end) {
        return start <= end;
    }

    // Fetch data and initialize/update Gantt chart
    function fetchDataAndUpdateGantt(startDate, endDate) {
        $.ajax({
            type: "GET",
            url: baseUrl + "/projects/fetch-gantt-data",
            data: {
                'start_date': startDate.toISOString().split('T')[0],
                'end_date': endDate.toISOString().split('T')[0],
                'favorite': $('#is_favorites').val()
            },
            dataType: "JSON",
            success: function (response) {
                var projects = response;
                var data = processProjectsData(projects, startDate, endDate);
                initGanttChart(data);
            },
            error: function (xhr) {
                var errors = xhr.responseJSON.errors;
                var errorMessages = [];
                $.each(errors, function (key, value) {
                    errorMessages.push(value);
                });
                toastr.error(errorMessages.join('<br>'));
            }
        });
    }

    // Process and validate projects data
    function processProjectsData(projects, startDate, endDate) {
        var processedData = [];
        $.each(projects, function (index, project) {
            if (project.id && project.title && project.start_date && project.end_date) {
                var projectStart = new Date(project.start_date);
                var projectEnd = new Date(project.end_date);

                if (!validateDateRange(projectStart, projectEnd)) {
                    return; // Skip invalid date ranges
                }

                processedData.push({
                    id: project.id.toString(),
                    name: project.title,
                    start: projectStart,
                    end: projectEnd,
                    progress: 100,
                    dependencies: [],
                    type: 'project'
                });

                if (Array.isArray(project.tasks)) {
                    $.each(project.tasks, function (index, task) {
                        if (task.id && task.title && task.start_date && task.due_date) {
                            var taskStart = new Date(task.start_date);
                            var taskEnd = new Date(task.due_date);

                            if (!validateDateRange(taskStart, taskEnd)) {
                                return; // Skip invalid date ranges
                            }

                            processedData.push({
                                id: task.id.toString(),
                                name: task.title,
                                start: taskStart,
                                end: taskEnd,
                                progress: 100,
                                type: 'task',
                                dependencies: [project.id.toString()]
                            });
                        }
                    });
                }
            }
        });

        if (processedData.length === 0) {
            processedData.push({
                id: 'no_tasks',
                name: label_no_projects_available,
                start: startDate,
                end: endDate,
                progress: 100
            });
        }
        return processedData;
    }

    function updateModuleDates(task, start, end) {
        $('#confirmUpdateDates').modal('show');
        var $confirmButton = $('#confirmUpdateDates').find('#confirm');
        $('#confirmUpdateDates').find('#confirm').off('click').on('click', function () {            
            $confirmButton.attr('disabled', true).html(label_please_wait);
            $.ajax({
                type: "POST",
                url: baseUrl + "/projects/gantt-chart-view/update-module-dates",
                data: {
                    'module': task,
                    'start_date': start,
                    'end_date': end,
                    '_token': $('meta[name="csrf-token"]').attr('content')
                },
                dataType: "JSON",
                success: function (response) {
                    if (!response.error) {
                        // Update previousTaskState to reflect the latest successful update
                        previousTaskState[task.id] = {
                            start: start, // Update with new start date
                            end: end      // Update with new end date
                        };
                        toastr.success(response.message);
                        $('#confirmUpdateDates').modal('hide');
                        manualClose = false;
                    }
                },
                error: function (xhr) {
                    var errors = xhr.responseJSON.errors;
                    var errorMessages = [];
                    $.each(errors, function (key, value) {
                        errorMessages.push(value);
                    });
                    toastr.error(errorMessages.join('<br>'));
                    revertTaskDates(task); // Revert on error
                },
                complete: function () {
                    $confirmButton.attr('disabled', false).html(label_yes);
                }
            });
        });
    
        // On cancel, revert the dates
        $('#confirmUpdateDates').find('#cancel').off('click').on('click', function () {
            $('#confirmUpdateDates').modal('hide');
            manualClose = true; // Set manual close flag
            revertTaskDates(task); // Revert on cancel
        });
        $('#confirmUpdateDates').find('.btn-close').off('click').on('click', function () {
            manualClose = true; // Set manual close flag
        });
    
        // Handle modal hidden event
        $('#confirmUpdateDates').on('hidden.bs.modal', function () {
            if (manualClose) {
                revertTaskDates(task); // Revert if not closed manually
            }
            manualClose = false; // Reset flag for next modal open
        });
    }
    
    function revertTaskDates(task) {
        if (previousTaskState[task.id]) {
            task.start = previousTaskState[task.id].start;
            task.end = previousTaskState[task.id].end;
            gantt.refresh(gantt.tasks); // Refresh to update Gantt chart with original dates
        }
    }

    // Debounce function to limit update requests
    const debounce = (func, delay) => {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func(...args), delay);
        };
    };

    // Create the debounced version of updateModuleDates
    const debouncedUpdateModuleDates = debounce(updateModuleDates, 500);

    // Update Gantt chart view based on the current view mode and selected month
    function updateGanttDates() {
        var startDate, endDate;

        switch (viewMode) {
            case 'Day':
                startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 1);
                break;
            case 'Week':
                startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate() - currentDate.getDay());
                endDate = new Date(startDate);
                endDate.setDate(endDate.getDate() + 7);
                break;
            case 'Month':
                startDate = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
                endDate = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
                break;
        }

        fetchDataAndUpdateGantt(startDate, endDate);
    }

    function formatDate(date) {
        return date.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    // Change the month view
    function changeMonth(delta) {
        currentDate.setMonth(currentDate.getMonth() + delta);
        updateGanttDates();
    }

    // Change view mode
    function changeViewMode(mode) {
        viewMode = mode;
        if (gantt) {
            gantt.change_view_mode(mode); // Call method only if gantt is initialized
        }
        updateGanttDates();
    }

    // Button event handlers
    $('#prev').on('click', function () {
        changeMonth(-1);
    });

    $('#next').on('click', function () {
        changeMonth(1);
    });

    $('#day-view').on('click', function () {
        $('.view-btns').removeClass('btn-primary');
        $('#navigation-buttons-container').hide();
        $(this).addClass('btn-primary');
        changeViewMode('Day');
    });

    $('#week-view').on('click', function () {
        $('.view-btns').removeClass('btn-primary');
        $('#navigation-buttons-container').show();
        $(this).addClass('btn-primary');
        changeViewMode('Week');
    });

    $('#month-view').on('click', function () {
        $('.view-btns').removeClass('btn-primary');
        $('#navigation-buttons-container').show();
        $(this).addClass('btn-primary');
        changeViewMode('Month');
    });

    // Initial setup
    fetchDataAndUpdateGantt(initialStartDate, initialEndDate);
});
