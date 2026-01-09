"use strict";
toastr.options = {
    positionClass: toastPosition,
    timeOut: parseFloat(toastTimeOut) * 1000,
    showDuration: "300",
    hideDuration: "1000",
    extendedTimeOut: "1000",
    progressBar: true,
    closeButton: true,
};
$(document).on("click", ".delete", function (e) {
    e.preventDefault();
    var id = $(this).data("id");
    var type = $(this).data("type");
    var reload = $(this).data("reload"); // Get the value of data-reload attribute
    if (typeof reload !== "undefined" && reload === true) {
        reload = true;
    } else {
        reload = false;
    }
    var tableID = $(this).data("table") || "table";
    var destroy =
        type == "users"
            ? "delete_user"
            : type == "contract-type"
                ? "delete-contract-type"
                : type == "project-media" || type == "task-media"
                    ? "delete-media"
                    : type == "expense-type"
                        ? "delete-expense-type"
                        : type == "milestone"
                            ? "delete-milestone"
                            : "destroy";
    type =
        type == "contract-type"
            ? "contracts"
            : type == "project-media"
                ? "projects"
                : type == "task-media"
                    ? "tasks"
                    : type == "expense-type"
                        ? "expenses"
                        : type == "milestone"
                            ? "projects"
                            : type;
    $("#deleteModal").modal("show"); // show the confirmation modal
    $("#deleteModal").off("click", "#confirmDelete");
    $("#deleteModal").on("click", "#confirmDelete", function (e) {
        $("#confirmDelete").html(label_please_wait).attr("disabled", true);
        $.ajax({
            url: baseUrl + "/" + type + "/" + destroy + "/" + id,
            type: "DELETE",
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            success: function (response) {
                $("#confirmDelete").html(label_yes).attr("disabled", false);
                $("#deleteModal").modal("hide");
                if (response.error == false) {
                    if (reload) {
                        location.reload();
                    } else {
                        toastr.success(response.message);
                        if (tableID) {
                            $("#" + tableID).bootstrapTable("refresh");
                        }
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (data) {
                $("#confirmDelete").html(label_yes).attr("disabled", false);
                $("#deleteModal").modal("hide");
                toastr.error(label_something_went_wrong);
            },
        });
    });
});
$(document).on("click", ".delete-selected", function (e) {
    e.preventDefault();
    var table = $(this).data("table");
    var type = $(this).data("type");
    var reload = $(this).data("reload");
    var destroy =
        type == "users"
            ? "delete_multiple_user"
            : type == "contract-types"
                ? "delete-multiple-contract-type"
                : type == "project-media" || type == "task-media"
                    ? "delete-multiple-media"
                    : type == "expense-types"
                        ? "delete-multiple-expense-type"
                        : type == "milestones"
                            ? "delete-multiple-milestone"
                            : "destroy_multiple";
    type =
        type == "contract-types"
            ? "contracts"
            : type == "project-media"
                ? "projects"
                : type == "task-media"
                    ? "tasks"
                    : type == "expense-types"
                        ? "expenses"
                        : type == "milestones"
                            ? "projects"
                            : type;
    var selections = $("#" + table).bootstrapTable("getSelections");
    var selectedIds = selections.map(function (row) {
        return row.id; // Replace 'id' with the field containing the unique ID
    });
    if (selectedIds.length > 0) {
        $("#confirmDeleteSelectedModal").modal("show"); // show the confirmation modal
        $("#confirmDeleteSelectedModal").off(
            "click",
            "#confirmDeleteSelections"
        );
        $("#confirmDeleteSelectedModal").on(
            "click",
            "#confirmDeleteSelections",
            function (e) {
                $("#confirmDeleteSelections")
                    .html(label_please_wait)
                    .attr("disabled", true);

                console.log("Selected IDs:", selectedIds); // Debugging line to check selected IDs
                console.log("Type:", type); // Debugging line to check type
                console.log("Destroy action:", destroy); // Debugging line to check destroy action

                $.ajax({
                    url: baseUrl + "/" + type + "/" + destroy,
                    data: {
                        ids: selectedIds,
                    },
                    type: "POST",
                    headers: {
                        "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                    },
                    success: function (response) {
                        $("#confirmDeleteSelections")
                            .html(label_yes)
                            .attr("disabled", false);
                        $("#confirmDeleteSelectedModal").modal("hide");
                        $("#" + table).bootstrapTable("refresh");
                        if (type == "settings/languages") {
                            location.reload();
                        } else {
                            if (reload) {
                                if (response.hasOwnProperty("message")) {
                                    if (response.error == false) {
                                        toastr.success(response["message"]);
                                        setTimeout(function () {
                                            location.reload();
                                        }, parseFloat(toastTimeOut) * 1000);
                                    } else {
                                        toastr.error(response["message"]);
                                    }
                                } else {
                                    location.reload();
                                }
                            } else {
                                if (response.error == false) {
                                    toastr.success(response.message);
                                } else {
                                    toastr.error(response.message);
                                }
                            }
                        }
                    },
                    error: function (data) {
                        $("#confirmDeleteSelections")
                            .html(label_yes)
                            .attr("disabled", false);
                        $("#confirmDeleteSelectedModal").modal("hide");
                        toastr.error(label_something_went_wrong);
                    },
                });
            }
        );
    } else {
        toastr.error(label_please_select_records_to_delete);
    }
});
$(document).ready(function () {
    // Handle delete selected notes or todos
    $("#delete-selected").on("click", function () {
        const itemType = $(this).data("type");
        const selectedIds = $(".selected-items:checked")
            .map(function () {
                return $(this).val();
            })
            .get();
        if (selectedIds.length > 0) {
            $("#confirmDeleteSelectedModal").modal("show"); // show the confirmation modal
            $("#confirmDeleteSelectedModal").off(
                "click",
                "#confirmDeleteSelections"
            );
            $("#confirmDeleteSelectedModal").on(
                "click",
                "#confirmDeleteSelections",
                function (e) {
                    $("#confirmDeleteSelections")
                        .html(label_please_wait)
                        .attr("disabled", true);
                    $.ajax({
                        url: baseUrl + "/" + itemType + "/destroy_multiple", // Adjust URL based on item type
                        data: {
                            ids: selectedIds,
                        },
                        type: "POST",
                        headers: {
                            "X-CSRF-TOKEN": $('input[name="_token"]').attr(
                                "value"
                            ),
                        },
                        success: function (response) {
                            $("#confirmDeleteSelections")
                                .html(label_yes)
                                .attr("disabled", false);
                            $("#confirmDeleteSelectedModal").modal("hide");
                            location.reload();
                        },
                        error: function (data) {
                            $("#confirmDeleteSelections")
                                .html(label_yes)
                                .attr("disabled", false);
                            $("#confirmDeleteSelectedModal").modal("hide");
                            toastr.error(label_something_went_wrong);
                        },
                    });
                }
            );
        } else {
            toastr.error(label_please_select_records_to_delete);
        }
    });
});
$("#select-all").on("click", function () {
    $(".selected-items").prop("checked", this.checked);
});
$(document).on("click", "#deleteAccount", function (e) {
    e.preventDefault();
    $("#deleteAccountModal").modal("show"); // show the confirmation modal
    $("#deleteAccountModal").off("click", "#confirmDeleteAccount");
    $("#deleteAccountModal").on("click", "#confirmDeleteAccount", function (e) {
        $("#confirmDeleteAccount")
            .html(label_please_wait)
            .attr("disabled", true);
        $.ajax({
            url: baseUrl + "/account/destroy",
            type: "DELETE",
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            success: function (response) {
                $("#confirmDeleteAccount")
                    .html(label_yes)
                    .attr("disabled", false);
                $("#deleteAccountModal").modal("hide");
                if (!response.error) {
                    toastr.success(response["message"]);
                    setTimeout(function () {
                        location.reload();
                    }, parseFloat(toastTimeOut) * 1000);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (data) {
                $("#confirmDeleteAccount")
                    .html(label_yes)
                    .attr("disabled", false);
                $("#deleteAccountModal").modal("hide");
                toastr.error(label_something_went_wrong);
            },
        });
    });
});
function update_status(e) {
    var id = e["id"];
    var name = e["name"];
    var status;
    var is_checked = $("input[name=" + name + "]:checked");
    if (is_checked.length >= 1) {
        status = 1;
    } else {
        status = 0;
    }
    $.ajax({
        url: baseUrl + "/todos/update_status",
        type: "POST", // Use POST method
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        data: {
            _method: "PUT", // Specify the desired method
            id: id,
            status: status,
        },
        success: function (response) {
            if (response.error == false) {
                location.reload();
            } else {
                toastr.error(response.message);
            }
        },
    });
}
$(document).on("click", ".edit-todo", function () {
    var id = $(this).data("id");
    $("#edit_todo_modal").modal("show");
    $.ajax({
        url: baseUrl + "/todos/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#todo_id").val(response.todo.id);
            $("#todo_title").val(response.todo.title);
            $("#todo_priority").val(response.todo.priority);
            $("#todo_description").val(response.todo.description);
            console.log(response.todo?.reminders?.length);

            if (response.todo?.reminders?.length > 0) {
                const reminder = response.todo.reminders[0];

                $("#edit-todo-reminder-switch").prop(
                    "checked",
                    reminder.is_active === 1
                ).trigger("change"); // trigger change event
                console.log(reminder.is_active);



                $("#edit-todo-frequency-type")
                    .val(reminder.frequency_type)
                    .trigger("change");

                switch (reminder.frequency_type) {
                    case "weekly":
                        $("#edit-todo-day-of-week").val(reminder.day_of_week || "");
                        break;
                    case "monthly":
                        $("#edit-todo-day-of-month").val(reminder.day_of_month || "");
                        break;
                }

                if (reminder.time_of_day) {
                    const timeOfDay = reminder.time_of_day.slice(0, 5);
                    $("#edit-todo-time-of-day").val(timeOfDay);
                }
            }

        },
    });
});

$(document).on("click", ".edit-note", function () {
    var id = $(this).data("id");
    $("#edit_note_modal").modal("show");

    // Get the current color class
    var classes = $("#note_color").attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    const noteTypeLabels = {
        text: "Text Note",
        drawing: "Drawing Note"
    };
    $.ajax({
        url: baseUrl + "/notes/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#note_id").val(response.note.id);
            $("#note_title").val(response.note.title);
            $("#note_color")
                .val(response.note.color)
                .removeClass(currentColorClass)
                .addClass("select-bg-label-" + response.note.color);

            let noteType = response.note.note_type;
            console.log("Note type:", noteType);

            // Set the note type select value and trigger change
            $("#editNoteType").val(noteType);
            $("#editNoteTypeDisplay").val(noteTypeLabels[noteType]);

            if (noteType === "text") {
                $("#edit-text-note-section").removeClass('d-none');
                $("#edit-drawing-note-section").addClass('d-none');
                $("#note_description").val(response.note.description || "");
            } else if (noteType === "drawing") {
                $("#edit-text-note-section").addClass('d-none');
                $("#edit-drawing-note-section").removeClass('d-none');

                // Set the drawing data
                $("#edit_drawing_data").val(response.note.drawing_data || "");

                // Initialize the drawing editor
                setTimeout(function () {
                    let drawingContainer = document.getElementById("edit_drawing-container");
                    if (drawingContainer) {
                        try {
                            console.log("Initializing edit drawing editor...");
                            // Clear any existing content
                            drawingContainer.innerHTML = '';

                            let editor = new jsdraw.Editor(drawingContainer);
                            editor.getRootElement().style.height = "260px";
                            const toolbar = editor.addToolbar();
                            $('.toolbar-internalWidgetId--selection-tool-widget, .toolbar-internalWidgetId--text-tool-widget, .toolbar-internalWidgetId--document-properties-widget, .pipetteButton,.toolbar-internalWidgetId--insert-image-widget').hide();

                            setTimeout(() => {
                                $(".toolbar--pen-tool-toggle-buttons").hide();
                            }, 500);

                            // Try to load the image using jsdraw's API
                            if (response.note.drawing_data) {
                                var svgSavedData = response.note.drawing_data;
                                console.log("Drawing data:", svgSavedData);
                                try {
                                    editor.loadFromSVG(svgSavedData);

                                } catch (error) {
                                    console.error("Error loading drawing data:", error);
                                }
                            }


                            // Update the drawing data when submitting
                            $(document).on('click', '#submit_btn', function (e) {
                                if (noteType === "drawing") {
                                    e.preventDefault();
                                    console.log("Saving edited drawing data...");
                                    let drawingData = editor.toSVG().outerHTML;
                                    let encodedDrawingData = btoa(unescape(encodeURIComponent(drawingData)));

                                    $("#edit_drawing_data").val(encodedDrawingData);

                                    console.log("Edited drawing data saved, length:", encodedDrawingData);
                                    $(this).off("submit").submit();
                                }
                            });
                        } catch (e) {
                            console.error("Error initializing jsDraw for edit:", e);
                        }
                    }
                }, 300);
            }
        },
        error: function (xhr, status, error) {
            console.error("Error fetching note data:", error);
        }
    });
});
$(document).on("click", ".edit-status", function () {
    var id = $(this).data("id");
    $("#edit_status_modal").modal("show");
    var classes = $("#status_color").attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    $.ajax({
        url: baseUrl + "/status/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#status_id").val(response.status.id);
            $("#status_title").val(response.status.title);
            $("#status_color")
                .val(response.status.color)
                .removeClass(currentColorClass)
                .addClass("select-bg-label-" + response.status.color);
            var modalForm = $("#edit_status_modal").find("form");
            var usersSelect = modalForm.find(
                '.js-example-basic-multiple[name="role_ids[]"]'
            );
            usersSelect.val(response.roles);
            usersSelect.trigger("change"); // Trigger change event to update select2
        },
    });
});
$(document).on("click", ".edit-tag", function () {
    var id = $(this).data("id");
    $("#edit_tag_modal").modal("show");
    var classes = $("#tag_color").attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    $.ajax({
        url: baseUrl + "/tags/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#tag_id").val(response.tag.id);
            $("#tag_title").val(response.tag.title);
            $("#tag_color")
                .val(response.tag.color)
                .removeClass(currentColorClass)
                .addClass("select-bg-label-" + response.tag.color);
        },
    });
});
$(document).on("click", ".edit-leave-request", function () {
    var id = $(this).data("id");
    $("#edit_leave_request_modal").modal("show");
    $.ajax({
        url: baseUrl + "/leave-requests/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            var formattedFromDate = moment(response.lr.from_date).format(
                js_date_format
            );
            var formattedToDate = moment(response.lr.to_date).format(
                js_date_format
            );
            var fromDateSelect = $("#edit_leave_request_modal").find(
                "#update_start_date"
            );
            var toDateSelect = $("#edit_leave_request_modal").find(
                "#update_end_date"
            );
            var reasonSelect = $("#edit_leave_request_modal").find(
                '[name="reason"]'
            );
            var commentSelect = $("#edit_leave_request_modal").find(
                '[name="comment"]'
            );
            var totalDaysSelect = $("#edit_leave_request_modal").find(
                "#update_total_days"
            );
            $("#lr_id").val(response.lr.id);
            $("#leaveUser").val(
                response.lr.user.first_name + " " + response.lr.user.last_name
            );
            fromDateSelect.val(formattedFromDate);
            toDateSelect.val(formattedToDate);
            initializeDateRangePicker("#update_start_date,#update_end_date");
            var start_date = moment(fromDateSelect.val(), js_date_format);
            var end_date = moment(toDateSelect.val(), js_date_format);
            var total_days = end_date.diff(start_date, "days") + 1;
            totalDaysSelect.val(total_days);
            if (response.lr.from_time && response.lr.to_time) {
                $("#updatePartialLeave")
                    .prop("checked", true)
                    .trigger("change");
                var fromTimeSelect = $("#edit_leave_request_modal").find(
                    '[name="from_time"]'
                );
                var toTimeSelect = $("#edit_leave_request_modal").find(
                    '[name="to_time"]'
                );
                fromTimeSelect.val(response.lr.from_time);
                toTimeSelect.val(response.lr.to_time);
            } else {
                $("#updatePartialLeave")
                    .prop("checked", false)
                    .trigger("change");
            }
            if (response.lr.visible_to_all) {
                $("#edit_leave_request_modal")
                    .find(".leaveVisibleToAll")
                    .prop("checked", true)
                    .trigger("change");
            } else {
                $("#edit_leave_request_modal")
                    .find(".leaveVisibleToAll")
                    .prop("checked", false)
                    .trigger("change");
                var visibleToSelect = $("#edit_leave_request_modal").find(
                    '.users_select[name="visible_to_ids[]"]'
                );
                if (
                    response.lr.visible_to_users &&
                    response.lr.visible_to_users.length > 0
                ) {
                    // Iterate through the users and add them to the select element
                    response.lr.visible_to_users.forEach(function (user) {
                        var userOption = new Option(
                            user.first_name + " " + user.last_name,
                            user.id,
                            true,
                            true
                        );
                        visibleToSelect.append(userOption);
                    });
                    // Trigger select2 to update the selected values
                    visibleToSelect.trigger("change");
                }
            }
            reasonSelect.val(response.lr.reason);
            commentSelect.val(response.lr.comment);
            $("input[name=status][value=" + response.lr.status + "]").prop(
                "checked",
                true
            );
        },
    });
});
$(document).on("click", ".edit-contract-type", function () {
    var id = $(this).data("id");
    $("#edit_contract_type_modal").modal("show");
    $.ajax({
        url: baseUrl + "/contracts/get-contract-type/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#update_contract_type_id").val(response.ct.id);
            $("#contract_type").val(response.ct.type);
        },
    });
});
$(document).on("click", ".edit-contract", function () {
    var id = $(this).data("id");
    $("#edit_contract_modal").modal("show");
    $.ajax({
        url: baseUrl + "/contracts/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            if (response.error == false) {
                var formattedStartDate = moment(
                    response.contract.start_date
                ).format(js_date_format);
                var formattedEndDate = moment(
                    response.contract.end_date
                ).format(js_date_format);
                $("#contract_id").val(response.contract.id);
                $("#title").val(response.contract.title);
                $("#value").val(response.contract.value);
                var clientOption = new Option(
                    response.contract.client.first_name +
                    " " +
                    response.contract.client.last_name,
                    response.contract.client.id,
                    true,
                    true
                );
                $("#client_id").append(clientOption).trigger("change");
                var projectOption = new Option(
                    response.contract.project.title,
                    response.contract.project.id,
                    true,
                    true
                );
                $("#project_id").append(projectOption).trigger("change");
                var contractTypeOption = new Option(
                    response.contract.contract_type.type,
                    response.contract.contract_type.id,
                    true,
                    true
                );
                $("#contract_type_id")
                    .append(contractTypeOption)
                    .trigger("change");
                var description =
                    response.contract.description !== null
                        ? response.contract.description
                        : "";
                $("#update_contract_description").val(description);
                $("#update_start_date").val(formattedStartDate);
                $("#update_end_date").val(formattedEndDate);
                initializeDateRangePicker(
                    "#update_start_date, #update_end_date"
                );
            } else {
                location.reload();
            }
        },
    });
});
$(document).on("click", ".edit-expense-type", function () {
    var id = $(this).data("id");
    $("#edit_expense_type_modal").modal("show");
    $.ajax({
        url: baseUrl + "/expenses/get-expense-type/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#update_expense_type_id").val(response.et.id);
            $("#expense_type_title").val(response.et.title);
            $("#expense_type_description").val(response.et.description);
        },
    });
});
$(document).on("click", ".edit-expense", function () {
    var id = $(this).data("id");
    $("#edit_expense_modal").modal("show");
    $.ajax({
        url: baseUrl + "/expenses/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            var formattedExpDate = moment(response.exp.expense_date).format(
                js_date_format
            );
            $("#update_expense_id").val(response.exp.id);
            $("#expense_title").val(response.exp.title);
            if (response.exp.expense_type) {
                if (response.exp.expense_type.title) {
                    var expenseTypeOption = new Option(
                        response.exp.expense_type.title,
                        response.exp.expense_type.id,
                        true, // Default selected
                        true // Selected
                    );
                    $("#expense_type_id")
                        .empty()
                        .append(expenseTypeOption)
                        .trigger("change");
                }
            }
            if (response.exp.user && response.exp.user.id) {
                var userOption = new Option(
                    response.exp.user.first_name +
                    " " +
                    response.exp.user.last_name, // Text for the option
                    response.exp.user.id, // Value for the option
                    true, // Default selected
                    true // Selected
                );
                $("#expense_user_id")
                    .empty()
                    .append(userOption)
                    .trigger("change");
            }
            $("#expense_amount").val(response.exp.amount);
            $("#update_expense_date").val(formattedExpDate);
            $("#expense_note").val(response.exp.note);
        },
    });
});
$(document).on("click", ".edit-language", function () {
    var id = $(this).data("id");
    $("#edit_language_modal").modal("show");
    $.ajax({
        url: baseUrl + "/settings/languages/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#language_id").val(response.language.id);
            $("#language_title").val(response.language.name);
        },
    });
});
$(document).on("click", ".edit-payment", function () {
    var id = $(this).data("id");
    $("#edit_payment_modal").modal("show");
    $.ajax({
        url: baseUrl + "/payments/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            var formattedExpDate = moment(response.payment.payment_date).format(
                js_date_format
            );
            $("#update_payment_id").val(response.payment.id);
            // Update payment_user_id with user details
            if (response.payment.user && response.payment.user.id) {
                var userOption = new Option(
                    response.payment.user.first_name +
                    " " +
                    response.payment.user.last_name,
                    response.payment.user.id,
                    true,
                    true
                );
                $("#payment_user_id")
                    .empty()
                    .append(userOption)
                    .trigger("change");
            }
            // Update payment_invoice_id with invoice details
            if (response.payment.invoice && response.payment.invoice.id) {
                var invoiceOption = new Option(
                    label_invoice_id_prefix + "" + response.payment.invoice.id,
                    response.payment.invoice.id,
                    true,
                    true
                );
                $("#payment_invoice_id")
                    .empty()
                    .append(invoiceOption)
                    .trigger("change");
            }
            // Update payment_pm_id with payment method details
            if (
                response.payment.payment_method &&
                response.payment.payment_method.title
            ) {
                var pmOption = new Option(
                    response.payment.payment_method.title,
                    response.payment.payment_method.id,
                    true,
                    true
                );
                $("#payment_pm_id").empty().append(pmOption).trigger("change");
            }
            $("#payment_amount").val(response.payment.amount);
            $("#update_payment_date").val(formattedExpDate);
            $("#payment_note").val(response.payment.note);
        },
    });
});
/**
 * Initializes DateRangePicker for specified input elements, supporting both modal and offcanvas contexts.
 * Configures single-date pickers with custom formatting, dynamic parent anchoring, and conditional start dates.
 *
 * @param {string} inputSelector - jQuery selector for the date input elements to initialize.
 * @returns {void}
 */
