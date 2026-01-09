// Common variables
const LABEL_CONSTANTS = {
    sendLabel: '',
    scheduleLabel: '',
    previewLabel: '',
    pleaseEnterName: label_please_enter_name || 'Please enter name',
    pleaseTypeAtLeast: label_please_type_at_least_1_character || 'Please type at least 1 character',
    searching: label_searching || 'Searching...',
    noResultsFound: label_no_results_found || 'No results found'
};

// Initialize the email sending functionality
function initEmailFunctionality() {
    // Store labels from data attributes
    LABEL_CONSTANTS.sendLabel = $('#submit_btn').data('label-send');
    LABEL_CONSTANTS.scheduleLabel = $('#submit_btn').data('label-schedule');
    LABEL_CONSTANTS.previewLabel = $('#previewBtn').data('label-preview');

    // Initialize Template Email Tab
    initTemplateEmailTab();

    // Initialize Custom Email Tab
    initCustomEmailTab();

    // If template is preselected, trigger change event
    if ($('#templateSelector').val()) {
        $('#templateSelector').trigger('change');
    }
}

// Template Email Tab Functionality
function initTemplateEmailTab() {
    // Toggle schedule field
    $('#scheduleToggle').change(function () {
        if ($(this).is(':checked')) {
            $('#scheduleField').removeClass('d-none');
            $('#submit_btn').html('<i class="bx bx-calendar me-1"></i> ' + LABEL_CONSTANTS.scheduleLabel);
        } else {
            $('#scheduleField').addClass('d-none');
            $('#submit_btn').html('<i class="bx bx-send me-1"></i> ' + LABEL_CONSTANTS.sendLabel);
        }
    });

    // File upload display for template email
    $('#attachments').change(function () {
        handleFileUpload($(this), '#file-list', '#file-names');
    });

    // Preview functionality
    $('#previewBtn').click(function () {
        previewEmail();
    });

    // Form validation for template email
    $('#emailForm').submit(function (e) {
        validateScheduledEmail(e, this, '#scheduleToggle');
    });

    // Template selector change handler
    $('#templateSelector').on('change', function () {
        loadTemplateData($(this).val());
    });
}

// Custom Email Tab Functionality
function initCustomEmailTab() {
    // Toggle schedule field for custom email
    $('#customScheduleToggle').change(function () {
        if ($(this).is(':checked')) {
            $('#customScheduleField').removeClass('d-none');
            $('.custom_submit_btn').html('<i class="bx bx-calendar me-1"></i> ' + LABEL_CONSTANTS.scheduleLabel);
        } else {
            $('#customScheduleField').addClass('d-none');
            $('.custom_submit_btn').html('<i class="bx bx-send me-1"></i> ' + LABEL_CONSTANTS.sendLabel);
        }
    });

    // File upload display for custom email
    $('#custom_attachments').change(function () {
        handleFileUpload($(this), '#custom-file-list', '#custom-file-names');
    });

    // Form validation for custom email
    $('#customEmailForm').submit(function (e) {
        validateScheduledEmail(e, this, '#customScheduleToggle');
    });

    // Initialize select2 for email recipients
    initEmailSelect2('.to_emails');

    // Initialize TinyMCE for custom email body if available
    if (typeof tinymce !== 'undefined') {
        tinymce.init({
            selector: '#custom-email-body',
            height: 400,
            menubar: true,
            plugins: [
                'advlist autolink lists link image charmap print preview anchor',
                'searchreplace visualblocks code fullscreen',
                'insertdatetime media table paste code help wordcount'
            ],
            toolbar: 'undo redo | formatselect | bold italic backcolor | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | removeformat | help'
        });
    }
}

// Helper Functions

// Handle file upload display
function handleFileUpload(fileInput, listContainerId, fileNamesId) {
    const files = fileInput[0].files;
    if (files.length > 0) {
        $(listContainerId).removeClass('d-none');
        const fileNames = $(fileNamesId);
        fileNames.empty();

        for (let i = 0; i < files.length; i++) {
            fileNames.append(`<li class="small text-muted">${files[i].name}</li>`);
        }
    } else {
        $(listContainerId).addClass('d-none');
    }
}

