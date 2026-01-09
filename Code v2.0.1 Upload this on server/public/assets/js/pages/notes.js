$(document).ready(function () {
    let editor = null;
    let drawingContainer = document.getElementById("drawing-container");
    let drawingDataInput = document.getElementById("drawing_data");

    // Debugging: Ensure jQuery is available
    console.log("jQuery Loaded:", typeof $ !== "undefined");

    // Ensure #noteType exists before binding event
    if ($("#noteType").length === 0) {
        console.error("Error: #noteType not found!");
    } else {
        console.log("Binding change event to #noteType...");
    }

    // Toggle note type for new notes
    $(document).on("change", "#noteType", function () {
        console.log("Note type changed to:", $(this).val());

        let selectedType = $(this).val();
        if (selectedType === "text") {
            $("#text-note-section").removeClass('d-none');
            $("#drawing-note-section").addClass('d-none');
        } else if (selectedType === "drawing") {
            $("#text-note-section").addClass('d-none');
            $("#drawing-note-section").removeClass('d-none');
            initDrawing();
        } else {
            console.warn("Unexpected note type:", selectedType);
        }
    });

    // Submit drawing data for new notes
    $(document).on('click', '#submit_btn', function (e) {
        if ($("#noteType").val() === "drawing" && editor) {
            e.preventDefault();
            console.log("Saving drawing data...");
            let drawingData = editor.toSVG().outerHTML;
            let encodedDrawingData = btoa(unescape(encodeURIComponent(drawingData)));

            $("#drawing_data").val(encodedDrawingData);
            console.log("Drawing data:", $("#drawing_data").val());
            console.log("Drawing data saved, length:", drawingData);
            // return false;

            $(this).closest("form").submit();
        }
    });

    function initDrawing() {
        if (!editor && drawingContainer) {
            try {
                console.log("Initializing drawing editor...");
                editor = new jsdraw.Editor(drawingContainer);
                editor.getRootElement().style.height = "260px";
                // editor.zoomLevel('45%');
                const toolbar = editor.addToolbar();
                $('.toolbar-internalWidgetId--selection-tool-widget, .toolbar-internalWidgetId--text-tool-widget, .toolbar-internalWidgetId--document-properties-widget, .pipetteButton ,.toolbar-internalWidgetId--insert-image-widget').hide();

                setTimeout(() => {
                    $(".toolbar--pen-tool-toggle-buttons").hide();
                }, 500);
            } catch (e) {
                console.error("Error initializing jsDraw:", e);
            }
        }
    }
});

// Prevent form submission on toolbar zoom button clicks
$(document).on('click', '#drawing-container button', function (e) {
    if ($(this).closest('.toolbar-zoomLevelEditor').length > 0) {
        e.preventDefault();
        e.stopPropagation();
    }
});

$("#create_note_modal").on("hidden.bs.modal", function () {
    $("#text-note-section").removeClass('d-none');
    $("#drawing-note-section").addClass('d-none');
});

$("#edit_note_modal").on("hidden.bs.modal", function () {
    $("#edit-text-note-section").removeClass('d-none');
    $("#edit-drawing-note-section").addClass('d-none');
});