function initializeDateRangePicker(inputSelector) {
    /**
     * List of modal and offcanvas IDs to check for parent context.
     * @type {string[]}
     */
    var modalsToCheck = [
        "#create_project_modal",
        "#edit_project_modal",
        "#create_task_modal",
        "#edit_task_modal",
        "#create_milestone_modal",
        "#edit_milestone_modal",
        "#create_project_offcanvas",
        "#edit_project_offcanvas",
        "#create_task_offcanvas",
        "#edit_task_offcanvas",
        "#create_milestone_offcanvas",
        "#edit_milestone_offcanvas",
    ];

    $(inputSelector).each(function () {
        var $input = $(this);
        var isEmpty = $input.val() === ""; // Check if the input is empty

        // Check for closest modal or offcanvas
        var parentOverlay = $input.closest(".modal, .offcanvas");
        var parentOverlayId = parentOverlay.length ? parentOverlay.attr("id") : "";

        // Debug: Log parent overlay detection
        console.log(`Input ${$input.attr("id")} parent overlay:`, parentOverlayId || "None");

        // Check if input is inside any of the specified modals or offcanvas
        var isInsideOverlay = modalsToCheck.some(function (overlayId) {
            var isInOverlay = $input.closest(overlayId).length > 0;
            if (isInOverlay) {
                console.log(`${$input.attr("id")} is inside ${overlayId}`);
            }
            return isInOverlay;
        });

        // Debug: Check if offcanvas exists
        if ($("#create_project_offcanvas").length) {
            console.log("Found #create_project_offcanvas in DOM");
        } else {
            console.warn("#create_project_offcanvas not found in DOM");
        }

        /**
         * Configuration for DateRangePicker.
         * @type {Object}
         */
        var daterangepickerOptions = {
            alwaysShowCalendars: true,
            showCustomRangeLabel: true,
            singleDatePicker: true,
            showDropdowns: true,
            autoUpdateInput: !isInsideOverlay,
            locale: {
                cancelLabel: "Clear",
                format: js_date_format,
            },
        };

        // Set parentEl to the closest modal or offcanvas, or body if none found
        if (parentOverlayId) {
            daterangepickerOptions.parentEl = `#${parentOverlayId}`;
        } else {
            daterangepickerOptions.parentEl = $(document.body);
        }

        // Conditionally add startDate if input is not empty
        if (!isEmpty) {
            daterangepickerOptions.startDate = moment($input.val(), js_date_format);
        }

        // Initialize DateRangePicker
        $input.daterangepicker(daterangepickerOptions);

        // Handle autoUpdateInput behavior
        if (isEmpty) {
            $input.val(""); // Ensure input remains empty if initially empty
        }

        // Manually update input value on date selection
        $input.on("apply.daterangepicker", function (ev, picker) {
            $(this).val(picker.startDate.format(js_date_format));
        });
    });
}
$(document).on("click", "#set-as-default", function (e) {
    e.preventDefault();
    var lang = $(this).data("lang");
    $("#default_language_modal").modal("show"); // show the confirmation modal
    $("#default_language_modal").on("click", "#confirm", function () {
        $("#default_language_modal")
            .find("#confirm")
            .html(label_please_wait)
            .attr("disabled", true);
        $.ajax({
            url: baseUrl + "/settings/languages/set-default",
            type: "PUT",
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            data: {
                lang: lang,
            },
            success: function (response) {
                $("#default_language_modal")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                if (response.error == false) {
                    location.reload();
                } else {
                    toastr.error(response.message);
                    $("#default_language_modal").modal("hide");
                }
            },
        });
    });
});
$(document).on("click", "#set-default-view", function (e) {
    e.preventDefault();
    var type = $(this).data("type");
    var view = $(this).data("view");
    var url = baseUrl + "/save-" + type + "-view-preference";
    $("#set_default_view_modal").modal("show");
    $("#set_default_view_modal").off("click", "#confirm");
    $("#set_default_view_modal").on("click", "#confirm", function () {
        $("#set_default_view_modal")
            .find("#confirm")
            .html(label_please_wait)
            .attr("disabled", true);
        $.ajax({
            url: url,
            type: "PUT",
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            data: {
                type: type,
                view: view,
            },
            success: function (response) {
                $("#set_default_view_modal")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                if (response.error == false) {
                    $("#set-default-view")
                        .text(label_default_view)
                        .removeClass("bg-secondary")
                        .addClass("bg-primary");
                    $("#set_default_view_modal").modal("hide");
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
        });
    });
});
$(document).on("click", "#remove-participant", function (e) {
    e.preventDefault();
    $("#leaveWorkspaceModal").modal("show"); // show the confirmation modal
    $("#leaveWorkspaceModal").on("click", "#confirm", function () {
        $("#leaveWorkspaceModal")
            .find("#confirm")
            .html(label_please_wait)
            .attr("disabled", true);
        $.ajax({
            url: baseUrl + "/workspaces/remove_participant",
            type: "GET",
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            success: function (response) {
                $("#leaveWorkspaceModal")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                location.reload();
            },
            error: function (data) {
                $("#leaveWorkspaceModal")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                location.reload();
            },
        });
    });
});
/**
 * Resets and reinitializes DateRangePicker fields within a form, supporting both modal and offcanvas contexts.
 * Clears or sets default dates based on whether the form is inside an overlay, and reinitializes DateRangePicker instances.
 *
 * @param {jQuery} $form - The jQuery object representing the form containing date inputs.
 * @returns {void}
 */
function resetDateFields($form) {
    /**
     * List of modal and offcanvas IDs to check for parent context.
     * @type {string[]}
     */
    var modalsToCheck = [
        "#create_project_modal",
        "#edit_project_modal",
        "#create_task_modal",
        "#edit_task_modal",
        "#create_milestone_modal",
        "#edit_milestone_modal",
        "#create_project_offcanvas",
        "#edit_project_offcanvas",
        "#create_task_offcanvas",
        "#edit_task_offcanvas",
        "#create_milestone_offcanvas",
        "#edit_milestone_offcanvas",
    ];

    var currentDate = moment().format(js_date_format); // Get current date

    $form.find("input").each(function () {
        var $this = $(this);
        if ($this.data("daterangepicker")) {
            // Debug: Log input being processed
            console.log(`Resetting DateRangePicker for input: ${$this.attr("id")}`);

            // Destroy old instance
            $this.data("daterangepicker").remove();

            // Check for closest modal or offcanvas
            var parentOverlay = $form.closest(".modal, .offcanvas");
            var parentOverlayId = parentOverlay.length ? parentOverlay.attr("id") : "";

            // Debug: Log parent overlay detection
            console.log(`Parent overlay for ${$this.attr("id")}:`, parentOverlayId || "None");

            // Check if form is inside any of the specified modals or offcanvas
            var isInsideOverlay = modalsToCheck.some(function (overlayId) {
                var isInOverlay = $form.closest(overlayId).length > 0;
                if (isInOverlay) {
                    console.log(`${$this.attr("id")} is inside ${overlayId}`);
                }
                return isInOverlay;
            });

            // Debug: Check if offcanvas exists
            if ($("#create_project_offcanvas").length) {
                console.log("Found #create_project_offcanvas in DOM");
            } else {
                console.warn("#create_project_offcanvas not found in DOM");
            }

            /**
             * Configuration for DateRangePicker.
             * @type {Object}
             */
            var daterangepickerOptions = {
                alwaysShowCalendars: true,
                showCustomRangeLabel: true,
                singleDatePicker: true,
                showDropdowns: true,
                autoUpdateInput: !isInsideOverlay,
                locale: {
                    cancelLabel: "Clear",
                    format: js_date_format,
                },
            };

            // Set parentEl to the closest modal or offcanvas, or body if none found
            if (parentOverlayId) {
                daterangepickerOptions.parentEl = `#${parentOverlayId}`;
            } else {
                daterangepickerOptions.parentEl = $(document.body);
            }

            // Set startDate if not in an overlay
            if (!isInsideOverlay) {
                daterangepickerOptions.startDate = moment(currentDate, js_date_format);
            }

            // Reinitialize DateRangePicker
            $this.daterangepicker(daterangepickerOptions);

            // Set or clear input value based on overlay context
            $this.val(isInsideOverlay ? "" : currentDate);

            // Manually update input value on date selection
            $this.on("apply.daterangepicker", function (ev, picker) {
                $(this).val(picker.startDate.format(js_date_format));
            });
        }
    });
}
/**
 * Initializes DateRangePicker for specified date input fields, supporting both modal and offcanvas contexts.
 * Configures single-date pickers with custom formatting, default values, and dynamic parent anchoring.
 * Handles specific date fields (#dob, #doj) with restricted date ranges.
 *
 * @listens document.ready - Executes when the DOM is fully loaded.
 * @returns {void}
 */
$(document).ready(function () {
    /**
     * List of input IDs to initialize with DateRangePicker.
     * @type {string[]}
     */
    var idsToProcess = [
        "#start_date",
        "#end_date",
        "#update_start_date",
        "#update_end_date",
        "#lr_end_date",
        "#meeting_end_date",
        "#expense_date",
        "#update_expense_date",
        "#payment_date",
        "#update_payment_date",
        "#update_milestone_start_date",
        "#update_milestone_end_date",
        "#task_start_date",
        "#task_end_date",
    ];

    /**
     * List of modal and offcanvas IDs to check for parent context.
     * @type {string[]}
     */
    var modalsToCheck = [
        "#create_project_modal",
        "#edit_project_modal",
        "#create_task_modal",
        "#edit_task_modal",
        "#create_milestone_modal",
        "#edit_milestone_modal",
        "#create_project_offcanvas",
        "#edit_project_offcanvas",
        "#create_task_offcanvas",
        "#edit_task_offcanvas",
        "#create_milestone_offcanvas",
        "#edit_milestone_offcanvas",
    ];

    /**
     * Base configuration for DateRangePicker.
     * @type {Object}
     */
    var daterangepickerOptions = {
        alwaysShowCalendars: true,
        showCustomRangeLabel: true,
        singleDatePicker: true,
        showDropdowns: true,
        autoUpdateInput: true,
        locale: {
            cancelLabel: "Clear",
            format: js_date_format,
        },
    };

    /**
     * Initializes DateRangePicker for general date inputs with dynamic parent detection.
     */
    idsToProcess.forEach(function (id) {
        var $input = $(id);
        if ($input.length) {

            // Check for closest modal or offcanvas
            var parentOverlay = $input.closest(".modal, .offcanvas");

            var isInsideOverlay = modalsToCheck.some(function (modalId) {
                var isInModal = $input.closest(modalId).length > 0;
                if (isInModal) {
                    // console.log(`${id} is inside ${modalId}`);
                }
                return isInModal;
            });

            // Set parentEl to the closest modal or offcanvas, or body if none found
            daterangepickerOptions.parentEl = parentOverlay.length
                ? parentOverlay
                : $(document.body);

            // Debug: Log the selected parentEl
            // console.log(`ParentEl for ${id}`, daterangepickerOptions.parentEl);

            // Disable autoUpdateInput for overlays or if data-defaultDate is false
            if (
                isInsideOverlay ||
                $input.attr("data-defaultDate") === "false"
            ) {
                daterangepickerOptions.autoUpdateInput = false;
            }

            // Set default date if empty, not in an overlay, and data-defaultDate is undefined or true
            if (
                $input.val() === "" &&
                !isInsideOverlay &&
                ($input.attr("data-defaultDate") === undefined ||
                    $input.attr("data-defaultDate") === "true")
            ) {
                $input.val(moment().format(js_date_format));
            }

            // Initialize DateRangePicker
            $input.daterangepicker(daterangepickerOptions);

            // Handle apply event
            $input.on("apply.daterangepicker", function (ev, picker) {
                $(this).val(picker.startDate.format(js_date_format));
            });

            // Handle cancel event
            $input.on("cancel.daterangepicker", function () {
                $(this).val("");
            });
        } else {
            // console.warn(`Input ${id} not found in DOM`);
        }
    });

    /**
     * Initializes DateRangePicker for #dob and #doj with restricted date ranges.
     */
    var restrictedIds = ["#dob", "#doj"];
    var minDate = moment("01/01/1950", "DD/MM/YYYY");
    var maxDate = moment();

    restrictedIds.forEach(function (id) {
        var $input = $(id);
        if ($input.length) {
            var parentOverlay = $input.closest(".modal, .offcanvas");
            // console.log(`Parent overlay for ${id}`, parentOverlay);

            $input.daterangepicker({
                alwaysShowCalendars: true,
                showCustomRangeLabel: true,
                singleDatePicker: true,
                showDropdowns: true,
                autoUpdateInput: false,
                minDate: minDate,
                maxDate: maxDate,
                parentEl: parentOverlay.length ? parentOverlay : $(document.body),
                locale: {
                    cancelLabel: "Clear",
                    format: js_date_format,
                },
            });

            $input.on("apply.daterangepicker", function (ev, picker) {
                $(this).val(picker.startDate.format(js_date_format));
            });
        } else {
            // console.warn(`Input ${id} not found in DOM`);
        }
    });
});
$(document).ready(function () {
    $(
        "#report_start_date_between,#report_end_date_between,#filter_date_range,#ie_date_between,#ms_date_between,#start_date_between,#end_date_between,#project_date_between,#project_start_date_between,#project_end_date_between,#task_date_between,#task_start_date_between,#task_end_date_between,#lr_date_between,#lr_start_date_between,#lr_end_date_between,#contract_date_between,#contract_start_date_between,#contract_end_date_between,#timesheet_date_between,#timesheet_start_date_between,#timesheet_end_date_between,#meeting_date_between,#meeting_start_date_between,#meeting_end_date_between,#activity_log_between_date,#notification_between_date,#expense_from_date_between,#payment_date_between,#lead_kanban_date_range,#lead_date_range,#candidate_date_between, #interview_date_between"
    ).daterangepicker({
        alwaysShowCalendars: true,
        showCustomRangeLabel: true,
        singleDatePicker: false,
        showDropdowns: true,
        autoUpdateInput: false,
        locale: {
            cancelLabel: "Clear",
            format: js_date_format,
        },
    });
    $(
        "#report_start_date_between,#report_end_date_between,#filter_date_range,#ie_date_between,#ms_date_between,#start_date_between,#end_date_between,#project_date_between,#project_start_date_between,#project_end_date_between,#task_date_between,#task_start_date_between,#task_end_date_between,#lr_date_between,#lr_start_date_between,#lr_end_date_between,#contract_date_between,#contract_start_date_between,#contract_end_date_between,#timesheet_date_between,#timesheet_start_date_between,#timesheet_end_date_between,#meeting_date_between,#meeting_start_date_between,#meeting_end_date_between,#activity_log_between_date,#notification_between_date,#expense_from_date_between,#payment_date_between,#lead_kanban_date_range,#lead_date_range,#candidate_date_between, #interview_date_between"
    ).on("apply.daterangepicker", function (ev, picker) {
        $(this).val(
            picker.startDate.format(js_date_format) +
            " " +
            label_to +
            " " +
            picker.endDate.format(js_date_format)
        );
    });
});
if ($("#project_start_date_between").length) {
    $("#project_start_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#project_start_date_from").val(startDate);
            $("#project_start_date_to").val(endDate);
            $("#projects_table").bootstrapTable("refresh");
        }
    );
    $("#project_start_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#project_start_date_from").val("");
            $("#project_start_date_to").val("");
            $("#project_start_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#projects_table").bootstrapTable("refresh");
        }
    );
    $("#project_end_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#project_end_date_from").val(startDate);
            $("#project_end_date_to").val(endDate);
            $("#projects_table").bootstrapTable("refresh");
        }
    );
    $("#project_end_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#project_end_date_from").val("");
            $("#project_end_date_to").val("");
            $("#project_end_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#projects_table").bootstrapTable("refresh");
        }
    );
}
if ($("#task_start_date_between").length) {
    $("#task_start_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#task_start_date_from").val(startDate);
            $("#task_start_date_to").val(endDate);
            $("#task_table").bootstrapTable("refresh");
        }
    );
    $("#task_start_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#task_start_date_from").val("");
            $("#task_start_date_to").val("");
            $("#task_start_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#task_table").bootstrapTable("refresh");
        }
    );
    $("#task_end_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#task_end_date_from").val(startDate);
            $("#task_end_date_to").val(endDate);
            $("#task_table").bootstrapTable("refresh");
        }
    );
    $("#task_end_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#task_end_date_from").val("");
            $("#task_end_date_to").val("");
            $("#task_end_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#task_table").bootstrapTable("refresh");
        }
    );
}
if ($("#timesheet_start_date_between").length) {
    $("#timesheet_start_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#timesheet_start_date_from").val(startDate);
            $("#timesheet_start_date_to").val(endDate);
            $("#timesheet_table").bootstrapTable("refresh");
        }
    );
    $("#timesheet_start_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#timesheet_start_date_from").val("");
            $("#timesheet_start_date_to").val("");
            $("#timesheet_start_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#timesheet_table").bootstrapTable("refresh");
        }
    );
    $("#timesheet_end_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#timesheet_end_date_from").val(startDate);
            $("#timesheet_end_date_to").val(endDate);
            $("#timesheet_table").bootstrapTable("refresh");
        }
    );
    $("#timesheet_end_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#timesheet_end_date_from").val("");
            $("#timesheet_end_date_to").val("");
            $("#timesheet_end_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#timesheet_table").bootstrapTable("refresh");
        }
    );
}
if ($("#meeting_start_date_between").length) {
    $("#meeting_start_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#meeting_start_date_from").val(startDate);
            $("#meeting_start_date_to").val(endDate);
            $("#meetings_table").bootstrapTable("refresh");
        }
    );
    $("#meeting_start_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#meeting_start_date_from").val("");
            $("#meeting_start_date_to").val("");
            $("#meeting_start_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#meetings_table").bootstrapTable("refresh");
        }
    );
    $("#meeting_end_date_between").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $("#meeting_end_date_from").val(startDate);
            $("#meeting_end_date_to").val(endDate);
            $("#meetings_table").bootstrapTable("refresh");
        }
    );
    $("#meeting_end_date_between").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $("#meeting_end_date_from").val("");
            $("#meeting_end_date_to").val("");
            $("#meeting_end_date_between").val("");
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#meetings_table").bootstrapTable("refresh");
        }
    );
}
$(
    "textarea#footer_text,textarea#contract_description,textarea#update_contract_description,textarea.description"
).tinymce({
    height: 250,
    menubar: false,
    plugins: [
        "link",
        "a11ychecker",
        "advlist",
        "advcode",
        "advtable",
        "autolink",
        "checklist",
        "export",
        "lists",
        "link",
        "image",
        "charmap",
        "preview",
        "anchor",
        "searchreplace",
        "visualblocks",
        "powerpaste",
        "fullscreen",
        "formatpainter",
        "insertdatetime",
        "media",
        "table",
        "help",
        "wordcount",
        "emoticons",
        "code",
    ],
    toolbar:
        "link | undo redo | a11ycheck casechange blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist checklist outdent indent | removeformat | code blockquote emoticons table help",
});
$(
    "textarea#privacy_policy,textarea#terms_conditions,textarea#about_us"
).tinymce({
    height: 400,
    menubar: false,
    plugins: [
        "link",
        "a11ychecker",
        "advlist",
        "advcode",
        "advtable",
        "autolink",
        "checklist",
        "export",
        "lists",
        "link",
        "image",
        "charmap",
        "preview",
        "anchor",
        "searchreplace",
        "visualblocks",
        "powerpaste",
        "fullscreen",
        "formatpainter",
        "insertdatetime",
        "media",
        "table",
        "help",
        "wordcount",
        "emoticons",
        "code",
    ],
    toolbar:
        "link | undo redo | a11ycheck casechange blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist checklist outdent indent | removeformat | code blockquote emoticons table help",
});
document.addEventListener("focusin", function (e) {
    if (
        e.target.closest(
            ".tox-tinymce-aux, .moxman-window, .tam-assetmanager-root"
        ) !== null
    ) {
        e.stopImmediatePropagation();
    }
});


$(document).on("submit", ".form-submit-event", function (e) {
    e.preventDefault();
    if ($("#net_payable").length > 0) {
        var net_payable = $("#net_payable").text();
        $("#net_pay").val(net_payable);
    }

    var formData = new FormData(this);
    // NEW CODE: Check if this is an HTML template and encode it if needed
    if ($(this).attr("action").includes("store_template") ||
        $(this).attr("action").includes("/email-templates/store") ||
        $(this).attr("action").includes("email-templates/update") ||
        $(this).attr("action").includes("/emails/store") ||
        $(this).attr("action").includes("/emails/preview")) {
        // Find the HTML content field - adjust the selector as needed
        var contentField = $(this).find('textarea[name="content"] , input[name="content"]');
        if (contentField.length > 0) {
            // Remove the original content from FormData
            formData.delete("content");

            // Add the content as base64 encoded to bypass ModSecurity filters
            var encodedContent = btoa(contentField.val());
            formData.append("content", encodedContent);
            formData.append("is_encoded", "1");
        }
    }
    // END OF NEW CODE



    var currentForm = $(this);
    var submit_btn = $(this).find("#submit_btn");
    var btn_html = submit_btn.html();
    var btn_val = submit_btn.val();
    var redirect_url = currentForm.find('input[name="redirect_url"]').val();
    redirect_url =
        typeof redirect_url !== "undefined" && redirect_url ? redirect_url : "";
    var button_text =
        btn_html != "" || btn_html != "undefined" ? btn_html : btn_val;
    var tableInput = currentForm.find('input[name="table"]');
    var tableID = tableInput.length ? tableInput.val() : "table";
    if (currentForm.closest("#edit_contract_modal").length > 0) {
        // Ensure Dropzone is initialized for #contract-dropzone
        if (Dropzone.instances.length > 0) {
            var dropzoneInstance = Dropzone.forElement("#contract-dropzone");
            if (dropzoneInstance.getAcceptedFiles().length > 0) {
                dropzoneInstance.getAcceptedFiles().forEach(function (file) {
                    formData.append("signed_pdf", file);
                });
            }
        }
    }
    $.ajax({
        type: "POST",
        url: $(this).attr("action"),
        data: formData,
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        beforeSend: function () {
            submit_btn.html(label_please_wait);
            submit_btn.attr("disabled", true);
        },
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            if (result["error"] == true) {
                toastr.error(result["message"]);
            } else {
                var modalWithClass = $(".modal.fade.show");
                var idOfModal = modalWithClass.attr("id");
                $("#" + idOfModal).modal("hide");
                if ($(".empty-state").length > 0) {
                    if (result.hasOwnProperty("message")) {
                        toastr.success(result["message"]);
                        setTimeout(
                            handleRedirection,
                            parseFloat(toastTimeOut) * 1000
                        );
                    } else {
                        handleRedirection();
                    }
                } else {
                    if (currentForm.find('input[name="dnr"]').length > 0) {
                        if (modalWithClass.length > 0) {
                            $("#" + tableID).bootstrapTable("refresh");
                            currentForm[0].reset();
                            var partialLeaveCheckbox = $("#partialLeave");
                            if (partialLeaveCheckbox.length) {
                                partialLeaveCheckbox.trigger("change");
                            }
                            resetDateFields(currentForm);
                            if (idOfModal == "create_status_modal") {
                                var dropdownSelector = modalWithClass.find(
                                    'select[name="status_id"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.status;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("data-color", newItem.color)
                                        .attr("selected", true)
                                        .text(
                                            newItem.title +
                                            " (" +
                                            newItem.color +
                                            ")"
                                        );
                                    $(dropdownSelector).append(newOption);
                                    var openModalId = dropdownSelector
                                        .closest(".modal.fade.show")
                                        .attr("id");
                                    // List of all possible modal IDs
                                    var modalIds = [
                                        "#create_project_modal",
                                        "#edit_project_modal",
                                        "#create_task_modal",
                                        "#edit_task_modal",
                                    ];
                                    // Iterate through each modal ID
                                    modalIds.forEach(function (modalId) {
                                        // If the modal ID is not the open one
                                        if (modalId !== "#" + openModalId) {
                                            // Find the select element within the modal
                                            var otherModalSelector = $(
                                                modalId
                                            ).find('select[name="status_id"]');
                                            // Create a new option without 'selected' attribute
                                            var otherOption = $(
                                                "<option></option>"
                                            )
                                                .attr("value", newItem.id)
                                                .attr(
                                                    "data-color",
                                                    newItem.color
                                                )
                                                .text(
                                                    newItem.title +
                                                    " (" +
                                                    newItem.color +
                                                    ")"
                                            );
                                            // Append the option to the select element in the modal
                                            otherModalSelector.append(
                                                otherOption
                                            );
                                        }
                                    });
                                }
                            }
                            if (idOfModal == "create_priority_modal") {
                                var dropdownSelector = modalWithClass.find(
                                    'select[name="priority_id"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.priority;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr(
                                            "class",
                                            "badge bg-label-" + newItem.color
                                        )
                                        .attr("selected", true)
                                        .text(
                                            newItem.title +
                                            " (" +
                                            newItem.color +
                                            ")"
                                        );
                                    $(dropdownSelector).append(newOption);
                                    var openModalId = dropdownSelector
                                        .closest(".modal.fade.show")
                                        .attr("id");
                                    // List of all possible modal IDs
                                    var modalIds = [
                                        "#create_project_modal",
                                        "#edit_project_modal",
                                        "#create_task_modal",
                                        "#edit_task_modal",
                                    ];
                                    // Iterate through each modal ID
                                    modalIds.forEach(function (modalId) {
                                        // If the modal ID is not the open one
                                        if (modalId !== "#" + openModalId) {
                                            // Find the select element within the modal
                                            var otherModalSelector = $(
                                                modalId
                                            ).find(
                                                'select[name="priority_id"]'
                                            );
                                            // Create a new option without 'selected' attribute
                                            var otherOption = $(
                                                "<option></option>"
                                            )
                                                .attr("value", newItem.id)
                                                .attr(
                                                    "class",
                                                    "badge bg-label-" +
                                                    newItem.color
                                                )
                                                .text(
                                                    newItem.title +
                                                    " (" +
                                                    newItem.color +
                                                    ")"
                                            );
                                            // Append the option to the select element in the modal
                                            otherModalSelector.append(
                                                otherOption
                                            );
                                        }
                                    });
                                }
                            }
                            if (idOfModal == "create_tag_modal") {
                                var dropdownSelector = modalWithClass.find(
                                    'select[name="tag_ids[]"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.tag;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("data-color", newItem.color)
                                        .attr("selected", true)
                                        .text(newItem.title);
                                    $(dropdownSelector).append(newOption);
                                    $(dropdownSelector).trigger("change");
                                    var openModalId = dropdownSelector
                                        .closest(".modal.fade.show")
                                        .attr("id");
                                    // List of all possible modal IDs
                                    var modalIds = [
                                        "#create_project_modal",
                                        "#edit_project_modal",
                                    ];
                                    // Iterate through each modal ID
                                    modalIds.forEach(function (modalId) {
                                        // If the modal ID is not the open one
                                        if (modalId !== "#" + openModalId) {
                                            // Find the select element within the modal
                                            var otherModalSelector = $(
                                                modalId
                                            ).find('select[name="tag_ids[]"]');
                                            // Create a new option without 'selected' attribute
                                            var otherOption = $(
                                                "<option></option>"
                                            )
                                                .attr("value", newItem.id)
                                                .attr(
                                                    "data-color",
                                                    newItem.color
                                                )
                                                .text(newItem.title);
                                            // Append the option to the select element in the modal
                                            otherModalSelector.append(
                                                otherOption
                                            );
                                        }
                                    });
                                }
                            }
                            if (idOfModal == "create_item_modal") {
                                var dropdownSelector = $("#item_id");
                                if (dropdownSelector.length) {
                                    var newItem = result.item;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("selected", true)
                                        .text(newItem.title);
                                    $(dropdownSelector).append(newOption);
                                    $(dropdownSelector).trigger("change");
                                }
                            }
                            if (idOfModal === "create_contract_type_modal") {
                                var newItem = result.ct;

                                // Find the currently open contract modal
                                var contractModal = $("#create_contract_modal.show, #edit_contract_modal.show");

                                if (contractModal.length) {
                                    var dropdownSelector = contractModal.find('select[name="contract_type_id"]');

                                    if (dropdownSelector.length) {
                                        // Append and select the new option
                                        var newOption = $("<option></option>")
                                            .attr("value", newItem.id)
                                            .text(newItem.type)
                                            .attr("selected", true);

                                        dropdownSelector.append(newOption).val(newItem.id).trigger("change");
                                    }
                                }

                                // Append to the *other* contract modal (to keep both in sync)
                                var otherContractModal = $("#create_contract_modal, #edit_contract_modal")
                                    .not(contractModal)
                                    .find('select[name="contract_type_id"]');

                                if (otherContractModal.length) {
                                    var otherOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .text(newItem.type);
                                    otherContractModal.append(otherOption);
                                }

                                // Close only the contract type modal
                                $("#create_contract_type_modal").modal("hide");

                                // Show success message
                                toastr.success(result["message"]);
                                currentForm.find(".error-message").html("");

                                // Stop further processing to prevent handleRedirection
                                return false;
                            }




                            if (idOfModal == "create_pm_modal") {
                                var dropdownSelector = $(
                                    'select[name="payment_method_id"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.pm;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("selected", true)
                                        .text(newItem.title);
                                    $(dropdownSelector).append(newOption);
                                }
                            }
                            if (idOfModal == "create_allowance_modal") {
                                var dropdownSelector = $(
                                    'select[name="allowance_id"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.allowance;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("selected", true)
                                        .text(newItem.title);
                                    $(dropdownSelector)
                                        .append(newOption)
                                        .trigger("change");
                                }
                            }
                            if (idOfModal == "create_deduction_modal") {
                                var dropdownSelector = $(
                                    'select[name="deduction_id"]'
                                );
                                if (dropdownSelector.length) {
                                    var newItem = result.deduction;
                                    var newOption = $("<option></option>")
                                        .attr("value", newItem.id)
                                        .attr("selected", true)
                                        .text(newItem.title);
                                    $(dropdownSelector)
                                        .append(newOption)
                                        .trigger("change");
                                }
                            }
                        }
                        toastr.success(result["message"]);
                        currentForm.find(".error-message").html("");
                    } else {
                        if (result.hasOwnProperty("message")) {
                            toastr.success(result["message"]);
                            setTimeout(
                                handleRedirection,
                                parseFloat(toastTimeOut) * 1000
                            );
                        } else {
                            handleRedirection();
                        }
                    }
                }
            }
        },
        error: function (xhr, status, error) {
            submit_btn.html(button_text);
            submit_btn.attr("disabled", false);
            if (xhr.status === 422) {
                // Handle validation errors here
                var response = xhr.responseJSON; // Assuming you're returning JSON
                console.log(response);

                // You can access validation errors from the response object
                var errors = response.errors;
                if (errors["country_code"]) {
                    errors["phone"] = errors["country_code"];
                    delete errors["country_code"];
                }
                // Example: Display the first validation error message
                toastr.error(label_please_correct_errors);
                var showInModal = response.showInModal; // Flag to decide if errors should be shown in the modal
                if (showInModal) {
                    // Get validation errors from the response
                    var errorHtmlBody = '';

                    // Loop through the validation errors
                    $.each(errors, function (row, fields) {
                        errorHtmlBody += `<div><strong>${row}</strong><ul>`;
                        $.each(fields, function (field, messages) {
                            messages.forEach(function (msg) {
                                errorHtmlBody += `<li>${msg}</li>`;
                            });
                        });
                        errorHtmlBody += `</ul></div>`;
                    });

                    // Inject error HTML into the modal
                    $('#errorModalContent').html(errorHtmlBody);
                    $('#errorModalBody').removeClass('d-none');
                }

                // Assuming you have a list of all input fields with error messages
                var inputFields = currentForm.find(
                    "input[name], select[name], textarea[name]"
                );
                inputFields = $(inputFields.toArray().reverse());
                // Iterate through all input fields
                inputFields.each(function () {
                    var inputField = $(this);
                    var fieldName = inputField.attr("name");
                    var errorMessageElement = $(
                        '<span class="text-danger error-message"></span>'
                    );
                    if (errors && errors[fieldName]) {
                        if (
                            inputField.attr("type") !== "radio" &&
                            inputField.attr("type") !== "hidden"
                        ) {
                            // Remove existing error messages
                            if (
                                inputField.hasClass("select2-hidden-accessible")
                            ) {
                                inputField
                                    .parent()
                                    .find(".text-danger.error-message")
                                    .remove();
                                inputField
                                    .siblings(".select2")
                                    .after(errorMessageElement);
                            } else if (
                                inputField.closest(".input-group-merge")
                                    .length > 0
                            ) {
                                var inputGroup =
                                    inputField.closest(".input-group-merge");
                                inputGroup
                                    .next(".text-danger.error-message")
                                    .remove();
                                inputGroup.after(errorMessageElement);
                            } else if (
                                inputField.closest(".input-group").length > 0
                            ) {
                                var inputGroup =
                                    inputField.closest(".input-group");
                                inputGroup
                                    .next(".text-danger.error-message")
                                    .remove();
                                inputGroup.after(errorMessageElement);
                            } else if (
                                inputField.is("textarea#privacy_policy")
                            ) {
                                // Handle textarea with id privacy_policy
                                inputField
                                    .next(".text-danger.error-message")
                                    .remove();
                                inputField
                                    .parent()
                                    .find(".mt-2")
                                    .first()
                                    .before(errorMessageElement);
                            } else {
                                inputField
                                    .next(".text-danger.error-message")
                                    .remove();
                                inputField.after(errorMessageElement);
                            }
                        }
                        // If there is a validation error message for this field, display it
                        if (errors[fieldName][0].includes("required")) {
                            errorMessageElement.text("This field is required.");
                        } else {
                            errorMessageElement.text(errors[fieldName]);
                        }
                        inputField[0].scrollIntoView({
                            behavior: "smooth",
                            block: "start",
                        });
                        inputField.focus();
                    } else {
                        // If there is no validation error message, clear the existing message
                        var existingErrorMessage = inputField.next(
                            ".text-danger.error-message"
                        );
                        if (inputField.hasClass("select2-hidden-accessible")) {
                            existingErrorMessage = inputField
                                .parent()
                                .find(".text-danger.error-message");
                        } else if (
                            inputField.closest(".input-group-merge").length > 0
                        ) {
                            var inputGroup =
                                inputField.closest(".input-group-merge");
                            existingErrorMessage = inputGroup.next(
                                ".text-danger.error-message"
                            );
                        } else if (
                            inputField.closest(".input-group").length > 0
                        ) {
                            var inputGroup = inputField.closest(".input-group");
                            existingErrorMessage = inputGroup.next(
                                ".text-danger.error-message"
                            );
                        }
                        if (existingErrorMessage.length > 0) {
                            existingErrorMessage.remove();
                        }
                    }
                });
            } else {
                var response = xhr.responseJSON;
                if (response && response.message && response.exception) {
                    var errorMessage = response.message;
                    var match = errorMessage.match(
                        /Access denied for user '([^']+)'@/
                    );
                    if (match) {
                        var dbUser = match[1];
                        var customErrorMessage =
                            "Please try changing the password for database user " +
                            dbUser +
                            " or recreate the database.";
                        toastr.error(customErrorMessage);
                    } else {
                        // Check if it's an SQL error and extract relevant part
                        var sqlErrorPattern = /SQLSTATE\[[0-9]+\]: [^\(]+/;
                        var nonSqlErrorPattern =
                            /\b(?!SQLSTATE\[[0-9]+\]): [^\r\n]+/;
                        if (sqlErrorPattern.test(errorMessage)) {
                            var shortErrorMessage =
                                errorMessage.match(sqlErrorPattern)[0];
                            toastr.error(shortErrorMessage);
                        } else if (nonSqlErrorPattern.test(errorMessage)) {
                            var shortErrorMessage =
                                errorMessage.match(nonSqlErrorPattern)[0];
                            toastr.error(shortErrorMessage);
                        } else {
                            toastr.error("An unexpected error occurred.");
                        }
                    }
                } else {
                    toastr.error("An unexpected error occurred.");
                }
            }
        },
    });
    function handleRedirection() {
        if (redirect_url === "") {
            window.location.reload(); // Reload the current page
        } else {
            window.location.href = redirect_url; // Redirect to specified URL
        }
    }
});



