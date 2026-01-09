$(document).on('click', '#createCandidateBtn', function () {

    $('#candidateModal').modal('show');

})

function queryParams(params) {
    const query = {
        limit: params.limit,
        offset: params.offset,
        search: params.search,
        sort: $('#sort').val(),
        order: params.order,
        candidate_status: $('#select_candidate_statuses').val(),
        start_date: $('#candidate_date_between_from').val(),
        end_date: $('#candidate_date_between_to').val(),
    };
    return query;
}


$(document).ready(function () {
    $("#sort").on("change", function () {
        $('#table').bootstrapTable('refresh');
    });

    $('#select_candidate_statuses').on('change', function () {
        $('#table').bootstrapTable('refresh');
    })

    $("#candidate_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $('#candidate_date_between_to').val(endDate);
            $('#candidate_date_between_from').val(startDate);
            $("#table").bootstrapTable('refresh');
        }
    );
    $("#candidate_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $('#candidate_date_between_to').val('');
            $('#candidate_date_between_from').val('');
            $('#candidate_date_between').val('');
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#table").bootstrapTable('refresh');
        }
    );

});

// $(document).ready(function () {
//     $('#candidate_date_between').on('apply.daterangepicker', function (ev, picker) {
//         var startDate = picker.startDate.format('YYYY-MM-DD');
//         var endDate = picker.endDate.format('YYYY-MM-DD');
//         console.log(startDate, endDate);
//         $('#candidate_date_between_from').val(startDate);
//         $('#candidate_date_between_to').val(endDate);

//         $('#table').bootstrapTable('refresh');
//     });

//     // Cancel event to clear values
//     $('#candidate_date_between').on('cancel.daterangepicker', function (ev, picker) {
//         $('#candidate_date_between_from').val('');
//         $('#candidate_date_between_to').val('');
//         $(this).val('');
//         picker.setStartDate(moment());
//         picker.setEndDate(moment());
//         picker.updateElement();
//         $('#table').bootstrapTable('refresh');

//     });

//     $('#select_candidate_statuses').on('change', function () {
//         $('#table').bootstrapTable('refresh');
//     })

//     $('#sort').on('change', function () {
//         $('#table').bootstrapTable('refresh');
//     })

// });







// Open Edit Template Modal with data
$(document).on('click', '.edit-candidate-btn', function () {
    const candidate = $(this).data('candidate');
    const actionUrl = `/candidate/update/${candidate.id}`;


    console.log(candidate);

    $('#updateCandidateForm').attr('action', actionUrl)
    $('#candidateName').val(candidate.name);
    $('#candidateEmail').val(candidate.email);
    $('#candidatePhone').val(candidate.phone);
    $('#candidateSource').val(candidate.source);
    $('#candidatePosition').val(candidate.position);


    $('#candidateStatusId').val(candidate.status_id)


    $('#candidateUpdateModal').modal('show');
});


// JavaScript code to add to your main.js or a separate file
$(document).ready(function () {
    // Handle click event on the View Interviews button
    $(document).on('click', '.view-interviews-btn', function () {

        const candidateId = $(this).data('id');
        const modal = $('#interviewDetailsModal');

        if (!candidateId) {
            showError(modal, 'Candidate ID is missing.');
            return;
        }

        // Show loading state
        modal.find('#interviewDetailsContent').html(`
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `);

        // Show the modal
        modal.modal('show');

        // AJAX call to fetch interview details
        $.ajax({
            url: `/candidate/${candidateId}/interviews`,
            method: 'GET',
            success: function (response) {
                if (response && !response.error) {
                    // Insert response HTML into modal
                    modal.find('#interviewDetailsContent').html(response.html);

                    // Update modal title
                    modal.find('.modal-title').html(`
                        <i class="bx bx-calendar-check me-2"></i> Interviews for ${response.candidate.name}
                    `);

                    // Re-initialize Bootstrap tooltips/popovers
                    initializeModalComponents();
                } else {
                    showError(modal, response.message || 'Error fetching interview details.');
                }
            },
            error: function (xhr) {
                const errorMessage = xhr.responseJSON?.message || 'An unexpected error occurred.';
                showError(modal, errorMessage);
            }
        });
    });

    // Bootstrap initializers (tooltips/popovers) inside dynamic modal
    function initializeModalComponents() {
        // Tooltips
        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
            new bootstrap.Tooltip(el);
        });

        // Popovers
        document.querySelectorAll('[data-bs-toggle="popover"]').forEach(el => {
            new bootstrap.Popover(el);
        });
    }

    // Error rendering helper
    function showError(modal, message) {
        modal.find('#interviewDetailsContent').html(`
            <div class="alert alert-danger d-flex align-items-center">
                <i class="bx bx-error-circle me-2 fs-5"></i>
                <div>${message}</div>
            </div>
        `);
    }
});

