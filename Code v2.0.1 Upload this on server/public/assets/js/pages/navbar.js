"use strict";

// Search Modal Js
$(document).ready(function () {
    // Open modal with keyboard shortcut
    $(document).on("keydown", function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key === "k") {
            e.preventDefault();
            $("#globalSearchModal").modal("show");
            setTimeout(() => {
                $("#modalSearchInput").focus();
            }, 200);
        }
    });

    // Close modal with escape key
    $("#globalSearchModal").on("shown.bs.modal", function () {
        $(this).on("keydown", function (e) {
            if (e.key === "Escape") {
                $("#globalSearchModal").modal("hide");
            }
        });
    });

    // Handle search input
    let searchTimeout;
    $("#modalSearchInput").on("input", function () {
        const query = $(this).val();

        // Clear previous timeout
        clearTimeout(searchTimeout);

        // Hide initial categories if searching
        if (query.length > 0) {
            $(".searchResults").removeClass("d-none");
        } else {
            $(".searchResults").addClass("d-none");
            return;
        }

        // Set new timeout for search
        searchTimeout = setTimeout(() => {
            if (query.length >= 1) {
                $.ajax({
                    url: baseUrl + "/search",
                    data: { q: query },
                    method: "GET",
                    success: function (response) {
                        renderSearchResults(response.results);
                    },
                });
            }
        }, 300);
    });

    // Reset Modal On Close
    $("#globalSearchModal").on("hidden.bs.modal", function () {
        $("#modalSearchInput").val("");
        $(".searchResults").addClass("d-none");
    });

    // Render search results
    function renderSearchResults(results) {
        const resultsList = $("#searchResultsList");
        resultsList.empty();

        for (const module in results) {
            if (results[module].length > 0) {
                // Add module header
                resultsList.append(`
                    <div class="search-category-header mt-3 mb-2">
                        <small class="text-muted">${module.toUpperCase()}</small>
                    </div>
                `);

                // Add results for this module
                results[module].forEach((item) => {
                    const redirectUrl = getRedirectUrl(module, item.id);
                    resultsList.append(`
                        <a href="${redirectUrl}" class="list-group-item list-group-item-action">
                            <i class="bx ${getModuleIcon(module)} me-2"></i>
                            ${item.title}
                        </a>
                    `);
                });
            }
        }

        if (resultsList.children().length === 0) {
            resultsList.append(`
                <div class="text-center text-muted py-3">
                    <i class="bi bi-search mb-2 d-block"></i>
                    No results found
                </div>
            `);
        }
    }

    // Helper function to get redirect URL based on module
    function getRedirectUrl(module, id) {
        const routes = {
            projects: `/projects/information/${id}`,
            tasks: `/tasks/information/${id}`,
            meetings: "/meetings",
            workspaces: "/workspaces",
            users: `/users/profile/${id}`,
            clients: `/clients/profile/${id}`,
            todos: "/todos",
            notes: "/notes",
        };
        return baseUrl + (routes[module] || "/");
    }

    // Helper function to get icon based on module
    function getModuleIcon(module) {
        const icons = {
            projects: "bx-briefcase-alt-2",
            tasks: "bx-task",
            meetings: "bx-shape-polygon",
            workspaces: "bx-check-square",
            users: "bx-group",
            clients: "bx-group",
            todos: "bx-list-check",
            notes: "bx-notepad",
        };
        return icons[module] || "bi-circle";
    }
});

$("#global-search").on("click", function () {
    $("#globalSearchModal").modal("show");
});
