// For searching in table
function queryParams(params) {
    const query = {
        limit: params.limit,
        offset: params.offset,
        search: params.search,
        sort: $('#sort').val(),
        order: params.order,
        categories: $('#select_categories').val(),
        assigned_to: $('#select_assigned_to').val(),
        asset_status: $('#asset_status').val(),
    };
    return query;
}

$(document).ready(function () {
    $('#select_categories').on('change', function () {
        $('#table').bootstrapTable('refresh');
    });

    $('#select_assigned_to').on('change', function () {
        $('#table').bootstrapTable('refresh');
    });

    $('#asset_status').on('change', function () {
        $('#table').bootstrapTable('refresh');
    });
});

// Modal event handlers
$(document).on('click', '#createCategoryModalBtn', function () {
    $('#createCategoryModal').modal('show');
});

$(document).on('click', '#bulkAssignModalBtn', function () {
    $('#bulkAssignModal').modal('show');
});

$(document).on('click', '#bulkAssetsUploadModalBtn', function () {
    $('#bulkAssetsUploadModal').modal('show');
});

// For duplicating asset
$(document).on('click', '.duplicateAsset', function () {
    const asset = $(this).data('asset');
    const actionUrl = `/assets/duplicate/${asset.id}`;

    $('#duplicateForm').attr('action', actionUrl);
    $('#duplicateAssetModal').modal('show');
});

// For showing and filling update asset category modal
$(document).on('click', '.updateCategoryModal', function () {
    const assetCategory = $(this).data('asset-category');
    const color = assetCategory.color;
    const actionUrl = `/assets/category/update/${assetCategory.id}`;

    $('#updateCategoryForm').attr('action', actionUrl);
    $('#categoryName').val(assetCategory.name);
    $('#categoryDescription').val(assetCategory.description);

    $('#category_color').val(color).change();

    $('#category_color')
        .removeClass('select-bg-label-primary select-bg-label-secondary select-bg-label-success select-bg-label-danger select-bg-label-warning select-bg-label-info select-bg-label-dark')
        .addClass(`select-bg-label-${color}`);

    $('#updateCategoryModal').modal('show');
});

// Show create modal
$(document).on('click', '#createAssetModalBtn', function () {
    $('#assetForm')[0].reset();
    resetImagePreview('create');
    $('#createAssetModal').modal('show');
});

// Show update modal
$(document).on('click', '.updateAssetModalBtn', function () {
    const asset = $(this).data('asset');

    $('#update-asset-name').val(asset.name);
    $('#update-asset-tag').val(asset.asset_tag);
    $('#update-asset-category').val(asset.category_id).trigger('change');
    $('#update-asset-assign-to').val(asset.assigned_to).trigger('change');
    $('#update-asset-purchase-date').val(asset.purchase_date);
    $('#update-asset-purchase-cost').val(asset.purchase_cost);
    $('#update-asset-description').val(asset.description);

    $('#updateAssetForm').attr('action', `/assets/update/${asset.id}`);

    if (asset.picture_url) {
        showImagePreview('update', asset.picture_url);
    } else {
        resetImagePreview('update');
    }

    if (asset.status == 'lent') {
        $('#update_asset_status_field').hide();
        $('#update-lent-status').remove();
        $('#updateAssetForm').append(`<input type="hidden" id="update-lent-status" name="status" value="${asset.status}">`);
        $('#update-asset-status').removeAttr('name');
    } else {
        $('#update_asset_status_field').show();
        $('#update-lent-status').remove();
        $('#update-asset-status').attr('name', 'status').val(asset.status);
    }

    $('#update-asset-picture').val('');
    $('#updateAssetModal').modal('show');
});

// Handle file input change for both modals
$(document).on('change', '.asset-picture-input', function () {
    const file = this.files[0];
    const modal = $(this).data('modal');

    if (file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            showImagePreview(modal, e.target.result);
        };
        reader.readAsDataURL(file);
    }
});

// Open full image in lightbox
$(document).on('click', '.open-full-image-btn', function () {
    const targetImg = $(this).data('target');
    const imgSrc = $(`#${targetImg}`).attr('src');

    if (imgSrc) {
        $('#lightboxImage').attr('src', imgSrc);
        $('#imageLightboxModal').modal('show');
    }
});

// Also allow clicking on image to open lightbox
$(document).on('click', '#create-preview-image, #update-preview-image', function () {
    const imgSrc = $(this).attr('src');
    if (imgSrc) {
        $('#lightboxImage').attr('src', imgSrc);
        $('#imageLightboxModal').modal('show');
    }
});

// Remove image functionality
$(document).on('click', '.remove-image-btn', function () {
    const modal = $(this).data('modal');
    resetImagePreview(modal);
    $(`#${modal}-asset-picture`).val('');
    $('#update_remove_picture').val(1);
});