// Click event handler for the favorite icon
$(document).on("click", ".favorite-icon", function () {
    var icon = $(this);
    var entityId = icon.data("id"); // ID of the entity (e.g., project, task)
    var entityType = icon.data("type") || "projects"; // Default to 'projects' if no type is provided
    var isFavorite = icon.attr("data-favorite");
    isFavorite = isFavorite == 1 ? 0 : 1;
    var dataTitle = icon.data("bs-original-title");
    var temp = dataTitle !== undefined ? "data-bs-original-title" : "title";
    // Send an AJAX request to update the favorite status
    $.ajax({
        url: baseUrl + "/" + entityType + "/update-favorite/" + entityId,
        type: "PATCH",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: {
            is_favorite: isFavorite,
        },
        success: function (response) {
            if (response.error == false) {
                if (isFavorite == 0) {
                    icon.removeClass("bxs-star");
                    icon.addClass("bx-star");
                    icon.attr(temp, add_favorite); // Update the tooltip text
                } else {
                    icon.removeClass("bx-star");
                    icon.addClass("bxs-star");
                    icon.attr(temp, remove_favorite); // Update the tooltip text
                }
                icon.attr("data-favorite", isFavorite);
                if (isFavorite == 0) {
                    toastr.success(label_removed_from_favorite_successfully);
                } else {
                    toastr.success(label_marked_as_favorite_successfully);
                }
            } else {
                toastr.error(response.message);
            }
        },
        error: function () {
            // Handle errors if necessary
            toastr.error(label_err_try_again);
        },
    });
});
// Click event handler for the pinned icon
$(document).on("click", ".pinned-icon", function () {
    var icon = $(this);
    var entityId = icon.data("id"); // ID of the entity (e.g., project, task)
    var entityType = icon.data("type") || "projects"; // Default to 'projects' if no type is provided
    var isPinned = icon.attr("data-pinned");
    isPinned = isPinned == 1 ? 0 : 1;
    var requireReload =
        icon.data("require_reload") !== undefined
            ? icon.data("require_reload")
            : 1;
    var dataTitle = icon.data("bs-original-title");
    var temp = dataTitle !== undefined ? "data-bs-original-title" : "title";
    // Send an AJAX request to update the pinned status
    $.ajax({
        url: baseUrl + "/" + entityType + "/update-pinned/" + entityId,
        type: "PATCH",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        data: {
            is_pinned: isPinned,
        },
        success: function (response) {
            if (response.error == false) {
                if (isPinned == 0) {
                    icon.removeClass("bxs-pin");
                    icon.addClass("bx-pin");
                    icon.attr(temp, label_click_pin); // Update the tooltip text
                } else {
                    icon.removeClass("bx-pin");
                    icon.addClass("bxs-pin");
                    icon.attr(temp, label_click_unpin); // Update the tooltip text
                }
                if (requireReload) {
                    // Show success message
                    toastr.success(response.message);
                    setTimeout(function () {
                        location.reload();
                    }, parseFloat(toastTimeOut) * 1000);
                } else {
                    icon.attr("data-pinned", isPinned);
                    if (isPinned == 0) {
                        toastr.success(label_unpinned_successfully);
                    } else {
                        toastr.success(label_pinned_successfully);
                    }
                    // Check if 'data-table' attribute is provided, otherwise default to 'projects_table'
                    var tableId = icon.data("table") || entityType + "_table";
                    if ($("#" + tableId).length) {
                        // Check if the table exists
                        $("#" + tableId).bootstrapTable("refresh"); // Refresh the table
                    }
                }
            } else {
                toastr.error(response.message);
            }
        },
        error: function () {
            // Handle errors if necessary
            toastr.error(label_err_try_again);
        },
    });
});
$(document).on("click", ".duplicate", function (e) {
    e.preventDefault();
    var id = $(this).data("id");
    var type = $(this).data("type");
    var reload = $(this).data("reload"); // Get the value of data-reload attribute
    if (typeof reload !== "undefined" && reload === true) {
        reload = true;
    } else {
        reload = false;
    }
    var tableID = $(this).data("table") || "table";
    $("#duplicateModal").modal("show"); // show the confirmation modal
    $("#duplicateModal").off("click", "#confirmDuplicate");
    if (type != "estimates-invoices" && type != "payslips") {
        $("#duplicateModal").find("#titleDiv").removeClass("d-none");
        var title = $(this).data("title");
        $("#duplicateModal").find("#updateTitle").val(title);
    } else {
        $("#duplicateModal").find("#titleDiv").addClass("d-none");
    }
    // Show or hide selection div based on data-type being 'workspaces'
    if (type === "workspaces") {
        $("#duplicateModal").find("#selectionDiv").removeClass("d-none"); // Show the selection div
    } else {
        $("#duplicateModal").find("#selectionDiv").addClass("d-none"); // Hide the selection div
    }
    $("#duplicateModal").on("click", "#confirmDuplicate", function (e) {
        e.preventDefault();
        var title = $("#duplicateModal").find("#updateTitle").val();
        const selectedOptions = $(".duplicate-option:checked")
            .map(function () {
                return $(this).val();
            })
            .get();
        $("#confirmDuplicate").html(label_please_wait).attr("disabled", true);
        $.ajax({
            url:
                baseUrl +
                "/" +
                type +
                "/duplicate/" +
                id +
                "?reload=" +
                reload +
                "&title=" +
                title +
                "&options=" +
                selectedOptions,
            type: "GET",
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            },
            success: function (response) {
                $("#confirmDuplicate").html(label_yes).attr("disabled", false);
                $("#duplicateModal").modal("hide");
                if (response.error == false) {
                    if (reload) {
                        if (response.message) {
                            // Show success message
                            toastr.success(response.message);
                            setTimeout(function () {
                                location.reload();
                            }, parseFloat(toastTimeOut) * 1000);
                        } else {
                            location.reload();
                        }
                    } else {
                        toastr.success(response.message);
                        if (tableID) {
                            $("#" + tableID).bootstrapTable("refresh");
                        }
                    }
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (data) {
                $("#confirmDuplicate").html(label_yes).attr("disabled", false);
                $("#duplicateModal").modal("hide");
                toastr.error(label_something_went_wrong);
            },
        });
    });
});
$("#duplicateProjects").on("change", function () {
    const projectTasksCheckbox = $("#duplicateProjectTasks");
    if ($(this).is(":checked")) {
        projectTasksCheckbox.prop("disabled", false);
    } else {
        projectTasksCheckbox.prop("disabled", true).prop("checked", false);
    }
});
$("#deduction_type").on("change", function (e) {
    if ($("#deduction_type").val() == "amount") {
        $("#amount_div").removeClass("d-none");
        $("#percentage_div").addClass("d-none");
    } else if ($("#deduction_type").val() == "percentage") {
        $("#amount_div").addClass("d-none");
        $("#percentage_div").removeClass("d-none");
    } else {
        $("#amount_div").addClass("d-none");
        $("#percentage_div").addClass("d-none");
    }
});
$("#update_deduction_type").on("change", function (e) {
    if ($("#update_deduction_type").val() == "amount") {
        $("#update_amount_div").removeClass("d-none");
        $("#update_percentage_div").addClass("d-none");
    } else if ($("#update_deduction_type").val() == "percentage") {
        $("#update_amount_div").addClass("d-none");
        $("#update_percentage_div").removeClass("d-none");
    } else {
        $("#update_amount_div").addClass("d-none");
        $("#update_percentage_div").addClass("d-none");
    }
});
$("#tax_type").on("change", function (e) {
    if ($("#tax_type").val() == "amount") {
        $("#amount_div").removeClass("d-none");
        $("#percentage_div").addClass("d-none");
    } else if ($("#tax_type").val() == "percentage") {
        $("#amount_div").addClass("d-none");
        $("#percentage_div").removeClass("d-none");
    } else {
        $("#amount_div").addClass("d-none");
        $("#percentage_div").addClass("d-none");
    }
});
$("#update_tax_type").on("change", function (e) {
    if ($("#update_tax_type").val() == "amount") {
        $("#update_amount_div").removeClass("d-none");
        $("#update_percentage_div").addClass("d-none");
    } else if ($("#update_tax_type").val() == "percentage") {
        $("#update_amount_div").addClass("d-none");
        $("#update_percentage_div").removeClass("d-none");
    } else {
        $("#update_amount_div").addClass("d-none");
        $("#update_percentage_div").addClass("d-none");
    }
});
if (document.getElementById("system-update-dropzone")) {
    if (!$("#system-update").hasClass("dropzone")) {
        var systemDropzone = new Dropzone("#system-update-dropzone", {
            url: $("#system-update").attr("action"),
            paramName: "update_file",
            autoProcessQueue: false,
            parallelUploads: 1,
            maxFiles: 1,
            acceptedFiles: ".zip",
            timeout: 360000,
            autoDiscover: false,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // Pass the CSRF token as a header
            },
            addRemoveLinks: true,
            dictRemoveFile: "x",
            dictMaxFilesExceeded: label_only_one_file_can_be_uploaded_at_a_time,
            dictResponseError: "Error",
            uploadMultiple: true,
            dictDefaultMessage:
                '<p><input type="button" value="' +
                label_select +
                '" class="btn btn-primary" /><br> ' +
                label_or +
                " <br> " +
                label_drag_and_drop_update_zip_file_here +
                "</p>",
        });
        systemDropzone.on("addedfile", function (file) {
            var i = 0;
            if (this.files.length) {
                var _i, _len;
                for (_i = 0, _len = this.files.length; _i < _len - 1; _i++) {
                    if (
                        this.files[_i].name === file.name &&
                        this.files[_i].size === file.size &&
                        this.files[_i].lastModifiedDate.toString() ===
                        file.lastModifiedDate.toString()
                    ) {
                        this.removeFile(file);
                        i++;
                    }
                }
            }
        });
        systemDropzone.on("error", function (file, response) {
            // Remove the file
            systemDropzone.removeFile(file);
            // Re-enable the submit button and reset its text
            $("#system_update_btn")
                .attr("disabled", false)
                .text(label_update_the_system);
            var errorMessage = label_err_try_again;
            if (typeof response === "string") {
                errorMessage = response; // Use the response text if it's a string
            } else if (response.message) {
                errorMessage = response.message; // Use response.message if it exists
            }
            toastr.error(errorMessage);
        });
        systemDropzone.on("success", function (file, response) {
            $("#system_update_btn")
                .attr("disabled", false)
                .text(label_update_the_system);
            if (response.error) {
                // Remove the file
                systemDropzone.removeFile(file);
                // Re-enable the submit button and reset its text
                // Show the error message
                toastr.error(response.message);
            } else {
                // Show success message
                toastr.success(response.message);
                setTimeout(function () {
                    location.reload();
                }, parseFloat(toastTimeOut) * 1000);
            }
        });
        $("#system_update_btn").on("click", function (e) {
            e.preventDefault();
            var queuedFiles = systemDropzone.getQueuedFiles();
            if (queuedFiles.length > 0) {
                $("#system_update_btn")
                    .attr("disabled", true)
                    .text(label_please_wait);
                systemDropzone.processQueue();
            } else {
                toastr.error(label_no_files_chosen);
            }
        });
    }
}
if (document.getElementById("media-upload-dropzone")) {
    var is_error = false;
    var mediaDropzone = new Dropzone("#media-upload-dropzone", {
        url: $("#media-upload").attr("action"),
        paramName: "media_files",
        autoProcessQueue: false,
        timeout: 0,
        autoDiscover: false,
        maxFilesize: allowedMaxFilesize,
        maxFiles: maxFilesAllowed,
        parallelUploads: maxFilesAllowed,
        acceptedFiles: allowedFileTypes,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // Pass the CSRF token as a header
        },
        addRemoveLinks: true,
        dictRemoveFile: "x",
        dictResponseError: "Error",
        uploadMultiple: true,
        dictDefaultMessage:
            '<p><input type="button" value="' +
            label_select +
            '" class="btn btn-primary" /><br> ' +
            label_or +
            " <br> " +
            label_drag_and_drop_files_here +
            " <br> " +
            label_allowed_max_upload_size +
            ": " +
            allowedMaxFilesizeFormatted +
            "<br> " +
            label_max_files_allowed +
            ": " +
            maxFilesAllowed +
            "</p>",
    });
    allowedFileTypes = allowedFileTypes.split(",");
    mediaDropzone.on("addedfile", function (file) {
        var removedFiles = 0;
        // Check if the number of files exceeds the maxFiles limit
        if (this.files.length > maxFilesAllowed) {
            this.removeFile(file); // Remove the extra file
            toastr.error(
                label_max_files_count_allowed.replace(":count", maxFilesAllowed)
            );
            return; // Exit to prevent further processing
        }
        // Check if file type is allowed
        var fileExtension = getFileExtension(file.name);
        if (!allowedFileTypes.includes(fileExtension)) {
            mediaDropzone.removeFile(file);
            removedFiles++;
        }
        // Show a message if a file was removed
        if (removedFiles > 0) {
            toastr.error(label_file_type_not_allowed + ": " + file.name);
        }
    });
    mediaDropzone.on("error", function (file, response) {
        // console.log(response);
    });
    mediaDropzone.on("sending", function (file, xhr, formData) {
        var id = $("#media_type_id").val();
        formData.append("id", id);
    });
    mediaDropzone.on("queuecomplete", function () {
        $("#upload_media_btn").attr("disabled", false).text(label_upload);
        if (mediaDropzone.files.length > 0) {
            var lastFileResponse =
                mediaDropzone.files[mediaDropzone.files.length - 1].xhr
                    .responseText;
            var response = JSON.parse(lastFileResponse);
            if (!response.error) {
                if ($("#add_media_modal").length) {
                    $("#add_media_modal").modal("hide");
                }
                if ($("#project_media_table").length) {
                    $("#project_media_table").bootstrapTable("refresh");
                }
                if ($("#task_media_table").length) {
                    $("#task_media_table").bootstrapTable("refresh");
                }
                toastr.success(response.message);
            } else {
                toastr.error(response.message);
            }
        }
        mediaDropzone.removeAllFiles();
    });
    $("#upload_media_btn").on("click", function (e) {
        e.preventDefault();
        if (mediaDropzone.getQueuedFiles().length > 0) {
            if (is_error == false) {
                $("#upload_media_btn")
                    .attr("disabled", true)
                    .text(label_please_wait);
                mediaDropzone.processQueue();
            }
        } else {
            toastr.error(label_no_files_chosen);
        }
    });
    // Clear Dropzone files when the modal is closed
    $("#add_media_modal").on("hide.bs.modal", function () {
        mediaDropzone.removeAllFiles();
        $("#upload_media_btn").attr("disabled", false).text(label_upload);
    });
}
if (document.getElementById("contract-dropzone")) {
    var contractDropzone = new Dropzone("#contract-dropzone", {
        url: $("#edit_contract_modal")
            .find(".form-submit-event")
            .attr("action"),
        paramName: "signed_pdf",
        autoProcessQueue: false,
        parallelUploads: 1,
        maxFilesize: 10, // Maximum file size in MB
        maxFiles: 1, // Only allow one file to be uploaded
        acceptedFiles: ".pdf", // Only accept PDF files
        timeout: 360000,
        autoDiscover: false,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // Pass CSRF token as header
        },
        addRemoveLinks: true,
        dictRemoveFile: "x",
        dictMaxFilesExceeded: label_only_one_file_can_be_uploaded_at_a_time,
        dictResponseError: "Error",
        uploadMultiple: false, // Allow only one file upload at a time
        dictDefaultMessage:
            '<p><input type="button" value="' +
            label_select +
            '" class="btn btn-primary" /><br> ' +
            label_or +
            " <br> " +
            label_drag_and_drop_file_here +
            "</p>",
    });
}
if (document.getElementById("bulk-upload-dropzone")) {
    var bulkUploadDropzone = new Dropzone("#bulk-upload-dropzone", {
        url: $("#bulk-upload-dropzone").closest("form").attr("action"), // Uses the form's action URL
        paramName: "bulk_file", // The name of the file input field
        autoProcessQueue: false, // Don't auto-submit
        parallelUploads: 1, // Only upload one file at a time
        maxFiles: 1, // Allow only one file at a time
        acceptedFiles: ".csv,.xlsx,.xls", // Only accept CSV or Excel files
        timeout: 360000,
        autoDiscover: false,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // Pass the CSRF token as a header
        },
        addRemoveLinks: true,
        dictRemoveFile: "x",
        dictMaxFilesExceeded: label_only_one_file_can_be_uploaded_at_a_time,
        dictResponseError: "Error",
        uploadMultiple: false, // Don't allow multiple files
        dictDefaultMessage:
            '<p><input type="button" value="' +
            label_select +
            '" class="btn btn-primary" /><br>' +
            label_or +
            "<br>" +
            label_drag_and_drop_file_here +
            "</p>",
    });
    // On added file
    bulkUploadDropzone.on("addedfile", function (file) {
        var i = 0;
        if (this.files.length) {
            var _i, _len;
            for (_i = 0, _len = this.files.length; _i < _len - 1; _i++) {
                if (
                    this.files[_i].name === file.name &&
                    this.files[_i].size === file.size &&
                    this.files[_i].lastModifiedDate.toString() ===
                    file.lastModifiedDate.toString()
                ) {
                    this.removeFile(file);
                    i++;
                }
            }
        }
    });
    // On error
    bulkUploadDropzone.on("error", function (file, response) {
        bulkUploadDropzone.removeFile(file);
        $("#submit_btn").attr("disabled", false).text(label_upload); // Re-enable the button
        $("#validation-errors").html("");
        if (
            response.validation_errors &&
            Object.keys(response.validation_errors).length > 0
        ) {
            toastr.error(label_please_correct_errors); // Show error message
            // Loop through the validation errors
            Object.values(response.validation_errors).forEach(function (error) {
                if (error.trim() !== "") {
                    // Ignore empty strings
                    $("#validation-errors").append("<p>" + error + "</p>");
                }
            });
        } else {
            if (response.message) {
                toastr.error(response.message); // Show error message
            } else {
                toastr.error(label_something_went_wrong); // Show error message
            }
        }
    });
    // On success
    bulkUploadDropzone.on("success", function (file, response) {
        $("#submit_btn").attr("disabled", false).text(label_upload); // Re-enable the button
        $("#validation-errors").html("");
        bulkUploadDropzone.removeFile(file); // Remove the file
        if (response.error) {
            toastr.error(response.message); // Show error message
        } else {
            toastr.success(response.message); // Show success message
        }
    });
    // On submit button click
    $("#submit_btn").on("click", function (e) {
        e.preventDefault();
        var queuedFiles = bulkUploadDropzone.getQueuedFiles();
        if (queuedFiles.length > 0) {
            $("#submit_btn").attr("disabled", true).text(label_please_wait); // Disable the button
            bulkUploadDropzone.processQueue(); // Start uploading
        } else {
            toastr.error(label_no_file_chosen); // Error message if no file is added
        }
    });
}
function getFileExtension(filename) {
    return "." + filename.split(".").pop().toLowerCase();
}
$(document).on("click", ".admin-login", function (e) {
    e.preventDefault();
    $("#email").val("admin@gmail.com");
    $("#password").val("123456");
});
$(document).on("click", ".member-login", function (e) {
    e.preventDefault();
    $("#email").val("member@gmail.com");
    $("#password").val("123456");
});
$(document).on("click", ".client-login", function (e) {
    e.preventDefault();
    $("#email").val("client@gmail.com");
    $("#password").val("123456");
});
// Row-wise Select/Deselect All
$(".row-permission-checkbox").change(function () {
    var module = $(this).data("module");
    var isChecked = $(this).prop("checked");
    $(`.permission-checkbox[data-module="${module}"]`).prop(
        "checked",
        isChecked
    );
});
$("#selectAllColumnPermissions").change(function () {
    var isChecked = $(this).prop("checked");
    $(".permission-checkbox").prop("checked", isChecked);
    if (isChecked) {
        $(".row-permission-checkbox").prop("checked", true).trigger("change"); // Check all row permissions when select all is checked
    } else {
        $(".row-permission-checkbox").prop("checked", false).trigger("change"); // Uncheck all row permissions when select all is unchecked
    }
    checkAllPermissions(); // Check all permissions
});
// Select/Deselect All for Rows
$("#selectAllPermissions").change(function () {
    var isChecked = $(this).prop("checked");
    $(".row-permission-checkbox").prop("checked", isChecked).trigger("change");
});
// Function to check/uncheck all permissions for a module
function checkModulePermissions(module) {
    var allChecked = true;
    $('.permission-checkbox[data-module="' + module + '"]').each(function () {
        if (!$(this).prop("checked")) {
            allChecked = false;
        }
    });
    $("#selectRow" + module).prop("checked", allChecked);
}
// Function to check if all permissions are checked and select/deselect "Select all" checkbox
function checkAllPermissions() {
    var allPermissionsChecked = true;
    $(".permission-checkbox").each(function () {
        if (!$(this).prop("checked")) {
            allPermissionsChecked = false;
        }
    });
    $("#selectAllColumnPermissions").prop("checked", allPermissionsChecked);
}
// Event handler for individual permission checkboxes
$(".permission-checkbox").on("change", function () {
    var module = $(this).data("module");
    checkModulePermissions(module);
    checkAllPermissions();
});
// Event handler for "Select all" checkbox
$("#selectAllColumnPermissions").on("change", function () {
    var isChecked = $(this).prop("checked");
    $(".permission-checkbox").prop("checked", isChecked);
});
// Initial check for permissions on page load
$(".row-permission-checkbox").each(function () {
    var module = $(this).data("module");
    checkModulePermissions(module);
});
checkAllPermissions();
$(document).ready(function () {
    $(".fixed-table-toolbar").each(function () {
        var $toolbar = $(this);
        var $data_type = $toolbar
            .closest(".table-responsive")
            .find("#data_type");
        var $data_table = $toolbar
            .closest(".table-responsive")
            .find("#data_table");
        var $multi_select = $toolbar
            .closest(".table-responsive")
            .find("#multi_select");
        var $save_column_visibility = $toolbar
            .closest(".table-responsive")
            .find("#save_column_visibility");
        if ($data_type.length > 0) {
            var data_type = $data_type.val();
            var data_table = $data_table.val() || "table";
            var multi_select = $multi_select.length > 0 ? 1 : 0;
            var multi_select_value = $multi_select.val() || null;
            var data_reload =
                $toolbar
                    .closest(".table-responsive")
                    .find("#data_reload")
                    .val() || 0;
            var action_class =
                "action_delete_" +
                (["project-media", "task-media"].includes(data_type)
                    ? "media"
                    : data_type.replace("-", "_"));
            var showDelete =
                data_type !== "report" &&
                    data_table !== "birthdays_table" &&
                    data_table !== "wa_table"
                    ? 1
                    : 0;
            // Create the "Delete selected" button
            if (showDelete) {
                var $deleteButton = $(
                    '<div class="columns columns-left btn-group float-left ' +
                    action_class +
                    '">' +
                    '<button type="button" class="btn btn-outline-danger float-left delete-selected" data-type="' +
                    data_type +
                    '" data-table="' +
                    data_table +
                    '" data-reload="' +
                    data_reload +
                    '">' +
                    '<i class="bx bx-trash"></i> ' +
                    label_delete_selected +
                    "</button>" +
                    "</div>"
                );
                // Add the "Delete selected" button before the first element in the toolbar
                $toolbar.prepend($deleteButton);
            }
            if (multi_select) {
                // Use multi_select value for clear button class if available, else use data_type
                var clearButtonClass = multi_select_value
                    ? "clear-" + multi_select_value + "-filters"
                    : "clear-" + data_type + "-filters";
                // Create the "Clear Filters" button
                var $clearFiltersButton = $(
                    '<div class="columns columns-left btn-group float-left">' +
                    '<button type="button" class="btn btn-outline-secondary ' +
                    clearButtonClass +
                    '">' +
                    '<i class="bx bx-x-circle"></i> ' +
                    label_clear_filters +
                    "</button>" +
                    "</div>"
                );
            }
            if (showDelete) {
                $deleteButton.after($clearFiltersButton);
            } else {
                $toolbar.prepend($clearFiltersButton);
            }
            if ($save_column_visibility.length > 0) {
                // Extract data-type and data-table from $save_column_visibility if they exist
                var saveType =
                    $save_column_visibility.data("type") || data_type;
                var saveTable =
                    $save_column_visibility.data("table") || data_table;
                var $savePreferencesButton = $(
                    '<div class="columns columns-left btn-group float-left">' +
                    '<button type="button" class="btn btn-outline-primary save-column-visibility" data-type="' +
                    saveType +
                    '" data-table="' +
                    saveTable +
                    '">' +
                    '<i class="bx bx-save"></i> ' +
                    label_save_column_visibility +
                    "</button>" +
                    "</div>"
                );
                // Add the Save Preferences button to the toolbar in the appropriate location
                if ($deleteButton) {
                    $deleteButton.after($savePreferencesButton);
                } else if ($clearFiltersButton) {
                    $clearFiltersButton.after($savePreferencesButton);
                } else {
                    $toolbar.prepend($savePreferencesButton);
                }
            }
        }
    });
});
$("#media_storage_type").on("change", function (e) {
    if ($("#media_storage_type").val() == "s3") {
        $(".aws-s3-fields").removeClass("d-none");
    } else {
        $(".aws-s3-fields").addClass("d-none");
    }
});
$(document).on("click", ".edit-milestone", function () {
    var id = $(this).data("id");
    $.ajax({
        url: baseUrl + "/projects/get-milestone/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            var formattedStartDate = response.ms.start_date
                ? moment(response.ms.start_date).format(js_date_format)
                : "";
            var formattedEndDate = response.ms.end_date
                ? moment(response.ms.end_date).format(js_date_format)
                : "";
            $("#milestone_id").val(response.ms.id);
            $("#milestone_title").val(response.ms.title);
            if (formattedStartDate) {
                $("#update_milestone_start_date").val(formattedStartDate);
            }
            if (formattedEndDate) {
                $("#update_milestone_end_date").val(formattedEndDate);
            }
            $("#milestone_status").val(response.ms.status);
            $("#milestone_cost").val(response.ms.cost);
            var description =
                response.ms.description !== null ? response.ms.description : "";
            $("#edit_milestone_modal")
                .find("#milestone_description")
                .val(description);
            $("#milestone_progress").val(response.ms.progress);
            $(".milestone-progress").text(response.ms.progress + "%");
        },
    });
});
$(document).on("click", "#mark-all-notifications-as-read", function (e) {
    e.preventDefault();
    $("#mark_all_notifications_as_read_modal").modal("show"); // show the confirmation modal
    $("#mark_all_notifications_as_read_modal").on(
        "click",
        "#confirmMarkAllAsRead",
        function () {
            $("#confirmMarkAllAsRead")
                .html(label_please_wait)
                .attr("disabled", true);
            $.ajax({
                url: baseUrl + "/notifications/mark-all-as-read",
                type: "PUT",
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                },
                success: function (response) {
                    location.reload();
                    // $('#confirmMarkAllAsRead').html(label_yes).attr('disabled', false);
                },
            });
        }
    );
});
$(document).on("click", ".update-notification-status", function (e) {
    var notificationId = $(this).data("id");
    var needConfirm = $(this).data("needconfirm") || false;
    if (needConfirm) {
        // Show the confirmation modal
        $("#update_notification_status_modal").modal("show");
        // Attach click event handler to the confirmation button
        $("#update_notification_status_modal").off(
            "click",
            "#confirmNotificationStatus"
        );
        $("#update_notification_status_modal").on(
            "click",
            "#confirmNotificationStatus",
            function () {
                $("#confirmNotificationStatus")
                    .html(label_please_wait)
                    .attr("disabled", true);
                performUpdate(notificationId, needConfirm);
            }
        );
    } else {
        // If confirmation is not needed, directly perform the update and handle response
        performUpdate(notificationId);
    }
});
function performUpdate(notificationId, needConfirm = "") {
    $.ajax({
        url: baseUrl + "/notifications/update-status",
        type: "PUT",
        data: { id: notificationId, needConfirm: needConfirm },
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        success: function (response) {
            console.log(response);


            if (needConfirm) {
                $("#confirmNotificationStatus")
                    .html(label_yes)
                    .attr("disabled", false);
                if (response.error == false) {
                    toastr.success(response.message);
                    $("#table").bootstrapTable("refresh");
                    // Redirect after successful update
                } else {
                    toastr.error(response.message);
                }
                $("#update_notification_status_modal").modal("hide");
            } else {
                var redirectUrl = determineRedirectUrl(
                    response.notification.type,
                    response.notification.type_id,
                    response.notification.action
                );
                window.location.href = redirectUrl;
            }
        },
    });
}
function determineRedirectUrl(type, typeId, action) {
    var redirectUrl = "";

    switch (type) {
        case "project":
            redirectUrl = baseUrl + "/projects/information/" + typeId;
            break;
        case "task":
            redirectUrl = baseUrl + "/tasks/information/" + typeId;
            break;
        case "project_comment_mention":
            redirectUrl =
                baseUrl +
                "/projects/information/" +
                typeId +
                "#navs-top-discussions";
            break;
        case "task_comment_mention":
            redirectUrl =
                baseUrl +
                "/tasks/information/" +
                typeId +
                "#navs-top-discussions";
            break;
        case "workspace":
            redirectUrl = baseUrl + "/workspaces";
            break;
        case "leave_request":
            if (action === "team_member_on_leave_alert") {
                redirectUrl = baseUrl + "/notifications";
            } else {
                redirectUrl = baseUrl + "/leave-requests";
            }
            break;
        case "meeting":
            redirectUrl = baseUrl + "/meetings";
            break;
        case "todo_reminder":

            redirectUrl = baseUrl + "/todos";
            break;
        default:
            redirectUrl = baseUrl + "/";
    }
    return redirectUrl;
}
if (
    typeof manage_notifications !== "undefined" &&
    manage_notifications == "true"
) {
    function updateUnreadNotifications() {
        // Make an AJAX request to fetch the count and HTML of unread notifications
        $.ajax({
            url: baseUrl + "/notifications/get-unread-notifications",
            type: "GET",
            dataType: "json",
            success: function (data) {
                const unreadNotificationsCount = data.count;
                const unreadNotificationsHtml = data.html;
                $("#unreadNotificationsCount").text(unreadNotificationsCount);
                $("#unreadNotificationsCount").toggleClass(
                    "d-none",
                    unreadNotificationsCount === 0
                );
                // Update the notifications list with the new HTML
                $("#unreadNotificationsContainer").html(
                    unreadNotificationsHtml
                );
            },
            error: function (xhr, status, error) {
                console.error("Error fetching unread notifications:", error);
            },
        });
    }
    // Call the updateUnreadNotifications function initially
    // updateUnreadNotifications();
    // Update the unread notifications every 30 seconds
    // setInterval(updateUnreadNotifications, 30000);
}
$(
    "textarea#email_verify_email,textarea#email_account_creation,textarea#email_forgot_password,textarea#email_project_assignment,textarea#email_task_assignment,textarea#email_workspace_assignment,textarea#email_meeting_assignment,textarea#email_leave_request_creation,textarea#email_leave_request_status_updation,textarea#email_project_status_updation,textarea#email_task_status_updation,textarea#email_team_member_on_leave_alert,textarea#email_birthday_wish,#email_work_anniversary_wish,textarea#email_task_reminder,textarea#email_recurring_task,textarea#template-body,textarea#editBody,textarea#email_interview_assignment,textarea#email_interview_status_update"
).tinymce({
    height: 821,
    menubar: true,
    plugins: [
        "link",
        "a11ychecker",
        "advlist",
        "advcode",
        "advtable",
        "autolink",
        "checklist",
        "export",
        "lists",
        "link",
        "image",
        "charmap",
        "preview",
        "anchor",
        "searchreplace",
        "visualblocks",
        "powerpaste",
        "fullscreen",
        "formatpainter",
        "insertdatetime",
        "media",
        "table",
        "help",
        "wordcount",
        "emoticons",
        "code",
    ],
    toolbar: false,
    // toolbar: 'link | undo redo | a11ycheck casechange blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist checklist outdent indent | removeformat | code blockquote emoticons table help'
});
// Handle click event on toolbar items
$(".tox-tbtn").click(function () {
    // Get the current editor instance
    var editor = tinyMCE.activeEditor;
    // Close any open toolbar dropdowns
    tinymce.ui.Factory.each(function (ctrl) {
        if (ctrl.type === "toolbarbutton" && ctrl.settings.toolbar) {
            if (ctrl !== this && ctrl.settings.toolbar === "toolbox") {
                ctrl.panel.hide();
            }
        }
    }, editor);
    // Execute the action associated with the clicked toolbar item
    editor.execCommand("mceInsertContent", false, "Clicked!");
});
$(document).on("click", ".restore-default", function (e) {
    e.preventDefault();
    var form = $(this).closest("form");
    var type = form.find('input[name="type"]').val();
    var name = form.find('input[name="name"]').val();
    var textarea = type + "_" + name;
    $("#restore_default_modal").modal("show"); // show the confirmation modal
    $("#restore_default_modal").off("click", "#confirmRestoreDefault");
    $("#restore_default_modal").on(
        "click",
        "#confirmRestoreDefault",
        function () {
            $("#confirmRestoreDefault")
                .html(label_please_wait)
                .attr("disabled", true);
            $.ajax({
                url: baseUrl + "/settings/get-default-template",
                type: "POST",
                data: { type: type, name: name },
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                },
                dataType: "json",
                success: function (response) {
                    $("#confirmRestoreDefault")
                        .html(label_yes)
                        .attr("disabled", false);
                    $("#restore_default_modal").modal("hide");
                    if (response.error == false) {
                        tinymce.get(textarea).setContent(response.content);
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
            });
        }
    );
});
$(document).on("click", ".sms-restore-default", function (e) {
    e.preventDefault();
    var form = $(this).closest("form");
    var type = form.find('input[name="type"]').val();
    var name = form.find('input[name="name"]').val();
    var textarea = type + "_" + name;
    $("#restore_default_modal").modal("show"); // show the confirmation modal
    $("#restore_default_modal").off("click", "#confirmRestoreDefault");
    $("#restore_default_modal").on(
        "click",
        "#confirmRestoreDefault",
        function () {
            $("#confirmRestoreDefault")
                .html(label_please_wait)
                .attr("disabled", true);
            $.ajax({
                url: baseUrl + "/settings/get-default-template",
                type: "POST",
                data: { type: type, name: name },
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                },
                dataType: "json",
                success: function (response) {
                    $("#confirmRestoreDefault")
                        .html(label_yes)
                        .attr("disabled", false);
                    $("#restore_default_modal").modal("hide");
                    if (response.error == false) {
                        $("#" + textarea).val(response.content);
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                    }
                },
            });
        }
    );
});
$(document).ready(function () {
    // Shared function to calculate total days
    function calculateTotalDays(startDateSelector, endDateSelector, totalDaysSelector) {
        var start_date = moment($(startDateSelector).val(), js_date_format);
        var end_date = moment($(endDateSelector).val(), js_date_format);
        if (start_date.isValid() && end_date.isValid()) {
            var total_days = end_date.diff(start_date, "days") + 1;
            $(totalDaysSelector).val(total_days);
        }
    }

    // Function to bind event listeners for date inputs
    function bindDateChangeListeners(startDateSelector, endDateSelector, totalDaysSelector) {
        $(startDateSelector + ", " + endDateSelector)
            .off("change")
            .on("change", function () {
                calculateTotalDays(startDateSelector, endDateSelector, totalDaysSelector);
            });

        $(startDateSelector).on("apply.daterangepicker", function () {
            calculateTotalDays(startDateSelector, endDateSelector, totalDaysSelector);
        });
        $(endDateSelector).on("apply.daterangepicker", function () {
            calculateTotalDays(startDateSelector, endDateSelector, totalDaysSelector);
        });
    }

    // Initial binding for create modal
    if ($("#total_days").length) {
        bindDateChangeListeners("#start_date", "#lr_end_date", "#total_days");
    }

    // Initial binding for update modal
    if ($("#update_total_days").length) {
        bindDateChangeListeners("#update_start_date", "#update_end_date", "#update_total_days");
    }

    // Reset form logic for both modal and offcanvas
    function resetModalForm(container) {
        var containerId = $(container).attr("id");
        var $form = $(container).find("form");
        $form.trigger("reset");

        if ($form.find("#total_days").length) {
            bindDateChangeListeners("#start_date", "#lr_end_date", "#total_days");
        }
        if ($form.find("#update_total_days").length) {
            bindDateChangeListeners("#update_start_date", "#update_end_date", "#update_total_days");
        }

        $form.find(".error-message").html("");

        var partialLeaveCheckbox = $("#partialLeave");
        if (partialLeaveCheckbox.length) {
            partialLeaveCheckbox.trigger("change");
        }

        var leaveVisibleToAllCheckbox = $form.find(".leaveVisibleToAll");
        if (leaveVisibleToAllCheckbox.length) {
            leaveVisibleToAllCheckbox.trigger("change");
        }

        var defaultColor = (containerId == "create_note_modal" || containerId == "edit_note_modal") ? "success" : "primary";
        var colorSelect = $form.find('select[name="color"]');
        if (colorSelect.length) {
            var classes = colorSelect.attr("class").split(" ");
            var currentColorClass = classes.find(c => c.startsWith("select-"));
            colorSelect.removeClass(currentColorClass).addClass("select-bg-label-" + defaultColor);
        }

        var selectPriority = $form.find('select[name="priority_id"]');
        if (selectPriority.length) {
            var classes = selectPriority.attr("class").split(" ");
            var currentClass = classes.find(c => c.startsWith("bg-label"));
            selectPriority.removeClass(currentClass).addClass("bg-label-secondary");
        }

        $form
            .find(".js-example-basic-multiple, .users_select, .clients_select, .projects_select, .contract_types_select, .invoices_select")
            .val(null)
            .trigger("change");

        $("#create_task_modal, #edit_task_modal")
            .find('select[name="user_id[]"]')
            .val(null)
            .trigger("change");

        if ($('.selectTaskProject[name="project"]').length) {
            $form.find($('.selectTaskProject[name="project"]')).trigger("change");
        }
        if ($('.statusDropdown[name="status_id"]').length) {
            $form.find($('.statusDropdown[name="status_id"]')).trigger("change");
        }
        if ($('.priorityDropdown[name="priority_id"]').length) {
            $form.find($('.priorityDropdown[name="priority_id"]')).trigger("change");
        }

        $("#users_associated_with_project, #task_update_users_associated_with_project").text("");

        $(container)
            .find('input[type="checkbox"]')
            .each(function () {
                $(this).prop("checked", false).trigger("change");
            });

        if (Dropzone.instances.length > 0) {
            Dropzone.instances.forEach(function (dz) {
                dz.removeAllFiles(true);
            });
        }

        resetDateFields($form);
    }

    // Reset when modal is closed
    $(".modal").on("hidden.bs.modal", function () {
        resetModalForm(this);
    });

    // ✅ Reset when offcanvas is closed
    $(".offcanvas").on("hidden.bs.offcanvas", function () {
        resetModalForm(this);
    });
});

