function queryParams(params) {

    const query = {
        limit: params.limit,
        offset: params.offset,
        search: params.search,
        sort: $('#sort').val(),
        order: params.order,
        status: $('#interview_status').val(),
        start_date: $('#interview_date_between_from').val(),
        end_date: $('#interview_date_between_to').val(),
    };

    console.log(query);
    return query;
}

$(document).on('click', '.edit-interview-btn', function () {
    const interview = $(this).data('interview');

    if (!interview || !interview.id) {
        console.error('Invalid interview data:', interview);
        toastr.error(label_something_went_wrong);
        return;
    }

    // Construct the form action URL dynamically
    const actionUrl = `/interviews/update/${interview.id}`;
    $('#editInterviewForm').attr('action', actionUrl);

    // Set form values
    $('#candidate_id').val(interview.candidate_id).trigger('change');
    $('#interviewer_id').val(interview.interviewer_id).trigger('change');
    $('#round').val(interview.round || '');
    $('#scheduled_at').val(interview.scheduled_at || '');
    $('#mode').val(interview.mode || '');
    $('#location').val(interview.location || '');
    $('#status').val(interview.status || '');

    // Open the modal
    $('#editInterviewModal').modal('show');
});



// $(document).ready(function () {
//     $('#interview_date_between').on('apply.daterangepicker', function (ev, picker) {
//         var startDate = picker.startDate.format('YYYY-MM-DD');
//         var endDate = picker.endDate.format('YYYY-MM-DD');
//         $('#interview_date_between').val(startDate);
//         $('#interview_date_between').val(endDate);

//         $('#table').bootstrapTable('refresh');
//     });

//     // Cancel event to clear values
//     $('#interview_date_between').on('cancel.daterangepicker', function (ev, picker) {
//         $('#interview_date_between').val('');
//         $('#interview_date_between').val('');
//         $(this).val('');
//         picker.setStartDate(moment());
//         picker.setEndDate(moment());
//         picker.updateElement();
//         $('#table').bootstrapTable('refresh');

//     });

//     $('#interview_date_between').on('change', function () {
//         $('#table').bootstrapTable('refresh');
//     })

//     $('#sort').on('change', function () {
//         $('#table').bootstrapTable('refresh');
//     })

//     $('#interview_status').on('change', function () {
//         $('#table').bootstrapTable('refresh');
//     });


// });


$(document).ready(function () {
    $("#sort").on("change", function () {
        $('#table').bootstrapTable('refresh');
    });

    $('#interview_status').on('change', function () {
        $('#table').bootstrapTable('refresh');
    });

    $("#interview_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $('#interview_date_between_to').val(endDate);
            $('#interview_date_between_from').val(startDate);
            console.log('Selected range:', startDate, endDate);

            $("#table").bootstrapTable('refresh');
        }
    );
    $("#interview_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $('#interview_date_between_to').val('');
            $('#interview_date_between_from').val('');
            $('#interview_date_between').val('');
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#table").bootstrapTable('refresh');
        }
    );

});