// Hover effect for image preview
$(document).on('mouseenter', '#create-preview-image, #update-preview-image', function () {
    $(this).siblings('.hover-overlay').removeClass('opacity-0').addClass('opacity-100');
});

$(document).on('mouseleave', '#create-preview-image, #update-preview-image', function () {
    $(this).siblings('.hover-overlay').removeClass('opacity-100').addClass('opacity-0');
});

// Reset modal forms when they are hidden
$(document).on('hidden.bs.modal', '#createAssetModal', function () {
    $('#assetForm')[0].reset();
    resetImagePreview('create');
});

$(document).on('hidden.bs.modal', '#updateAssetModal', function () {
    $('#updateAssetForm')[0].reset();
    resetImagePreview('update');
});

// Helper functions
function showImagePreview(modal, src) {
    $(`#${modal}-preview-image`).attr('src', src);
    $(`#${modal}-current-picture-preview`).show();
    $(`#${modal}-image-actions`).removeClass('d-none');
    $(`#${modal}-no-image-placeholder`).hide();
}

function resetImagePreview(modal) {
    $(`#${modal}-current-picture-preview`).hide();
    $(`#${modal}-image-actions`).addClass('d-none');
    $(`#${modal}-no-image-placeholder`).show();
    $(`#${modal}-preview-image`).attr('src', '');
}

// Lend Asset Form Submission
const lendAssetForm = document.getElementById('lendAssetForm');
if (lendAssetForm) {
    lendAssetForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            const formData = new FormData(this);
            const assetId = formData.get('asset_id');

            const response = await fetch(`/assets/${assetId}/lend`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lent_to: formData.get('assigned_to'),
                    estimated_return_date: formData.get('estimated_return_date'),
                    notes: formData.get('notes')
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'An error occurred while lending the asset.');
            }

            toastr.success(data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('lendAssetModal'));
            modal.hide();
            setTimeout(() => location.reload(), 1500);
        } catch (error) {
            toastr.error(error.message);
        }
    });
}

// Return Asset Form Submission
const returnAssetForm = document.getElementById('returnAssetForm');
if (returnAssetForm) {
    returnAssetForm.addEventListener('submit', async function (e) {
        e.preventDefault();

        try {
            const formData = new FormData(this);
            const assetId = formData.get('asset_id');

            const response = await fetch(`/assets/${assetId}/return`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    notes: formData.get('notes')
                })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'An error occurred while returning the asset.');
            }

            toastr.success(data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('returnAssetModal'));
            modal.hide();
            setTimeout(() => { window.location.href = '/assets/index' }, 1500);
        } catch (error) {
            toastr.error(error.message);
        }
    });
}

// Set minimum date for estimated return date
document.addEventListener('DOMContentLoaded', function () {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const estimatedReturnDateInput = document.getElementById('estimated_return_date');
    if (estimatedReturnDateInput) {
        estimatedReturnDateInput.min = tomorrow.toISOString().slice(0, 16);
    }
});

// Index page ready handler
$(document).ready(function () {
    $('#asset_status').select2({
        placeholder: 'Filter by Statuses',
        allowClear: true
    });
});

// Custom function for select2
function initAssetSelect2(selector, type) {
    $(selector).each(function () {
        if (!$(this).length) return;

        var $this = $(this);
        var allowClear = $this.data("allow-clear") !== "false";
        var singleSelect = $this.data("single-select") !== false;

        $this.select2({
            data: $this.find('option').map(function () {
                return {
                    id: this.value,
                    text: $(this).text(),
                };
            }).get(),
            ajax: {
                url: "/assets/search-assets",
                dataType: "json",
                delay: 250,
                data: function (params) {
                    return {
                        q: params.term,
                        type: type
                    };
                },
                processResults: function (data) {
                    return {
                        results: data.results.map(function (item) {
                            return {
                                id: item.id,
                                text: item.text,
                            };
                        }),
                    };
                },
                cache: true,
            },
            minimumInputLength: 0,
            allowClear: allowClear,
            closeOnSelect: singleSelect,
            language: {
                inputTooShort: function () {
                    return "Please type at least 1 character";
                },
                searching: function () {
                    return "Searching...";
                },
                noResults: function () {
                    return "No results found";
                },
            },
            dropdownParent: $this.closest(".modal").length && singleSelect
                ? $this.closest(".modal")
                : undefined,
        });

        $(".cancel-button").on("click", function () {
            $this.select2("close");
        });
    });
}

// Usage
$(document).ready(function () {
    initAssetSelect2('.select-asset-category', "asset_category");
    initAssetSelect2('.select-asset-assigned_to', "users");
    initAssetSelect2('.select-assets', "assets");
});