$(document).ready(function () {
    // Listen for changes on the project select element within the modal
    $('.selectTaskProject[name="project"]').on("change", function (e) {
        var projectId = $(this).val();
        var currentModal = $(this).closest(".modal"); // Adjust the selector to match your modal structure
        var usersSelect = currentModal.find('select[name="user_id[]"]');
        var modalId = currentModal.attr("id");
        if (projectId) {
            $.ajax({
                url: baseUrl + "/projects/get/" + projectId,
                type: "GET",
                success: function (response) {
                    currentModal
                        .find("#users_associated_with_project")
                        .html(
                            "(" +
                            label_users_associated_with_project +
                            " <strong>" +
                            response.project.title +
                            "</strong>)"
                    );
                    usersSelect.empty(); // Clear existing options
                    // Check if task_accessibility is 'project_users'
                    if (response.users && response.users.length > 0) {
                        // Iterate through users and append options
                        response.users.forEach(function (user) {
                            var userOption = new Option(
                                user.first_name + " " + user.last_name,
                                user.id,
                                false,
                                false
                            ); // Unselected initially
                            usersSelect.append(userOption);
                        });
                        // Set task users or default to authUserId based on task accessibility
                        if (
                            response.project.task_accessibility ==
                            "project_users"
                        ) {
                            var taskUsers = response.users.map(
                                (user) => user.id
                            );
                            usersSelect.val(taskUsers);
                        } else {
                            if (
                                guard != "client" &&
                                modalId == "create_task_modal"
                            ) {
                                usersSelect.val(authUserId);
                            }
                        }
                        usersSelect.trigger("change");
                    } else {
                        // Handle case when no users are returned
                        if (
                            guard != "client" &&
                            modalId == "create_task_modal"
                        ) {
                            usersSelect.val(authUserId);
                        }
                        usersSelect.trigger("change");
                    }
                },
                error: function (xhr, status, error) {
                    console.error(error);
                },
            });
        }
    });
});
$(document).on("click", ".edit-task", function () {
    console.log('here');
    var id = $(this).data("id");
    $("#edit_task_modal").modal("show");
    $.ajax({
        url: baseUrl + "/tasks/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            console.log(response);
            var formattedStartDate = response.task.start_date
                ? moment(response.task.start_date).format(js_date_format)
                : "";
            var formattedEndDate = response.task.due_date
                ? moment(response.task.due_date).format(js_date_format)
                : "";
            $("#task_update_users_associated_with_project").html(
                "(" +
                label_users_associated_with_project +
                " <strong>" +
                response.project.title +
                "</strong>)"
            );
            $("#id").val(response.task.id);
            $("#title").val(response.task.title);
            $("#task_status_id").val(response.task.status_id).trigger("change");
            $("#priority_id").val(response.task.priority_id).trigger("change");
            // Initialize task list select2
            var editTaskList = $("#edit_task_list");
            editTaskList.select2({
                dropdownParent: $("#edit_task_modal"),
                width: "100%",
                ajax: {
                    url: baseUrl + "/task-lists/search",
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        return {
                            search: params.term || "",
                            project_id: response.project.id,
                        };
                    },
                    processResults: function (data) {
                        return {
                            results: data.map(function (item) {
                                return {
                                    id: item.id,
                                    text: item.name,
                                };
                            }),
                        };
                    },
                    cache: true,
                },
                placeholder: "Select a task list",
                minimumInputLength: 0,
                allowClear: true,
            });
            // Prefill task list if it exists
            if (response.task.task_list_id) {
                // Make an AJAX call to get the task list details
                $.ajax({
                    url: baseUrl + "/task-lists/search",
                    data: { id: response.task.task_list_id },
                    dataType: "json",
                    success: function (data) {
                        // Find the matching task list
                        var taskList = data.find(
                            (item) => item.id === response.task.task_list_id
                        );
                        if (taskList) {
                            // Create the option and set it as selected
                            var option = new Option(
                                taskList.name,
                                taskList.id,
                                true,
                                true
                            );
                            editTaskList.append(option).trigger("change");
                        }
                    },
                });
            }
            if (formattedStartDate) {
                $("#update_start_date").val(formattedStartDate);
            }
            if (formattedEndDate) {
                $("#update_end_date").val(formattedEndDate);
            }
            initializeDateRangePicker("#update_start_date, #update_end_date");
            $("#update_project_title").val(response.project.title);
            var description =
                response.task.description !== null
                    ? response.task.description
                    : "";
            $("#edit_task_modal").find("#task_description").val(description);
            $("#taskNote").val(response.task.note);
            $("#edit_billing_type")
                .val(response.task.billing_type)
                .trigger("change");
            $("#edit_completion_percentage")
                .val(response.task.completion_percentage)
                .trigger("change");
            var usersSelect = $("#edit_task_modal").find(
                'select[name="user_id[]"]'
            );
            // Clear existing options
            usersSelect.empty();
            // Check if response.project.users exists and has users
            if (
                response.project &&
                response.project.users &&
                response.project.users.length > 0
            ) {
                // Add users from response.project.users to the select options
                response.project.users.forEach(function (user) {
                    var userOption = new Option(
                        user.first_name + " " + user.last_name,
                        user.id,
                        false,
                        false
                    );
                    usersSelect.append(userOption);
                });
            }
            // Handle the selection of users based on response.task.users
            if (
                response.task &&
                response.task.users &&
                response.task.users.length > 0
            ) {
                var selectedTaskUsers = response.task.users.map(function (
                    user
                ) {
                    return user.id;
                });
                usersSelect.val(selectedTaskUsers);
            } else {
                usersSelect.val(null);
            }
            usersSelect.trigger("change");
            if (response.task.client_can_discuss == 1) {
                $("#edit_task_modal")
                    .find("#updateClientCanDiscussTask")
                    .prop("checked", true);
            } else {
                $("#edit_task_modal")
                    .find("#updateClientCanDiscussTask")
                    .prop("checked", false);
            }
            if (response.task.recurring_task) {
                $("#edit-recurring-task-switch").prop("checked", true);
                $("#edit-recurring-task-settings").removeClass("d-none");
                $("#edit-recurrence-frequency")
                    .val(response.task.recurring_task.frequency)
                    .trigger("change");
                switch (response.task.recurring_task.frequency) {
                    case "weekly":
                        $("#edit-recurrence-day-of-week").val(
                            response.task.recurring_task.day_of_week || ""
                        );
                        break;
                    case "monthly":
                    case "yearly":
                        $("#edit-recurrence-day-of-month").val(
                            response.task.recurring_task.day_of_month || ""
                        );
                        break;
                    case "yearly":
                        $("#edit-recurrence-month-of-year").val(
                            response.task.recurring_task.month_of_year || ""
                        );
                        break;
                }
                $("#edit-recurrence-starts-from").val(
                    response.task.recurring_task.starts_from
                        ? moment(
                            response.task.recurring_task.starts_from
                        ).format("YYYY-MM-DD")
                        : ""
                );
                $("#edit-recurrence-occurrences").val(
                    response.task.recurring_task.number_of_occurrences || ""
                );
            } else {
                $("#edit-recurring-task-switch").prop("checked", false);
                $("#edit-recurring-task-settings").addClass("d-none");
            }
            if (response.task?.reminders?.length > 0) {
                const reminder = response.task.reminders[0];
                $("#edit-reminder-switch").prop(
                    "checked",
                    reminder.is_active === 1
                );
                if (reminder.is_active === 1) {
                    $("#edit-reminder-settings").removeClass("d-none");
                } else {
                    $("#edit-reminder-settings").addClass("d-none");
                }
                $("#edit-frequency-type")
                    .val(reminder.frequency_type)
                    .trigger("change");
                switch (reminder.frequency_type) {
                    case "weekly":
                        $("#edit-day-of-week-group").removeClass("d-none");
                        $("#edit-day-of-month-group").addClass("d-none");
                        $("#edit-day-of-week").val(reminder.day_of_week || "");
                        break;
                    case "monthly":
                        $("#edit-day-of-month-group").removeClass("d-none");
                        $("#edit-day-of-week-group").addClass("d-none");
                        $("#edit-day-of-month").val(
                            reminder.day_of_month || ""
                        );
                        break;
                    default:
                        $("#edit-day-of-week-group").addClass("d-none");
                        $("#edit-day-of-month-group").addClass("d-none");
                }
                const timeOfDay = reminder.time_of_day.slice(0, 5);
                $("#edit-time-of-day").val(timeOfDay);
            }

            // Populate custom fields
            if (response.task.formatted_custom_fields) {
                console.log('Custom Fields:', response.task.formatted_custom_fields);
                // Ensure modal is fully loaded
                setTimeout(function () {
                    $.each(response.task.formatted_custom_fields, function (fieldId, field) {
                        var fieldName = `custom_fields[${field.field_id}]`;
                        var fieldSelector = `#edit_cf_${field.field_id}`;
                        var fieldType = field.field_type.toLowerCase();

                        console.log(`Processing field: ${field.field_label}, Type: ${fieldType}, Value: ${field.value}, Selector: ${fieldSelector}, Exists: ${$(fieldSelector).length}`);

                        switch (fieldType) {
                            case 'checkbox':
                                var values = field.value ? JSON.parse(field.value) : [];
                                $(`input[name="${fieldName}[]"]`).each(function () {
                                    $(this).prop('checked', values.includes($(this).val()));
                                });
                                break;
                            case 'radio':
                                $(`input[name="${fieldName}"]`).each(function () {
                                    $(this).prop('checked', $(this).val() === field.value);
                                });
                                break;
                            case 'select':
                                if ($(fieldSelector).length) {
                                    $(fieldSelector).val(field.value).trigger('change');
                                } else {
                                    console.warn(`Select field not found: ${fieldSelector}`);
                                }
                                break;
                            case 'textarea':
                                if ($(fieldSelector).length) {
                                    $(fieldSelector).val(field.value);
                                } else {
                                    console.warn(`Textarea field not found: ${fieldSelector}`);
                                }
                                break;
                            case 'date':
                                if ($(fieldSelector).length) {
                                    var formattedDate = field.value ? moment(field.value).format(js_date_format) : '';
                                    $(fieldSelector).val(formattedDate);

                                    // Re-initialize this specific datepicker with the correct date
                                    if ($(fieldSelector).data('daterangepicker')) {
                                        $(fieldSelector).data('daterangepicker').remove();
                                    }

                                    $(fieldSelector).daterangepicker({
                                        singleDatePicker: true,
                                        showDropdowns: true,
                                        autoUpdateInput: true,
                                        locale: {
                                            cancelLabel: "Clear",
                                            format: js_date_format
                                        },
                                        startDate: formattedDate || moment()
                                    });

                                    $(fieldSelector).on('cancel.daterangepicker', function (ev, picker) {
                                        $(this).val('');
                                    });

                                    console.log(`Date field initialized: ${fieldSelector} with value: ${formattedDate}`);
                                } else {
                                    console.warn(`Date field not found: ${fieldSelector}`);
                                }
                                break;
                            case 'text':
                            case 'password':
                            case 'number':
                                if ($(fieldSelector).length) {
                                    console.log('Trying to set: ', fieldSelector, field.value);
                                    $(fieldSelector).val(field.value);
                                    console.log('After set', fieldSelector, '=>', $(fieldSelector).val());

                                } else {
                                    console.warn(`Input field not found: ${fieldSelector}`);
                                }
                                break;

                            default:
                                console.warn(`Unsupported field type: ${fieldType}`);
                        }
                    });
                }, 500); // Delay to ensure DOM is ready
            }
        },
        error: function (xhr, status, error) {
            console.error(error);
        },
    });
});
/**
 * Handles the click event on elements with class 'edit-project' to open and populate an edit project form
 * in either a modal or offcanvas, fetching project data via AJAX and initializing form fields.
 *
 * @param {Event} e - The click event.
 * @listens click - Binds to the 'click' event of elements with class 'edit-project'.
 * @returns {void}
 */
function editProject(projectId, isOffcanvas = true, baseUrl, js_date_format) {
    const overlayId = isOffcanvas ? "#edit_project_offcanvas" : "#edit_project_modal";
    const overlayType = isOffcanvas ? "offcanvas" : "modal";

    console.log(`Opening ${overlayType}: ${overlayId}`);

    // Open the overlay
    const $overlay = $(overlayId);
    if (isOffcanvas) {
        $overlay.offcanvas("show");
    } else {
        $overlay.modal("show");
    }

    $.ajax({
        url: `${baseUrl}/projects/get/${projectId}`,
        type: "GET",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        },
        dataType: "json",
        success: function (response) {
            console.log("AJAX response:", response);

            if (!$overlay.length) {
                console.warn(`${overlayId} not found in DOM`);
                return;
            }

            // Format dates
            const formattedStartDate = response.project.start_date
                ? moment(response.project.start_date).format(js_date_format)
                : "";
            const formattedEndDate = response.project.end_date
                ? moment(response.project.end_date).format(js_date_format)
                : "";

            // Populate form fields
            $overlay.find("#project_id").val(response.project.id);
            $overlay.find("#project_title").val(response.project.title);
            $overlay.find("#project_status_id").val(response.project.status_id).trigger("change");
            $overlay.find("#project_priority_id").val(response.project.priority_id).trigger("change");
            $overlay.find("#project_budget").val(response.project.budget);
            $overlay.find("#update_start_date").val(formattedStartDate);
            $overlay.find("#update_end_date").val(formattedEndDate);
            $overlay.find("#task_accessibility").val(response.project.task_accessibility);
            $overlay.find("#projectNote").val(response.project.note);
            $overlay.find("#project_description").val(response.project.description || "");

            // Initialize DateRangePicker
            initializeDateRangePicker($overlay.find("#update_start_date, #update_end_date"));

            // Populate users multi-select
            const usersSelect = $overlay.find(".users_select");
            usersSelect.empty();
            if (response.users && response.users.length > 0) {
                response.users.forEach(user => {
                    const userOption = new Option(
                        `${user.first_name} ${user.last_name}`,
                        user.id,
                        true,
                        true
                    );
                    usersSelect.append(userOption);
                });
                usersSelect.trigger("change");
            } else {
                usersSelect.val(null).trigger("change");
            }

            // Populate clients multi-select
            const clientsSelect = $overlay.find(".clients_select");
            clientsSelect.empty();
            if (response.clients && response.clients.length > 0) {
                response.clients.forEach(client => {
                    const clientOption = new Option(
                        `${client.first_name} ${client.last_name}`,
                        client.id,
                        true,
                        true
                    );
                    clientsSelect.append(clientOption);
                });
                clientsSelect.trigger("change");
            } else {
                clientsSelect.val(null).trigger("change");
            }

            // Populate tags multi-select
            const tagsSelect = $overlay.find('[name="tag_ids[]"]');
            tagsSelect.empty();
            if (response.tags && response.tags.length > 0) {
                response.tags.forEach(tag => {
                    const tagOption = new Option(tag.title, tag.id, true, true);
                    tagsSelect.append(tagOption);
                });
                tagsSelect.trigger("change");
            } else {
                tagsSelect.val(null).trigger("change");
            }

            // Handle checkboxes
            $overlay.find("#updateClientCanDiscussProject")
                .prop("checked", response.project.client_can_discuss === 1);
            $overlay.find("#tasks_time_entries")
                .prop("checked", response.project.enable_tasks_time_entries === 1);

            // Handle custom fields
            if (response.customFieldValues) {
                console.log("Custom field values:", response.customFieldValues);
                $.each(response.customFieldValues, function (fieldId, value) {
                    const inputField = $overlay.find(`#edit_cf_${fieldId}`);
                    if (inputField.length) {
                        if (inputField.is("select")) {
                            inputField.val(value).trigger("change");
                        } else if (inputField.hasClass("custom-datepicker")) {
                            inputField.val(value ? moment(value).format(js_date_format) : "");
                        } else {
                            inputField.val(value);
                        }
                    } else if ($overlay.find(`input[type="radio"][name="custom_fields[${fieldId}]"]`).length) {
                        $overlay.find(`input[type="radio"][name="custom_fields[${fieldId}]"][value="${value}"]`)
                            .prop("checked", true);
                    } else if ($overlay.find(`input[type="checkbox"][name="custom_fields[${fieldId}][]"]`).length) {
                        try {
                            const checkboxValues = typeof value === "string" && value.includes("[")
                                ? JSON.parse(value)
                                : [value];
                            $overlay.find(`input[type="checkbox"][name="custom_fields[${fieldId}][]"]`)
                                .prop("checked", false);
                            checkboxValues.forEach(val => {
                                $overlay.find(`input[type="checkbox"][name="custom_fields[${fieldId}][]"][value="${val}"]`)
                                    .prop("checked", true);
                            });
                        } catch (e) {
                            console.error(`Error parsing checkbox values for field ${fieldId}:`, e);
                        }
                    }
                });
            }
        },
        error: function (xhr, status, error) {
            console.error("AJAX error:", error);
            toastr.error("Failed to load project data");
        },
    });
}

$(document).on("click", ".edit-project", function () {
    editProject($(this).data("id"), $(this).data("offcanvas") === true, baseUrl, js_date_format);
});
$(document).on("click", ".edit-priority", function () {
    var id = $(this).data("id");
    $("#edit_priority_modal").modal("show");
    var classes = $("#priority_color").attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    $.ajax({
        url: baseUrl + "/priority/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#priority_id").val(response.priority.id);
            $("#priority_title").val(response.priority.title);
            $("#priority_color")
                .val(response.priority.color)
                .removeClass(currentColorClass)
                .addClass("select-bg-label-" + response.priority.color);
        },
    });
});
$(document).on("click", ".edit-workspace", function () {
    var id = $(this).data("id");
    $("#editWorkspaceModal").modal("show");
    var $modal = $("#editWorkspaceModal");
    $.ajax({
        url: baseUrl + "/workspaces/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            $("#workspace_id").val(response.workspace.id);
            $("#workspace_title").val(response.workspace.title);
            var usersSelect = $modal.find(".users_select");
            var clientsSelect = $modal.find(".clients_select");
            // Clear existing options
            usersSelect.empty();
            clientsSelect.empty();
            // Handle multi-select for users
            if (
                response.workspace.users &&
                response.workspace.users.length > 0
            ) {
                response.workspace.users.forEach(function (user) {
                    var userOption = new Option(
                        user.first_name + " " + user.last_name,
                        user.id,
                        true,
                        true
                    );
                    usersSelect.append(userOption);
                });
                usersSelect.trigger("change");
            } else {
                usersSelect.val(null).trigger("change"); // Handle case when no users are present
            }
            // Handle multi-select for clients
            if (
                response.workspace.clients &&
                response.workspace.clients.length > 0
            ) {
                response.workspace.clients.forEach(function (client) {
                    var clientOption = new Option(
                        client.first_name + " " + client.last_name,
                        client.id,
                        true,
                        true
                    );
                    clientsSelect.append(clientOption);
                });
                clientsSelect.trigger("change");
            } else {
                clientsSelect.val(null).trigger("change"); // Handle case when no clients are present
            }
            if (response.workspace.is_primary == 1) {
                $("#editWorkspaceModal")
                    .find("#updatePrimaryWorkspace")
                    .prop("checked", true)
                    .prop("disabled", true);
            } else {
                $("#editWorkspaceModal")
                    .find("#updatePrimaryWorkspace")
                    .prop("checked", false)
                    .prop("disabled", false);
            }
        },
        error: function (xhr, status, error) {
            console.error(error);
        },
    });
});
function setDefaultWorkspace(workspaceId, isDefault) {
    const isDefaultNumeric = isDefault ? 1 : 0;
    $.ajax({
        url: baseUrl + "/workspaces/" + workspaceId + "/default",
        type: "patch",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        data: {
            is_default: isDefaultNumeric,
        },
        success: function (response) {
            if (response.error == false) {
                toastr.success(response.message);
                $("#table").bootstrapTable("refresh");
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr, status, error) {
            toastr.error(
                "An error occurred while updating the default workspace."
            );
        },
    });
}
$(document).on("click", ".edit-meeting", function () {
    var id = $(this).data("id");
    $("#editMeetingModal").modal("show");
    $.ajax({
        url: baseUrl + "/meetings/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        dataType: "json",
        success: function (response) {
            var formattedStartDate = moment(response.meeting.start_date).format(
                js_date_format
            );
            var formattedEndDate = moment(response.meeting.end_date).format(
                js_date_format
            );
            var startDateInput = $("#editMeetingModal").find(
                '[name="start_date"]'
            );
            var endDateInput = $("#editMeetingModal").find('[name="end_date"]');
            $("#meeting_id").val(response.meeting.id);
            $("#meeting_title").val(response.meeting.title);
            startDateInput.val(formattedStartDate);
            endDateInput.val(formattedEndDate);
            $("#meeting_start_time").val(response.meeting.start_time);
            $("#meeting_end_time").val(response.meeting.end_time);
            var usersSelect = $("#editMeetingModal").find(".users_select");
            var clientsSelect = $("#editMeetingModal").find(".clients_select");
            // Clear existing options
            usersSelect.empty();
            clientsSelect.empty();
            // Handle multi-select for users
            if (response.meeting.users && response.meeting.users.length > 0) {
                response.meeting.users.forEach(function (user) {
                    var userOption = new Option(
                        user.first_name + " " + user.last_name,
                        user.id,
                        true,
                        true
                    );
                    usersSelect.append(userOption);
                });
                usersSelect.trigger("change");
            } else {
                usersSelect.val(null).trigger("change"); // Handle case when no users are present
            }
            // Handle multi-select for clients
            if (
                response.meeting.clients &&
                response.meeting.clients.length > 0
            ) {
                response.meeting.clients.forEach(function (client) {
                    var clientOption = new Option(
                        client.first_name + " " + client.last_name,
                        client.id,
                        true,
                        true
                    );
                    clientsSelect.append(clientOption);
                });
                clientsSelect.trigger("change");
            } else {
                clientsSelect.val(null).trigger("change"); // Handle case when no clients are present
            }
        },
        error: function (xhr, status, error) {
            console.error(error);
        },
    });
});
$(document).on("change", "#statusSelect", function (e) {
    e.preventDefault();
    var id = $(this).data("id");
    var statusId = this.value;
    var type = $(this).data("type") || "project";
    var reload = $(this).data("reload") || false;
    var select = $(this);
    var originalStatusId = select.data("original-status-id");
    var originalColorClass = select.data("original-color-class");
    var classes = select.attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    var selectedOption = select.find("option:selected");
    var selectedOptionClasses = selectedOption.attr("class").split(" ");
    var newColorClass = "select-" + selectedOptionClasses[1];
    select.removeClass(currentColorClass).addClass(newColorClass);
    $.ajax({
        url: baseUrl + "/" + type + "s/get/" + id,
        type: "GET",
        success: function (response) {
            if (response.error == false) {
                $("#confirmUpdateStatusModal").modal("show");
                $("#confirmUpdateStatusModal").off(
                    "click",
                    "#confirmUpdateStatus"
                );
                if (type == "task" && response.task) {
                    $("#statusNote").val(response.task.note);
                    originalStatusId = response.task.status_id;
                } else if (type == "project" && response.project) {
                    $("#statusNote").val(response.project.note);
                    originalStatusId = response.project.status_id;
                }
                $("#confirmUpdateStatusModal").on(
                    "click",
                    "#confirmUpdateStatus",
                    function (e) {
                        $("#confirmUpdateStatus")
                            .html(label_please_wait)
                            .attr("disabled", true);
                        $.ajax({
                            type: "POST",
                            url: baseUrl + "/update-" + type + "-status",
                            headers: {
                                "X-CSRF-TOKEN": $('input[name="_token"]').val(), // Use .val() instead of .attr('value')
                            },
                            data: {
                                id: id,
                                statusId: statusId,
                                note: $("#statusNote").val(),
                            },
                            success: function (response) {
                                $("#confirmUpdateStatus")
                                    .html(label_yes)
                                    .attr("disabled", false);
                                if (response.error == false) {
                                    setTimeout(function () {
                                        if (reload) {
                                            window.location.reload();
                                        }
                                    }, parseFloat(toastTimeOut) * 1000);
                                    $("#confirmUpdateStatusModal").modal(
                                        "hide"
                                    );
                                    var tableSelector =
                                        type == "project"
                                            ? "projects_table"
                                            : "task_table";
                                    var $table = $("#" + tableSelector);
                                    if ($table.length) {
                                        $table.bootstrapTable("refresh");
                                    }
                                    if ($("#activity_log_table").length) {
                                        $("#activity_log_table").bootstrapTable(
                                            "refresh"
                                        );
                                    }
                                    select.attr(
                                        "data-original-status-id",
                                        statusId
                                    );
                                    toastr.success(response.message);
                                } else {
                                    select
                                        .removeClass(newColorClass)
                                        .addClass(originalColorClass);
                                    select.val(originalStatusId);
                                    toastr.error(response.message);
                                }
                            },
                            error: function (xhr, status, error) {
                                $("#confirmUpdateStatus")
                                    .html(label_yes)
                                    .attr("disabled", false);
                                select
                                    .removeClass(newColorClass)
                                    .addClass(originalColorClass);
                                select.val(originalStatusId);
                                toastr.error("Something Went Wrong");
                            },
                        });
                    }
                );
            } else {
                $("#confirmUpdateStatus")
                    .html(label_yes)
                    .attr("disabled", false);
                select.val(originalStatusId);
                toastr.error(response.message);
            }
        },
        error: function (xhr, status, error) {
            toastr.error("Something Went Wrong");
        },
    });
    $("#confirmUpdateStatusModal").off(
        "click",
        ".btn-close, #declineUpdateStatus"
    );
    $("#confirmUpdateStatusModal").on(
        "click",
        ".btn-close, #declineUpdateStatus",
        function (e) {
            select.val(originalStatusId);
            select.removeClass(newColorClass).addClass(currentColorClass);
        }
    );
});
$(document).on("change", "#prioritySelect", function (e) {
    e.preventDefault();
    var id = $(this).data("id");
    var priorityId = this.value;
    var type = $(this).data("type") || "project";
    var reload = $(this).data("reload") || false;
    var select = $(this);
    var originalPriorityId = select.data("original-priority-id") || "";
    var originalColorClass = select.data("original-color-class");
    var classes = select.attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    var selectedOption = select.find("option:selected");
    var selectedOptionClasses = selectedOption.attr("class").split(" ");
    var newColorClass = "select-" + selectedOptionClasses[1];
    select.removeClass(currentColorClass).addClass(newColorClass);
    $("#confirmUpdatePriorityModal").modal("show"); // show the confirmation modal
    $("#confirmUpdatePriorityModal").off("click", "#confirmUpdatePriority");
    $("#confirmUpdatePriorityModal").on(
        "click",
        "#confirmUpdatePriority",
        function (e) {
            $("#confirmUpdatePriority")
                .html(label_please_wait)
                .attr("disabled", true);
            $.ajax({
                type: "POST",
                url: baseUrl + "/update-" + type + "-priority",
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').val(),
                },
                data: {
                    id: id,
                    priorityId: priorityId,
                },
                success: function (response) {
                    $("#confirmUpdatePriority")
                        .html(label_yes)
                        .attr("disabled", false);
                    if (response.error == false) {
                        setTimeout(function () {
                            if (reload) {
                                window.location.reload(); // Reload the current page
                            }
                        }, parseFloat(toastTimeOut) * 1000);
                        $("#confirmUpdatePriorityModal").modal("hide");
                        var tableSelector =
                            type == "project" ? "projects_table" : "task_table";
                        var $table = $("#" + tableSelector);
                        if ($table.length) {
                            $table.bootstrapTable("refresh");
                        }
                        if ($("#activity_log_table").length) {
                            $("#activity_log_table").bootstrapTable("refresh");
                        }
                        select.data("original-priority-id", priorityId);
                        toastr.success(response.message);
                    } else {
                        select
                            .removeClass(newColorClass)
                            .addClass(originalColorClass);
                        select.val(originalPriorityId);
                        toastr.error(response.message);
                    }
                },
                error: function (xhr, status, error) {
                    $("#confirmUpdatePriority")
                        .html(label_yes)
                        .attr("disabled", false);
                    // Handle error
                    select
                        .removeClass(newColorClass)
                        .addClass(originalColorClass);
                    select.val(originalPriorityId);
                    toastr.error("Something Went Wrong");
                },
            });
        }
    );
    // Handle modal close event
    $("#confirmUpdatePriorityModal").off(
        "click",
        ".btn-close, #declineUpdatePriority"
    );
    $("#confirmUpdatePriorityModal").on(
        "click",
        ".btn-close, #declineUpdatePriority",
        function (e) {
            // Set original priority when modal is closed without confirmation
            select.val(originalPriorityId);
            select.removeClass(newColorClass).addClass(currentColorClass);
        }
    );
});
$(document).on("click", ".quick-view", function (e) {
    e.preventDefault();
    var id = $(this).data("id");
    var type = $(this).data("type") || "task";
    $("#type").val(type);
    $("#typeId").val(id);
    $.ajax({
        url: baseUrl + "/" + type + "s/get/" + id,
        type: "GET",
        success: function (response) {
            if (response.error == false) {
                $("#quickViewModal").modal("show");
                if (type == "task" && response.task) {
                    $("#quickViewTitlePlaceholder").text(response.task.title);
                    $("#quickViewDescPlaceholder").html(
                        response.task.description
                    );
                } else if (type == "project" && response.project) {
                    $("#quickViewTitlePlaceholder").text(
                        response.project.title
                    );
                    $("#quickViewDescPlaceholder").html(
                        response.project.description
                    );
                }
                $("#typePlaceholder").text(
                    type == "task" ? label_task : label_project
                );
                $("#usersTable").bootstrapTable("refresh");
                $("#clientsTable").bootstrapTable("refresh");
            } else {
                toastr.error(response.message);
            }
        },
        error: function (xhr, status, error) {
            // Handle error
            toastr.error("Something Went Wrong");
        },
    });
});
$("#partialLeave, #updatePartialLeave").on("change", function () {
    var $form = $(this).closest("form"); // Get the closest form element
    var isChecked = $(this).prop("checked");
    if (isChecked) {
        // If the checkbox is checked
        $form
            .find(".leave-from-date-div")
            .removeClass("col-5")
            .addClass("col-3");
        $form.find(".leave-to-date-div").removeClass("col-5").addClass("col-3");
        $form
            .find(".leave-from-time-div, .leave-to-time-div")
            .removeClass("d-none");
    } else {
        // If the checkbox is unchecked, revert the changes
        $form.find('input[name="from_time"]').val("");
        $form.find('input[name="to_time"]').val("");
        $form
            .find(".leave-from-date-div")
            .removeClass("col-3")
            .addClass("col-5");
        $form.find(".leave-to-date-div").removeClass("col-3").addClass("col-5");
        $form
            .find(".leave-from-time-div, .leave-to-time-div")
            .addClass("d-none");
    }
});
$(".leaveVisibleToAll").on("change", function () {
    var $form = $(this).closest("form"); // Get the closest form element
    var isChecked = $(this).prop("checked");
    if (isChecked) {
        // If the checkbox is checked
        $form.find(".leaveVisibleToDiv").addClass("d-none");
        var visibleToSelect = $form.find(
            '.js-example-basic-multiple[name="visible_to_ids[]"]'
        );
        visibleToSelect.val(null).trigger("change");
    } else {
        // If the checkbox is unchecked, revert the changes
        $form.find(".leaveVisibleToDiv").removeClass("d-none");
    }
});
$(document).ready(function () {
    var upcomingBDCalendarInitialized = false;
    var upcomingWACalendarInitialized = false;
    var membersOnLeaveCalendarInitialized = false;

    // Listen for the inner calendar tab click
    $(document).on("shown.bs.tab", ".calendar-button", function (event) {
        var tabId = $(event.target).attr("data-bs-target");

        if (tabId === "#upcomingBirthdaysCalendar-calendar" && !upcomingBDCalendarInitialized) {
            initializeUpcomingBDCalendar();
            upcomingBDCalendarInitialized = true;
        }
        else if (tabId === "#upcomingWorkAnniversariesCalendar-calendar" && !upcomingWACalendarInitialized) {
            initializeUpcomingWACalendar();
            upcomingWACalendarInitialized = true;
        }
        else if (tabId === "#membersOnLeaveCalendar-calendar" && !membersOnLeaveCalendarInitialized) {
            initializeMembersOnLeaveCalendar();
            membersOnLeaveCalendarInitialized = true;
        }
    });
});

