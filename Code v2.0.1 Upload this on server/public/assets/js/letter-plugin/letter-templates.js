$(document).ready(function () {
    const $table = $('#letter_templates_table');

    window.queryParams = function (params) {
        const category = $('#category_filter').val();
        const is_active = $('#status_filter').val();
        if (category) params.categories = category;
        if (is_active !== "") params.is_active = is_active;
        return params;
    };

    $('#category_filter, #status_filter').on('change', function () {
        $table.bootstrapTable('refresh');
    });

    $('#reset_filters').on('click', function () {
        $('#category_filter').val(null).trigger('change');
        $('#status_filter').val('');
        $table.bootstrapTable('refresh');
    });

    // Handle duplicate
    $(document).on('click', '.duplicate-template', function () {
        const id = $(this).data('id');
        if (!id) return;
        Swal.fire({
            title: 'Duplicate Template?',
            text: 'A copy will be created with a new name.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Duplicate',
        }).then(result => {
            if (result.isConfirmed) {
                $.post(`/letter-templates/${id}/duplicate`, { _token: csrf_token })
                    .done(response => {
                        if (!response.error) {
                            toastr.success(response.message);
                            $table.bootstrapTable('refresh');
                        } else {
                            toastr.error(response.message);
                        }
                    }).fail(() => toastr.error('Could not duplicate template.'));
            }
        });
    });

    // Handle delete
    $(document).on('click', '.delete-template', function () {
        const id = $(this).data('id');
        if (!id) return;
        Swal.fire({
            title: 'Delete Template?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
        }).then(result => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/letter-templates/${id}`,
                    type: 'DELETE',
                    data: { _token: csrf_token },
                    success: function (response) {
                        if (!response.error) {
                            toastr.success(response.message);
                            $table.bootstrapTable('refresh');
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function () {
                        toastr.error('Could not delete template.');
                    }
                });
            }
        });
    });
});
