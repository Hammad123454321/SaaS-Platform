window.icons = {
    refresh: "bx-refresh",
};

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>';
}

function queryParamsTasks(p) {
    let task_parent_id2 =
        typeof task_parent_id !== "undefined" && task_parent_id !== null
            ? task_parent_id
            : null;
    return {
        status_ids: $("#task_status_filter").val(),
        priority_ids: $("#task_priority_filter").val(),
        user_ids: $("#task_user_filter").val(),
        client_ids: $("#task_client_filter").val(),
        project_ids: $("#task_project_filter").val(),
        task_date_between_from: $("#task_date_between_from").val(),
        task_date_between_to: $("#task_date_between_to").val(),
        task_start_date_from: $("#task_start_date_from").val(),
        task_start_date_to: $("#task_start_date_to").val(),
        task_end_date_from: $("#task_end_date_from").val(),
        task_end_date_to: $("#task_end_date_to").val(),
        is_favorites: $("#is_favorites").val(),
        task_parent_id: task_parent_id2,
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}
addDebouncedEventListener(
    "#task_status_filter, #task_priority_filter, #task_user_filter, #task_client_filter, #task_project_filter",
    "change",
    function (e, refreshTable) {
        e.preventDefault();
        if (typeof refreshTable === "undefined" || refreshTable) {
            $("#task_table").bootstrapTable("refresh");
        }
    }
);

function assignedFormatter(value, row, index) {
    return (
        '<div class="d-flex justify-content-start align-items-center"><div class="text-center mx-4"><span class="badge rounded-pill bg-primary" >' +
        row.projects +
        "</span><div>" +
        label_projects +
        "</div></div>" +
        '<div class="text-center"><span class="badge rounded-pill bg-primary" >' +
        row.tasks +
        "</span><div>" +
        label_tasks +
        "</div></div></div>"
    );
}

function queryParamsUsersClients(p) {
    return {
        type: $("#type").val(),
        typeId: $("#typeId").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

$(document).on("click", ".clear-tasks-filters", function (e) {
    e.preventDefault();
    $("#task_date_between").val("");
    $("#task_date_between_from").val("");
    $("#task_date_between_to").val("");
    $("#task_start_date_between").val("");
    $("#task_end_date_between").val("");
    $("#task_start_date_from").val("");
    $("#task_start_date_to").val("");
    $("#task_end_date_from").val("");
    $("#task_end_date_to").val("");
    $("#task_project_filter").val("").trigger("change", [0]);
    $("#task_user_filter").val("").trigger("change", [0]);
    $("#task_client_filter").val("").trigger("change", [0]);
    $("#task_status_filter").val("").trigger("change", [0]);
    $("#task_priority_filter").val("").trigger("change", [0]);
    $("#task_table").bootstrapTable("refresh");
});

$("#viewAssignedModal").on("hidden.bs.modal", function (e) {
    e.preventDefault();
    $(".clear-tasks-filters").trigger("click");
});

$(document).ready(function () {
    $("#task_date_between").on("apply.daterangepicker", function (ev, picker) {
        var startDate = picker.startDate.format("YYYY-MM-DD");
        var endDate = picker.endDate.format("YYYY-MM-DD");
        $("#task_date_between_from").val(startDate);
        $("#task_date_between_to").val(endDate);
        $("#task_table").bootstrapTable("refresh");
    });

    // Cancel event to clear values
    $("#task_date_between").on("cancel.daterangepicker", function (ev, picker) {
        $("#task_date_between_from").val("");
        $("#task_date_between_to").val("");
        $(this).val("");
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $("#task_table").bootstrapTable("refresh");
    });
});