function initializeUpcomingBDCalendar() {
    var upcomingBDCalendar = document.getElementById(
        "upcomingBirthdaysCalendar"
    );
    // Check if the calendar element exists
    if (upcomingBDCalendar) {
        var BDcalendar = new FullCalendar.Calendar(upcomingBDCalendar, {
            plugins: ["interaction", "dayGrid", "list"],
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,listYear",
            },
            editable: true,
            eventLimit: 4, // Show max 4 events per day
            events: function (fetchInfo, successCallback, failureCallback) {
                // Make AJAX request to fetch dynamic data
                $.ajax({
                    url: baseUrl + "/home/upcoming-birthdays-calendar",
                    type: "GET",
                    data: {
                        startDate: fetchInfo.startStr,
                        endDate: fetchInfo.endStr,
                    },
                    success: function (response) {
                        // Parse and format dynamic data for FullCalendar
                        var events = response.map(function (event) {
                            return {
                                title: event.title,
                                start: event.start,
                                end: event.start,
                                backgroundColor: event.backgroundColor,
                                borderColor: event.borderColor,
                                textColor: event.textColor,
                                userId: event.userId,
                                type: event.type,
                            };
                        });
                        // Invoke success callback with dynamic data
                        successCallback(events);
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        // Invoke failure callback if there's an error
                        failureCallback(error);
                    },
                });
            },
            eventClick: function (info) {
                if (
                    info.event.extendedProps &&
                    info.event.extendedProps.userId
                ) {
                    var userId = info.event.extendedProps.userId;
                    // Check if the type is 'client'
                    if (info.event.extendedProps.type === "client") {
                        var url = baseUrl + "/clients/profile/" + userId; // Redirect to client's profile
                    } else {
                        var url = baseUrl + "/users/profile/" + userId; // Redirect to user's profile
                    }
                    window.location.href = url;
                }
            },
            eventMouseEnter: function (info) {
                // Create a tooltip element
                var tooltip = document.createElement("div");
                tooltip.innerHTML = info.event.title; // Set the tooltip content
                tooltip.style.position = "absolute";
                tooltip.style.background = "rgba(0, 0, 0, 0.8)";
                tooltip.style.color = "#fff";
                tooltip.style.padding = "5px";
                tooltip.style.borderRadius = "5px";
                tooltip.style.zIndex = "1000";
                tooltip.style.pointerEvents = "none"; // Prevent mouse events
                // Append the tooltip to the body
                document.body.appendChild(tooltip);
                // Position the tooltip
                var rect = info.el.getBoundingClientRect();
                tooltip.style.left = rect.left + window.scrollX + "px";
                tooltip.style.top = rect.bottom + window.scrollY + "px";
                // Remove tooltip on mouse leave
                info.el.addEventListener(
                    "mouseleave",
                    function () {
                        document.body.removeChild(tooltip);
                    },
                    { once: true }
                );
            },
        });
        BDcalendar.render();
    }
}
function initializeUpcomingWACalendar() {
    var upcomingWACalendar = document.getElementById(
        "upcomingWorkAnniversariesCalendar"
    );
    // Check if the calendar element exists
    if (upcomingWACalendar) {
        var WAcalendar = new FullCalendar.Calendar(upcomingWACalendar, {
            plugins: ["interaction", "dayGrid", "list"],
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,listYear",
            },
            editable: true,
            height: "auto",
            eventLimit: 4, // Show max 4 events per day
            events: function (fetchInfo, successCallback, failureCallback) {
                // Make AJAX request to fetch dynamic data
                $.ajax({
                    url: baseUrl + "/home/upcoming-work-anniversaries-calendar",
                    type: "GET",
                    data: {
                        startDate: fetchInfo.startStr,
                        endDate: fetchInfo.endStr,
                    },
                    success: function (response) {
                        // Parse and format dynamic data for FullCalendar
                        var events = response.map(function (event) {
                            return {
                                title: event.title,
                                start: event.start,
                                end: event.start,
                                backgroundColor: event.backgroundColor,
                                borderColor: event.borderColor,
                                textColor: event.textColor,
                                userId: event.userId,
                                type: event.type,
                            };
                        });
                        // Invoke success callback with dynamic data
                        successCallback(events);
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        // Invoke failure callback if there's an error
                        failureCallback(error);
                    },
                });
            },
            eventClick: function (info) {
                if (
                    info.event.extendedProps &&
                    info.event.extendedProps.userId
                ) {
                    var userId = info.event.extendedProps.userId;
                    // Check if the type is 'client'
                    if (info.event.extendedProps.type === "client") {
                        var url = baseUrl + "/clients/profile/" + userId; // Redirect to client's profile
                    } else {
                        var url = baseUrl + "/users/profile/" + userId; // Redirect to user's profile
                    }
                    window.location.href = url;
                }
            },
            eventMouseEnter: function (info) {
                // Create a tooltip element
                var tooltip = document.createElement("div");
                tooltip.innerHTML = info.event.title; // Set the tooltip content
                tooltip.style.position = "absolute";
                tooltip.style.background = "rgba(0, 0, 0, 0.8)";
                tooltip.style.color = "#fff";
                tooltip.style.padding = "5px";
                tooltip.style.borderRadius = "5px";
                tooltip.style.zIndex = "1000";
                tooltip.style.pointerEvents = "none"; // Prevent mouse events
                // Append the tooltip to the body
                document.body.appendChild(tooltip);
                // Position the tooltip
                var rect = info.el.getBoundingClientRect();
                tooltip.style.left = rect.left + window.scrollX + "px";
                tooltip.style.top = rect.bottom + window.scrollY + "px";
                // Remove tooltip on mouse leave
                info.el.addEventListener(
                    "mouseleave",
                    function () {
                        document.body.removeChild(tooltip);
                    },
                    { once: true }
                );
            },
        });
        WAcalendar.render();
    }
}
function initializeMembersOnLeaveCalendar() {
    var membersOnLeaveCalendar = document.getElementById(
        "membersOnLeaveCalendar"
    );
    // Check if the calendar element exists
    if (membersOnLeaveCalendar) {
        var MOLcalendar = new FullCalendar.Calendar(membersOnLeaveCalendar, {
            plugins: ["interaction", "dayGrid", "list"],
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,listYear",
            },
            editable: true,
            displayEventTime: true,
            eventLimit: 4, // Show max 4 events per day
            events: function (fetchInfo, successCallback, failureCallback) {
                // Make AJAX request to fetch dynamic data
                $.ajax({
                    url: baseUrl + "/home/members-on-leave-calendar",
                    type: "GET",
                    data: {
                        startDate: fetchInfo.startStr,
                        endDate: fetchInfo.endStr,
                    },
                    success: function (response) {
                        // Parse and format dynamic data for FullCalendar
                        var events = response.map(function (event) {
                            var eventData = {
                                title: event.title,
                                start: event.start,
                                end: moment(event.end)
                                    .add(1, "days")
                                    .format("YYYY-MM-DD"),
                                backgroundColor: event.backgroundColor,
                                borderColor: event.borderColor,
                                textColor: event.textColor,
                                userId: event.userId,
                            };
                            // Check if the event is partial and has start and end times
                            if (event.startTime && event.endTime) {
                                // Include start and end times directly in the event data
                                eventData.extendedProps = {
                                    startTime: event.startTime,
                                    endTime: event.endTime,
                                };
                            }
                            return eventData;
                        });
                        // Invoke success callback with dynamic data
                        successCallback(events);
                    },
                    error: function (xhr, status, error) {
                        console.error(xhr.responseText);
                        // Invoke failure callback if there's an error
                        failureCallback(error);
                    },
                });
            },
            eventClick: function (info) {
                if (
                    info.event.extendedProps &&
                    info.event.extendedProps.userId
                ) {
                    var userId = info.event.extendedProps.userId;
                    var url = baseUrl + "/users/profile/" + userId;
                    window.location.href = url;
                }
            },
            eventMouseEnter: function (info) {
                // Create a tooltip element
                var tooltip = document.createElement("div");
                tooltip.innerHTML = info.event.title; // Set the tooltip content
                tooltip.style.position = "absolute";
                tooltip.style.background = "rgba(0, 0, 0, 0.8)";
                tooltip.style.color = "#fff";
                tooltip.style.padding = "5px";
                tooltip.style.borderRadius = "5px";
                tooltip.style.zIndex = "1000";
                tooltip.style.pointerEvents = "none"; // Prevent mouse events
                // Append the tooltip to the body
                document.body.appendChild(tooltip);
                // Position the tooltip
                var rect = info.el.getBoundingClientRect();
                tooltip.style.left = rect.left + window.scrollX + "px";
                tooltip.style.top = rect.bottom + window.scrollY + "px";
                // Remove tooltip on mouse leave
                info.el.addEventListener(
                    "mouseleave",
                    function () {
                        document.body.removeChild(tooltip);
                    },
                    { once: true }
                );
            },
        });
        MOLcalendar.render();
    }
}
// Preprocess permissions to avoid redundant checks
var permissionSet = new Set(permissions);
$(document).ready(function () {
    // Loop through classes starting with 'action-'
    $('[class*="action_"]').each(function () {
        // Extract the part of class name after "action-"
        var className = $(this).attr("class");
        var permission = className.substring(
            className.indexOf("action_") + "action_".length
        );
        // Check if the user is not an admin and if the permission does not exist
        if (
            (typeof isAdmin == "undefined" || !isAdmin) &&
            !permissionSet.has(permission)
        ) {
            $(this).addClass("d-none");
        }
    });
});
$(document).on("click", ".save-column-visibility", function (e) {
    e.preventDefault();
    var tableName = $(this).data("table");
    var type = $(this).data("type");
    type = type.replace("-", "_");
    $("#confirmSaveColumnVisibility").modal("show");
    $("#confirmSaveColumnVisibility").off("click", "#confirm");
    $("#confirmSaveColumnVisibility").on("click", "#confirm", function () {
        $("#confirmSaveColumnVisibility")
            .find("#confirm")
            .html(label_please_wait)
            .attr("disabled", true);
        var visibleColumns = [];
        $("#" + tableName)
            .bootstrapTable("getVisibleColumns")
            .forEach((column) => {
                if (!column.checkbox) {
                    visibleColumns.push(column.field);
                }
            });
        // Send preferences to the server
        $.ajax({
            url: baseUrl + "/save-column-visibility",
            type: "POST",
            data: {
                type: type,
                visible_columns: JSON.stringify(visibleColumns),
            },
            headers: {
                "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
            },
            success: function (response) {
                $("#confirmSaveColumnVisibility")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                if (response.error == false) {
                    $("#confirmSaveColumnVisibility").modal("hide");
                    toastr.success(response.message);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (data) {
                $("#confirmSaveColumnVisibility")
                    .find("#confirm")
                    .html(label_yes)
                    .attr("disabled", false);
                $("#confirmSaveColumnVisibility").modal("hide");
                toastr.error(label_something_went_wrong);
            },
        });
    });
});
$(document).on("click", ".viewAssigned", function (e) {
    e.preventDefault();
    var projectsUrl = baseUrl + "/projects/listing";
    var tasksUrl = baseUrl + "/tasks/list";
    var id = $(this).data("id");
    var type = $(this).data("type");
    var user = $(this).data("user");
    projectsUrl = projectsUrl + (id ? "/" + id : "");
    tasksUrl = tasksUrl + (id ? "/" + id : "");
    $("#viewAssignedModal").modal("show");
    var projectsTable = $("#viewAssignedModal").find("#projects_table");
    var tasksTable = $("#viewAssignedModal").find("#task_table");
    if (type === "tasks") {
        $('.nav-link[data-bs-target="#navs-top-view-assigned-tasks"]').tab(
            "show"
        );
        $(
            '.nav-link[data-bs-target="#navs-top-view-assigned-projects"]'
        ).removeClass("active");
        $("#navs-top-view-assigned-projects").removeClass("show active");
        $("#navs-top-view-assigned-tasks").addClass("show active");
    } else {
        $('.nav-link[data-bs-target="#navs-top-view-assigned-projects"]').tab(
            "show"
        );
        $(
            '.nav-link[data-bs-target="#navs-top-view-assigned-tasks"]'
        ).removeClass("active");
        $("#navs-top-view-assigned-tasks").removeClass("show active");
        $("#navs-top-view-assigned-projects").addClass("show active");
    }
    $("#userPlaceholder").text(user);
    $(projectsTable).bootstrapTable("refresh", {
        url: projectsUrl,
    });
    $(tasksTable).bootstrapTable("refresh", {
        url: tasksUrl,
    });
});
$(document).on("click", ".openCreateStatusModal", function (e) {
    e.preventDefault();
    $("#create_status_modal").modal("show");
});
$(document).on("click", ".openCreatePriorityModal", function (e) {
    e.preventDefault();
    $("#create_priority_modal").modal("show");
});
$(document).on("click", ".openCreateTagModal", function (e) {
    e.preventDefault();
    $("#create_tag_modal").modal("show");
});
$(document).on("click", ".openCreateContractTypeModal", function (e) {
    e.preventDefault();
    $("#create_contract_type_modal").modal("show");
});
$(document).on("click", ".openCreatePmModal", function (e) {
    e.preventDefault();
    $("#create_pm_modal").modal("show");
});
$(document).on("click", ".openCreateAllowanceModal", function (e) {
    e.preventDefault();
    $("#create_allowance_modal").modal("show");
});
$(document).on("click", ".openCreateDeductionModal", function (e) {
    e.preventDefault();
    $("#create_deduction_modal").modal("show");
});
function formatTag(tag) {
    if (!tag.id) {
        return tag.text;
    }
    var color = tag.color;
    return $(
        '<span class="badge bg-label-' + color + '">' + tag.text + "</span>"
    );
}
/**
 * Initializes Select2 dropdowns for status and priority fields with dynamic parent detection for modals and offcanvas.
 * Formats dropdown options with colored badges and handles clearing behavior for priority dropdowns.
 *
 * @listens document.ready - Executes when the DOM is fully loaded.
 * @returns {void}
 */
$(document).ready(function () {
    /**
     * Formats status dropdown options with a colored badge.
     *
     * @param {Object} status - The Select2 option object containing id, text, and data attributes.
     * @returns {jQuery|string} - A jQuery element with a colored badge or the plain text if no id is present.
     */
    function formatStatus(status) {
        if (!status.id) {
            return status.text;
        }
        var color = $(status.element).data("color") || "primary"; // Fallback to 'primary' if no color
        return $('<span class="badge bg-label-' + color + '">' + status.text + "</span>");
    }

    /**
     * Formats priority dropdown options with a colored badge.
     *
     * @param {Object} priority - The Select2 option object containing id, text, and data attributes.
     * @returns {jQuery|string} - A jQuery element with a colored badge or the plain text if no id is present.
     */
    function formatPriority(priority) {
        if (!priority.id) {
            return priority.text;
        }
        var color = $(priority.element).data("color") || "primary"; // Fallback to 'primary' if no color
        return $('<span class="badge bg-label-' + color + '">' + priority.text + "</span>");
    }

    /**
     * Initializes Select2 for elements with class 'statusDropdown'.
     */
    $(".statusDropdown").each(function () {
        var $this = $(this);
        var dropdownParent = $this.closest(".modal, .offcanvas").length
            ? $this.closest(".modal, .offcanvas")
            : $(document.body); // Fallback to body if no modal/offcanvas

        $this.select2({
            dropdownParent: dropdownParent,
            templateResult: formatStatus,
            templateSelection: formatStatus,
            escapeMarkup: function (markup) {
                return markup;
            },
            language: {
                noResults: function () {
                    return label_no_results_found;
                },
                searching: function () {
                    return label_searching;
                },
            },
        });
    });

    /**
     * Initializes Select2 for elements with class 'priorityDropdown', with clearable options.
     */
    $(".priorityDropdown").each(function () {
        var $this = $(this);
        var dropdownParent = $this.closest(".modal, .offcanvas").length
            ? $this.closest(".modal, .offcanvas")
            : $(document.body); // Fallback to body if no modal/offcanvas

        $this.select2({
            dropdownParent: dropdownParent,
            templateResult: formatPriority,
            templateSelection: formatPriority,
            allowClear: true,
            escapeMarkup: function (markup) {
                return markup;
            },
            language: {
                noResults: function () {
                    return label_no_results_found;
                },
                searching: function () {
                    return label_searching;
                },
            },
        });

        // Prevent dropdown from opening when clear button is clicked
        $this
            .on("select2:unselecting", function (e) {
                $(this).data("state", "unselecting");
            })
            .on("select2:open", function (e) {
                if ($(this).data("state") === "unselecting") {
                    $(this).removeData("state");
                    $this.select2("close");
                }
            });
    });
});
$(document).on("change", 'select[name="color"]', function (e) {
    e.preventDefault();
    var select = $(this);
    var classes = $(this).attr("class").split(" ");
    var currentColorClass = classes.filter(function (className) {
        return className.startsWith("select-");
    })[0];
    var selectedOption = $(this).find("option:selected");
    var selectedOptionClasses = selectedOption.attr("class").split(" ");
    var newColorClass = "select-" + selectedOptionClasses[1];
    select.removeClass(currentColorClass).addClass(newColorClass);
});
function toggleChatIframe() {
    var iframeContainer = document.getElementById("chatIframeContainer");
    if (
        iframeContainer.style.display === "none" ||
        iframeContainer.style.display === ""
    ) {
        iframeContainer.style.display = "block";
    } else {
        iframeContainer.style.display = "none";
    }
}
$(document).ready(function () {
    if ($("#selectAllPreferences").length) {
        // Check initial state of checkboxes and update selectAllPreferences checkbox
        updateSelectAll();
        // Select/deselect all checkboxes when the selectAllPreferences checkbox is clicked
        $("#selectAllPreferences").click(function () {
            var isChecked = $(this).prop("checked");
            $('input[name="enabled_notifications[]"]:not(:disabled)').prop(
                "checked",
                isChecked
            );
        });
        // Update the selectAllPreferences checkbox state based on the checkboxes' status
        $('input[name="enabled_notifications[]"]').change(function () {
            updateSelectAll();
        });
        // Function to update selectAllPreferences checkbox based on checkboxes' status
        function updateSelectAll() {
            var allChecked =
                $('input[name="enabled_notifications[]"]:not(:disabled)')
                    .length ===
                $(
                    'input[name="enabled_notifications[]"]:not(:disabled):checked'
                ).length;
            $("#selectAllPreferences").prop("checked", allChecked);
        }
    }
});
// $(window).on('load', function () {
//     // Select the elements and replace the text
//     $('.pagination-info').each(function () {
//         var text = $(this).text();
//         text = text.replace("Showing", label_showing)
//             .replace("to", label_to_for_pagination)
//             .replace("of", label_of)
//             .replace("rows", label_rows);
//         $(this).text(text);
//     });
//     $('.page-list').each(function () {
//         var text = $(this).html();
//         text = text.replace("rows per page", label_rows_per_page);
//         $(this).html(text);
//     });
// });
$("#internal_client").change(function () {
    var isChecked = $(this).prop("checked");
    $("#password, #password_confirmation").val("");
    $("#passDiv, #confirmPassDiv, #statusDiv, #requireEvDiv").toggleClass(
        "d-none",
        isChecked
    );
    $("#client_deactive").prop("checked", true);
    $("#require_ev_" + (isChecked ? "no" : "yes")).prop("checked", true);
    $("#password").next(".error-message").remove();
    $("#password_confirmation").next(".error-message").remove();
});
$("#update_internal_client").change(function () {
    var isChecked = $(this).prop("checked");
    $("#password, #password_confirmation").val("");
    $("#passDiv, #confirmPassDiv, #statusDiv, #requireEvDiv").toggleClass(
        "d-none",
        isChecked
    );
    // Remove .error-message elements next to #password and #password_confirmation
    $("#password").next(".error-message").remove();
    $("#password_confirmation").next(".error-message").remove();
});
$(document).ready(function () {
    $("#previewToast").click(function () {
        var previewToastPosition = $("#toastPosition").val();
        var toastTimeoutInput = $("#toastTimeout");
        var previewToastTimeout = parseFloat(toastTimeoutInput.val());
        // Validate toast timeout is not blank and is a positive number
        if (isNaN(previewToastTimeout) || previewToastTimeout <= 0) {
            toastr.options = {
                positionClass: toastPosition,
                timeOut: parseFloat(toastTimeOut) * 1000,
                showDuration: "300",
                hideDuration: "1000",
                extendedTimeOut: "1000",
                progressBar: true,
                closeButton: true,
            };
            toastr.error("Please enter a valid timeout value in seconds.");
            toastTimeoutInput.focus();
            return;
        }
        // Convert timeout to milliseconds
        previewToastTimeout *= 1000;
        toastr.options = {
            positionClass: previewToastPosition,
            timeOut: previewToastTimeout,
            showDuration: "300",
            hideDuration: "1000",
            extendedTimeOut: "1000",
            progressBar: true,
            closeButton: true,
        };
        toastr.success(
            "This is a preview of your toast message!",
            "Toast Preview"
        );
    });
});
$(document).ready(function () {
    var $canvas = $("#promisor_sign");
    var $resetButton = $("#reset_promisor_sign");
    // Function to resize canvas
    function resizeCanvas() {
        var $modalBody = $canvas.closest(".modal-body");
        var maxWidth = $modalBody.width() - 32; // Subtract padding
        var aspectRatio = $canvas[0].width / $canvas[0].height;
        $canvas.attr("width", maxWidth);
        $canvas.attr("height", maxWidth / aspectRatio);
    }
    // Resize canvas when the modal is shown
    $("#create_contract_sign_modal").on("shown.bs.modal", function () {
        resizeCanvas();
    });
    // Handle canvas reset
    $resetButton.on("click", function () {
        var context = $canvas[0].getContext("2d");
        context.clearRect(0, 0, $canvas[0].width, $canvas[0].height);
    });
});
$(document).on("click", "#testSmsSettingsButton", function (e) {
    e.preventDefault();
    $("#testSmsSettingsModal").modal("show");
});
$("#testSmsSettingsForm").on("submit", function (event) {
    event.preventDefault();
    var recipientNumber = $("#testSmsRecipientNumber").val();
    var recipientCountryCode = $("#testSmsRecipientCountryCode").val();
    var message = $("#testSmsMessage").val();
    // AJAX request
    $.ajax({
        url: baseUrl + "/settings/notifications/test",
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        data: {
            type: "sms",
            recipientCountryCode: recipientCountryCode,
            recipientNumber: recipientNumber,
            message: message,
        },
        dataType: "json",
        beforeSend: function () {
            $("#performTestSmsSettingsButton")
                .prop("disabled", true)
                .html(label_sending);
        },
        success: function (response) {
            // Handle successful response
            $("#performTestSmsSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#smsTestResponse").removeClass("d-none");
            $("#smsResponseText").text(JSON.stringify(response, null, 2));
        },
        error: function (xhr, status, error) {
            // Handle error
            $("#performTestSmsSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#smsTestResponse").removeClass("d-none");
            $("#smsResponseText").text("Error: " + xhr.responseText);
        },
    });
});
$("#testSmsSettingsModal").on("hidden.bs.modal", function () {
    $("#smsTestResponse").addClass("d-none");
    $("#smsResponseText").text("");
});
$(document).on("click", "#testWhatsappSettingsButton", function (e) {
    e.preventDefault();
    $("#testWhatsappSettingsModal").modal("show");
});
$("#testWhatsappSettingsForm").on("submit", function (event) {
    event.preventDefault();
    var recipientNumber = $("#testWhatsappRecipientNumber").val();
    var recipientCountryCode = $("#testWhatsappRecipientCountryCode").val();
    var message = $("#testWhatsappMessage").val();
    // AJAX request
    $.ajax({
        url: baseUrl + "/settings/notifications/test",
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        data: {
            type: "whatsapp",
            recipientCountryCode: recipientCountryCode,
            recipientNumber: recipientNumber,
            message: message,
        },
        dataType: "json",
        beforeSend: function () {
            $("#performTestWhatsappSettingsButton")
                .prop("disabled", true)
                .html(label_sending);
        },
        success: function (response) {
            // Handle successful response
            $("#performTestWhatsappSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#whatsappTestResponse").removeClass("d-none");
            $("#whatsappResponseText").text(JSON.stringify(response, null, 2));
        },
        error: function (xhr, status, error) {
            // Handle error
            $("#performTestWhatsappSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#whatsappTestResponse").removeClass("d-none");
            $("#whatsappResponseText").text("Error: " + xhr.responseText);
        },
    });
});
$("#testWhatsappSettingsModal").on("hidden.bs.modal", function () {
    $("#whatsappTestResponse").addClass("d-none");
    $("#whatsappResponseText").text("");
});
$(document).on("click", "#testSlackSettingsButton", function (e) {
    e.preventDefault();
    $("#testSlackSettingsModal").modal("show");
});
$("#testSlackSettingsForm").on("submit", function (event) {
    event.preventDefault();
    var recipientEmail = $("#testSlackRecipientEmail").val();
    var message = $("#testSlackMessage").val();
    // AJAX request
    $.ajax({
        url: baseUrl + "/settings/notifications/test",
        method: "POST",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
        },
        data: {
            type: "slack",
            recipientEmail: recipientEmail,
            message: message,
        },
        dataType: "json",
        beforeSend: function () {
            $("#performTestSlackSettingsButton")
                .prop("disabled", true)
                .html(label_sending);
        },
        success: function (response) {
            // Handle successful response
            $("#performTestSlackSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#slackTestResponse").removeClass("d-none");
            $("#slackResponseText").text(JSON.stringify(response, null, 2));
        },
        error: function (xhr, status, error) {
            // Handle error
            $("#performTestSlackSettingsButton")
                .prop("disabled", false)
                .html(label_submit);
            $("#slackTestResponse").removeClass("d-none");
            $("#slackResponseText").text("Error: " + xhr.responseText);
        },
    });
});
$("#testSlackSettingsModal").on("hidden.bs.modal", function () {
    $("#slackTestResponse").addClass("d-none");
    $("#slackResponseText").text("");
});
$(document).ready(function () {
    // Function to validate input
    function validateCurrencyInput() {
        var input = $(this);
        var value = input.val();
        // Check for disallowed characters
        if (/[^0-9.,]/.test(value)) {
            toastr.error(label_currency_restriction);
            value = value.replace(/[^0-9.,]/g, "");
        }
        // Check for multiple decimal points
        var multipleDecimalPoints = value.split(".").length - 1;
        if (multipleDecimalPoints > 1) {
            toastr.error(label_currency_restriction_1);
            // Keep only the first decimal point
            value = value.replace(/(\..*)\./g, "$1");
        }
        input.val(value);
    }
    // Apply validation to all inputs with class "currency"
    $(document).on("input", ".currency", validateCurrencyInput);
    function validateDecimalInput() {
        var input = $(this);
        var value = input.val();
        // Remove any commas
        value = value.replace(/,/g, "");
        // Check for disallowed characters (anything other than digits and decimal point)
        if (/[^0-9.]/.test(value)) {
            toastr.error(label_currency_restriction_2);
            value = value.replace(/[^0-9.]/g, "");
        }
        // Check for multiple decimal points
        var multipleDecimalPoints = value.split(".").length - 1;
        if (multipleDecimalPoints > 1) {
            toastr.error(label_currency_restriction_1);
            // Keep only the first decimal point
            value = value.replace(/(\..*)\./g, "$1");
        }
        input.val(value);
    }
    $(document).on("input", ".decimal-currency", validateDecimalInput);
});
$(document).ready(function () {
    const input = $("#phone")[0]; // Get the actual DOM element for intlTelInput
    // Check if the input element exists and has the data-type="create" attribute
    if (input) {
        var $countryCodeIsoInput = $("#country_iso_code");
        var $countryCodeNumInput = $("#country_code");
        var initialCountryCode = "";
        // Check if the hidden input exists and has a value
        if ($countryCodeIsoInput.length && $countryCodeIsoInput.val()) {
            initialCountryCode = $countryCodeIsoInput.val();
        }
        // Determine whether to set initial country to 'auto' or leave it unset
        var auto = $(input).data("type") === "create" ? "auto" : "";
        // Initialize intlTelInput with the appropriate initial country setting
        const iti = window.intlTelInput(input, {
            initialCountry: initialCountryCode || auto, // Set to 'auto' or initialCountryCode if available
            geoIpLookup: (callback) => {
                fetch("https://ipapi.co/json")
                    .then((res) => res.json())
                    .then((data) => callback(data.country_code))
                    .catch(() => callback("us"));
            },
            utilsScript:
                "https://cdn.jsdelivr.net/npm/intl-tel-input@24.3.4/build/js/utils.js",
            separateDialCode: true,
        });
        $(input).on("countrychange", () => {
            const countryData = iti.getSelectedCountryData();
            if (countryData && countryData.iso2 && countryData.dialCode) {
                // Update the hidden input with the selected country code
                $countryCodeIsoInput.val(countryData.iso2);
                $countryCodeNumInput.val("+" + countryData.dialCode);
            } else {
                // Clear the hidden inputs if the country data is not valid
                $countryCodeIsoInput.val("");
                $countryCodeNumInput.val("");
                iti.setCountry("");
            }
        });
        // Clear and reset country selection when the phone input is cleared
        $(input).on("input", function () {
            if ($(this).val() === "") {
                $countryCodeIsoInput.val("");
                $countryCodeNumInput.val("");
                iti.setCountry("");
            }
        });
        // Add functionality to clear the phone input and reset the country code
        $(".clear-input").on("click", function () {
            $(input).val(""); // Clear the phone input
            $countryCodeIsoInput.val(""); // Clear the hidden country code fields
            $countryCodeNumInput.val("");
            iti.setCountry(""); // Clear the country flag
        });
    }
});
function initSelect2WithAjax(selector, type) {
    $(selector).each(function () {
        if ($(this).length) {
            var $this = $(this);
            var allowClear =
                $this.data("allow-clear") === "false" ? false : true;
            var leaveVisibleToUsers = $this.data("leave-visible-to-users");
            leaveVisibleToUsers =
                leaveVisibleToUsers == undefined
                    ? false
                    : leaveVisibleToUsers === false
                        ? false
                        : true;
            var ignoreAdmins = $this.data("ignore-admins");
            ignoreAdmins =
                ignoreAdmins == undefined
                    ? false
                    : ignoreAdmins === false
                        ? false
                        : true;
            // Check if the 'data-consider-workspace' attribute is defined
            var considerWorkspace = $this.data("consider-workspace");
            // If 'considerWorkspace' is undefined, default to true
            considerWorkspace =
                considerWorkspace == undefined
                    ? true
                    : considerWorkspace === false
                        ? false
                        : true;
            var singleSelect =
                $this.data("single-select") === undefined ||
                    $this.data("single-select") === false
                    ? false
                    : true;

            // New: Check if initial values should be loaded
            var loadInitialValues = $this.data("load-initial") !== false; // Default to true unless explicitly set to false
            var initialLimit = $this.data("initial-limit") || 10; // Default to 10 initial items

            var ajaxOptions = {
                ajax: {
                    url: "/search", // API endpoint to fetch data dynamically
                    dataType: "json",
                    delay: 250,
                    data: function (params) {
                        var requestData = {
                            q: params.term, // search term
                            type: type, // dynamic type: 'tags', 'statuses', 'priorities'
                            considerWorkspace: considerWorkspace,
                            leaveVisibleToUsers: leaveVisibleToUsers,
                            ignoreAdmins: ignoreAdmins,
                        };

                        // If no search term and initial values should be loaded
                        if (!params.term && loadInitialValues) {
                            requestData.initial = true;
                            requestData.limit = initialLimit;
                        }

                        return requestData;
                    },
                    processResults: function (data) {
                        return {
                            results: data.results.map(function (item) {
                                // Handle 'color' only for 'tags'
                                // if (type === 'tags') {
                                //     return {
                                //         id: item.id,
                                //         text: item.text,
                                //         color: item.color
                                //     };
                                // }
                                // Default handling for other types
                                return {
                                    id: item.id,
                                    text: item.text,
                                };
                            }),
                        };
                    },
                    cache: true,
                },
                minimumInputLength: loadInitialValues ? 0 : 1, // Allow opening without typing if initial values are enabled
                allowClear: allowClear,
                closeOnSelect: singleSelect,
                language: {
                    inputTooShort: function () {
                        return label_please_type_at_least_1_character;
                    },
                    searching: function () {
                        return label_searching;
                    },
                    noResults: function () {
                        return label_no_results_found;
                    },
                },
            };

            // Apply specific templates if type is 'tags'
            // if (type === 'tags') {
            //     ajaxOptions.templateResult = formatTag;
            //     ajaxOptions.templateSelection = formatTag;
            //     ajaxOptions.escapeMarkup = function (markup) {
            //         return markup; // Prevent escaping of markup
            //     };
            // }

            // Check if the element is inside a modal
            if (
                $this.closest(".modal").length &&
                $this.data("single-select") == true
            ) {
                var modalId = $this.closest(".modal").attr("id"); // Get the ID of the closest .modal
                if (modalId) {
                    ajaxOptions.dropdownParent = $("#" + modalId); // Use the ID to reference the modal
                }
            }

            $this.select2(ajaxOptions);

            $(".cancel-button").on("click", function () {
                $this.select2("close"); // Close the dropdown
            });
        }
    });
}
$(document).ready(function () {
    initSelect2WithAjax(".projects_select", "projects");
    initSelect2WithAjax(".users_select", "users");
    initSelect2WithAjax(".clients_select", "clients");
    initSelect2WithAjax(".tags_select", "tags");
    initSelect2WithAjax(".contract_types_select", "contract_types");
    initSelect2WithAjax(".expense_types_select", "expense_types");
    initSelect2WithAjax(".allowances_select", "allowances");
    initSelect2WithAjax(".deductions_select", "deductions");
    initSelect2WithAjax(".items_select", "items");
    initSelect2WithAjax(".invoices_select", "invoices");
    initSelect2WithAjax(".statuses_filter", "statuses");
    initSelect2WithAjax(".priorities_filter", "priorities");
    initSelect2WithAjax("#select_lead_source", "lead_sources");
    initSelect2WithAjax("#select_lead_stage", "lead_stages");
    initSelect2WithAjax("#select_lead_assignee", "users");
    initSelect2WithAjax("#create_follow_up_assigned_to", "users");
    initSelect2WithAjax("#edit_follow_up_assigned_to", "users");
    initSelect2WithAjax("#selected_sources", "lead_sources");
    initSelect2WithAjax("#selected_stages", "lead_stages");
    initSelect2WithAjax("#select_candidate_statuses", "candidate_statuses");
    initSelect2WithAjax('.select-interview-candidate', "interview_candidates");
    initSelect2WithAjax('.select-interview-interviewer', "interview_interviewer");


    $("#create_task_modal, #edit_task_modal")
        .find('select[name="user_id[]"]')
        .each(function () {
            if ($(this).length) {
                $(this).select2({
                    minimumInputLength: 1,
                    allowClear: true,
                    language: {
                        inputTooShort: function () {
                            return label_please_type_at_least_1_character;
                        },
                        searching: function () {
                            return label_searching;
                        },
                        noResults: function () {
                            return label_no_results_found;
                        },
                    },
                });
            }
        });
});
$(document).ready(function () {
    // Function to load users for a specific project
    function loadProjectUsers(projectId) {
        var usersSelect = $("#create_task_modal").find(
            'select[name="user_id[]"]'
        );
        usersSelect.empty(); // Clear any previous options
        if (projectId) {
            $.ajax({
                url: baseUrl + "/projects/get/" + projectId, // Endpoint to get users based on project
                type: "GET",
                success: function (response) {
                    // Add the project users as options
                    if (response.users && response.users.length > 0) {
                        // Iterate through the users and add them to the select element
                        response.users.forEach(function (user) {
                            var userOption = new Option(
                                user.first_name + " " + user.last_name,
                                user.id,
                                false,
                                false
                            );
                            usersSelect.append(userOption);
                        });
                        // If task_accessibility is 'project_users', select the users automatically
                        if (
                            response.project.task_accessibility ===
                            "project_users"
                        ) {
                            var projectUserIds = response.users.map(function (
                                user
                            ) {
                                return user.id;
                            });
                            // Set selected users
                            usersSelect.val(projectUserIds);
                        }
                        // Trigger select2 to update the selected values
                        usersSelect.trigger("change");
                    } else {
                        // Handle case when there are no users
                        usersSelect.val(null).trigger("change");
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error loading project users:", error);
                },
            });
        }
    }
    // Check if the project is set via a hidden input (when project is not selectable)
    var projectInput = $('input[name="project"]'); // Cache the selector
    if (projectInput.length) {
        var projectId = projectInput.val();
        if (projectId) {
            loadProjectUsers(projectId); // Load users if the project is pre-selected and not selectable
        }
    }
});
$(document).ready(function () {
    $("#generate-password").on("click", function () {
        function generatePassword(length) {
            var charset =
                "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()";
            var password = "";
            for (var i = 0, n = charset.length; i < length; ++i) {
                password += charset.charAt(Math.floor(Math.random() * n));
            }
            return password;
        }
        // Generate a new random password
        var newPassword = generatePassword(12);
        // Set the generated password in both password and confirm password fields
        $("#password").val(newPassword);
        $("#password_confirmation").val(newPassword);
        // Ensure password is visible after generation
        var passwordField = $("#password");
        var toggleIcon = $(".toggle-password i");
        // Explicitly set the password field type to 'text'
        if (passwordField.attr("type") === "password") {
            passwordField.attr("type", "text"); // Show password
            // Ensure the toggle icon is in 'show' state
            toggleIcon.removeClass("bx-hide").addClass("bx-show");
        }
    });
});
$("#create_project_modal").on("shown.bs.modal", function (event) {
    var currentUrl = window.location.pathname;
    // Check if the current URL contains one of the favorite project routes
    if (
        currentUrl.includes("/kanban/favorite") ||
        currentUrl.includes("/list/favorite") ||
        currentUrl.includes("/favorite")
    ) {
        $("#create_project_modal #is_favorite").val(1); // Set is_favorite to 1 if on a favorite page
    } else {
        $("#create_project_modal #is_favorite").val(0); // Set is_favorite to 0 if not on a favorite page
    }
    var button = $(event.relatedTarget); // Button that triggered the modal
    var statusId = button.data("status-id"); // Extract status ID from data attribute
    // Find the status dropdown
    var $statusDropdown = $(this).find('select[name="status_id"]');
    // Check if the status ID is defined
    if (statusId) {
        // Check if the dropdown contains the option with the given status ID
        if ($statusDropdown.find(`option[value="${statusId}"]`).length) {
            // Set the selected status in the dropdown
            $statusDropdown.val(statusId).trigger("change");
        }
    }
});
$("#create_task_modal").on("shown.bs.modal", function (event) {
    if (window.location.search.includes("favorite=1")) {
        $("#create_task_modal #is_favorite").val(1); // Set is_favorite to 1 if the query parameter favorite=1 is present
    } else {
        $("#create_task_modal #is_favorite").val(0); // Set is_favorite to 0 if the query parameter favorite is not present
    }
});
// Initialize the calendar on page load
$(document).ready(function () {
    var taskCalenderDiv = document.getElementById("taskCalenderDiv");
    if (taskCalenderDiv) {
        calenderView(taskCalenderDiv);
    }
});
// Calendar View For the Tasks
function calenderView(taskCalenderDiv) {
    // Check if the calendar element exists
    var taskcalendar = new FullCalendar.Calendar(taskCalenderDiv, {
        plugins: ["interaction", "dayGrid", "list"],
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,listYear",
        },
        editable: true,
        selectable: true,
        selectHelper: true,
        height: "auto",
        eventLimit: 4, // Show max 4 events per day
        events: function (fetchInfo, successCallback, failureCallback) {
            // Fetch tasks for the current month
            fetchTasks(
                fetchInfo.start,
                fetchInfo.end,
                successCallback,
                failureCallback
            );
        },
        datesSet: function (info) {
            // Fetch tasks when the month changes
            taskcalendar.removeAllEvents();
            taskcalendar.refetchEvents();
        },
        eventClick: function (info) {
            // Show the edit modal
            $("#edit_task_modal").modal("show");
            // AJAX call to fetch the event details (similar to fetching task details)
            $.ajax({
                url: baseUrl + "/tasks/get/" + info.event.id, // Fetch event by ID
                type: "GET",
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"), // CSRF token for security
                },
                dataType: "json",
                success: function (response) {
                    if (response.error == false) {
                        // Format the start and end dates
                        var formattedStartDate = response.task.start_date
                            ? moment(response.task.start_date).format(
                                js_date_format
                            )
                            : "";
                        var formattedEndDate = response.task.due_date
                            ? moment(response.task.due_date).format(
                                js_date_format
                            )
                            : "";
                        // Update the modal fields with task data
                        $("#id").val(response.task.id);
                        $("#title").val(response.task.title);
                        $("#project_status_id")
                            .val(response.task.status_id)
                            .trigger("change");
                        $("#priority_id")
                            .val(response.task.priority_id)
                            .trigger("change");
                        $("#update_start_date").val(formattedStartDate);
                        $("#update_end_date").val(formattedEndDate);
                        // Initialize date pickers for start and end dates
                        initializeDateRangePicker(
                            "#update_start_date, #update_end_date"
                        );
                        // Update the project title and task note
                        $("#update_project_title").val(response.project.title);
                        $("#taskNote").val(response.task.note);
                        // Set task description (handling null case)
                        $("#edit_task_modal")
                            .find("#task_description")
                            .val(response.task.description || "");
                        // Populate users associated with the project
                        var usersSelect = $("#edit_task_modal").find(
                            'select[name="user_id[]"]'
                        );
                        usersSelect.empty(); // Clear existing options
                        // Populate users from response
                        if (
                            response.project &&
                            response.project.users &&
                            response.project.users.length > 0
                        ) {
                            response.project.users.forEach(function (user) {
                                var userOption = new Option(
                                    user.first_name + " " + user.last_name,
                                    user.id,
                                    false,
                                    false
                                ); // Unselected initially
                                usersSelect.append(userOption);
                            });
                        }
                        // Set the selected values based on the task's users
                        if (
                            response.task &&
                            response.task.users &&
                            response.task.users.length > 0
                        ) {
                            var selectedTaskUsers = response.task.users.map(
                                function (user) {
                                    return user.id; // Get the user IDs from task
                                }
                            );
                            // Set the selected values in the dropdown
                            usersSelect.val(selectedTaskUsers);
                        } else {
                            // Handle case when there are no task users
                            usersSelect.val(null);
                        }
                        usersSelect.trigger("change"); // Trigger change to reflect selection
                    } else {
                        console.error("Failed to fetch event details");
                    }
                },
                error: function (xhr, status, error) {
                    console.error("Error:", error);
                },
            });
        },
        dateClick: function (info) {
            var start = moment(info.dateStr).format(js_date_format);
            $("#task_start_date").val(start);
            $("#task_end_date").val(start);
            $("#create_task_modal").modal("show");
        },
        select: function (info) {
            var startDate = moment(info.startStr).format(js_date_format);
            var endDate = moment(info.endStr)
                .subtract(1, "days")
                .format(js_date_format);
            $("#task_start_date").val(startDate);
            $("#task_end_date").val(endDate);
            $("#create_task_modal").modal("show");
        },
        eventDrop: function (info) {
            var id = info.event.id;
            // Show the confirmation modal
            $("#confirmDragTaskModal").modal("show");
            // Remove previous click event to avoid duplication
            $("#confirmDragTaskModal").off("click", "#confirm");
            // When the confirmation button is clicked
            $("#confirmDragTaskModal").on("click", "#confirm", function () {
                $("#confirmDragTaskModal")
                    .find("#confirm")
                    .html(label_please_wait)
                    .attr("disabled", true);
                // Format the start and end dates
                var start = moment(info.event.start).format(js_date_format);
                var end = moment(info.event.end)
                    .subtract(1, "days")
                    .format(js_date_format); // Subtracting one day
                // Handle case where the end date is invalid
                if (end === "Invalid date") {
                    end = start;
                }
                $.ajax({
                    url: baseUrl + "/tasks/update-dates",
                    type: "patch",
                    headers: {
                        "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                    },
                    data: {
                        id: id,
                        start_date: start,
                        due_date: end,
                    },
                    success: function (response) {
                        $("#confirmDragTaskModal")
                            .find("#confirm")
                            .html(label_yes)
                            .attr("disabled", false);
                        if (response.error == false) {
                            $("#confirmDragTaskModal").modal("hide");
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        // Handle error
                        $("#confirmDragTaskModal")
                            .find("#confirm")
                            .html(label_yes)
                            .attr("disabled", false);
                        $("#confirmDragTaskModal").modal("hide");
                        toastr.error(label_something_went_wrong);
                    },
                });
            });
            // Handle cancel event
            $("#confirmDragTaskModal").on("click", "#cancel", function () {
                info.revert(); // Revert the event to its original position
                $("#confirmDragTaskModal").modal("hide");
            });
        },
        eventResize: function (info) {
            var id = info.event.id;
            // Show confirmation modal for resizing
            $("#confirmResizeTaskModal").modal("show");
            $("#confirmResizeTaskModal").off("click", "#confirm");
            $("#confirmResizeTaskModal").on("click", "#confirm", function () {
                $("#confirmResizeTaskModal")
                    .find("#confirm")
                    .html(label_please_wait)
                    .attr("disabled", true);
                // Format the new start and end dates
                var start = moment(info.event.start).format(js_date_format);
                var end = moment(info.event.end)
                    .subtract(1, "days")
                    .format(js_date_format); // Subtracting one day
                $.ajax({
                    url: baseUrl + "/tasks/update-dates",
                    type: "PATCH",
                    headers: {
                        "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"),
                    },
                    data: {
                        id: id,
                        start_date: start,
                        due_date: end,
                    },
                    success: function (response) {
                        $("#confirmResizeTaskModal")
                            .find("#confirm")
                            .html(label_yes)
                            .attr("disabled", false);
                        if (response.error == false) {
                            $("#confirmResizeTaskModal").modal("hide");
                            toastr.success(response.message);
                        } else {
                            toastr.error(response.message);
                        }
                    },
                    error: function (xhr, status, error) {
                        $("#confirmResizeTaskModal")
                            .find("#confirm")
                            .html(label_yes)
                            .attr("disabled", false);
                        $("#confirmResizeTaskModal").modal("hide");
                        toastr.error(label_something_went_wrong);
                    },
                });
            });
            // Handle cancellation
            $("#confirmResizeTaskModal").on("click", "#cancel", function () {
                info.revert(); // Revert the event to its original position
                $("#confirmResizeTaskModal").modal("hide");
            });
        },
        eventMouseEnter: function (info) {
            // Create a tooltip element
            var tooltip = document.createElement("div");
            tooltip.innerHTML = info.event.title;
            tooltip.style.position = "absolute";
            tooltip.style.background = "rgba(0, 0, 0, 0.8)";
            tooltip.style.color = "#fff";
            tooltip.style.padding = "5px";
            tooltip.style.borderRadius = "5px";
            tooltip.style.zIndex = "1000";
            tooltip.style.pointerEvents = "none"; // Prevent mouse events
            // Append the tooltip to the body
            document.body.appendChild(tooltip);
            // Position the tooltip
            var rect = info.el.getBoundingClientRect();
            tooltip.style.left = rect.left + window.scrollX + "px";
            tooltip.style.top = rect.bottom + window.scrollY + "px";
            // Remove tooltip on mouse leave
            info.el.addEventListener(
                "mouseleave",
                function () {
                    document.body.removeChild(tooltip);
                },
                { once: true }
            );
        },
    });
    taskcalendar.render();
}
function fetchTasks(startDate, endDate, successCallback, failureCallback) {
    var projectId = $("#projectId").val();
    $.ajax({
        url: baseUrl + "/tasks/get-calendar-data",
        type: "GET",
        data: {
            start: startDate.toISOString(),
            end: endDate.toISOString(),
            projectId: projectId,
            is_favorites: $("#is_favorites").val(),
        },
        success: function (response) {
            // Parse and format dynamic data for FullCalendar
            var events = response.map(function (event) {
                return {
                    id: event.id,
                    tasks_info_url: event.tasks_info_url,
                    title: event.title,
                    start: event.start,
                    end: moment(event.end).add(1, "days").format("YYYY-MM-DD"),
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    textColor: event.textColor,
                };
            });
            // Invoke success callback with dynamic data
            successCallback(events);
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
            // Invoke failure callback if there's an error
            failureCallback(error);
        },
    });
}
$(document).ready(function () {
    $("#menu-search").on("input", function () {


        var searchQuery = $(this).val().toLowerCase();
        console.log("searchQuery:", searchQuery)
        var menuItems = $(".menu-item");
        console.log("menu items", menuItems);
        if (searchQuery === "") {
            // If search is empty, reset everything
            menuItems.show(); // Show all menu items
            $(".menu-inner > li").removeClass("open"); // Remove 'open' class from all main menus
            return; // Exit the function
        }
        menuItems.each(function () {
            var item = $(this);
            var itemText = item.text().toLowerCase();
            var parentMenu = item.closest("li"); // Get the parent <li>
            // Check if the item text or any submenu text matches the query
            if (itemText.includes(searchQuery)) {
                item.show(); // Show the menu item
                if (item.closest("ul").hasClass("menu-sub")) {
                    // If it's a submenu and its parent menu has submenus, add 'open'
                    parentMenu
                        .closest(".menu-inner > li:has(.menu-sub)")
                        .addClass("open");
                }
            } else {
                item.hide(); // Hide the menu item
            }
        });
        // Loop through parent menus with submenus and open them if any of their submenus are visible
        $(".menu-inner > li:has(.menu-sub)").each(function () {
            var parentMenu = $(this);
            var submenus = parentMenu.find(".menu-sub");
            var anyVisible = submenus.find(".menu-item:visible").length > 0;
            if (anyVisible) {
                parentMenu.addClass("open"); // Add 'open' to parent if any submenu is visible
            } else {
                parentMenu.removeClass("open"); // Remove 'open' if no submenu is visible
            }
        });
    });
});
function addClearButtonFunctionality() {
    $(".custom-search-input").each(function () {
        const $inputField = $(this); // Current input field
        // Listen for input changes on the input field
        $inputField.on("input", function () {
            var searchQuery = $(this).val().toLowerCase();
            // Check if the search query is not empty
            if (searchQuery) {
                // Add the clear button if it doesn't already exist
                if ($inputField.siblings(".clear-search").length === 0) {
                    // Create and append the clear button
                    const clearButtonHtml =
                        '<span class="input-group-text cursor-pointer clear-search"><i class="bx bx-x"></i></span>';
                    $inputField.after(clearButtonHtml);
                }
            } else {
                // Remove the clear button if the input is empty
                $inputField.siblings(".clear-search").remove();
            }
        });
    });
    // Clear button click functionality
    $(document).on("click", ".clear-search", function () {
        const $inputField = $(this).prev(".custom-search-input"); // Get the associated input field
        $inputField.val("").trigger("input"); // Clear the input and trigger the input event
        $(this).remove(); // Remove the clear button
    });
}
$(document).ready(function () {
    // Apply clear button functionality to all search inputs
    addClearButtonFunctionality();
});
// Mention in the text area
function initializeMentionTextarea($textarea) {
    // Extract mention id and type from the data attributes of the textarea
    const mentionID = $textarea.data("mention-id");
    const mentionType = $textarea.data("mention-type");
    // Check if the textarea element exists
    if ($textarea.length === 0) {
        console.error("Textarea not found.");
        return;
    }
    // Initialize Tribute.js with the provided textarea
    const tribute = new Tribute({
        values: function (text, cb) {
            // Fetch users based on the search term and mention info
            $.ajax({
                url: baseUrl + "/users/get-mentions",
                method: "GET",
                data: {
                    search: text,
                    mention_id: mentionID,
                    mention_type: mentionType,
                },
                success: function (response) {
                    const mappedUsers = response.map((user) => ({
                        key: user.id, // Use 'id' as key
                        value: user.first_name + " " + user.last_name,
                    }));
                    cb(mappedUsers); // Provide the data to Tribute.js callback
                },
                error: function (xhr, status, error) {
                    console.error("Error fetching users:", error);
                },
            });
        },
        selectTemplate: function (item) {
            return `@${item.original.value}`; // What gets inserted when selected
        },
        lookup: "value", // Attribute used for lookup
        menuItemTemplate: function (item) {
            return `${item.original.value}`; // How items appear in the dropdown
        },
    });
    // Attach Tribute.js to the textarea
    tribute.attach($textarea[0]);
}
function stripHtml(content) {
    // Replace <a> tags with the inner text, but only add '@' if the inner text doesn't already start with it
    return content.replace(
        /<a [^>]*class=["'][^"']*mention[^"']*["'][^>]*>([^<]+)<\/a>/g,
        function (match, innerText) {
            // Check if the innerText already starts with @
            return innerText.startsWith("@") ? innerText : "@" + innerText;
        }
    );
}
// Recuring Task Settings
$(document).ready(function () {
    // Toggle Recurring Task Settings Visibility
    $("#recurring-task-switch").on("change", function () {
        const isChecked = $(this).is(":checked");
        $("#recurring-task-settings").toggleClass("d-none", !isChecked);
        // Toggle required attributes based on switch state
        $(
            "#recurrence-frequency, #recurrence-starts-from, #recurrence-occurrences"
        ).prop("required", isChecked);
        // Trigger change event to update dependent fields
        if (isChecked) {
            $("#recurrence-frequency").trigger("change");
        }
    });
    // Dynamic Display Based on Recurrence Frequency Type
    $("#recurrence-frequency").on("change", function () {
        const value = $(this).val();
        // Hide all frequency-specific groups
        $(
            "#recurrence-day-of-week-group, #recurrence-day-of-month-group, #recurrence-month-of-year-group"
        ).addClass("d-none");
        // Show appropriate groups based on selected frequency
        switch (value) {
            case "weekly":
                $("#recurrence-day-of-week-group").removeClass("d-none");
                break;
            case "monthly":
                $("#recurrence-day-of-month-group").removeClass("d-none");
                break;
            case "yearly":
                $(
                    "#recurrence-day-of-month-group, #recurrence-month-of-year-group"
                ).removeClass("d-none");
                break;
        }
    });
    // Initialize Settings on Page Load
    function initializeSettings() {
        const isRecurring = $("#recurring-task-switch").is(":checked");
        // Toggle visibility of recurring task settings
        $("#recurring-task-settings").toggleClass("d-none", !isRecurring);
        // If recurring is enabled, show appropriate fields based on frequency
        if (isRecurring) {
            $("#recurrence-frequency").trigger("change");
        }
    }
    // Run initialization on page load
    initializeSettings();
});
//Edit Recuring Task Settings
$(document).ready(function () {
    // Toggle Recurring Task Settings Visibility
    $("#edit-recurring-task-switch").on("change", function () {
        const isChecked = $(this).is(":checked");
        $("#edit-recurring-task-settings").toggleClass("d-none", !isChecked);
        // Toggle required attributes based on switch state
        $(
            "#edit-recurrence-frequency, #edit-recurrence-starts-from, #edit-recurrence-occurrences"
        ).prop("required", isChecked);
        // Trigger change event to update dependent fields
        if (isChecked) {
            $("#edit-recurrence-frequency").trigger("change");
        }
    });
    // Dynamic Display Based on Recurrence Frequency Type
    $("#edit-recurrence-frequency").on("change", function () {
        const value = $(this).val();
        // Hide all frequency-specific groups
        $(
            "#edit-recurrence-day-of-week-group, #edit-recurrence-day-of-month-group, #edit-recurrence-month-of-year-group"
        ).addClass("d-none");
        // Show appropriate groups based on selected frequency
        switch (value) {
            case "weekly":
                $("#edit-recurrence-day-of-week-group").removeClass("d-none");
                break;
            case "monthly":
                $("#edit-recurrence-day-of-month-group").removeClass("d-none");
                break;
            case "yearly":
                $(
                    "#edit-recurrence-day-of-month-group, #edit-recurrence-month-of-year-group"
                ).removeClass("d-none");
                break;
        }
    });
    // Initialize Settings on Page Load
    function initializeSettings() {
        const isRecurring = $("#edit-recurring-task-switch").is(":checked");
        // Toggle visibility of recurring task settings
        $("#edit-recurring-task-settings").toggleClass("d-none", !isRecurring);
        // If recurring is enabled, show appropriate fields based on frequency
        if (isRecurring) {
            $("#edit-recurrence-frequency").trigger("change");
        }
    }
    // Run initialization on page load
    initializeSettings();
});
// Task Reminder Settings
$(document).ready(function () {
    // Toggle Reminder Settings Visibility
    $("#reminder-switch").on("change", function () {
        if ($(this).is(":checked")) {
            $("#reminder-settings").removeClass("d-none");
            $("#time-of-day").prop("required", true);
        } else {
            $("#reminder-settings").addClass("d-none");
            $("#time-of-day").prop("required", false);
        }
    });
    // Dynamic Display Based on Frequency Type
    $("#frequency-type").on("change", function () {
        const value = $(this).val();
        $("#day-of-week-group").addClass("d-none");
        $("#day-of-month-group").addClass("d-none");
        if (value === "weekly") {
            $("#day-of-week-group").removeClass("d-none");
        } else if (value === "monthly") {
            $("#day-of-month-group").removeClass("d-none");
        }
    });
    $("#edit-reminder-switch").on("change", function () {
        if ($(this).is(":checked")) {
            $("#edit-reminder-settings").removeClass("d-none");
        } else {
            $("#edit-reminder-settings").addClass("d-none");
        }
    });
    $("#edit-frequency-type").on("change", function () {
        const value = $(this).val();
        $("#edit-day-of-week-group").addClass("d-none");
        $("#edit-day-of-month-group").addClass("d-none");
        if (value === "weekly") {
            $("#edit-day-of-week-group").removeClass("d-none");
        } else if (value === "monthly") {
            $("#edit-day-of-month-group").removeClass("d-none");
        }
    });


    $("#edit-todo-reminder-switch").on("change", function () {
        if ($(this).is(":checked")) {
            $("#edit-todo-reminder-settings").removeClass("d-none");
        } else {
            $("#edit-todo-reminder-settings").addClass("d-none");
        }
    });
    $("#edit-todo-frequency-type").on("change", function () {
        const value = $(this).val();
        $("#edit-todo-day-of-week-group").addClass("d-none");
        $("#edit-todo-day-of-month-group").addClass("d-none");
        if (value === "weekly") {
            $("#edit-todo-day-of-week-group").removeClass("d-none");
        } else if (value === "monthly") {
            $("#edit-todo-day-of-month-group").removeClass("d-none");
        }
    });
});
// Taks List Selection
$(document).ready(function () {
    // Initialize task list select2
    $("#task_list").select2({
        dropdownParent: $("#create_task_modal"), // Add this line to fix dropdown in modal
        width: "100%", // Ensure full width
        ajax: {
            url: baseUrl + "/task-lists/search",
            dataType: "json",
            delay: 250,
            data: function (params) {
                return {
                    search: params.term || "", // Search term, use empty string if undefined
                    project_id: $('.selectTaskProject[name="project"]').val(), // Get current project ID
                };
            },
            processResults: function (data) {
                return {
                    results: data.map(function (item) {
                        return {
                            id: item.id,
                            text: item.name,
                        };
                    }),
                };
            },
            cache: true,
        },
        placeholder: "Type to search task list",
        minimumInputLength: 0,
        allowClear: true,
        // Add initial loading of all task lists
        initSelection: function (element, callback) {
            var projectId = $('.selectTaskProject[name="project"]').val();
            if (projectId) {
                $.ajax({
                    url: baseUrl + "/task-lists/search",
                    data: { project_id: projectId },
                    dataType: "json",
                }).then(function (data) {
                    callback(
                        data.map(function (item) {
                            return {
                                id: item.id,
                                text: item.name,
                            };
                        })
                    );
                });
            }
        },
    });
    // Add necessary CSS to fix cursor and input issues
    $(".select2-search__field").css("cursor", "text");
    // Disable task list select initially if no project is selected
    if (!$('.selectTaskProject[name="project"]').val()) {
        $("#task_list").prop("disabled", true);
    }
    // Listen for project selection change
    $('.selectTaskProject[name="project"]').on("change", function () {
        var projectId = $(this).val();
        var taskListSelect = $("#task_list");
        if (projectId) {
            // Enable task list select
            taskListSelect.prop("disabled", false);
            // Clear previous selection
            taskListSelect.val(null).trigger("change");
            // Load task lists for selected project
            $.ajax({
                url: baseUrl + "/task-lists/search",
                data: { project_id: projectId },
                dataType: "json",
                success: function (data) {
                    // Clear existing options
                    taskListSelect.empty();
                    // Add placeholder option
                    taskListSelect.append(
                        new Option("Select a task list", "", true, true)
                    );
                    // Add received options
                    data.forEach(function (item) {
                        taskListSelect.append(
                            new Option(item.name, item.id, false, false)
                        );
                    });
                    taskListSelect.trigger("change");
                },
            });
        } else {
            // Disable and clear task list select if no project selected
            taskListSelect.prop("disabled", true);
            taskListSelect.val(null).trigger("change");
        }
    });
});
// Show the Active Tab
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(function (button) {
    button.addEventListener("click", function () {
        // Check if the clicked button has the "list-button" or "calendar-button" class
        if (
            this.classList.contains("list-button") ||
            this.classList.contains("calendar-button")
        ) {
            // Remove bg-primary and text-white from "List" and "Calendar" buttons only
            document
                .querySelectorAll(".list-button, .calendar-button")
                .forEach(function (specificButton) {
                    specificButton.classList.remove("bg-primary", "text-white");
                });
            // Add bg-primary and text-white to the clicked button
            this.classList.add("bg-primary", "text-white");
        }
        // Handle nested tabs
        const parentPane = document.querySelector(this.dataset.bsTarget);
        if (parentPane) {
            parentPane
                .querySelectorAll(".list-button, .calendar-button")
                .forEach(function (subTab) {
                    subTab.classList.remove("bg-primary", "text-white");
                });
            const activeSubTab = parentPane.querySelector(
                ".list-button.active, .calendar-button.active"
            );
            if (activeSubTab) {
                activeSubTab.classList.add("bg-primary", "text-white");
            }
        }
    });
});
// Meetings Calendar
function meetings_calendar_view(meetingsCalenderDiv) {
    // Check if the calendar element exists
    var meetingCalendar = new FullCalendar.Calendar(meetingsCalenderDiv, {
        plugins: ["interaction", "dayGrid", "list"],
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,listYear",
        },
        editable: false,
        selectable: true,
        selectHelper: true,
        height: "auto",
        eventLimit: 4, // Show max 4 events per day
        events: function (fetchInfo, successCallback, failureCallback) {
            // Fetch tasks for the current month
            fetchMeetings(
                fetchInfo.start,
                fetchInfo.end,
                successCallback,
                failureCallback
            );
        },
        datesSet: function (info) {
            // Fetch tasks when the month changes
            meetingCalendar.removeAllEvents();
            meetingCalendar.refetchEvents();
        },
        dateClick: function (info) {
            var start = moment(info.dateStr).format(js_date_format);
            $("#start_date").val(start);
            $("#start_time").val()
            $("#end_date").val(start);
            $("#end_time").val()
            $("#createMeetingModal").modal("show");
        },
        select: function (info) {
            var startDate = moment(info.startStr).format(js_date_format);
            var endDate = moment(info.endStr)
                .subtract(1, "days")
                .format(js_date_format);
            $("#start_date").val(startDate);
            $("#end_date").val(endDate);
            $("#createMeetingModal").modal("show");
        },
        eventMouseEnter: function (info) {
            // Create a tooltip element
            var tooltip = document.createElement("div");
            tooltip.innerHTML = info.event.extendedProps.description || "No description available";
            tooltip.style.position = "absolute";
            tooltip.style.background = "rgba(0, 0, 0, 0.8)";
            tooltip.style.color = "#fff";
            tooltip.style.padding = "5px";
            tooltip.style.borderRadius = "5px";
            tooltip.style.zIndex = "1000";
            tooltip.style.pointerEvents = "none"; // Prevent mouse events
            // Append the tooltip to the body
            document.body.appendChild(tooltip);
            // Position the tooltip
            var rect = info.el.getBoundingClientRect();
            tooltip.style.left = rect.left + window.scrollX + "px";
            tooltip.style.top = rect.bottom + window.scrollY + "px";
            // Remove tooltip on mouse leave
            info.el.addEventListener(
                "mouseleave",
                function () {
                    document.body.removeChild(tooltip);
                },
                { once: true }
            );
        },
        eventClick: function (info) {
            //Join Meeting
            var status = info.event.extendedProps.status;
            console.log(status);
            if (status == "Ongoing") {
                window.location.href = "/meetings/join/" + info.event.id;
            }
            else {
                toastr.error("Meeting is not available to join");
            }
            // if()
        },
    });
    meetingCalendar.render();
}
function fetchMeetings(startDate, endDate, successCallback, failureCallback) {
    $.ajax({
        url: "/meetings/get-calendar-data",
        type: "GET",
        data: {
            start: startDate.toISOString(),
            end: endDate.toISOString(),
        },
        success: function (response) {
            console.log(response);
            // Parse and format dynamic data for FullCalendar
            var events = response.map(function (event) {
                return {
                    id: event.id,
                    description: event.description,
                    title: event.title,
                    start: event.start,
                    end: moment(event.end).add(1, "days").format("YYYY-MM-DD"),
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    textColor: event.textColor,
                    status: event.extendedProps.status,
                };
            });
            // Invoke success callback with dynamic data
            successCallback(events);
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
            // Invoke failure callback if there's an error
            failureCallback(error);
        },
    });
}
// Activity Log Calendar
function activity_calendar_view(activityCalenderDiv) {
    // Check if the calendar element exists
    var activityCalendar = new FullCalendar.Calendar(activityCalenderDiv, {
        plugins: ["interaction", "dayGrid", "list"],
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,listYear",
        },
        editable: false,
        selectable: true,
        selectHelper: true,
        height: "auto",
        eventLimit: 4, // Show max 4 events per day
        events: function (fetchInfo, successCallback, failureCallback) {
            // Fetch tasks for the current month
            fetchActivities(
                fetchInfo.start,
                fetchInfo.end,
                successCallback,
                failureCallback
            );
        },
        datesSet: function (info) {
            // Fetch tasks when the month changes
            activityCalendar.removeAllEvents();
            activityCalendar.refetchEvents();
        },


        eventMouseEnter: function (info) {
            // Create a tooltip element
            var tooltip = document.createElement("div");
            tooltip.innerHTML = info.event.title || "No description available";
            tooltip.style.position = "absolute";
            tooltip.style.background = "rgba(0, 0, 0, 0.8)";
            tooltip.style.color = "#fff";
            tooltip.style.padding = "5px";
            tooltip.style.borderRadius = "5px";
            tooltip.style.zIndex = "1000";
            tooltip.style.pointerEvents = "none"; // Prevent mouse events
            // Append the tooltip to the body
            document.body.appendChild(tooltip);
            // Position the tooltip
            var rect = info.el.getBoundingClientRect();
            tooltip.style.left = rect.left + window.scrollX + "px";
            tooltip.style.top = rect.bottom + window.scrollY + "px";
            // Remove tooltip on mouse leave
            info.el.addEventListener(
                "mouseleave",
                function () {
                    document.body.removeChild(tooltip);
                },
                { once: true }
            );
        },
        eventClick: function (info) {
            //Join Meeting
            console.log(info);
            // if()
        },
    });
    activityCalendar.render();
}
function fetchActivities(startDate, endDate, successCallback, failureCallback) {
    $.ajax({
        url: "/activity-log/get-calendar-data",
        type: "GET",
        data: {
            date_from: moment(startDate).format(js_date_format),
            date_to: moment(endDate).format(js_date_format),
            limit: 1500,
        },
        success: function (response) {
            console.log(response);
            // Parse and format dynamic data for FullCalendar
            var events = response.map(function (event) {
                return {
                    id: event.id,
                    description: event.description,
                    title: event.title,
                    start: event.start,
                    end: moment(event.end).add(1, "days").format("YYYY-MM-DD"),
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    textColor: event.textColor,
                    url: event.url,
                    // status: event.extendedProps.status,
                };
            });
            // Invoke success callback with dynamic data
            successCallback(events);
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
            // Invoke failure callback if there's an error
            failureCallback(error);
        },
    });
}
// Leave Request Calendar