// Assets plugin main functionality
$(document).ready(function () {
    // Ensure CSRF token is included in AJAX requests
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });

    // Handle form submission for bulkAssetsUploadModal
    $('#bulkAssetsUploadModal .form-submit-event').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submitBtn = $('#submit_btn');
        var $uploadErrors = $('#uploadErrors');
        var $uploadErrorsList = $('#uploadErrorsList');

        $uploadErrors.addClass('d-none').find('#uploadErrorsList').empty();

        $submitBtn.prop('disabled', true).html('<i class="bx bx-loader bx-spin"></i> {{ get_label("importing", "Importing...") }}');

        var formData = new FormData($form[0]);

        $.ajax({
            url: $form.attr('action'),
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.error) {
                    if (response.validation_errors && response.validation_errors.length > 0) {
                        let errorHtml = '';
                        response.validation_errors.forEach(function (error) {
                            errorHtml += `<li>Row ${error.row}: ${error.messages.join(', ')}</li>`;
                        });
                        $uploadErrorsList.html(errorHtml);
                        $uploadErrors.removeClass('d-none');
                    } else {
                        $uploadErrorsList.html(`<li>${response.message}</li>`);
                        $uploadErrors.removeClass('d-none');
                    }
                } else {
                    $('#bulkAssetsUploadModal').modal('hide');
                    $('#table').bootstrapTable('refresh');
                    toastr.success(response.message);
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    try {
                        var response = JSON.parse(xhr.responseText);

                        if (response.validation_errors && response.validation_errors.length > 0) {
                            let errorHtml = '';
                            response.validation_errors.forEach(function (error) {
                                errorHtml += `<li>Row ${error.row}: ${error.messages.join(', ')}</li>`;
                            });
                            $uploadErrorsList.html(errorHtml);
                        } else {
                            $uploadErrorsList.html(`<li>${response.message || 'Validation failed.'}</li>`);
                        }
                        $uploadErrors.removeClass('d-none');
                    } catch (e) {
                        $uploadErrorsList.html('<li>An error occurred during validation. Please try again.</li>');
                        $uploadErrors.removeClass('d-none');
                    }
                } else if (xhr.status === 413) {
                    $uploadErrorsList.html('<li>The uploaded file is too large. Please try a smaller file.</li>');
                    $uploadErrors.removeClass('d-none');
                } else if (xhr.status === 500) {
                    try {
                        var response = JSON.parse(xhr.responseText);
                        $uploadErrorsList.html(`<li>${response.message || 'An internal server error occurred.'}</li>`);
                    } catch (e) {
                        $uploadErrorsList.html('<li>An internal server error occurred. Please try again.</li>');
                    }
                    $uploadErrors.removeClass('d-none');
                } else {
                    $uploadErrorsList.html('<li>An error occurred during the upload. Please try again.</li>');
                    $uploadErrors.removeClass('d-none');
                }
            },
            complete: function () {
                $submitBtn.prop('disabled', false).html('{{ get_label("import", "Import") }}');
            }
        });
    });

    // Client-side file validation
    $('#bulkAssetsUploadModal input[name="file"]').on('change', function () {
        var file = this.files[0];
        var $uploadErrors = $('#uploadErrors');
        var $uploadErrorsList = $('#uploadErrorsList');

        if (file) {
            var ext = file.name.split('.').pop().toLowerCase();
            if (!['xlsx', 'xls', 'csv'].includes(ext)) {
                $uploadErrorsList.html('<li>Please upload a valid Excel or CSV file.</li>');
                $uploadErrors.removeClass('d-none');
                this.value = '';
            } else {
                $uploadErrors.addClass('d-none').find('#uploadErrorsList').empty();
            }
        }
    });

    // Function to initialize Asset Analytics Chart
    function initAssetAnalyticsChart() {
        if (typeof window.assetAnalyticsData !== 'undefined' && $('#statusChart').length > 0) {
            var options = {
                chart: {
                    type: 'pie',
                    height: 350
                },
                series: window.assetAnalyticsData.statusValues,
                labels: window.assetAnalyticsData.statusLabels,
                colors: [
                    '#28a745',
                    '#ffc107',
                    '#dc3545',
                    '#343a40',
                    '#6c757d',
                    '#17a2b8'
                ],
                legend: {
                    position: 'bottom'
                },
                responsive: [{
                    breakpoint: 480,
                    options: {
                        chart: {
                            width: 200
                        },
                        legend: {
                            position: 'bottom'
                        }
                    }
                }]
            };

            var chart = new ApexCharts(document.querySelector("#statusChart"), options);
            chart.render();
        }
    }

    initAssetAnalyticsChart();
});
