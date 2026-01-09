
'use strict';
function queryParams(p) {
    return {
        "statuses": $('#status_filter').val(),
        "client_ids": $('#client_filter').val(),
        "project_ids": $('#project_filter').val(),
        "type_ids": $('#type_filter').val(),
        "date_between_from": $('#contract_date_between_from').val(),
        "date_between_to": $('#contract_date_between_to').val(),
        "start_date_from": $('#contract_start_date_from').val(),
        "start_date_to": $('#contract_start_date_to').val(),
        "end_date_from": $('#contract_end_date_from').val(),
        "end_date_to": $('#contract_end_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}


window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

function idFormatter(value, row, index) {
    return [
        '<a href="' + baseUrl + '/contracts/sign/' + row.id + '">' + label_contract_id_prefix + row.id + '</a>'
    ]
}
if ($('#promisor_sign').length) {
    var canvas = document.getElementById('promisor_sign');
    var signaturePad = new SignaturePad(canvas);
    $('#create_contract_sign_modal #submit_btn').on('click', function (e) {
        e.preventDefault();
        if (!isSignatureEmpty()) {
            var img_data = signaturePad.toDataURL('image/png');
            $("<input>").attr({
                type: "hidden",
                name: "signatureImage",
                value: img_data
            }).appendTo("#contract_sign_form");
            $("#contract_sign_form").submit();
        } else {
            toastr.error('Please draw signature.');
        }
    });
}

$('#contract_sign_form').on("submit", function (e) {
    e.preventDefault();
    var formData = new FormData(this);
    var submit_btn = $(this).find('#submit_btn');
    var btn_html = submit_btn.html();
    $.ajax({
        type: 'POST',
        url: $(this).attr('action'),
        data: formData,
        headers: {
            'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
        },
        beforeSend: function () {
            submit_btn.html(label_please_wait);
            submit_btn.attr('disabled', true);
        },
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            if (result.error == false) {
                location.reload();
            }
            else {
                submit_btn.html(btn_html);
                submit_btn.attr('disabled', false);
                toastr.error(result.message);
            }
        }
    });

});

$(document).on('click', '#reset_promisor_sign', function (e) {
    e.preventDefault();
    signaturePad.clear();
});

function isSignatureEmpty() {
    // Get the data URL of the canvas
    var dataURL = signaturePad.toDataURL();

    // Define an initial state or known empty state
    var initialStateDataURL = 'data:image/png;base64,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'; // Replace with your empty state data URL

    // Check if the data URL matches the initial state data URL
    return dataURL === initialStateDataURL;
}

$(document).on('click', '.delete_contract_sign', function (e) {
    e.preventDefault();
    var id = $(this).data('id');
    $('#delete_contract_sign_modal').off('click', '#confirmDelete');
    $('#delete_contract_sign_modal').on('click', '#confirmDelete', function (e) {
        e.preventDefault();
        $.ajax({
            url: baseUrl + '/contracts/delete-sign/' + id,
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
            },
            beforeSend: function () {
                $('#confirmDelete').html(label_please_wait).attr('disabled', true);
            },
            success: function (response) {
                location.reload();
            },
            error: function (data) {
                location.reload();
            }

        });
    });
});

$('#contract_start_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#contract_start_date_from').val(startDate);
    $('#contract_start_date_to').val(endDate);

    $('#contracts_table').bootstrapTable('refresh');
});

$('#contract_start_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#contract_start_date_from').val('');
    $('#contract_start_date_to').val('');
    $('#contract_start_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#contracts_table').bootstrapTable('refresh');
});

$('#contract_end_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#contract_end_date_from').val(startDate);
    $('#contract_end_date_to').val(endDate);

    $('#contracts_table').bootstrapTable('refresh');
});
$('#contract_end_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#contract_end_date_from').val('');
    $('#contract_end_date_to').val('');
    $('#contract_end_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#contracts_table').bootstrapTable('refresh');
});

$(document).ready(function () {
    $('#contract_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');
        $('#contract_date_between_from').val(startDate);
        $('#contract_date_between_to').val(endDate);
        $('#contracts_table').bootstrapTable('refresh');
    });

    // Cancel event to clear values
    $('#contract_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#contract_date_between_from').val('');
        $('#contract_date_between_to').val('');
        $(this).val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#contracts_table').bootstrapTable('refresh');
    });
});

addDebouncedEventListener('#status_filter, #client_filter, #project_filter, #type_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#contracts_table').bootstrapTable('refresh');
    }
});
$(document).on('click', '.clear-contracts-filters', function (e) {
    e.preventDefault();
    $('#contract_date_between').val('');
    $('#contract_date_between_from').val('');
    $('#contract_date_between_to').val('');
    $('#contract_start_date_between').val('');
    $('#contract_end_date_between').val('');
    $('#contract_start_date_from').val('');
    $('#contract_start_date_to').val('');
    $('#contract_end_date_from').val('');
    $('#contract_end_date_to').val('');
    $('#status_filter').val('').trigger('change', [0]);
    $('#client_filter').val('').trigger('change', [0]);
    $('#project_filter').val('').trigger('change', [0]);
    $('#type_filter').val('').trigger('change', [0]);
    $('#contracts_table').bootstrapTable('refresh');
})