function leave_request_calendar_view(leaveRequestCalenderDiv) {
    // Check if the calendar element exists
    var leaveRequestCalendar = new FullCalendar.Calendar(leaveRequestCalenderDiv, {
        plugins: ["interaction", "dayGrid", "list"],
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,listYear",
        },
        editable: false,
        selectable: true,
        selectHelper: true,
        height: "auto",
        eventLimit: 4, // Show max 4 events per day
        events: function (fetchInfo, successCallback, failureCallback) {
            // Fetch tasks for the current month
            fetchLeaveRequests(
                fetchInfo.start,
                fetchInfo.end,
                successCallback,
                failureCallback
            );
        },
        datesSet: function (info) {
            // Fetch tasks when the month changes
            leaveRequestCalendar.removeAllEvents();
            leaveRequestCalendar.refetchEvents();
        },


        eventMouseEnter: function (info) {
            // Create a tooltip element

            var tooltip = document.createElement("div");
            tooltip.innerHTML = info.event.extendedProps.description || "No description available";
            tooltip.style.position = "absolute";
            tooltip.style.background = "rgba(0, 0, 0, 0.8)";
            tooltip.style.color = "#fff";
            tooltip.style.padding = "5px";
            tooltip.style.borderRadius = "5px";
            tooltip.style.zIndex = "1000";
            tooltip.style.pointerEvents = "none"; // Prevent mouse events
            // Append the tooltip to the body
            document.body.appendChild(tooltip);
            // Position the tooltip
            var rect = info.el.getBoundingClientRect();
            tooltip.style.left = rect.left + window.scrollX + "px";
            tooltip.style.top = rect.bottom + window.scrollY + "px";
            // Remove tooltip on mouse leave
            info.el.addEventListener(
                "mouseleave",
                function () {
                    document.body.removeChild(tooltip);
                },
                { once: true }
            );
        },
        eventClick: function (info) {
            //Join Meeting
            console.log(info);
            // if()
        },
    });
    leaveRequestCalendar.render();
}
function fetchLeaveRequests(startDate, endDate, successCallback, failureCallback) {
    $.ajax({
        url: "/leave-requests/get-calendar-data",
        type: "GET",
        data: {
            date_from: moment(startDate).format(js_date_format),
            date_to: moment(endDate).format(js_date_format),
        },
        success: function (response) {
            console.log(response);
            // Parse and format dynamic data for FullCalendar
            var events = response.map(function (event) {
                return {
                    id: event.id,
                    description: event.description,
                    title: event.title,
                    start: event.start,
                    end: moment(event.end).add(1, "days").format("YYYY-MM-DD"),
                    backgroundColor: event.backgroundColor,
                    borderColor: event.borderColor,
                    textColor: event.textColor,
                    url: event.url,
                    status: event.extendedProps.status,
                    type: 'leave',
                };
            });
            // Invoke success callback with dynamic data
            successCallback(events);
        },
        error: function (xhr, status, error) {
            console.error(xhr.responseText);
            // Invoke failure callback if there's an error
            failureCallback(error);
        },
    });
}
$(document).ready(function () {
    var meetingsCalenderDiv = document.getElementById("meetings_calendar_view");
    if (meetingsCalenderDiv) {
        meetings_calendar_view(meetingsCalenderDiv);
    }
    var activityCalenderDiv = document.getElementById("activity_calendar_view");
    if (activityCalenderDiv) {
        activity_calendar_view(activityCalenderDiv);
    }
    var leaveRequestCalenderDiv = document.getElementById("leave_request_calendar_view");
    if (leaveRequestCalenderDiv) {
        leave_request_calendar_view(leaveRequestCalenderDiv);
    }
});

