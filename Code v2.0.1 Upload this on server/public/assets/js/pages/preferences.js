'use strict';
// $(document).ready(function () {
//     var $sortable = $('#sortable-menu');

//     // Initialize main menu sortable
//     Sortable.create($sortable[0], {
//         animation: 150,
//         handle: '.handle'
//     });

//     // Initialize submenu sortable
//     $('.submenu').each(function () {
//         var $submenu = $(this);
//         Sortable.create($submenu[0], {
//             animation: 150,
//             handle: '.handle'
//         });
//     });

//     // Handle form submission
//     $('#menu-order-form').on('submit', function (e) {
//         e.preventDefault(); // Prevent default form submission
//         var $submitButton = $('#btnSaveMenuOrder');
//         $submitButton.attr('disabled', true).html(label_please_wait);
//         var menuOrder = [];
//         $('#sortable-menu li').each(function () {
//             var menuId = $(this).data('id');
//             var submenus = [];

//             // Check if there are submenus
//             $(this).find('.submenu li').each(function () {
//                 submenus.push({ id: $(this).data('id') });
//             });

//             menuOrder.push({ id: menuId, submenus: submenus });
//         });

//         // Send the sorted IDs to your backend via AJAX
//         $.ajax({
//             url: baseUrl + '/save-menu-order',
//             method: 'POST',
//             data: {
//                 menu_order: menuOrder
//             },
//             headers: {
//                 'X-CSRF-TOKEN': $('input[name="_token"]').attr('value')
//             },
//             success: function (response) {
//                 if (response.error == false) {
//                     toastr.success(response['message']);
//                     setTimeout(function () {
//                         location.reload();
//                     }, parseFloat(toastTimeOut) * 1000);
//                 } else {
//                     toastr.error(response.message);
//                 }
//             },
//             error: function (xhr, status, error) {
//                 toastr.error(label_something_went_wrong);
//             },
//             complete: function () {
//                 $submitButton.attr('disabled', false).html(label_update);
//             }
//         });
//     });
// });


$(document).ready(function () {
    // Initialize category-level sorting - we don't sort categories themselves
    var $categoryList = $('#sortable-menu');
    Sortable.create($categoryList[0], {
        animation: 150,
        handle: '.handle',
        // Disable dragging for category headers
        // filter: '.category-header',
        onMove: function (evt) {
            return evt.related.className.indexOf('category-header') === -1;
        }
    });

    // Initialize menu-level sorting (only within their categories)
    $('.menu-list').each(function () {
        var $menuList = $(this);
        Sortable.create($menuList[0], {
            animation: 150,
            handle: '.handle',
            group: 'menu', // Makes all menus belong to same group
            // Only allow sorting within the same category parent
            onMove: function (evt) {
                var fromCategory = evt.from.closest('.category-item');
                var toCategory = evt.to.closest('.category-item');
                return fromCategory === toCategory;
            }
        });
    });

    // Initialize submenu-level sorting (only within their parent menu)
    $('.submenu-list').each(function () {
        var $submenuList = $(this);
        Sortable.create($submenuList[0], {
            animation: 150,
            handle: '.handle',
            group: 'submenu', // Makes all submenus belong to same group
            // Only allow sorting within the same menu parent
            onMove: function (evt) {
                var fromMenu = evt.from.closest('.menu-item');
                var toMenu = evt.to.closest('.menu-item');
                return fromMenu === toMenu;
            }
        });
    });

    // Handle form submission
    $('#menu-order-form').on('submit', function (e) {
        e.preventDefault(); // Prevent default form submission
        var $submitButton = $('#btnSaveMenuOrder');
        $submitButton.attr('disabled', true).html(label_please_wait);

        var menuOrder = [];

        // Loop through each category
        $('.category-item').each(function () {
            var category = $(this).data('category');
            var menus = [];

            // Loop through each menu item within this category
            $(this).find('.menu-list > .menu-item').each(function () {
                var menuId = $(this).data('id');
                var submenus = [];

                // Loop through each submenu in this menu
                $(this).find('.submenu-list .submenu-item').each(function () {
                    submenus.push({
                        id: $(this).data('id')
                    });
                });

                // Add menu with submenus (if present)
                var menuData = {
                    id: menuId
                };

                if (submenus.length > 0) {
                    menuData.submenus = submenus;
                }

                menus.push(menuData);
            });

            // Push the category with its menus
            menuOrder.push({
                category: category,
                menus: menus
            });
        });

        console.log(menuOrder);
        // return;

        // Send the sorted structure to the backend via AJAX
        $.ajax({
            url: baseUrl + '/save-menu-order',
            method: 'POST',
            data: {
                menu_order: menuOrder
            },
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').attr('value')
            },
            success: function (response) {
                if (!response.error) {
                    toastr.success(response.message);
                    setTimeout(function () {
                        location.reload();
                    }, parseFloat(toastTimeOut) * 1000);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function () {
                toastr.error(label_something_went_wrong);
            },
            complete: function () {
                $submitButton.attr('disabled', false).html(label_update);
            }
        });
    });

});
$(document).on('click', '#btnResetDefaultMenuOrder', function (e) {
    e.preventDefault();
    $('#confirmResetDefaultMenuOrderModal').modal('show'); // show the confirmation modal
    $('#confirmResetDefaultMenuOrderModal').off('click', '#btnconfirmResetDefaultMenuOrder');
    $('#confirmResetDefaultMenuOrderModal').on('click', '#btnconfirmResetDefaultMenuOrder', function (e) {
        $('#btnconfirmResetDefaultMenuOrder').html(label_please_wait).attr('disabled', true);
        $.ajax({
            url: baseUrl + '/reset-default-menu-order',
            type: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').attr('value')
            },
            success: function (response) {
                if (response.error == false) {
                    toastr.success(response['message']);
                    setTimeout(function () {
                        location.reload();
                    }, parseFloat(toastTimeOut) * 1000);
                } else {
                    toastr.error(response.message);
                }
            },
            error: function (data) {
                toastr.error(label_something_went_wrong);
            },
            complete: function () {
                $('#confirmResetDefaultMenuOrderModal').modal('hide');
                $('#btnconfirmResetDefaultMenuOrder').attr('disabled', false).html(label_yes);
            }
        });
    });
});
