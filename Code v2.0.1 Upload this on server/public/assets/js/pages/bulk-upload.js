// Initialize when document is ready
$(document).ready(function () {
    // Configuration
    const SELECTORS = {
        STEPS: {
            CONTENT: ['#step1-content', '#step2-content', '#step3-content'],
            TABS: ['#step1-tab', '#step2-tab', '#step3-tab']
        },
        FORMS: {
            UPLOAD: '#upload-form',
            MAPPING: '#mapping-form'
        },
        ALERTS: {
            CONTAINER: '#alert-container',
            MAPPING_ERROR: '#mapping-error-alert',
            MAPPING_SUCCESS: '#mapping-success-alert'
        },
        PREVIEWS: {
            RAW: '#raw-preview',
            MAPPED: '#mapped-preview'
        },
        BUTTONS: {
            SUBMIT: '#submit-btn',
            PREVIEW: '#preview-mapped-leads',
            BACK: '#back-to-step1',
            NEW_IMPORT: '#start-new-import'
        },
        CONTENTS: {
            FILE_SUMMARY: '#file-summary',
            MAPPING_BODY: '#mapping-body',
            MAPPING_ERROR: '#mapping-error-content',
            MAPPING_SUCCESS: '#mapping-success-content',
            RESULTS_SUMMARY: '#results-summary',
            RESULTS_DETAILS: '#results-details'
        }
    };

    // Database fields configuration
    const DB_FIELDS = [
        { name: 'first_name', required: true },
        { name: 'last_name', required: true },
        { name: 'email', required: true },
        { name: 'country_code', required: true },
        { name: 'country_iso_code', required: true },
        { name: 'phone', required: true },
        { name: 'source', required: true },
        { name: 'stage', required: true },
        { name: 'company', required: true },
        { name: 'job_title', required: false },
        { name: 'industry', required: false },
        { name: 'website', required: false },
        { name: 'linkedin', required: false },
        { name: 'instagram', required: false },
        { name: 'facebook', required: false },
        { name: 'pinterest', required: false },
        { name: 'city', required: false },
        { name: 'state', required: false },
        { name: 'zip', required: false },
        { name: 'country', required: false }
    ];

    // Constants for imported leads display
    const DISPLAY_COLUMNS = ['id', 'first_name', 'last_name', 'email', 'phone', 'company'];

    /**
     * Alert and notification functions
     */
    const notifications = {
        // Show general alert
        showAlert: function (type, message) {
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            `;
            $(SELECTORS.ALERTS.CONTAINER).html(alertHtml);
        },

        // Show section error
        showSectionError: function (elementId, contentId, errorData) {
            $(`#${contentId}`).html('');

            if (typeof errorData === 'string') {
                $(`#${contentId}`).html(`<p>${errorData}</p>`);
            } else {
                let errorHtml = '<ul class="mb-0">';

                // Process different error data structures
                if (Array.isArray(errorData)) {
                    errorData.forEach(err => {
                        errorHtml += `<li>${err}</li>`;
                    });
                } else if (typeof errorData === 'object') {
                    // For row-specific errors
                    for (const [row, errors] of Object.entries(errorData)) {
                        errorHtml += `<li><strong>${row}:</strong><ul>`;

                        if (typeof errors === 'object') {
                            for (const [field, fieldErrors] of Object.entries(errors)) {
                                if (Array.isArray(fieldErrors)) {
                                    fieldErrors.forEach(err => {
                                        errorHtml += `<li>${field}: ${err}</li>`;
                                    });
                                } else {
                                    errorHtml += `<li>${field}: ${fieldErrors}</li>`;
                                }
                            }
                        } else {
                            errorHtml += `<li>${errors}</li>`;
                        }

                        errorHtml += `</ul></li>`;
                    }
                }

                errorHtml += '</ul>';
                $(`#${contentId}`).html(errorHtml);
            }

            $(`#${elementId}`).removeClass('d-none');
        },

        // Show section success
        showSectionSuccess: function (elementId, contentId, message) {
            $(`#${contentId}`).html(message);
            $(`#${elementId}`).removeClass('d-none');
        }
    };

    /**
     * Navigation functions
     */
    const navigation = {
        // Navigate between steps
        goToStep: function (stepNumber) {
            // Hide all steps
            $(SELECTORS.STEPS.CONTENT.join(', ')).addClass('d-none');
            $(SELECTORS.STEPS.TABS.join(', ')).removeClass('active').addClass('disabled');

            // Show the selected step
            $(`#step${stepNumber}-content`).removeClass('d-none');
            $(`#step${stepNumber}-tab`).removeClass('disabled').addClass('active');

            // Enable previous steps
            for (let i = 1; i < stepNumber; i++) {
                $(`#step${i}-tab`).removeClass('disabled');
            }
        },

        // Reset everything for a new import
        resetImport: function () {
            $(SELECTORS.FORMS.UPLOAD)[0].reset();
            $(SELECTORS.FORMS.MAPPING)[0].reset();
            $(SELECTORS.PREVIEWS.RAW + ', ' + SELECTORS.PREVIEWS.MAPPED + ', ' +
                SELECTORS.CONTENTS.RESULTS_SUMMARY + ', ' + SELECTORS.CONTENTS.RESULTS_DETAILS).html('');
            $(SELECTORS.ALERTS.MAPPING_ERROR + ', ' + SELECTORS.ALERTS.MAPPING_SUCCESS).addClass('d-none');
            $(SELECTORS.ALERTS.CONTAINER).html('');
            navigation.goToStep(1);
        }
    };

    /**
     * Data preview functions
     */
    const preview = {
        // Display file summary
        updateFileSummary: function (data) {
            $(SELECTORS.CONTENTS.FILE_SUMMARY).html(`
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${label_file_processed}</strong><br>
                        ${label_total_rows}: ${data.total_rows} |
                        ${label_showing_preview.replace('${count}', data.rows.length).replace('${total}', data.total_rows)}
                    </div>
                </div>
            `);
        },

        // Generate field mappings UI
        generateFieldMappings: function (headers, dbFields) {
            console.log('Excel headers found:', headers); // Debug: see your actual headers
            let options = headers.map(h => `<option value="${h}">${h}</option>`).join('');
            let html = '';

            dbFields.forEach(field => {
                const requiredMark = field.required ? '<span class="text-danger">*</span>' : '';
                html += `
                    <tr>
                        <td class="fw-semibold">${field.name} ${requiredMark}</td>
                        <td>
                            <select class="form-select form-select-sm mapping-select" name="mapping[${field.name}]" ${field.required ? 'required' : ''}>
                                <option value="">${label_select_option}</option>
                                ${options}
                            </select>
                        </td>
                    </tr>
                `;
            });

            $(SELECTORS.CONTENTS.MAPPING_BODY).html(html);

            // Initialize Select2 with proper configuration
            $('.mapping-select').select2({
                width: '100%',
                dropdownAutoWidth: true,
                dropdownParent: $(SELECTORS.CONTENTS.MAPPING_BODY),
                containerCssClass: 'mapping-select2-container',
                dropdownCssClass: 'mapping-select2-dropdown'
            }).on('select2:open', function () {
                // Ensure proper positioning of dropdown
                setTimeout(function () {
                    $('.select2-dropdown').css('width', 'auto');
                }, 0);
            });

            preview.autoMatchFields(headers, dbFields);
        },

        // Auto-match fields based on name similarity
        autoMatchFields: function (headers, dbFields) {
            // Create a mapping of common field variations
            const fieldMappings = {
                'first_name': ['first name', 'firstname', 'first_name', 'fname', 'given name', 'givenname'],
                'last_name': ['last name', 'lastname', 'last_name', 'lname', 'surname', 'family name', 'familyname'],
                'email': ['email', 'email address', 'e-mail', 'mail'],
                'phone': ['phone', 'phone number', 'mobile', 'cell', 'telephone', 'tel'],
                'company': ['company', 'organization', 'org', 'business', 'employer'],
                'job_title': ['job title', 'jobtitle', 'job_title', 'title', 'position', 'role', 'designation'],
                'country_code': ['country code', 'countrycode', 'country_code', 'cc', 'dial code'],
                'country_iso_code': ['country iso', 'iso code', 'country_iso_code', 'iso', 'country iso code'],
                'source': ['source', 'lead source', 'source id'],
                'stage': ['stage', 'lead stage', 'stage id', 'status'],
                'industry': ['industry', 'sector', 'business type'],
                'website': ['website', 'web', 'url', 'site'],
                'linkedin': ['linkedin', 'linkedin url', 'linkedin profile'],
                'instagram': ['instagram', 'instagram url', 'instagram profile'],
                'facebook': ['facebook', 'facebook url', 'facebook profile'],
                'pinterest': ['pinterest', 'pinterest url', 'pinterest profile'],
                'city': ['city', 'town', 'locality'],
                'state': ['state', 'province', 'region'],
                'zip': ['zip', 'postal code', 'zipcode', 'postcode', 'zip code'],
                'country': ['country', 'nation']
            };

            // First, try exact matches
            headers.forEach(header => {
                const cleanHeader = header.toLowerCase().trim();
                dbFields.forEach(field => {
                    const fieldName = field.name.toLowerCase();
                    if (cleanHeader === fieldName) {
                        const select = $(`select[name="mapping[${field.name}]"]`);
                        if (select.val() === '') { // Only set if not already matched
                            select.val(header);
                            select.trigger('change');
                        }
                    }
                });
            });

            // Then try fuzzy matching using the field mappings
            headers.forEach(header => {
                const cleanHeader = header.toLowerCase().trim();

                for (const [dbField, variations] of Object.entries(fieldMappings)) {
                    const select = $(`select[name="mapping[${dbField}]"]`);

                    // Skip if already matched
                    if (select.val() !== '') continue;

                    // Check if header matches any variation
                    const isMatch = variations.some(variation => {
                        return cleanHeader === variation ||
                            cleanHeader.includes(variation) ||
                            variation.includes(cleanHeader) ||
                            this.isSimilarString(cleanHeader, variation);
                    });

                    if (isMatch) {
                        select.val(header);
                        select.trigger('change');
                        break; // Move to next header once matched
                    }
                }
            });
        },

        // Helper function to check string similarity
        isSimilarString: function (str1, str2) {
            // Remove spaces and special characters for comparison
            const clean1 = str1.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();
            const clean2 = str2.replace(/[^a-zA-Z0-9]/g, '').toLowerCase();

            // Check if one string contains the other (with at least 3 characters)
            if (clean1.length >= 3 && clean2.length >= 3) {
                return clean1.includes(clean2) || clean2.includes(clean1);
            }

            return false;
        },

        // Show raw data preview
        showRawDataPreview: function (data) {
            let previewTable = '<table class="table table-bordered table-sm"><thead><tr>';
            data.headers.forEach(header => {
                previewTable += `<th>${header}</th>`;
            });
            previewTable += '</tr></thead><tbody>';

            data.rows.forEach(row => {
                previewTable += '<tr>';
                row.forEach(cell => {
                    previewTable += `<td>${cell || '-'}</td>`;
                });
                previewTable += '</tr>';
            });

            previewTable += '</tbody></table>';
            $(SELECTORS.PREVIEWS.RAW).html(previewTable);
        },

        // Generate preview toggle UI
        addRawPreviewToggle: function () {
            const rawPreviewToggle = `
                <div class="mb-2 small text-muted d-flex justify-content-between align-items-center">
                    <span></span>
                    <button class="btn btn-link btn-sm p-0 toggle-preview" data-showing="raw">${label_show_mapped_data}</button>
                </div>
            `;
            $(SELECTORS.PREVIEWS.RAW).prepend(rawPreviewToggle);
        },

        // Generate mapped data preview table
        generatePreviewTable: function (data) {
            let table = '<table class="table table-bordered table-sm"><thead><tr>';

            if (data.mapped_data.length > 0) {
                Object.keys(data.mapped_data[0]).forEach(key => {
                    table += `<th>${key}</th>`;
                });
            }

            table += '</tr></thead><tbody>';

            data.mapped_data.forEach(row => {
                table += '<tr>';
                for (let key in row) {
                    table += `<td>${row[key] || '-'}</td>`;
                }
                table += '</tr>';
            });

            table += '</tbody></table>';

            return `
                <div class="mb-2 small text-muted d-flex justify-content-between align-items-center">
                    <span>${label_showing_preview.replace('${count}', data.mapped_data.length).replace('${total}', data.total_rows)}</span>
                    <button class="btn btn-link btn-sm p-0 toggle-preview" data-showing="mapped">${label_show_raw_data}</button>
                </div>
                ${table}
            `;
        },

        // Setup preview toggle functionality
        setupPreviewToggle: function () {
            $(document).off('click', '.toggle-preview');
            $(document).on('click', '.toggle-preview', function (e) {
                e.preventDefault();
                const showing = $(this).data('showing');

                if (showing === 'mapped') {
                    $(SELECTORS.PREVIEWS.MAPPED).addClass('d-none');
                    $(SELECTORS.PREVIEWS.RAW).removeClass('d-none');
                } else {
                    $(SELECTORS.PREVIEWS.RAW).addClass('d-none');
                    $(SELECTORS.PREVIEWS.MAPPED).removeClass('d-none');
                }
            });
        }
    };

    /**
     * Import results and reporting functions
     */
    const importResults = {
        // Generate imported leads table
        generateImportedLeadsTable: function (leads) {
            let html = `<h6>${label_imported_leads}</h6>`;
            html += '<div class="table-responsive"><table class="table table-sm table-bordered">';

            // Headers
            html += '<thead><tr>';
            DISPLAY_COLUMNS.forEach(col => {
                if (leads[0].hasOwnProperty(col)) {
                    html += `<th>${col}</th>`;
                }
            });
            html += '</tr></thead>';

            // Rows
            html += '<tbody>';
            leads.forEach(lead => {
                html += '<tr>';
                DISPLAY_COLUMNS.forEach(col => {
                    if (lead.hasOwnProperty(col)) {
                        html += `<td>${lead[col] || '-'}</td>`;
                    }
                });
                html += '</tr>';
            });
            html += '</tbody></table></div>';

            return html;
        },

        // Generate partial import summary
        generatePartialImportSummary: function (response) {
            return `
                <div class="alert alert-warning">
                    <h6 class="alert-heading">${label_import_partially_completed}</h6>
                    <p>${response.message}</p>
                    <hr>
                    <p class="mb-0">
                        ${label_successfully_imported}: ${response.data.successful} leads<br>
                        Failed records: ${response.data.failed} leads<br>
                        Total records processed: ${response.data.total} leads
                    </p>
                </div>
            `;
        },

        // Generate error details
        generateErrorDetails: function (failedRows) {
            if (!failedRows?.length) return `<p>${label_no_detailed_error_information_available}</p>`;

            let html = `<h6 class="text-danger">${label_import_errors}</h6><div class="error-list">`;

            failedRows.forEach(row => {
                html += `
                    <div class="alert alert-danger mb-3">
                        <strong>Row ${row.row}</strong>
                        <ul class="list-unstyled mb-0 mt-2">
                            ${Object.entries(row.errors).map(([field, messages]) =>
                    `<li>• ${field}: ${Array.isArray(messages) ? messages.join(', ') : messages}</li>`
                ).join('')}
                        </ul>
                    </div>
                `;
            });

            return html + '</div>';
        }
    };

    /**
     * Ajax handlers
     */
    const ajaxHandlers = {
        // Handle file parse success
        handleParseSuccess: function (response) {
            $('#temp_path').val(response.data.temp_path);
            preview.updateFileSummary(response.data);
            preview.generateFieldMappings(response.data.headers, DB_FIELDS);
            preview.showRawDataPreview(response.data);
            navigation.goToStep(2);
        },

        // Handle preview success
        handlePreviewSuccess: function (response) {
            $(SELECTORS.PREVIEWS.RAW).addClass('d-none');
            $(SELECTORS.PREVIEWS.MAPPED).removeClass('d-none');

            const previewHtml = preview.generatePreviewTable(response.data);
            $(SELECTORS.PREVIEWS.MAPPED).html(previewHtml);

            if (!$(SELECTORS.PREVIEWS.RAW).find('.toggle-preview').length) {
                preview.addRawPreviewToggle();
            }

            $(SELECTORS.BUTTONS.SUBMIT).prop('disabled', false);
            notifications.showSectionSuccess(
                'mapping-success-alert',
                'mapping-success-content',
                label_data_mapped_success
            );

            preview.setupPreviewToggle();
        },

        // Handle import success
        handleImportSuccess: function (response) {
            const summary = `
                <div class="alert alert-success">
                    <h6 class="alert-heading">
                    ${label_import_success}
                    </h6>
                    <p>${response.message}</p>
                    <hr>
                    <p class="mb-0">${label_successfully_imported}: ${response.data.total} leads</p>
                </div>
            `;

            $(SELECTORS.CONTENTS.RESULTS_SUMMARY).html(summary);

            if (response.data.imported_leads?.length > 0) {
                $(SELECTORS.CONTENTS.RESULTS_DETAILS).html(importResults.generateImportedLeadsTable(response.data.imported_leads));
            } else {
                $(SELECTORS.CONTENTS.RESULTS_DETAILS).html('');
            }

            navigation.goToStep(3);
            notifications.showAlert('success', response.message);
        },

        // Handle import failure
        handleImportFailure: function (response) {
            if (response.data?.successful) {
                ajaxHandlers.handlePartialImport(response);
            } else {
                notifications.showSectionError(
                    'mapping-error-alert',
                    'mapping-error-content',
                    response.message || 'Failed to import leads.'
                );
            }
        },

        // Handle partial import
        handlePartialImport: function (response) {
            const summaryHtml = importResults.generatePartialImportSummary(response);
            const errorDetailsHtml = importResults.generateErrorDetails(response.data.failed_rows);

            $(SELECTORS.CONTENTS.RESULTS_SUMMARY).html(summaryHtml);
            $(SELECTORS.CONTENTS.RESULTS_DETAILS).html(errorDetailsHtml);
            navigation.goToStep(3);
            notifications.showAlert('warning', response.message);
        },

        // Handle import error
        handleImportError: function (response) {
            if (response.data?.failed_rows) {
                ajaxHandlers.handlePartialImport(response);
            } else {
                notifications.showAlert('danger', response.message || 'Error importing leads. Please try again.');
            }
        }
    };

    /**
     * Event Handlers - Using more specific event delegation
     */

    // Handle file upload form submission
    $(document).on('submit', SELECTORS.FORMS.UPLOAD, function (e) {
        e.preventDefault();
        let formData = new FormData(this);

        // Show loading indicator
        $(this).find('button[type="submit"]')
            .html(`<i class="bx bx-loader bx-spin me-1"></i>${label_uploading}`)
            .prop('disabled', true);

        // Reset any existing alerts
        $(SELECTORS.ALERTS.CONTAINER).html('');
        $(SELECTORS.ALERTS.MAPPING_ERROR + ', ' + SELECTORS.ALERTS.MAPPING_SUCCESS).addClass('d-none');

        $.ajax({
            type: 'POST',
            url: routes.parse,
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    ajaxHandlers.handleParseSuccess(response);
                } else {
                    notifications.showAlert('danger', response.message || 'Failed to parse file.');
                }
            },
            error: function (xhr) {
                const response = xhr.responseJSON || {};
                notifications.showAlert('danger', response.message || 'Error uploading file. Please try again.');
            },
            complete: function () {
                // Reset button state
                $(SELECTORS.FORMS.UPLOAD).find('button[type="submit"]')
                    .html(`<i class="bx bx-upload me-1"></i>${label_upload_and_continue}`)
                    .prop('disabled', false);
            }
        });
    });

    // Preview mapped leads - Using event delegation to ensure it works with dynamically added elements
    $(document).on('click', SELECTORS.BUTTONS.PREVIEW, function (e) {
        e.preventDefault();
        console.log('Preview button clicked'); // Debug log

        $(this).html(`<i class="bx bx-loader bx-spin me-1"></i>${label_processing}`).prop('disabled', true);
        $(SELECTORS.ALERTS.MAPPING_ERROR + ', ' + SELECTORS.ALERTS.MAPPING_SUCCESS).addClass('d-none');

        let mappings = {};
        $('select[name^="mapping"]').each(function () {
            let dbField = $(this).attr('name').split('[')[1].split(']')[0];
            mappings[dbField] = $(this).val();
        });

        const tempPath = $('#temp_path').val();
        console.log('Mappings:', mappings); // Debug log
        console.log('Temp path:', tempPath); // Debug log

        $.ajax({
            type: 'POST',
            url: routes.previewMappedLeads,
            data: {
                mapping: mappings,
                temp_path: tempPath,
                _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
            },
            success: function (response) {
                console.log('Preview response:', response); // Debug log
                if (response.success) {
                    ajaxHandlers.handlePreviewSuccess(response);
                } else {
                    notifications.showSectionError(
                        'mapping-error-alert',
                        'mapping-error-content',
                        response.message || 'Failed to map data.'
                    );
                }
            },
            error: function (xhr) {
                console.log('Preview error:', xhr); // Debug log
                const response = xhr.responseJSON || {};
                notifications.showSectionError(
                    'mapping-error-alert',
                    'mapping-error-content',
                    response.message || 'Error generating preview. Please check your field mappings.'
                );
            },
            complete: function () {
                $(SELECTORS.BUTTONS.PREVIEW)
                    .html(`<i class="bx bx-search me-1"></i>${label_preview_mapped_leads}`)
                    .prop('disabled', false);
            }
        });
    });

    // Handle import form submission - FIXED: Changed from form selector to document delegation
    $(document).on('submit', SELECTORS.FORMS.MAPPING, function (e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent event bubbling

        console.log('Import form submitted'); // Debug log

        const $submitBtn = $(SELECTORS.BUTTONS.SUBMIT);
        $submitBtn.html(`<i class="bx bx-loader bx-spin me-1"></i>${label_importing}`).prop('disabled', true);
        $(SELECTORS.ALERTS.MAPPING_ERROR + ', ' + SELECTORS.ALERTS.MAPPING_SUCCESS).addClass('d-none');

        // Get form data
        const formData = $(this).serialize();
        console.log('Form data:', formData); // Debug log

        $.ajax({
            type: 'POST',
            url: routes.import,
            data: formData,
            success: function (response) {
                console.log('Import response:', response); // Debug log
                if (response.success) {
                    ajaxHandlers.handleImportSuccess(response);
                } else {
                    ajaxHandlers.handleImportFailure(response);
                }
            },
            error: function (xhr) {
                console.log('Import error:', xhr); // Debug log
                ajaxHandlers.handleImportError(xhr.responseJSON || {});
            },
            complete: function () {
                // Fixed: Use correct label variable
                $submitBtn
                    .html(`<i class="bx bx-import me-1"></i>Import Data`)
                    .prop('disabled', false);
            }
        });
    });

    // Event handlers for navigation - Using event delegation
    $(document).on('click', SELECTORS.BUTTONS.BACK, function (e) {
        e.preventDefault();
        navigation.goToStep(1);
    });

    $(document).on('click', SELECTORS.BUTTONS.NEW_IMPORT, function (e) {
        e.preventDefault();
        navigation.resetImport();
    });

    // Add form validation to prevent empty submissions
    $(document).on('click', SELECTORS.BUTTONS.SUBMIT, function (e) {
        const form = $(SELECTORS.FORMS.MAPPING)[0];
        if (!form.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
            form.classList.add('was-validated');
            notifications.showSectionError(
                'mapping-error-alert',
                'mapping-error-content',
                'Please fill in all required fields before importing.'
            );
            return false;
        }
    });

    // Initialize
    navigation.goToStep(1);
});
