$(document).ready(function () {
    // Initialize Bootstrap table for custom fields if the table exists
    if ($("#table").length) {
        $("#table").bootstrapTable({});
    }

    // Show add field modal when button is clicked
    $("#add_field_btn").on("click", function () {
        $("#add_field_modal").modal("show");
    });

    // Handle field type change to show additional options if needed
    $("#field_type").on("change", function () {
        const fieldType = $(this).val();
        const fieldOptionsContainer = $("#field_options_container");

        fieldOptionsContainer.empty().addClass("d-none");

        if (
            fieldType === "radio" ||
            fieldType === "checkbox" ||
            fieldType === "select"
        ) {
            fieldOptionsContainer.removeClass("d-none").append(`
        <div class="mb-3">
            <label class="form-label">Options</label>
            <div id="options_list">
                <div class="input-group mb-2 option-item">
                    <input type="text" class="form-control" name="options[]" placeholder="Enter option">
                    <button type="button" class="btn btn-danger remove-option">Remove</button>
                </div>
            </div>
            <button type="button" class="btn btn-primary add-option">Add Option</button>
        </div>
    `);
        }
    });

    // Handle adding new option input
    $(document).on("click", ".add-option", function () {
        const optionsList = $("#options_list");
        optionsList.append(`
        <div class="input-group mb-2 option-item">
            <input type="text" class="form-control" name="options[]" placeholder="Enter option">
            <button type="button" class="btn btn-danger remove-option">Remove</button>
        </div>
    `);
    });

    // Handle removing an option input
    $(document).on("click", ".remove-option", function () {
        const optionItem = $(this).closest(".option-item");
        if ($("#options_list .option-item").length > 1) {
            optionItem.remove();
        } else {
            toastr.error("At least one option is required.");
        }
    });

    // Form validation for custom fields form
    $(".form-submit-event2").on("submit", function (e) {
        e.preventDefault();

        const form = $(this);
        const actionUrl = form.attr("action");

        // Debug - check if field_type is selected
        console.log("Field type selected:", $("#field_type").val());

        const options = form
            .find('input[name="options[]"]')
            .map(function () {
                return $(this).val().trim();
            })
            .get()
            .filter(val => val !== "");

        // Create standard JSON payload
        const formData = {};
        form.serializeArray().forEach(item => {
            if (item.name !== "options[]") {
                formData[item.name] = item.value;
            }
        });

        formData.options = options; // ✅ Add options as array
        $.ajax({
            url: actionUrl,
            method: "POST",
            data: $.param(formData),
            success: function (response) {
                console.log("Success response:", response);
                if (response.error) {
                    toastr.error(response.message);
                }
                else {
                    toastr.success(response.message);
                    $("#add_field_modal").modal("hide");
                    $("#table").bootstrapTable("refresh");
                }

            },
            error: function (xhr) {
                console.log("Error response:", xhr.responseJSON);
                const errors = xhr.responseJSON.errors;
                $.each(errors, function (key, value) {
                    toastr.error(value[0]);
                });
            },
        });
    });

    // Function to show error messages
    function showErrorMessage(message) {
        if (typeof toastr !== "undefined") {
            toastr.error(message);
        } else {
            alert(message);
        }
    }

    // Handle edit button click for custom fields
    $(document).on("click", ".edit-custom-field", function () {
        console.log('here');
        console.log($(this));
        var id = $(this).data("id");
        console.log("Editing field with ID:", id);
        $.ajax({
            url: "/settings/custom-fields/" + id + "/edit",
            type: "get",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            dataType: "json",
            success: function (response) {
                console.log(response);
                if (response.success) {
                    const field = response.data;

                    // Populate form fields
                    $("#module").val(field.module);
                    $("#field_label").val(field.field_label);
                    $("#field_type").val(field.field_type).trigger("change");

                    // Handle required radio buttons
                    if (field.required == "1") {
                        $("#required_yes").prop("checked", true);
                    } else {
                        $("#required_no").prop("checked", true);
                    }

                    // Handle visibility checkbox
                    $("#show_in_table").prop("checked", field.visibility == "1");

                    // Populate options for Select, Radio, or CheckBox
                    if (
                        field.options &&
                        (field.field_type === "radio" ||
                            field.field_type === "checkbox" ||
                            field.field_type === "select")
                    ) {
                        // Trigger change to render the options container
                        $("#field_type").trigger("change");
                        // Ensure the options list exists
                        const optionsList = $("#options_list");
                        if (optionsList.length) {
                            optionsList.empty(); // Clear default option input
                            // Use options directly (now an array from server)
                            const options = Array.isArray(field.options)
                                ? field.options
                                : field.options.split("\n").filter((opt) => opt.trim() !== "");
                            // Append each option to the options list
                            options.forEach((option) => {
                                optionsList.append(`
                                <div class="input-group mb-2 option-item">
                                    <input type="text" class="form-control" name="options[]" value="${option}">
                                    <button type="button" class="btn btn-danger remove-option">Remove</button>
                                </div>
                            `);
                            });
                        } else {
                            console.error("Options list container not found");
                            showErrorMessage("Failed to load options container");
                        }
                    }


                    // Change form action to update route
                    $(".form-submit-event2").attr(
                        "action",
                        "/settings/custom-fields/update/" + id
                    );
                    // if ($('input[name="_method"]').length === 0) {
                    //     $(".form-submit-event2").append(
                    //         '<input type="hidden" name="_method" value="PUT">'
                    //     );
                    // } else {
                    //     $('input[name="_method"]').val("PUT");
                    // }

                    $("#edit_custom_field").text("Edit Field");
                    $("#add_field_modal").modal("show");
                } else {
                    showErrorMessage("Could not fetch field data");
                }
            },
            error: function (xhr, status, error) {
                console.error(error);
                showErrorMessage("An error occurred while fetching field data");
            },
        });
    });

    // Reset form when modal is closed
    $("#add_field_modal").on("hidden.bs.modal", function () {
        const form = $(this).find("form");
        form[0].reset();
        $("#field_options_container").empty().addClass("d-none");
        form.attr("action", "/settings/custom-fields");
        $('input[name="_method"]').remove();
        $("#exampleModalLabel1").text("Add Field");
    });

});

function customFieldActionsFormatter(value, row, index) {
    return [
        '<a href="javascript:void(0);" class="edit-custom-field" data-id=' +
        row.id +
        ' title="Edit" class="card-link"><i class="bx bx-edit mx-1"></i></a>' +
        '<button title="Delete" type="button" class="btn delete" data-type"settings/custom-fields" data-id=' +
        row.id +
        ">" +
        '<i class="bx bx-trash text-danger mx-1"></i>' +
        "</button>",
    ];
}

function queryParams(params) {
    return {
        search: params.search,
        sort: params.sort,
        order: params.order,
        limit: params.limit,
        offset: params.offset
    };
}

// Ensure table is initialized with error handling
$(document).ready(function () {
    if ($("#custom_fields_table").length) {
        $("#custom_fields_table").bootstrapTable({
            onLoadSuccess: function () {
                console.log('Table data loaded successfully');
            },
            onLoadError: function (status, res) {
                console.error('Table load error:', status, res);
            }
        });
    }
});