class ProjectCalendarManager {
    constructor(config = {}) {
        this.config = {
            calendarContainerId: 'projectCalenderDiv',
            dateRangePickerId: 'daterange-picker',
            statusFiltersId: 'status-filters-container',
            priorityFiltersId: 'priority-filters-container',
            baseUrl: config.baseUrl || window.baseUrl || '',
            dateFormat: config.dateFormat || window.js_date_format || 'DD/MM/YYYY',
            csrfToken: config.csrfToken || $('input[name="_token"]').attr("value"),
            ...config
        };

        this.state = {
            calendar: null,
            allProjects: [],
            projectStatuses: [],
            projectPriorities: [],
            activeFilters: {
                status: [],
                priority: []
            },
            isInitialized: false
        };

        this.cache = new Map();
        this.debounceTimers = new Map();
    }

    async init() {
        if (this.state.isInitialized) {
            console.warn('ProjectCalendarManager already initialized');
            return this;
        }

        try {
            await this.initializeDateRangePicker();
            await this.loadFilterOptions();
            await this.initializeCalendar();
            this.initializeQuickActions();
            this.state.isInitialized = true;
            return this;
        } catch (error) {
            console.error('Failed to initialize ProjectCalendarManager:', error);
            throw error;
        }
    }

    initializeDateRangePicker() {
        return new Promise((resolve) => {
            const today = moment();
            const picker = $(`#${this.config.dateRangePickerId}`);

            if (!picker.length) {
                console.warn(`Date range picker element #${this.config.dateRangePickerId} not found`);
                resolve();
                return;
            }

            picker.daterangepicker({
                startDate: today.clone().startOf('month'),
                endDate: today.clone().endOf('month'),
                locale: { format: this.config.dateFormat },
                ranges: this.getDateRanges()
            }, (start, end) => {
                this.handleDateRangeChange(start, end);
            });

            resolve();
        });
    }

    getDateRanges() {
        const m = moment;
        return {
            'Today': [m(), m()],
            'Yesterday': [m().subtract(1, 'days'), m().subtract(1, 'days')],
            'Last 7 Days': [m().subtract(6, 'days'), m()],
            'Last 30 Days': [m().subtract(29, 'days'), m()],
            'This Month': [m().startOf('month'), m().endOf('month')],
            'Last Month': [m().subtract(1, 'month').startOf('month'), m().subtract(1, 'month').endOf('month')],
            'Next Month': [m().add(1, 'month').startOf('month'), m().add(1, 'month').endOf('month')],
            'This Year': [m().startOf('year'), m().endOf('year')]
        };
    }

    handleDateRangeChange(start, end) {
        this.debounce('dateRangeChange', () => {
            if (this.state.calendar) {
                this.state.calendar.gotoDate(start.toDate());
                this.state.calendar.setOption('visibleRange', {
                    start: start.toDate(),
                    end: end.toDate()
                });
                this.state.calendar.refetchEvents();
            }
        }, 300);
    }

    async loadFilterOptions() {
        const filterSection = $('.filter-section');
        filterSection.addClass('loading-filters');

        try {
            const [statusResponse, priorityResponse] = await Promise.all([
                this.apiRequest('/projects/get-statuses'),
                this.apiRequest('/projects/get-priorities')
            ]);

            this.state.projectStatuses = statusResponse.statuses || statusResponse;
            this.state.projectPriorities = priorityResponse.priorities || priorityResponse;

            this.state.activeFilters.status = this.state.projectStatuses.map(s => s.id.toString());
            this.state.activeFilters.priority = this.state.projectPriorities.map(p => p.id.toString());

            this.renderFilters();
        } catch (error) {
            console.error('Error loading filter options:', error);
            this.handleFilterLoadError();
        } finally {
            filterSection.removeClass('loading-filters');
        }
    }

    async apiRequest(endpoint, options = {}) {
        const cacheKey = `${endpoint}-${JSON.stringify(options)}`;

        if (this.cache.has(cacheKey)) {
            return this.cache.get(cacheKey);
        }

        const response = await $.ajax({
            url: this.config.baseUrl + endpoint,
            type: options.method || "GET",
            headers: {
                "X-CSRF-TOKEN": this.config.csrfToken,
                ...options.headers
            },
            dataType: "json",
            ...options
        });

        this.cache.set(cacheKey, response);
        return response;
    }

    renderFilters() {
        this.renderStatusFilters();
        this.renderPriorityFilters();
    }

    renderStatusFilters() {
        const container = $(`#${this.config.statusFiltersId}`);
        if (!container.length) return;

        const filtersHtml = this.state.projectStatuses.map(status =>
            this.createFilterHtml('status', status)
        ).join('');

        container.html(filtersHtml);
        this.bindFilterEvents('status');
    }

    renderPriorityFilters() {
        const container = $(`#${this.config.priorityFiltersId}`);
        if (!container.length) return;

        const filtersHtml = this.state.projectPriorities.map(priority =>
            this.createFilterHtml('priority', priority)
        ).join('');

        container.html(filtersHtml);
        this.bindFilterEvents('priority');
    }

    createFilterHtml(type, item) {
        return `
            <div class="form-check">
                <input class="form-check-input ${type}-filter" type="checkbox" checked
                       data-${type}="${item.id}" id="filter${type.charAt(0).toUpperCase() + type.slice(1)}${item.id}">
                <label class="form-check-label" for="filter${type.charAt(0).toUpperCase() + type.slice(1)}${item.id}">
                    <div class="d-flex align-items-center">
                        <span class="badge bg-label-${item.color || 'secondary'}">${item.title || item.name}</span>
                    </div>
                    <span class="filter-counter" id="count-${type}-${item.id}">0</span>
                </label>
            </div>
        `;
    }

    bindFilterEvents(type) {
        $(`.${type}-filter`).off('change.pcm').on('change.pcm', (e) => {
            const itemId = $(e.target).data(type).toString();
            const isChecked = $(e.target).is(':checked');

            this.updateActiveFilters(type, itemId, isChecked);
            this.debounce('applyFilters', () => this.applyFilters(), 150);
        });
    }

    updateActiveFilters(type, itemId, isChecked) {
        if (isChecked) {
            if (!this.state.activeFilters[type].includes(itemId)) {
                this.state.activeFilters[type].push(itemId);
            }
        } else {
            this.state.activeFilters[type] = this.state.activeFilters[type].filter(id => id !== itemId);
        }
    }

    initializeQuickActions() {
        $('#selectAllFilters').off('click.pcm').on('click.pcm', () => this.selectAllFilters());
        $('#clearAllFilters').off('click.pcm').on('click.pcm', () => this.clearAllFilters());
        $('#refreshCalendar').off('click.pcm').on('click.pcm', () => this.refreshCalendar());
    }

    selectAllFilters() {
        $('.status-filter, .priority-filter').prop('checked', true);
        this.state.activeFilters.status = this.state.projectStatuses.map(s => s.id.toString());
        this.state.activeFilters.priority = this.state.projectPriorities.map(p => p.id.toString());
        this.applyFilters();
    }

    clearAllFilters() {
        $('.status-filter, .priority-filter').prop('checked', false);
        this.state.activeFilters.status = [];
        this.state.activeFilters.priority = [];
        this.applyFilters();
    }

    refreshCalendar() {
        if (this.state.calendar) {
            this.state.calendar.refetchEvents();
        }
    }

    applyFilters() {
        if (!this.state.calendar) return;

        this.state.calendar.refetchEvents();
        this.updateFilterCounters();
        this.updateStatistics();
    }

    updateFilterCounters() {
        const counts = this.calculateCounts();

        this.state.projectStatuses.forEach(status => {
            $(`#count-status-${status.id}`).text(counts.status[status.id.toString()] || 0);
        });

        this.state.projectPriorities.forEach(priority => {
            $(`#count-priority-${priority.id}`).text(counts.priority[priority.id.toString()] || 0);
        });
    }

    calculateCounts() {
        return this.state.allProjects.reduce((acc, project) => {
            const statusId = project.status_id?.toString();
            const priorityId = project.priority_id?.toString();

            if (statusId) {
                acc.status[statusId] = (acc.status[statusId] || 0) + 1;
            }
            if (priorityId) {
                acc.priority[priorityId] = (acc.priority[priorityId] || 0) + 1;
            }

            return acc;
        }, { status: {}, priority: {} });
    }

    updateStatistics() {
        const totalProjects = this.state.allProjects.length;
        const visibleProjects = this.getVisibleProjectsCount();

        $('#total-projects').text(totalProjects);
        $('#visible-projects').text(visibleProjects);
        $('#filtered-projects').text(totalProjects - visibleProjects);
    }

    getVisibleProjectsCount() {
        return this.state.allProjects.filter(project => {
            const statusMatch = this.state.activeFilters.status.length === 0 ||
                this.state.activeFilters.status.includes(project.status_id?.toString());
            const priorityMatch = this.state.activeFilters.priority.length === 0 ||
                this.state.activeFilters.priority.includes(project.priority_id?.toString());
            return statusMatch && priorityMatch;
        }).length;
    }

    initializeCalendar() {
        const calendarEl = document.getElementById(this.config.calendarContainerId);
        if (!calendarEl) {
            throw new Error(`Calendar container #${this.config.calendarContainerId} not found`);
        }

        this.state.calendar = new FullCalendar.Calendar(calendarEl, {
            plugins: ["interaction", "dayGrid", "list"],
            headerToolbar: {
                left: "prev,next today",
                center: "title",
                right: "dayGridMonth,listYear,",
            },
            initialView: "dayGridMonth",
            editable: true,
            selectable: true,
            selectHelper: true,
            height: "auto",
            eventLimit: 4,
            events: (fetchInfo, successCallback, failureCallback) => {
                this.fetchProjects(fetchInfo, successCallback, failureCallback);
            },
            datesSet: (info) => this.handleDatesSet(info),
            eventDidMount: (info) => this.handleEventDidMount(info),
            eventClick: (info) => this.handleEventClick(info),
            dateClick: (info) => this.handleDateClick(info),
            select: (info) => this.handleSelect(info),
            eventDrop: (info) => this.handleEventDrop(info),
            eventResize: (info) => this.handleEventResize(info),
            eventMouseEnter: (info) => this.handleEventMouseEnter(info)
        });

        this.state.calendar.render();
    }

    async fetchProjects(fetchInfo, successCallback, failureCallback) {
        try {
            const response = await $.ajax({
                url: this.config.baseUrl + "/projects/get-calendar-data",
                type: "GET",
                data: {
                    start: fetchInfo.start.toISOString(),
                    end: fetchInfo.end.toISOString(),
                }
            });

            console.log('API Response:', response);
            const events = this.transformEvents(response);
            console.log('Transformed Events:', events);
            this.state.allProjects = events;

            const filteredEvents = this.filterEvents(events);
            console.log('Filtered Events:', filteredEvents);
            console.log('Active Filters:', this.state.activeFilters);

            this.updateFilterCounters();
            this.updateStatistics();

            successCallback(filteredEvents);
        } catch (error) {
            console.error('Error fetching projects:', error);
            failureCallback(error);
        }
    }

    transformEvents(response) {
        return response.map(event => ({
            id: event.id,
            tasks_info_url: event.project_info_url,
            title: event.title,
            start: event.start,
            end: moment(event.end).add(1, "days").format("YYYY-MM-DD"),
            backgroundColor: event.backgroundColor,
            borderColor: event.borderColor,
            textColor: event.textColor,
            extendedProps: {
                status_id: event.status_id,
                priority_id: event.priority_id
            },
            status_id: event.status_id,
            priority_id: event.priority_id
        }));
    }

    filterEvents(events) {
        return events.filter(event => {
            const statusMatch = this.state.activeFilters.status.length === 0 ||
                this.state.activeFilters.status.includes(event.status_id?.toString());
            const priorityMatch = this.state.activeFilters.priority.length === 0 ||
                this.state.activeFilters.priority.includes(event.priority_id?.toString());
            return statusMatch && priorityMatch;
        });
    }

    handleDatesSet(info) {
        const start = moment(info.start);
        const end = moment(info.end).subtract(1, 'day');
        const picker = $(`#${this.config.dateRangePickerId}`).data('daterangepicker');

        if (picker) {
            picker.setStartDate(start);
            picker.setEndDate(end);
        }
    }

    handleEventDidMount(info) {
        const event = info.event;
        const element = info.el;

        const status = this.state.projectStatuses.find(s =>
            s.id.toString() === event.extendedProps.status_id?.toString()
        );
        const priority = this.state.projectPriorities.find(p =>
            p.id.toString() === event.extendedProps.priority_id?.toString()
        );

        if (status?.color) {
            element.style.backgroundColor = status.color;
            element.style.borderColor = status.color;
            element.classList.add('status-color');
        }

        if (priority?.color) {
            element.style.borderLeft = `4px solid ${priority.color}`;
        }
    }

    handleEventClick(info) {
        editProject(info.event.id, true, this.config.baseUrl, this.config.dateFormat);
    }

    handleDateClick(info) {
        const date = moment(info.dateStr).format(this.config.dateFormat);
        this.openCreateProjectOffcanvas(date, date);
    }

    handleSelect(info) {
        const startDate = moment(info.startStr).format(this.config.dateFormat);
        const endDate = moment(info.endStr).subtract(1, "days").format(this.config.dateFormat);
        this.openCreateProjectOffcanvas(startDate, endDate);
    }

    openCreateProjectOffcanvas(startDate, endDate) {
        const $offcanvas = $("#create_project_offcanvas");
        if (!$offcanvas.length) {
            console.warn("#create_project_offcanvas not found in DOM");
            toastr.error("Create project form not found");
            return;
        }

        // Open offcanvas
        $offcanvas.offcanvas("show");

        // Populate start and end date fields
        $offcanvas.find("#start_date").val(startDate);
        $offcanvas.find("#end_date").val(endDate);

        // Initialize DateRangePicker for date fields
        initializeDateRangePicker($offcanvas.find("#start_date, #end_date"));
    }

    handleEventDrop(info) {
        this.showUpdateConfirmation(info, 'drag');
    }

    handleEventResize(info) {
        this.showUpdateConfirmation(info, 'resize');
    }

    handleEventMouseEnter(info) {
        this.showTooltip(info);
    }

    showUpdateConfirmation(info, type) {
        const modalId = type === 'drag' ? '#confirmDragProjectModal' : '#confirmResizeProjectModal';
        $(modalId).modal("show");

        $(modalId).off("click.pcm", "#confirm").on("click.pcm", "#confirm", () => {
            this.updateProjectDates(info, modalId);
        });

        $(modalId).off("click.pcm", "#cancel").on("click.pcm", "#cancel", () => {
            info.revert();
            $(modalId).modal("hide");
        });
    }

    async updateProjectDates(info, modalId) {
        const confirmBtn = $(modalId).find("#confirm");
        confirmBtn.html(window.label_please_wait || 'Please wait...').attr("disabled", true);

        try {
            const start = moment(info.event.start).format(this.config.dateFormat);
            const end = moment(info.event.end).subtract(1, "days").format(this.config.dateFormat);

            const response = await $.ajax({
                url: this.config.baseUrl + "/projects/update-dates",
                type: "PATCH",
                headers: { "X-CSRF-TOKEN": this.config.csrfToken },
                data: {
                    id: info.event.id,
                    start_date: start,
                    end_date: end === "Invalid date" ? start : end,
                }
            });

            if (response.error === false) {
                $(modalId).modal("hide");
                toastr.success(response.message);
                this.state.calendar.refetchEvents();
            } else {
                toastr.error(response.message);
                info.revert();
            }
        } catch (error) {
            console.error('Error updating project dates:', error);
            toastr.error(window.label_something_went_wrong || 'Something went wrong');
            info.revert();
        } finally {
            confirmBtn.html(window.label_yes || 'Yes').attr("disabled", false);
            $(modalId).modal("hide");
        }
    }

    showTooltip(info) {
        const tooltip = $(`<div class="calendar-tooltip">${info.event.title}</div>`);
        tooltip.css({
            position: "absolute",
            background: "rgba(0, 0, 0, 0.8)",
            color: "#fff",
            padding: "5px",
            borderRadius: "5px",
            zIndex: "1000",
            pointerEvents: "none"
        });

        $('body').append(tooltip);

        const rect = info.el.getBoundingClientRect();
        tooltip.css({
            left: rect.left + window.scrollX,
            top: rect.bottom + window.scrollY
    });

        $(info.el).one('mouseleave', () => tooltip.remove());
    }

    handleFilterLoadError() {
        $(`#${this.config.statusFiltersId}`).html('<p class="text-danger small">Error loading statuses</p>');
        $(`#${this.config.priorityFiltersId}`).html('<p class="text-danger small">Error loading priorities</p>');
    }

    destroy() {
        if (this.state.calendar) {
            this.state.calendar.destroy();
        }

        this.debounceTimers.forEach(timer => clearTimeout(timer));
        this.debounceTimers.clear();
        this.cache.clear();

        $('.status-filter, .priority-filter').off('.pcm');
        $('#selectAllFilters, #clearAllFilters, #refreshCalendar').off('.pcm');

        this.state.isInitialized = false;
    }

    /**
    * Utility methods
    */
    debounce(key, func, delay) {
        if (this.debounceTimers.has(key)) {
            clearTimeout(this.debounceTimers.get(key));
        }

        const timeoutId = setTimeout(() => {
            func();
            this.debounceTimers.delete(key);
        }, delay);

        this.debounceTimers.set(key, timeoutId);
    }
}

// Usage function that wraps initialization
async function initializeProjectCalendar(config = {}) {
    try {
        const calendarManager = new ProjectCalendarManager(config);
        await calendarManager.init();

        // Store instance globally for external access if needed
        if (!window.projectCalendarInstances) {
            window.projectCalendarInstances = new Map();
        }
        window.projectCalendarInstances.set(config.calendarContainerId || 'projectCalenderDiv', calendarManager);

        return calendarManager;
    } catch (error) {
        console.error('Failed to initialize project calendar:', error);
        throw error;
    }
}

// Auto-initialize when document is ready (maintains backward compatibility)
$(document).ready(function () {
    const calendarEl = document.getElementById('projectCalenderDiv');
    if (calendarEl) {
        initializeProjectCalendar().catch(console.error);
    }
});
function googleCalendarView(googleCalendarDiv) {
    var googleCalendar = new FullCalendar.Calendar(googleCalendarDiv, {

        plugins: ['interaction', 'dayGrid', 'list', 'googleCalendar'], // FullCalendar plugins
        header: {
            // left: 'prev,next today',
            // center: 'title',
            right: 'dayGridMonth,listMonth,prev,next today'
        },
        editable: false,
        selectable: false,
        height: "auto",
        eventLimit: 4,
        themeSystem: 'bootstrap5',

        // ✅ Load Google Calendar Events
        eventSources: [
            {
                googleCalendarId: google_calendar_id, // Replace with your Google Calendar ID
                googleCalendarApiKey: google_calendar_api_key, // Replace with your API Key
                backgroundColor: '#696cff',
                borderColor: '#696cff',
            },
            function (fetchInfo, successCallback, failureCallback) {
                fetchLeaveRequests(fetchInfo.start, fetchInfo.end, successCallback, failureCallback);
            }
        ],

        eventMouseEnter: function (info) {
            // Create a tooltip element

            var tooltip = document.createElement("div");
            tooltip.innerHTML = info.event.extendedProps.description || "No description available";
            tooltip.style.position = "absolute";
            tooltip.style.background = "rgba(0, 0, 0, 0.8)";
            tooltip.style.color = "#fff";
            tooltip.style.padding = "5px";
            tooltip.style.borderRadius = "5px";
            tooltip.style.zIndex = "1000";
            tooltip.style.pointerEvents = "none"; // Prevent mouse events
            // Append the tooltip to the body
            document.body.appendChild(tooltip);
            // Position the tooltip
            var rect = info.el.getBoundingClientRect();
            tooltip.style.left = rect.left + window.scrollX + "px";
            tooltip.style.top = rect.bottom + window.scrollY + "px";
            // Remove tooltip on mouse leave
            info.el.addEventListener(
                "mouseleave",
                function () {
                    document.body.removeChild(tooltip);
                },
                { once: true }
            );
        },

        // ✅ Event Click → Show Details in a Bootstrap Modal
        eventClick: function (info) {
            var event = info.event;
            info.jsEvent.preventDefault();
        },


    });

    googleCalendar.render();
}
$(document).ready(function () {

    var googleCalendarDiv = document.getElementById("googleCalendarDiv");
    if (googleCalendarDiv) {
        googleCalendarView(googleCalendarDiv);
    }
});



