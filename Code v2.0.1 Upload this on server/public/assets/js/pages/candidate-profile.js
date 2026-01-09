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




$(document).ready(function () {
    function setupFileUpload(modalId) {
        console.log(modalId);

        const $modal = $('#' + modalId);
        const $fileInput = $modal.find('.file-input');
        const $fileNamesList = $modal.find('.file-names-list');
        let selectedFiles = [];

        $fileInput.on('change', function () {
            selectedFiles = Array.from(this.files);
            console.log("Selected files:", selectedFiles); // Debug
            renderFileList();
        });

        $fileNamesList.on('click', '.btn-close', function () {
            const fileNameToRemove = $(this).data('file');
            selectedFiles = selectedFiles.filter(file => file.name !== fileNameToRemove);

            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            $fileInput[0].files = dataTransfer.files;
            console.log("Selected files:", selectedFiles); // Debug
            renderFileList();
        });

        function renderFileList() {
            $fileNamesList.empty();
            selectedFiles.forEach(file => {
                const $li = $(`
                    <li class="list-group-item d-flex justify-content-between align-items-center py-1 px-2 small">
                        <span>${file.name}</span>
                        <button type="button" class="btn btn-sm btn-close" aria-label="Remove" data-file="${file.name}"></button>
                    </li>
                `);
                $fileNamesList.append($li);
            });
        }

        // Reset input and file list on modal hide
        $modal.on('hidden.bs.modal', function () {
            $fileInput.val('');
            $fileNamesList.empty();
            selectedFiles = [];
        });
    }

    // Initialize for both modals
    setupFileUpload('candidateModal');
    setupFileUpload('candidateUpdateModal');
});

