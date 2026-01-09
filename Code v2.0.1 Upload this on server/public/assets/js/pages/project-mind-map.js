$(document).ready(function () {
    var options = {
        container: 'mind-map',
        editable: false,
        theme: 'taskify',
        mode: 'full',
        support_html: true,
    };

    // Create a new jsMind instance
    var jm = new jsMind(options);
    jm.show(mindMapData);

    // Handle clicks on the mind map nodes
    $('#mind-map').on('click', function () {
        var node = jm.get_selected_node(); // Get the currently selected node
        if (node && node.data && node.data.link) {
            window.location.href = node.data.link; // Redirect to the URL
        }
    });

    // Export functionality
    $('.export-mindmap-btn').on('click', function () {
        try {
            jm.shoot(); // Trigger the export
            setTimeout(function () {
                toastr.success(label_mm_export_success);
            }, 2000);
        } catch (error) {
            toastr.error(label_mm_export_failed);
            console.log(error);
        }
    });
});
