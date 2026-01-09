'use strict';
var elements = [];

// Loop through the statusArray and generate elements
for (var i = 0; i < statusArray.length; i++) {
    var sts = statusArray[i];    
    var element = document.getElementById(sts.slug);

    // Check if the element exists before adding it to the elements array
    if (element) {
        elements.push(element);
    }
}

$(function () {
    var drake = dragula(elements, {
        revertOnSpill: true
    });

    if (!drake) {
        console.error("Dragula initialization failed");
        return;
    }

    var oldParent; // Variable to store the old parent element

    drake.on('drag', function (el, source) {
        // Store the old parent element when dragging starts
        oldParent = source;
    });

    drake.on('drop', function (el, target) {
        // Get the task ID and new status
        var taskId = el.getAttribute('data-task-id');
        var newStatus = target.getAttribute('data-status');

        // Make an AJAX call to update the task status
        $.ajax({
            method: "PUT",
            url: baseUrl + '/tasks/' + taskId + '/update-status/' + newStatus,
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                'flash_message_only': 1,
            },
            success: function (response) {
                if (response.error == false) {
                    toastr.success(response.message); // show a success message
                } else {
                    toastr.error(response.message);
                    // Revert back to the old status
                    drake.cancel(true); // Cancel the drop operation
                    // Manually revert the element to the old status
                    $(oldParent).append(el); // Append the element back to the old parent
                }
            },
            error: function () {
                toastr.error("An error occurred during the AJAX request");
                // Revert back to the old status
                drake.cancel(true); // Cancel the drop operation
                // Manually revert the element to the old status
                $(oldParent).append(el); // Append the element back to the old parent
            }
        });
    });
});