$(document).on("click", ".edit-task-list", function () {
    var id = $(this).data("id");
    var routePrefix = $("#table").data("routePrefix");
    $("#edit_task_list_modal").modal("show");
    $.ajax({
        url: "/task-lists/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"), // Replace with your method of getting the CSRF token
        },
        dataType: "json",
        success: function (response) {
            console.log(response.task_list.id);

            $("#task_list_id").val(response.task_list.id);
            $("#task_list_project").val(response.task_list.project.title);
            $("#task_list_name").val(response.task_list.name);
            var projectData = {
                id: response.task_list.project.id, // Ensure this matches your project's ID field
                text: response.task_list.project.title, // Ensure this matches your project's title field
            };

            var $projectSelect = $("#task_list_project_id");
            $projectSelect.empty().append(new Option(projectData.text, projectData.id, true, true)).trigger("change");
        },
    });
});


// Edit Lead Sources
$(document).on("click", ".edit-lead-source", function () {
    var id = $(this).data("id");
    $("#edit_lead_source_modal").modal("show");
    $.ajax({
        url: "/lead-sources/get/" + id,
        type: "get",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').attr("value"), // Replace with your method of getting the CSRF token
        },
        dataType: "json",
        success: function (response) {

            $("#lead_source_id").val(response.lead_source.id);
            $("#lead_source_name").val(response.lead_source.name);
        },
    });
});

$(document).on("click", ".edit-lead-follow-up", function () {
    var id = $(this).data("id");
    $("#edit_lead_follow_up_modal").modal("show");

    $.ajax({
        url: "/leads/follow-up/get/" + id,
        type: "GET",
        headers: {
            "X-CSRF-TOKEN": $('input[name="_token"]').val()
        },
        dataType: "json",
        success: function (response) {
            console.log(response);

            // Prefill the form with data from the response
            var followUp = response.follow_up;
            var lead = response.follow_up.lead;

            // Prefill ID (hidden field)
            $('input[name="id"]').val(followUp.id);

            // Prefill Assigned To field (select)
            var dropdownSelector = $('select[name="assigned_to"]');

            if (dropdownSelector.length) {
                var newItem = response.follow_up.assigned_to;

                var newOption = $("<option></option>")
                    .attr("value", newItem.id)
                    .attr("selected", true)
                    .text(newItem.first_name + " " + newItem.last_name);

                dropdownSelector.append(newOption).trigger("change");
            }

            // Prefill Follow Up Date
            var formatted = moment(followUp.follow_up_at).format('YYYY-MM-DDTHH:mm');
            $('input[name="follow_up_at"]').val(formatted);

            // Prefill Follow Up Type
            $('select[name="type"]').val(followUp.type);

            // Prefill Status field
            $('select[name="status"]').val(followUp.status);

            // Prefill Note field (make sure to decode HTML entities if needed)
            $('#edit_follow_up_note').val(followUp.note);

            // Optionally, you can populate any additional lead-related information if needed
            // Example: Pre-fill any lead-specific info in the form, if required

        },
        error: function (xhr, status, error) {
            console.error('Error:', error);
        }
    });
});


$(document).ready(function () {
    if ($("textarea#follow_up_note,textarea#edit_follow_up_note").length > 0) {

        $("textarea#follow_up_note,textarea#edit_follow_up_note").tinymce({
            height: 300,
            menubar: true,
        });
    }
});

$(document).ready(function () {
    // Use event delegation to handle clicks on dynamically loaded buttons
    $(document).on('click', '.convert-to-client', function (e) {
        e.preventDefault();

        var leadId = $(this).data('id');
        var csrfToken = $('meta[name="csrf-token"]').attr('content');

        if (!leadId) {
            toastr.error('Invalid lead ID.');
            return;
        }

        $.ajax({
            url: '/leads/' + leadId + '/convert-to-client',
            type: 'POST',
            data: {
                _token: csrfToken,
                lead_id: leadId
            },
            success: handleConvertSuccess,
            error: handleConvertError
        });
    });

    function handleConvertSuccess(response) {
        if (response.error || response.status === false) {
            toastr.error(response.message || 'Conversion failed.');
        } else {
            toastr.success(response.message || 'Lead successfully converted.');
            setTimeout(() => {
                location.reload();
            }, parseFloat(toastTimeOut) * 1000);
        }
    }

    function handleConvertError(xhr) {
        let message = 'Something went wrong.';
        if (xhr.responseJSON && xhr.responseJSON.message) {
            message = xhr.responseJSON.message;
        }
        toastr.error(message);
        setTimeout(() => {
            location.reload();
        }, parseFloat(toastTimeOut) * 1000);
    }
});



function setupScopedAIGenerator(generateBtnSelector, options = {}) {
    const defaultOptions = {
        promptSelector: '.ai-title',
        customPromptSelector: '.ai-custom-prompt',
        outputSelector: '.ai-output',
        loaderSelector: '.ai-loader',
        customPromptSwitchSelector: '.enableCustomPrompt',
        customPromptContainerSelector: '.customPromptContainer',
        endpoint: '/ai/generate-description'
    };
    const settings = { ...defaultOptions, ...options };
    // Toggle custom prompt textarea visibility using Bootstrap's d-none
    $(document).on('change', settings.customPromptSwitchSelector, function () {
        const isChecked = $(this).is(':checked');
        const $container = $(settings.customPromptContainerSelector);

        if (isChecked) {
            $container.removeClass('d-none');
        } else {
            $container.addClass('d-none');
        }
    });


    $(document).on('click', generateBtnSelector, function () {
        const $btn = $(this);
        const $scope = $btn.closest('.ai-wrapper');

        const useCustomPrompt = $scope.find(settings.customPromptSwitchSelector).is(':checked');
        let prompt;

        if (useCustomPrompt) {
            prompt = $scope.find(settings.customPromptSelector).val();
            if (!prompt) {
                toastr.error(label_enter_custom_prompt_first);
                return;
            }
        } else {
            prompt = $scope.find(settings.promptSelector).val();
            if (!prompt) {
                toastr.error(label_enter_project_title_first);
                return;
            }
        }

        const $output = $scope.find(settings.outputSelector);
        const existingDescription = $output.val(); // Get the existing description
        const $loader = $scope.find(settings.loaderSelector);

        $btn.prop('disabled', true);
        if ($loader.length) $loader.removeClass('d-none');

        $.ajax({
            url: settings.endpoint,
            method: 'POST',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                prompt: prompt,
                isCustomPrompt: useCustomPrompt,
                existingDescription: existingDescription // Send existing description to backend
            },
            success: function (response) {
                if (response.error) {
                    toastr.error(response.message);
                } else {
                    toastr.success(response.message);
                    $output.val(response.description);
                }
            },
            error: function (xhr) {
                if (xhr.status === 422) {
                    let errors = xhr.responseJSON.errors;
                    $.each(errors, function (key, value) {
                        toastr.error(value[0]);
                    });
                } else {
                    toastr.error(label_something_went_wrong);
                }
            },
            complete: function () {
                $btn.prop('disabled', false);
                if ($loader.length) $loader.addClass('d-none');
            }
        });
    });
}
// Initialize
$(document).ready(function () {
    setupScopedAIGenerator('.generate-ai');
});
$(document).ready(function () {
    // Listen for change events on the radio buttons with the class 'is_active_ai_model'
    $('.is_active_ai_model').on('change', function () {
        // When a radio button is selected, uncheck all others
        $('.is_active_ai_model').not(this).prop('checked', false);
    });
});
$(document).ready(function () {
    // Update temperature value displays when sliders change
    $('#openrouter_temperature').on('input', function () {
        $('#openrouter_temperature_value').text($(this).val());
    });

    $('#gemini_temperature').on('input', function () {
        $('#gemini_temperature_value').text($(this).val());
    });

    // Initialize Bootstrap tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});


if (document.getElementById("install-plugin-dropzone")) {
    // Initialize Dropzone for plugin installation
    if (!$("#install-plugin").hasClass("dropzone")) {
        var systemDropzone = new Dropzone("#install-plugin-dropzone", {
            url: $("#install-plugin").attr("action"),
            paramName: "plugin_zip",
            autoProcessQueue: false,
            parallelUploads: 1,
            maxFiles: 1,
            acceptedFiles: ".zip",
            timeout: 360000,
            autoDiscover: false,
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"), // Pass the CSRF token as a header
            },
            addRemoveLinks: true,
            dictRemoveFile: "x",
            dictMaxFilesExceeded: label_only_one_file_can_be_uploaded_at_a_time,
            dictResponseError: "Error",
            uploadMultiple: true,
            dictDefaultMessage:
                '<p><input type="button" value="' +
                label_select +
                '" class="btn btn-primary" /><br> ' +
                label_or +
                " <br> " +
                "Drag and drop the ZIP file here" +
                "</p>",
        });
        systemDropzone.on("addedfile", function (file) {
            var i = 0;
            if (this.files.length) {
                var _i, _len;
                for (_i = 0, _len = this.files.length; _i < _len - 1; _i++) {
                    if (
                        this.files[_i].name === file.name &&
                        this.files[_i].size === file.size &&
                        this.files[_i].lastModifiedDate.toString() ===
                        file.lastModifiedDate.toString()
                    ) {
                        this.removeFile(file);
                        i++;
                    }
                }
            }
        });
        systemDropzone.on("error", function (file, response) {
            // Remove the file
            systemDropzone.removeFile(file);
            // Re-enable the submit button and reset its text
            $("#install_plugin_btn")
                .attr("disabled", false)
                .text(label_update_the_system);
            var errorMessage = label_err_try_again;
            if (typeof response === "string") {
                errorMessage = response; // Use the response text if it's a string
            } else if (response.message) {
                errorMessage = response.message; // Use response.message if it exists
            }
            toastr.error(errorMessage);
        });
        systemDropzone.on("success", function (file, response) {
            console.log(response);

            $("#install_plugin_btn")
                .attr("disabled", false)
                .text(label_update_the_system);
            if (response.error) {
                // Remove the file
                systemDropzone.removeFile(file);
                // Re-enable the submit button and reset its text
                // Show the error message
                toastr.error(response.message);
            } else {
                // Show success message
                toastr.success(response.message);
                setTimeout(function () {
                    location.reload();
                }, parseFloat(toastTimeOut) * 1000);
            }
        });
        $("#install_plugin_btn").on("click", function (e) {
            e.preventDefault();
            var queuedFiles = systemDropzone.getQueuedFiles();
            if (queuedFiles.length > 0) {
                $("#install_plugin_btn")
                    .attr("disabled", true)
                    .text(label_please_wait);
                systemDropzone.processQueue();
            } else {
                toastr.error(label_no_files_chosen);
            }
        });
    }
}

/**
 * Enhanced Form Submission Handler with Smart Modal/Offcanvas Management
 *
 * This handler manages form submissions across the application with intelligent overlay closing,
 * error handling, and dependent property management. Designed for systems transitioning from
 * modals to offcanvas for better UX while maintaining backward compatibility.
 *
 *
 * FEATURES:
 * ========
 * • Smart Context Detection: Automatically detects modal vs offcanvas forms
 * • Nested Overlay Support: Handles Project (offcanvas) + Dependencies (modal) scenarios
 * • Intelligent Closing: Only closes relevant overlays based on form context
 * • Enhanced Error Handling: Context-aware error display and robust server error parsing
 * • Dependent Property Management: Seamless dropdown refresh for related entities
 * • Accessibility Compliant: Proper ARIA attributes and focus management
 * • Multi-Entity Support: Handles various entity types (projects, status, priority, tags, etc.)
 *
 * SUPPORTED SCENARIOS:
 * ==================
 * 1. Main Entity Forms (Projects, Users, etc.):
 *    - Opens in offcanvas
 *    - Closes offcanvas + any modals on success
 *    - Redirects or reloads page
 *
 * 2. Dependent Property Forms (Status, Priority, Tags):
 *    - Opens in modal (even when parent is offcanvas)
 *    - Only closes the modal on success
 *    - Keeps parent offcanvas open
 *    - Auto-refreshes parent form dropdowns
 *
 * 3. Independent Entity Forms:
 *    - Can be in modal or offcanvas
 *    - Closes only its container
 *
 * FORM MARKUP REQUIREMENTS:
 * ========================
 * 1. All forms must have class: "new-form-submit-event"
 * 2. Submit button must have id: "submit_btn"
 * 3. CSRF token via meta tag: <meta name="csrf-token" content="...">
 *
 * DEPENDENT PROPERTY DETECTION:
 * ============================
 * Mark dependent properties using ANY of these methods:
 * • Form class: <form class="new-form-submit-event dependent-property-form">
 * • Hidden input: <input type="hidden" name="is_dependent_property" value="1">
 * • Modal class: <div class="modal dependent-property-modal">
 * • Offcanvas class: <div class="offcanvas dependent-property-offcanvas">
 *
 * SERVER RESPONSE FORMAT:
 * ======================
 * Success Response:
 * {
 *   "error": false,
 *   "message": "Success message",
 *   "type": "status|priority|tag|etc", // For dropdown refresh
 *   "data": {
 *     "id": 123,
 *     "name": "New Item Name"
 *   }
 * }
 *
 * Validation Error Response (422):
 * {
 *   "errors": {
 *     "field_name": ["Error message 1", "Error message 2"]
 *   },
 *   "showInModal": true // Optional: show errors in modal/offcanvas container
 * }
 *
 * SPECIAL FORM INPUTS:
 * ===================
 * • redirect_url: Custom redirect after success
 * • table: Table ID for bootstrap-table refresh (default: "table")
 * • dnr: "Do Not Redirect" - refreshes table instead of redirecting
 * • is_dependent_property: Marks form as dependent property
 * • is_encoded: Indicates content field is base64 encoded
 *
 * SUPPORTED OVERLAYS:
 * ==================
 * • Bootstrap 5 Modals (.modal)
 * • Bootstrap 5 Offcanvas (.offcanvas)
 * • Backward compatibility with Bootstrap 4 modals
 *
 * SUPPORTED PLUGINS:
 * =================
 * • Select2 dropdowns
 * • Tinymce editors
 * • Dropzone file uploads
 * • Bootstrap Table
 * • Toastr notifications
 *
 * ERROR HANDLING:
 * ==============
 * • Field-level validation errors with visual indicators
 * • Database connection and SQL error parsing
 * • Graceful fallbacks for plugin failures
 * • Automatic focus on first error field
 * • Enhanced error messages for common database issues
 *
 * DROPDOWN REFRESH:
 * ================
 * Automatically refreshes parent form dropdowns when dependent properties are created.
 * Supports: status, priority, tags, categories, departments, clients, etc.
 *
 * ACCESSIBILITY:
 * =============
 * • Proper ARIA attributes management
 * • Keyboard navigation support
 * • Screen reader friendly error messages
 * • Focus management for error fields
 *
 * BROWSER SUPPORT:
 * ===============
 * • Modern browsers with ES6+ support
 * • jQuery 3.x required
 * • Bootstrap 5.x recommended (Bootstrap 4.x compatible)
 *
 * TROUBLESHOOTING:
 * ===============
 * • Check browser console for detailed error logs
 * • Ensure CSRF token is properly set
 * • Verify form has required classes and IDs
 * • Check server response format matches expected structure
 *
 * @example
 * // Basic form setup
 * <form class="new-form-submit-event" action="/projects/store" method="POST">
 *   <input type="hidden" name="_token" value="...">
 *   <input type="hidden" name="table" value="projects-table">
 *   <!-- form fields -->
 *   <button type="submit" id="submit_btn">Save Project</button>
 * </form>
 *
 * @example
 * // Dependent property form
 * <form class="new-form-submit-event dependent-property-form" action="/status/store" method="POST">
 *   <input type="hidden" name="_token" value="...">
 *   <input type="hidden" name="dnr" value="1">
 *   <!-- form fields -->
 *   <button type="submit" id="submit_btn">Save Status</button>
 * </form>
 */
$(document).on("submit", ".new-form-submit-event", function (e) {
    e.preventDefault();

    if ($("#net_payable").length > 0) {
        $("#net_pay").val($("#net_payable").text());
    }

    var formData = new FormData(this);

    // Encode HTML content for template saving
    if (
        $(this).attr("action").includes("store_template") ||
        $(this).attr("action").includes("/email-templates/store") ||
        $(this).attr("action").includes("email-templates/update") ||
        $(this).attr("action").includes("/emails/store") ||
        $(this).attr("action").includes("/emails/preview")
    ) {
        var contentField = $(this).find('textarea[name="content"], input[name="content"]');
        if (contentField.length > 0) {
            formData.delete("content");
            formData.append("content", btoa(contentField.val()));
            formData.append("is_encoded", "1");
        }
    }

    var currentForm = $(this);
    var submit_btn = currentForm.find("#submit_btn");
    var button_text = submit_btn.html() || submit_btn.val();
    var redirect_url = currentForm.find('input[name="redirect_url"]').val() || "";
    var tableID = currentForm.find('input[name="table"]').val() || "table";

    // Enhanced overlay type detection
    var isInModal = currentForm.closest('.modal').length > 0;
    var isInOffcanvas = currentForm.closest('.offcanvas').length > 0;
    var parentModal = isInModal ? currentForm.closest('.modal') : null;
    var parentOffcanvas = isInOffcanvas ? currentForm.closest('.offcanvas') : null;

    // Enhanced dependent property detection
    var isDependentProperty = currentForm.hasClass('dependent-property-form') ||
        currentForm.find('input[name="is_dependent_property"]').length > 0 ||
        currentForm.closest('.modal').hasClass('dependent-property-modal') ||
        currentForm.closest('.offcanvas').hasClass('dependent-property-offcanvas');

    // Enhanced contract dropzone handling for both modal and offcanvas
    if (currentForm.closest("#edit_contract_offcanvas, #edit_contract_modal").length > 0 &&
        typeof Dropzone !== 'undefined' && Dropzone.instances.length > 0) {
        try {
            var dropzoneInstance = Dropzone.forElement("#contract-dropzone");
            if (dropzoneInstance) {
                dropzoneInstance.getAcceptedFiles().forEach(function (file) {
                    formData.append("signed_pdf", file);
                });
            }
        } catch (error) {
            console.warn('Dropzone error:', error);
        }
    }




    $.ajax({
        type: "POST",
        url: currentForm.attr("action"),
        data: formData,
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val(),
        },
        beforeSend: function () {
            submit_btn.html(label_please_wait).prop("disabled", true);
        },
        cache: false,
        contentType: false,
        processData: false,
        dataType: "json",
        success: function (result) {
            submit_btn.html(button_text).prop("disabled", false);



            if (result.error) {
                toastr.error(result.message);
                return;
            }

            // Smart overlay closing
            handleOverlayClosing(isDependentProperty, parentModal, parentOffcanvas);




            // Handle success scenarios - REORDERED to check DNR first
            if (currentForm.find('input[name="dnr"]').length > 0) {

                // DNR scenario - refresh table and reset form
                if ($("#" + tableID).length) {
                    $("#" + tableID).bootstrapTable("refresh");
                }

                resetForm(currentForm);
                toastr.success(result.message || "Success");

                // Always try to refresh parent dropdowns for DNR forms in modal/offcanvas
                if ((isInModal || isInOffcanvas) && result.type && result.data) {

                    refreshParentFormDropdowns(result);
                }

                // Also refresh for dependent properties
                if (isDependentProperty) {
                    refreshParentFormDropdowns(result);
                }
            } else if ($(".empty-state").length > 0) {
                toastr.success(result.message || "Success");
                setTimeout(handleRedirection, parseFloat(toastTimeOut) * 1000);
            } else {
                toastr.success(result.message || "Success");

                // Always try to refresh parent dropdowns if we're in a modal/offcanvas
                if ((isInModal || isInOffcanvas) && result.type && result.data) {
                    refreshParentFormDropdowns(result);
                }

                setTimeout(handleRedirection, parseFloat(toastTimeOut) * 1000);
            }


            // Clear all error messages
            currentForm.find(".error-message").remove();
        },
        error: function (xhr) {
            submit_btn.html(button_text).prop("disabled", false);

            if (xhr.status === 422) {
                handleValidationErrors(xhr, currentForm, isInOffcanvas);
            } else {
                handleServerErrors(xhr);
            }
        }
    });

    // ✅ ENHANCED OVERLAY CLOSING LOGIC
    function handleOverlayClosing(isDependentProperty, parentModal, parentOffcanvas) {
        try {
            if (isDependentProperty) {
                // For dependent properties: only close the immediate modal
                if (parentModal) {
                    closeSpecificModal(parentModal);
                }
                // Keep offcanvas open for dependent properties
            } else if (parentOffcanvas) {
                // For main entities in offcanvas: close offcanvas and any modals
                closeSpecificOffcanvas(parentOffcanvas);
                closeAllModals();
            } else if (parentModal) {
                // For independent entities in modals: close only the modal
                closeSpecificModal(parentModal);
            } else {
                // Fallback: close everything
                closeAllOverlays();
            }
        } catch (error) {
            console.warn('Error in smart overlay closing:', error);
            closeAllOverlays();
        }
    }

    // ✅ ENHANCED MODAL CLOSING
    function closeSpecificModal(modalElement) {
        try {
            let modalInstance = bootstrap.Modal.getInstance(modalElement[0]);
            if (modalInstance) {
                modalInstance.hide();
            } else {
                modalElement.modal("hide");
            }
        } catch (error) {
            console.warn('Error closing specific modal:', error);
            modalElement.removeClass('show').css('display', 'none');
            modalElement.attr('aria-hidden', 'true').removeAttr('aria-modal');
        }
    }

    // ✅ ENHANCED OFFCANVAS CLOSING
    function closeSpecificOffcanvas(offcanvasElement) {
        try {
            let offcanvasInstance = bootstrap.Offcanvas.getInstance(offcanvasElement[0]);
            if (offcanvasInstance) {
                offcanvasInstance.hide();
            } else {
                offcanvasElement.removeClass('show').css('visibility', 'hidden');
                $('body').removeClass('offcanvas-open');
            }
        } catch (error) {
            console.warn('Error closing specific offcanvas:', error);
            offcanvasElement.removeClass('show').css('visibility', 'hidden');
            offcanvasElement.attr('aria-hidden', 'true');
            $('body').removeClass('offcanvas-open');
        }
    }

    // ✅ CLOSE ALL MODALS
    function closeAllModals() {
        try {
            $(".modal.show").each(function () {
                let modalInstance = bootstrap.Modal.getInstance(this);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    $(this).modal("hide");
                }
            });
        } catch (error) {
            console.warn('Error closing all modals:', error);
            $(".modal").removeClass('show').css('display', 'none');
        }
    }

    // ✅ ENHANCED CLOSE ALL OVERLAYS
    function closeAllOverlays() {
        try {
            // Close modals
            $(".modal.show").each(function () {
                let modalInstance = bootstrap.Modal.getInstance(this);
                if (modalInstance) {
                    modalInstance.hide();
                } else {
                    $(this).modal("hide");
                }
            });

            // Close offcanvas
            $(".offcanvas.show").each(function () {
                let offcanvasInstance = bootstrap.Offcanvas.getInstance(this);
                if (offcanvasInstance) {
                    offcanvasInstance.hide();
                } else {
                    $(this).removeClass('show').css('visibility', 'hidden');
                }
            });

            // Cleanup backdrops and body classes after animation
            setTimeout(function () {
                $('.modal-backdrop, .offcanvas-backdrop').remove();
                $('body').removeClass('modal-open offcanvas-open');
            }, 300);

        } catch (error) {
            console.warn('Error in closeAllOverlays:', error);
            // Force cleanup
            $('.modal, .offcanvas').removeClass('show');
            $('.modal-backdrop, .offcanvas-backdrop').remove();
            $('body').removeClass('modal-open offcanvas-open');
        }
    }

    // ✅ ENHANCED FORM RESET
    function resetForm(form) {
        try {
            form[0].reset();
            form.find("select").val(null).trigger("change");

            // Handle Select2 dropdowns
            form.find(".select2").each(function () {
                $(this).val(null).trigger('change');
            });

            // Handle specific elements
            if ($("#partialLeave").length) {
                $("#partialLeave").trigger("change");
            }

            if (typeof resetDateFields === 'function') {
                resetDateFields(form);
            }

            // Clear any summernote editors
            form.find('.summernote').each(function () {
                if ($(this).summernote) {
                    $(this).summernote('code', '');
                }
            });

        } catch (error) {
            console.warn('Error resetting form:', error);
        }
    }

    // ✅ ENHANCED VALIDATION ERROR HANDLING
    function handleValidationErrors(xhr, currentForm, isInOffcanvas) {
        toastr.error(label_please_correct_errors);
        var errors = xhr.responseJSON.errors || {};

        // Show errors in overlay-specific container
        if (xhr.responseJSON.showInModal) {
            var errorContainerId = isInOffcanvas ? '#errorOffcanvasContent' : '#errorModalContent';
            var errorBodyId = isInOffcanvas ? '#errorOffcanvasBody' : '#errorModalBody';
            let errorHtmlBody = "";

            $.each(errors, function (field, messages) {
                errorHtmlBody += `<div class="mb-2"><strong class="text-capitalize">${field.replace(/_/g, ' ')}</strong><ul class="mb-0 mt-1">`;
                $.each(messages, function (_, msg) {
                    errorHtmlBody += `<li>${msg}</li>`;
                });
                errorHtmlBody += `</ul></div>`;
            });

            $(errorContainerId).html(errorHtmlBody);
            $(errorBodyId).removeClass('d-none');
        }

        // Render field-specific errors with enhanced targeting
        var inputFields = $(currentForm.find("input[name], select[name], textarea[name]").get().reverse());
        var firstErrorField = null;

        inputFields.each(function () {
            var input = $(this);
            var fieldName = input.attr("name");
            var errorMessage = errors[fieldName];
            var parent = input.closest(".form-group, .input-group, .form-control, .form-select, .mb-3, .mb-2");

            // Remove existing error messages
            parent.find(".text-danger.error-message").remove();

            if (errorMessage) {
                if (!firstErrorField) firstErrorField = input;

                var msg = Array.isArray(errorMessage) ? errorMessage[0] : errorMessage;
                var errorEl = $('<span class="text-danger error-message d-block mt-1 small"></span>').text(msg);

                // Enhanced error placement logic
                if (input.hasClass("select2-hidden-accessible")) {
                    input.siblings(".select2").after(errorEl);
                } else if (input.is("textarea#privacy_policy")) {
                    input.parent().find(".mt-2").first().before(errorEl);
                } else if (input.closest('.input-group').length) {
                    input.closest('.input-group').after(errorEl);
                } else {
                    input.after(errorEl);
                }
            }
        });

        // Scroll to first error field
        if (firstErrorField) {
            setTimeout(function () {
                firstErrorField[0].scrollIntoView({ behavior: "smooth", block: "center" });
                firstErrorField.focus();
            }, 100);
        }
    }

    // ✅ ENHANCED SERVER ERROR HANDLING
    function handleServerErrors(xhr) {
        let msg = xhr.responseJSON?.message || "An unexpected error occurred.";

        // Enhanced error message parsing
        if (xhr.responseJSON?.exception) {
            // Database connection errors
            let match = msg.match(/Access denied for user '([^']+)'@/);
            if (match) {
                msg = `Database access denied for user '${match[1]}'. Please check your database credentials.`;
            }
            // SQL State errors
            else if (/SQLSTATE\[(\w+)\]/.test(msg)) {
                let sqlMatch = msg.match(/SQLSTATE\[(\w+)\]: (.+?)(?:\s\(SQL:|$)/);
                if (sqlMatch) {
                    msg = `Database Error [${sqlMatch[1]}]: ${sqlMatch[2]}`;
                }
            }
            // Connection timeout errors
            else if (msg.includes('Connection timed out')) {
                msg = "Database connection timed out. Please try again.";
            }
            // General SQL errors
            else if (msg.includes('Query Exception')) {
                msg = "Database query error. Please contact support if this persists.";
            }
        }

        toastr.error(msg);
    }

    // ✅ ENHANCED DROPDOWN REFRESH WITH MORE ENTITY TYPES AND COMPREHENSIVE DEBUG
    function refreshParentFormDropdowns(result) {
        console.log('=== DEBUG REFRESH FUNCTION ===');
        console.log('Result received:', result);
        console.log('isInModal:', isInModal);
        console.log('isInOffcanvas:', isInOffcanvas);

        if (!result.data || !result.data.id || !result.data.name || !result.type) {
            console.log('Missing required data - exiting function');
            console.log('result.data:', result.data);
            console.log('result.type:', result.type);
            return;
        }

        // Enhanced mapping for more entity types
        let selectMap = {
            status: ['status_id', 'project_status_id', 'task_status_id', 'status'],
            priority: ['priority_id', 'project_priority_id', 'task_priority_id', 'priority'],
            tag: ['tag_ids[]', 'tags[]', 'tag_id'],
            contract_type: ['contract_type_id', 'contract_type'],
            payment_method: ['payment_method_id'],
            allowance: ['allowance_id', 'allowance_ids[]'],
            deduction: ['deduction_id', 'deduction_ids[]'],
            item: ['item_id', 'product_id'],
            category: ['category_id'],
            department: ['department_id'],
            designation: ['designation_id'],
            client: ['client_id', 'customer_id'],
            project: ['project_id'],
            workspace: ['workspace_id']
        };

        console.log('Looking for type:', result.type);
        let targetSelects = selectMap[result.type] || [];
        console.log('Target selects:', targetSelects);

        if (!targetSelects.length) {
            console.log('No target selects found for type:', result.type);
            return;
        }

        // Look for selects in the active offcanvas first, then modal
        let activeContainer = $(".offcanvas.show");
        if (!activeContainer.length) {
            activeContainer = $(".modal.show").not('#create_status_modal'); // Exclude the status creation modal itself
        }

        console.log('Active container found:', activeContainer.length);
        console.log('Container selector:', activeContainer.selector || 'Direct jQuery object');

        if (activeContainer.length) {
            console.log('All selects in container:', activeContainer.find('select').map(function () {
                return {
                    name: this.name || 'NO_NAME',
                    id: this.id || 'NO_ID',
                    classes: this.className
                };
            }).get());

            let foundAndUpdated = false;

            targetSelects.forEach(function (selectName) {
                console.log('Looking for selector with name:', selectName);
                let selector = activeContainer.find(`select[name="${selectName}"]`);
                console.log("Found selector:", selector.length, selector);

                if (selector.length) {
                    console.log('Found matching selector - adding option');
                    foundAndUpdated = true;

                    // Check if option already exists
                    if (selector.find(`option[value="${result.data.id}"]`).length === 0) {
                        let newOption = new Option(result.data.name, result.data.id, true, true);
                        selector.append(newOption);
                        console.log('Added new option:', result.data.name);
                    } else {
                        selector.val(result.data.id);
                        console.log('Selected existing option:', result.data.name);
                    }

                    // Trigger change event for Select2 and other plugins
                    selector.trigger('change');

                    // Handle Select2 specifically
                    if (selector.hasClass('select2-hidden-accessible')) {
                        console.log('Triggering Select2 events');
                        selector.trigger('change.select2');

                        // Force Select2 to recognize the new option
                        setTimeout(function () {
                            selector.val(result.data.id).trigger('change.select2');
                        }, 100);
                    }

                    // Handle Ajax Select2 dropdowns differently
                    if (selector.data('select2') && selector.data('select2').options && selector.data('select2').options.ajax) {
                        console.log('Handling Ajax Select2');
                        // For Ajax Select2, we need to trigger a manual selection
                        selector.trigger({
                            type: 'select2:select',
                            params: {
                                data: {
                                    id: result.data.id,
                                    text: result.data.name
                                }
                            }
                        });
                    }
                } else {
                    console.log('No selector found for:', selectName);
                }
            });

            if (!foundAndUpdated) {
                console.warn('No matching selector found for type:', result.type, 'in container');
            }
        } else {
            console.log('No active container found');
        }
        console.log('=== END DEBUG ===');
    }

    // ✅ ENHANCED REDIRECTION
    function handleRedirection() {
        try {
            if (redirect_url) {
                window.location.href = redirect_url;
            } else {
                window.location.reload();
            }
        } catch (error) {
            console.warn('Redirection error:', error);
            window.location.reload();
        }
    }
});