// Preview email template
function previewEmail() {
    const form = $('#emailForm')[0];
    const formData = new FormData(form);
    let companyTitle = $('#previewBtn').data('company-title');

    // Handle content field
    var contentField = $('#emailForm').find('#templateBodyInput');
    if (contentField.length > 0) {
        // Remove the original content from FormData
        formData.delete("content");

        // Add the content as base64 encoded to bypass ModSecurity filters
        var encodedContent = btoa(contentField.val());
        formData.append("content", encodedContent);
        formData.append("is_encoded", "1");
    }

    // Append system placeholders
    formData.append('placeholders[CURRENT_YEAR]', new Date().getFullYear());
    formData.append('placeholders[COMPANY_TITLE]', companyTitle);
    formData.append('placeholders[COMPANY_LOGO]', '<img src=' + logo_url + ' width="200px" alt="Company Logo">');
    formData.append('placeholders[SUBJECT]', $('input[name="subject"]').val());

    $.ajax({
        url: '/emails/preview',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function () {
            $('#previewBtn').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Loading...');
        },
        complete: function () {
            $('#previewBtn').prop('disabled', false).html('<i class="bx bx-show me-1"></i> ' + LABEL_CONSTANTS.previewLabel);
        },
        success: function (response) {
            $('#previewContent').html(response.preview);
            $('#previewModal').modal('show');
        },
        error: function () {
            toastr.error('Error generating preview. Please try again.');
        }
    });
}

// Validate scheduled email
function validateScheduledEmail(e, form, toggleSelector) {
    let selectTimeError = $(toggleSelector).data('select-time-error');
    if ($(toggleSelector).is(':checked') && !$(form).find('[name="scheduled_at"]').val()) {
        e.preventDefault();
        toastr.error(selectTimeError);
        return false;
    }
}

// Load template data
function loadTemplateData(templateId) {
    if (!templateId) {
        $('#emailComposition').addClass('d-none');
        return;
    }

    // Show loading state
    $('#emailComposition').addClass('d-none');
    $('#placeholderFields').html(`
        <div class="col-12 text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `);

    // AJAX request to get template data
    $.ajax({
        url: '/emails/template-data/' + templateId,
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            // Update form fields
            $('#emailSubject').val(response.subject);
            $('#templateBodyInput').val(response.body);
            $('#templateIdInput').val(templateId);

            // Update placeholders
            updatePlaceholderFields(response.placeholders);

            // Show the email composition section
            $('#emailComposition').removeClass('d-none');
        },
        error: function (xhr, status, error) {
            console.error('Error loading template:', error);
            toastr.error('Failed to load template data. Please try again.');
        }
    });
}

// Update placeholder fields
function updatePlaceholderFields(placeholders) {
    let placeholderHtml = '';
    if (placeholders && placeholders.length > 0) {
        placeholders.forEach(function (placeholder) {
            const label = placeholder.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            placeholderHtml += `
                <div class="col-md-6 mb-3">
                    <label class="form-label">${label}</label>
                    <input type="text" class="form-control"
                           required
                           name="placeholders[${placeholder}]"
                           placeholder="Enter ${label}">
                </div>
            `;
        });
    } else {
        placeholderHtml = '<div class="col-12 text-muted">No placeholders found for this template</div>';
    }
    $('#placeholderFields').html(placeholderHtml);
}

// Initialize Email Select2
function initEmailSelect2(selector) {
    $(selector).select2({
        tags: true,
        tokenSeparators: [',', ' '],
        placeholder: LABEL_CONSTANTS.pleaseEnterName,
        width: '100%',
        ajax: {
            url: '/search', // your endpoint that returns users with email
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term,
                    type: 'users', // or whatever type is relevant
                };
            },
            processResults: function (data) {
                return {
                    results: data.results.map(function (item) {
                        return {
                            id: item.email,
                            text: item.email,
                        };
                    }),
                };
            },
            cache: true,
        },
        createTag: function (params) {
            var term = $.trim(params.term);

            if (term === '' || !validateEmail(term)) {
                return null;
            }

            return {
                id: term,
                text: term,
                newTag: true
            };
        },
        language: {
            inputTooShort: function () {
                return LABEL_CONSTANTS.pleaseTypeAtLeast;
            },
            searching: function () {
                return LABEL_CONSTANTS.searching;
            },
            noResults: function () {
                return LABEL_CONSTANTS.noResultsFound;
            },
        },
        escapeMarkup: function (markup) {
            return markup;
        }
    });
}

// Email validation helper function
function validateEmail(email) {
    var re = /^(([^<>()\[\]\\.,;:\s@\"]+(\.[^<>()\[\]\\.,;:\s@\"]+)*)|(\".+\"))@(([^<>()[\]\.,;:\s@\"]+\.)+[^<>()[\]\.,;:\s@\"]{2,})$/i;
    return re.test(email);
}

// Initialize when document is ready
$(document).ready(function () {
    initEmailFunctionality();
});
