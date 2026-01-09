/**
 * Dashboard Analytics
 */
"use strict";

function queryParamsProjectMedia(p) {
    return {
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

(function () {
    let cardColor, headingColor, axisColor, shadeColor, borderColor;

    cardColor = config.colors.white;
    headingColor = config.colors.headingColor;
    axisColor = config.colors.axisColor;
    borderColor = config.colors.borderColor;

    // Tasks Statistics Chart
    // --------------------------------------------------------------------

    var options = {
        labels: labels,
        series: task_data,
        colors: bg_colors,
        chart: {
            type: "donut",
            height: 300,
            width: 300,
        },
        responsive: [
            {
                breakpoint: 480,
                options: {
                    chart: {
                        width: 200,
                    },
                },
            },
        ],
    };

    var chart = new ApexCharts(
        document.querySelector("#taskStatisticsChart"),
        options
    );
    chart.render();
})();

function queryParams(p) {
    return {
        user_ids: $("#user_filter").val(),
        client_ids: $("#client_filter").val(),
        activities: $("#activity_filter").val(),
        type: "project",
        type_id: $("#type_id").val(),
        types: $("#type_filter").val(),
        date_from: $("#activity_log_between_date_from").val(),
        date_to: $("#activity_log_between_date_to").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

$(document).on("click", ".clear-activity-log-filters", function (e) {
    e.preventDefault();
    $("#activity_log_between_date_from").val("");
    $("#activity_log_between_date_to").val("");
    $("#activity_log_between_date").val("");
    $("#user_filter").val("").trigger("change", [0]);
    $("#client_filter").val("").trigger("change", [0]);
    $("#activity_filter").val("").trigger("change", [0]);
    $("#type_filter").val("").trigger("change", [0]);
    $("#activity_log_table").bootstrapTable("refresh");
});

function queryParamsProjectMilestones(p) {
    return {
        type_id: $("#type_id").val(),
        date_between_from: $("#ms_date_between_from").val(),
        date_between_to: $("#ms_date_between_to").val(),
        start_date_from: $("#start_date_from").val(),
        start_date_to: $("#start_date_to").val(),
        end_date_from: $("#end_date_from").val(),
        end_date_to: $("#end_date_to").val(),
        statuses: $("#status_filter").val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search,
    };
}

$(document).on("click", ".clear-milestones-filters", function (e) {
    e.preventDefault();
    $("#ms_date_between").val("");
    $("#ms_date_between_from").val("");
    $("#ms_date_between_to").val("");
    $("#start_date_between").val("");
    $("#end_date_between").val("");
    $("#start_date_from").val("");
    $("#start_date_to").val("");
    $("#end_date_from").val("");
    $("#end_date_to").val("");
    $("#status_filter").val("").trigger("change", [0]);
    $("#project_milestones_table").bootstrapTable("refresh");
});

$("#start_date_between").on("apply.daterangepicker", function (ev, picker) {
    var startDate = picker.startDate.format("YYYY-MM-DD");
    var endDate = picker.endDate.format("YYYY-MM-DD");

    $("#start_date_from").val(startDate);
    $("#start_date_to").val(endDate);

    $("#project_milestones_table").bootstrapTable("refresh");
});

$("#start_date_between").on("cancel.daterangepicker", function (ev, picker) {
    $("#start_date_from").val("");
    $("#start_date_to").val("");
    $("#start_date_between").val("");
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $("#project_milestones_table").bootstrapTable("refresh");
});

$("#end_date_between").on("apply.daterangepicker", function (ev, picker) {
    var startDate = picker.startDate.format("YYYY-MM-DD");
    var endDate = picker.endDate.format("YYYY-MM-DD");

    $("#end_date_from").val(startDate);
    $("#end_date_to").val(endDate);

    $("#project_milestones_table").bootstrapTable("refresh");
});
$("#end_date_between").on("cancel.daterangepicker", function (ev, picker) {
    $("#end_date_from").val("");
    $("#end_date_to").val("");
    $("#end_date_between").val("");
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $("#project_milestones_table").bootstrapTable("refresh");
});

$("#status_filter").on("change", function (e) {
    e.preventDefault();
    $("#project_milestones_table").bootstrapTable("refresh");
});

$("#milestone_progress").on("change", function (e) {
    var rangeValue = $(this).val();
    $(".milestone-progress").text(rangeValue + "%");
    if (rangeValue == 100) {
        $("#milestone_status").val("complete");
    } else {
        $("#milestone_status").val("incomplete");
    }
});

$(document).ready(function () {
    $("#ms_date_between").on("apply.daterangepicker", function (ev, picker) {
        var startDate = picker.startDate.format("YYYY-MM-DD");
        var endDate = picker.endDate.format("YYYY-MM-DD");
        $("#ms_date_between_from").val(startDate);
        $("#ms_date_between_to").val(endDate);
        $("#project_milestones_table").bootstrapTable("refresh");
    });

    // Cancel event to clear values
    $("#ms_date_between").on("cancel.daterangepicker", function (ev, picker) {
        $("#ms_date_between_from").val("");
        $("#ms_date_between_to").val("");
        $(this).val("");
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $("#project_milestones_table").bootstrapTable("refresh");
    });
});

$("#activity_log_between_date").on(
    "apply.daterangepicker",
    function (ev, picker) {
        var startDate = picker.startDate.format("YYYY-MM-DD");
        var endDate = picker.endDate.format("YYYY-MM-DD");

        $("#activity_log_between_date_from").val(startDate);
        $("#activity_log_between_date_to").val(endDate);

        $("#activity_log_table").bootstrapTable("refresh");
    }
);

$("#activity_log_between_date").on(
    "cancel.daterangepicker",
    function (ev, picker) {
        $("#activity_log_between_date_from").val("");
        $("#activity_log_between_date_to").val("");
        $("#activity_log_between_date").val("");
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $("#activity_log_table").bootstrapTable("refresh");
    }
);

addDebouncedEventListener(
    "#user_filter, #client_filter, #activity_filter, #type_filter",
    "change",
    function (e, refreshTable) {
        e.preventDefault();
        if (typeof refreshTable === "undefined" || refreshTable) {
            $("#activity_log_table").bootstrapTable("refresh");
        }
    }
);

$(document).ready(function () {
    // Constants and cache DOM elements
    const imageBaseUrl = window.location.origin;
    const $commentModal = new bootstrap.Modal($("#commentModal")[0]);
    const $replyModal = new bootstrap.Modal($("#replyModal")[0]);
    const $commentForm = $("#comment-form");
    const $replyForm = $("#replyForm");
    const $commentThread = $(".comment-thread");
    const $loadMoreButton = $("#load-more-comments");
    const $hideButton = $("#hide-comments");
    let visibleCommentsCount = 5;
    // Event Handlers
    $(document).on("click", ".open-reply-modal", openReplyModal);
    $commentForm.on("submit", handleCommentSubmit);
    $replyForm.on("submit", handleReplySubmit);
    $(document).on("click", "#cancel-comment-btn", () =>
        cancelForm($commentForm, $commentModal)
    );
    $(document).on("click", "#cancel-reply-btn", () =>
        cancelForm($replyForm, $replyModal)
    );
    $(document).on("mouseenter", ".attachment-link", function () {
        togglePreview($(this), true);
    });
    $(document).on("mouseleave", ".attachment-link", function () {
        togglePreview($(this), false);
    });
    $loadMoreButton.on("click", loadMoreComments);
    $hideButton.on("click", hideComments);
    // Initialize comment visibility
    initializeCommentVisibility();
    function openReplyModal() {
        const parentId = $(this).data("comment-id");
        $replyForm.find('input[name="parent_id"]').val(parentId);
        $replyModal.show();
    }
    function handleCommentSubmit(event) {
        event.preventDefault();
        submitForm($(this), $commentModal, prependNewComment);
    }
    function handleReplySubmit(event) {
        event.preventDefault();
        submitForm($(this), $replyModal, prependNewReply);
    }
    function submitForm($form, modal, successCallback) {
        // Select the submit button
        const $submitButton = $form.find('button[type="submit"]');
        const originalButtonText = $submitButton.html();

        // Disable the button and change its text
        $submitButton.prop("disabled", true).html(label_please_wait);

        $.ajax({
            url: $form.attr("action"),
            type: "POST",
            data: new FormData($form[0]),
            processData: false,
            contentType: false,
            success: function (data) {
                if (data.success) {
                    modal.hide();
                    successCallback(data);
                    $(".no_comments").hide();
                    toastr.success(data.message);
                    $form[0].reset();
                } else {
                    toastr.error(label_something_went_wrong);
                }
            },
            error: function (data) {
                if (data.responseJSON && data.responseJSON.message) {
                    toastr.error(data.responseJSON.message);
                } else {
                    toastr.error(label_something_went_wrong);
                }
            },
            complete: function () {
                $submitButton.attr("disabled", false).html(originalButtonText);
            },
        });
    }

    function prependNewComment(data) {
        $commentThread.prepend(createCommentHTML(data, true));
    }
    function prependNewReply(data) {
        const $parentComment = $(`#comment-${data.comment.parent_id}`);
        let $repliesContainer = $parentComment.find(".replies");
        if ($repliesContainer.length === 0) {
            $repliesContainer = $("<div class=" + label_replies + "></div>");
            $parentComment.append($repliesContainer);
        }
        $repliesContainer.prepend(createCommentHTML(data, false));
    }
    function createCommentHTML(data, isMainComment) {
        // Check if commenter is a user or a client
        const isClient = data.comment.commenter_type == "App\\Models\\Client";
        const commenter = data.user;

        // Determine if the profile link should be displayed
        const showProfileLink = isClient ? canManageClients : canManageUsers;

        return `
            <details open class="comment" id="comment-${data.comment.id}">
                <a href="#comment-${
                    data.comment.id
                }" class="comment-border-link">
                    <span class="sr-only">${label_jump_to_comment}-${data.comment.id}</span>
                </a>
                <summary>
                    <div class="comment-heading">
                        <div class="comment-avatar">
                            <img src="${
                                commenter.photo
                                    ? `${imageBaseUrl}/storage/${commenter.photo}`
                                    : `${imageBaseUrl}/storage/photos/no-image.jpg`
                            }"
                            alt="${commenter.first_name} ${commenter.last_name}"
                            class="bg-footer-theme rounded-circle border" width="40">
                        </div>

                        <div class="comment-info">
                            ${
                                showProfileLink
                                    ? `<a href="${
                                          isClient
                                              ? `${imageBaseUrl}/clients/profile/${commenter.id}`
                                              : `${imageBaseUrl}/users/profile/${commenter.id}`
                                      }"
                                         class="comment-author ${
                                             isMainComment
                                                 ? "fw-semibold"
                                                 : "fw-light"
                                         } text-body">
                                        ${commenter.first_name} ${
                                          commenter.last_name
                                      }
                                    </a>`
                                    : `<span class="comment-author text-body fw-light cursor-default text-decoration-none">
                                        ${commenter.first_name} ${commenter.last_name}
                                    </span>`
                            }
                            <p class="m-0">${data.created_at}</p>
                        </div>

                        ${
                            isAdminOrHasAllDataAccess
                                ? `
                        <div class="comment-actions d-flex ms-5 p-0">
                            <a href="javascript:void(0);"
                               data-comment-id="${data.comment.id}"
                               class="btn btn-sm text-primary edit-comment p-0"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="${label_edit}">
                                <i class="bx bx-edit"></i>
                            </a>
                            <a href="javascript:void(0);"
                               data-comment-id="${data.comment.id}"
                               class="btn btn-sm text-danger delete-comment p-0"
                               data-bs-toggle="tooltip"
                               data-bs-placement="top"
                               title="${label_delete}">
                                <i class="bx bx-trash"></i>
                            </a>
                        </div>
                        `
                                : ""
                        }
                    </div>
                </summary>
                <div class="comment-body">
                    <p ${
                        !isMainComment ? 'class="text-secondary"' : ""
                    }>${data.comment.content}</p>
                    ${createAttachmentsHTML(data.comment.attachments)}
                    ${
                        isMainComment
                            ? `<button type="button" class="open-reply-modal mt-3" data-comment-id="${data.comment.id}">${label_reply}</button>`
                            : ""
                    }
                </div>
            </details>
        `;
    }
    function createAttachmentsHTML(attachments) {
        if (!attachments || attachments.length === 0) return "";

        return `
            <div class="attachments mt-2">
                ${attachments
                    .map(
                        (att) => `
                        <div class="attachment-item d-flex align-items-center justify-content-between">
                            <div class="attachment-preview-container">
                                <a href="${imageBaseUrl}/storage/${
                            att.file_path
                        }" target="_blank"
                                   class="attachment-link" data-preview-url="${imageBaseUrl}/storage/${
                            att.file_path
                        }">
                                    ${
                                        att.file_name
                                            ? att.file_name
                                            : "Attachment"
                                    }
                                </a>
                                <div class="attachment-preview"></div>
                            </div>
                            <!-- Action Buttons Group -->
                            <div class="attachment-actions d-flex gap-2">
                                <!-- Download Button -->
                                <a href="${imageBaseUrl}/storage/${
                            att.file_path
                        }"
                                   download="${
                                       att.file_name ? att.file_name : "file"
                                   }"
                                   class="text-primary" title="${label_download}">
                                    <i class="bx bx-download fs-4"></i>
                                </a>
                                <!-- Delete Icon -->
                                <a href="javascript:void(0);"
                                   class="text-danger delete-attachment"
                                   data-attachment-id="${att.id}"
                                   title="${label_delete}">
                                   <i class="bx bx-trash fs-4"></i>
                                </a>
                            </div>
                        </div>
                    `
                    )
                    .join("")}
            </div>
        `;
    }

    function cancelForm($form, modal) {
        $form[0].reset();
        modal.hide();
    }
    function togglePreview($link, show) {
        const $previewContainer = $link.next(".attachment-preview");
        if (show) {
            const previewUrl = $link.data("preview-url");
            $previewContainer.empty();
            if (previewUrl.match(/\.(jpeg|jpg|gif|png)$/i)) {
                $("<img>", {
                    src: previewUrl,
                    css: { maxWidth: "300px", maxHeight: "200px" },
                }).appendTo($previewContainer);
            } else if (previewUrl.match(/\.(pdf)$/i)) {
                $("<iframe>", {
                    src: previewUrl,
                    width: "250",
                    height: "150",
                }).appendTo($previewContainer);
            } else {
                $previewContainer.text(label_preview_not_available);
            }
            $previewContainer.show();
        } else {
            $previewContainer.hide();
        }
    }
    function initializeCommentVisibility() {
        const $comments = $commentThread.find(".comment");
        $comments.each(function (index) {
            $(this).toggle(index < visibleCommentsCount);
        });
        $hideButton.hide(); // Hide the "Hide" button initially
        $loadMoreButton.toggle($comments.length > visibleCommentsCount);
    }
    function loadMoreComments() {
        visibleCommentsCount += 5;
        const $comments = $commentThread.find(".comment");
        $comments.each(function (index) {
            $(this).toggle(index < visibleCommentsCount);
        });
        $hideButton.toggle(visibleCommentsCount > 5); // Show the "Hide" button if more than 5 comments are visible
        $loadMoreButton.toggle(visibleCommentsCount < $comments.length); // Hide the "Load More" button if all comments are visible
    }
    function hideComments() {
        visibleCommentsCount = 5;
        const $comments = $commentThread.find(".comment");
        $comments.each(function (index) {
            $(this).toggle(index < visibleCommentsCount);
        });
        $hideButton.hide(); // Hide the "Hide" button
        $loadMoreButton.show(); // Show the "Load More" button
    }
});
$(document).ready(function () {
    // Check if the URL contains the specific hash
    if (window.location.hash === "#navs-top-discussions") {
        // Select the tab trigger
        var discussionsTabTrigger = document.querySelector(
            '[data-bs-target="#navs-top-discussions"]'
        );
        if (discussionsTabTrigger) {
            // Activate the tab
            var tabInstance = new bootstrap.Tab(discussionsTabTrigger);
            tabInstance.show();

            // Scroll to the tab content after a slight delay
            setTimeout(function () {
                discussionsTabTrigger.scrollIntoView({
                    behavior: "smooth",
                    block: "start",
                });
            }, 100); // Small delay to ensure tab transition is complete
        } else {
        }
    }
});
$(document).on("click", ".edit-comment", function () {
    var commentId = $(this).data("comment-id");
    $.ajax({
        type: "GET",
        url: baseUrl + "/projects/comments/get/" + commentId,
        dataType: "JSON",
        success: function (response) {
            $("#comment_id").val(response.comment.id);
            $("#edit-project-comment-content").val(
                stripHtml(response.comment.content)
            );
            $("#EditCommentModal").modal("show");
        },
    });
});
$(document).on("click", ".delete-comment", function () {
    var commentId = $(this).data("comment-id");
    $.ajax({
        type: "GET",
        url: baseUrl + "/projects/comments/get/" + commentId,
        dataType: "JSON",
        success: function (response) {
            $("#delete_comment_id").val(response.comment.id);
            $("#DeleteCommentModal").modal("show");
        },
    });
});
$(document).ready(function () {
    // Initialize for different textareas
    initializeMentionTextarea($("#project-comment-content")); // For general mention textarea
    initializeMentionTextarea($("#edit-project-comment-content")); // For edit comment textarea
    initializeMentionTextarea($("#project-reply-content")); // For create comment textarea
});

