$(document).ready(function () {
    $("#sort").on("change", function () {
        $('#table').bootstrapTable('refresh');
    });
    $("#selected_sources").on("change", function () {
        $('#table').bootstrapTable('refresh');
    });
    $("#selected_stages").on("change", function () {
        $('#table').bootstrapTable('refresh');
    });
    $("#lead_date_range").on(
        "apply.daterangepicker",
        function (ev, picker) {
            var startDate = picker.startDate.format("YYYY-MM-DD");
            var endDate = picker.endDate.format("YYYY-MM-DD");
            $('#lead_end_date').val(endDate);
            $('#lead_start_date').val(startDate);
            $("#table").bootstrapTable('refresh');
        }
    );
    $("#lead_date_range").on(
        "cancel.daterangepicker",
        function (ev, picker) {
            $('#lead_end_date').val('');
            $('#lead_start_date').val('');
            $('#lead_date_range').val('');
            picker.setStartDate(moment());
            picker.setEndDate(moment());
            picker.updateElement();
            $("#table").bootstrapTable('refresh');
        }
    );

});
function queryParamsLead(p) {
    return {
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
        sort: $('#sort').val(),
        source_ids: $('#selected_sources').val(),
        start_date: $('#lead_start_date').val(),
        end_date: $('#lead_end_date').val(),
        stage_ids: $('#selected_stages').val(),
    };
}
