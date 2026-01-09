$(document).ready(function () {
    if (!window.datatablesConfig) {
        console.error('No datatablesConfig found.');
        return;
    }

    // Initialize each configured table
    $.each(window.datatablesConfig, function (tableKey, config) {
        initializeDataTable(config);
    });

    function initializeDataTable(config) {
        const selector = '#' + config.tableId;
        const csrfToken = $('meta[name="csrf-token"]').attr('content');
        const $wrapper = $(selector).closest('.datatable-wrapper');
        const $loading = $wrapper.find('.datatable-loading');

        // Destroy existing table if it exists
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().destroy();
        }

        // Build buttons array
        const buttons = buildButtons(config);

        // Build columns array
        const dtColumns = buildColumns(config);

        // Build column definitions
        const dtColumnDefs = buildColumnDefs(config);

        // Base DataTable configuration
        const dataTableConfig = {
            processing: true,
            serverSide: !!config.ajaxUrl,
            responsive: false,
            select: config.showCheckbox ? {
                style: "multi",
                selector: "td:first-child .dt-checkboxes"
            } : false,
            pageLength: config.pageLength || 10,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            autoWidth: false,
            fixedHeader: true,
            dom: buildDom(config),
            buttons: buttons,
            columns: dtColumns,
            columnDefs: dtColumnDefs,
            order: [[config.showCheckbox ? 1 : 0, 'desc']],
            language: {
                processing: '<div class="d-flex justify-content-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>',
                emptyTable: '<div class="text-center py-4"><i class="bx bx-info-circle fs-1 text-muted"></i><br>No data available</div>',
                zeroRecords: '<div class="text-center py-4"><i class="bx bx-search fs-1 text-muted"></i><br>No matching records found</div>',
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                infoEmpty: "Showing 0 to 0 of 0 entries",
                infoFiltered: "(filtered from _MAX_ total entries)",
                search: "Search:",
                paginate: {
                    first: '<i class="bx bx-chevrons-left"></i>',
                    last: '<i class="bx bx-chevrons-right"></i>',
                    next: '<i class="bx bx-chevron-right"></i>',
                    previous: '<i class="bx bx-chevron-left"></i>'
                }
            },
            drawCallback: function (settings) {
                // Initialize tooltips
                $('[data-bs-toggle="tooltip"]').tooltip();

                // Handle select all checkbox
                handleSelectAllCheckbox(config.tableId);
            },
            ...config.extraOptions
        };

        // Add AJAX configuration if URL provided
        if (config.ajaxUrl) {
            dataTableConfig.ajax = {
                url: config.ajaxUrl,
                type: 'GET',
                headers: { 'X-CSRF-TOKEN': csrfToken },
                data: function (d) {
                    // Add custom filter parameters
                    return $.extend({}, d, getFilterData(config.tableId));
                },
                beforeSend: function () {
                    $loading.show();
                },
                complete: function () {
                    $loading.hide();
                },
                error: function (xhr, error, thrown) {
                    console.error('DataTable AJAX error:', error, thrown);
                    toastr.error('Failed to load data. Please try again.');
                    $loading.hide();
                }
            };
        }

        // Initialize the DataTable
        const table = $(selector).DataTable(dataTableConfig);

        // Store table reference globally
        window[config.tableId + '_table'] = table;

        // Bind events
        bindTableEvents(config, table);
        bindFilterEvents(config, table);
    }

    function buildButtons(config) {
        const buttons = [];

        // Export buttons
        if (config.showExport) {
            buttons.push({
                extend: 'collection',
                className: 'btn btn-sm btn-outline-secondary dropdown-toggle me-2 d-flex align-items-center gap-1',
                text: '<i class="bx bx-export"></i><span>Export</span>',
                buttons: [
                    {
                        extend: 'print',
                        text: '<i class="bx bx-printer me-1"></i>Print',
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    },
                    {
                        extend: 'csv',
                        text: '<i class="bx bx-file me-1"></i>CSV',
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    },
                    {
                        extend: 'excel',
                        text: '<i class="bx bx-spreadsheet me-1"></i>Excel',
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    },
                    {
                        extend: 'pdf',
                        text: '<i class="bx bx-file-blank me-1"></i>PDF',
                        className: 'dropdown-item',
                        exportOptions: {
                            columns: ':visible:not(:last-child)'
                        }
                    }
                ]
            });
        }

        // Column visibility button
        if (config.showColumnVisibility) {
            buttons.push({
                extend: 'colvis',
                className: 'btn btn-sm btn-outline-secondary me-2 d-flex align-items-center gap-1',
                text: '<i class="bx bx-columns"></i><span>Columns</span>'
            });
        }

        // Refresh button
        if (config.showRefresh) {
            buttons.push({
                text: '<i class="bx bx-refresh"></i><span>Refresh</span>',
                className: 'btn btn-sm btn-outline-secondary me-2 d-flex align-items-center gap-1',
                action: function (e, dt, node, config) {
                    dt.ajax.reload();
                }
            });
        }

        // Add New button
        if (config.showAddNew) {
            buttons.push({
                text: '<i class="bx bx-plus"></i><span>' + config.addNewText + '</span>',
                className: 'btn btn-sm btn-primary me-2 d-flex align-items-center gap-1',
                action: function () {
                    window.location.href = config.addNewUrl;
                }
            });
        }

        // Custom buttons
        $.each(config.customButtons || [], function (index, button) {
            buttons.push({
                text: button.text || '<i class="bx bx-cog"></i><span>Custom</span>',
                className: button.class || 'btn btn-sm btn-secondary me-2 d-flex align-items-center gap-1',
                action: function () {
                    if (button.action && typeof button.action === 'function') {
                        button.action();
                    } else if (button.url) {
                        window.location.href = button.url;
                    }
                }
            });
        });

        return buttons;
    }


    function buildColumns(config) {
        const dtColumns = [];

        // Checkbox column
        if (config.showCheckbox) {
            dtColumns.push({
                data: null,
                orderable: false,
                searchable: false,
                className: 'text-center'
            });
        }

        // Data columns
        $.each(config.columns, function (index, col) {
            dtColumns.push({
                data: col.data,
                name: col.name || col.data,
                orderable: col.orderable !== false,
                searchable: col.searchable !== false,
                className: col.className || '',
                width: col.width || null
            });
        });

        // Actions column
        dtColumns.push({
            data: null,
            orderable: false,
            searchable: false,
            className: 'text-center'
        });

        return dtColumns;
    }

    function buildColumnDefs(config) {
        const dtColumnDefs = [];

        // Checkbox column definition
        if (config.showCheckbox) {
            dtColumnDefs.push({
                targets: 0,
                render: function (data, type, row) {
                    return `<input type="checkbox" class="dt-checkboxes form-check-input" value="${row.id || ''}">`;
                }
            });
        }

        // Actions column definition
        dtColumnDefs.push({
            targets: -1,
            render: function (data, type, row) {
                return `
                    <div class="action-buttons">
                        <button class="btn btn-sm btn-outline-info view-record"
                                data-id="${row.id}"
                                data-bs-toggle="tooltip"
                                title="View">
                            <i class="bx bx-show"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-primary edit-record"
                                data-id="${row.id}"
                                data-bs-toggle="tooltip"
                                title="Edit">
                            <i class="bx bx-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-outline-danger delete-record"
                                data-id="${row.id}"
                                data-bs-toggle="tooltip"
                                title="Delete">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                `;
            }
        });

        return dtColumnDefs;
    }

    function buildDom(config) {
        let dom = '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
            '<"row"<"col-sm-12"B>>' +
            '<"row"<"col-sm-12"rt>>' +
            '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>';

        if (!config.showSearch) {
            dom = dom.replace('f', '');
        }

        return dom;
    }

    function bindTableEvents(config, table) {
        const selector = '#' + config.tableId;

        // View record event
        $(selector).on('click', '.view-record', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            $(document).trigger('datatable:view', {
                id: id,
                table: config.tableId,
                tableInstance: table
            });
        });

        // Edit record event
        $(selector).on('click', '.edit-record', function (e) {
            e.preventDefault();
            const id = $(this).data('id');
            $(document).trigger('datatable:edit', {
                id: id,
                table: config.tableId,
                tableInstance: table
            });
        });

        // Delete record event
        $(selector).on('click', '.delete-record', function (e) {
            e.preventDefault();
            const id = $(this).data('id');

            // Use SweetAlert2 if available, otherwise use confirm
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $(document).trigger('datatable:delete', {
                            id: id,
                            table: config.tableId,
                            tableInstance: table
                        });
                    }
                });
            } else if (confirm('Are you sure you want to delete this record?')) {
                $(document).trigger('datatable:delete', {
                    id: id,
                    table: config.tableId,
                    tableInstance: table
                });
            }
        });
    }

    function bindFilterEvents(config, table) {
        // Apply filters button
        $(`#apply-filters-${config.tableId}`).on('click', function () {
            table.ajax.reload();
        });

        // Clear filters button
        $(`#clear-filters-${config.tableId}`).on('click', function () {
            // Clear all filter inputs
            $.each(config.filters || [], function (index, filter) {
                $(`#${filter.id}`).val('').trigger('change');
                if (filter.type === 'date_range') {
                    $(`#${filter.id}_from`).val('');
                    $(`#${filter.id}_to`).val('');
                }
            });
            table.ajax.reload();
        });

        // Auto-apply on enter key
        $(config.filters).each(function (index, filter) {
            $(`#${filter.id}`).on('keypress', function (e) {
                if (e.which === 13) { // Enter key
                    table.ajax.reload();
                }
            });
        });
    }

    function handleSelectAllCheckbox(tableId) {
        const $selectAll = $(`#select-all-${tableId}`);
        const $checkboxes = $(`#${tableId} .dt-checkboxes`);

        $selectAll.on('change', function () {
            $checkboxes.prop('checked', this.checked);
        });

        $checkboxes.on('change', function () {
            const allChecked = $checkboxes.length === $checkboxes.filter(':checked').length;
            const noneChecked = $checkboxes.filter(':checked').length === 0;

            $selectAll.prop('checked', allChecked);
            $selectAll.prop('indeterminate', !allChecked && !noneChecked);
        });
    }

    function getFilterData(tableId) {
        const filterData = {};
        const config = window.datatablesConfig[tableId];

        if (config && config.filters) {
            $.each(config.filters, function (index, filter) {
                const $input = $(`#${filter.id}`);
                const value = $input.val();

                if (Array.isArray(value)) {
                    if (value.length > 0) {
                        filterData[filter.id] = value;
                    }
                } else if (typeof value === 'string' && value.trim() !== '') {
                    if (filter.type === 'date_range') {
                        const dates = value.split(' - ');
                        if (dates.length === 2) {
                            filterData[filter.id + '_from'] = dates[0];
                            filterData[filter.id + '_to'] = dates[1];
                        }
                    } else {
                        filterData[filter.id] = value.trim();
                    }
                }
            });
        }

        return filterData;
    }


    // Utility functions for external use
    window.datatableUtils = {
        getSelectedIds: function (tableId) {
            const ids = [];
            $(`#${tableId} .dt-checkboxes:checked`).each(function () {
                const id = $(this).val();
                if (id) ids.push(id);
            });
            return ids;
        },

        refreshTable: function (tableId) {
            const table = window[tableId + '_table'];
            if (table) {
                table.ajax.reload();
            }
        },

        reloadTable: function (tableId) {
            const table = window[tableId + '_table'];
            if (table) {
                table.ajax.reload(null, false); // Keep current page
            }
        }
    };
});