$(function () {
    let attachmentId; // Store attachmentId temporarily for deletion

    // When the delete button is clicked
    $(document).on("click", ".delete-attachment", function () {
        attachmentId = $(this).data("attachment-id"); // Get the attachment ID

        // Show the Bootstrap delete confirmation modal
        $("#deleteModal").modal("show");
        // When the confirmation button is clicked in the modal
        $("#confirmDelete").on("click", function () {
            const originalButtonText = $(this).text();

            $("#confirmDelete").html(label_please_wait).attr("disabled", true);

            // Proceed with the deletion
            $.ajax({
                type: "DELETE",
                url:
                    baseUrl +
                    "/projects/comments/destroy-attachment/" +
                    attachmentId,
                dataType: "JSON",
                headers: {
                    "X-CSRF-TOKEN": $('input[name="_token"]').val(),
                },
                success: function (response) {
                    if (response.error === false) {
                        toastr.success(response.message);
                        setTimeout(function () {
                            window.location.reload();
                        }, parseFloat(toastTimeOut) * 1000);
                    } else {
                        toastr.error(label_something_went_wrong);
                    }
                },
                error: function (xhr, status, error) {
                    toastr.error(label_something_went_wrong);
                },
                complete: function () {
                    $("#confirmDelete")
                        .attr("disabled", false)
                        .html(originalButtonText);
                    $("#deleteModal").modal("hide");
                },
            });
        });
    });
});
$(document).ready(function () {
    $("#deleteCommentBtn").on("click", function () {
        var $btn = $(this);
        var $form = $("#delete-comment-form");

        // Disable the button and change the text
        $btn.prop("disabled", true);
        $btn.text(label_please_wait);

        // Submit the form using AJAX
        $.ajax({
            type: $form.attr("method"),
            url: $form.attr("action"),
            data: $form.serialize(),
            success: function (response) {
                if (response.error === false) {
                    toastr.success(response.message);
                    setTimeout(function () {
                        window.location.reload();
                    }, parseFloat(toastTimeOut) * 1000);
                } else {
                    toastr.error(label_something_went_wrong);
                }
            },
            error: function (xhr) {
                toastr.error(label_something_went_wrong);
            },
            complete: function () {
                // Re-enable the button and reset text after request is complete
                $btn.prop("disabled", false);
                $btn.text(label_yes);
                $("#DeleteCommentModal").modal("hide");
            },
        });
    });
});
