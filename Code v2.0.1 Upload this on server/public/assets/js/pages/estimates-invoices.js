'use strict';

function queryParams(p) {
    return {
        "types": $('#type_filter').val(),
        "status": $('#hidden_status').val(),
        "client_ids": $('#client_filter').val(),
        "created_by_user_ids": $('#user_creators_filter').val(),
        "created_by_client_ids": $('#client_creators_filter').val(),
        "date_between_from": $('#date_between_from').val(),
        "date_between_to": $('#date_between_to').val(),
        "start_date_from": $('#start_date_from').val(),
        "start_date_to": $('#start_date_to').val(),
        "end_date_from": $('#end_date_from').val(),
        "end_date_to": $('#end_date_to').val(),
        page: p.offset / p.limit + 1,
        limit: p.limit,
        sort: p.sort,
        order: p.order,
        offset: p.offset,
        search: p.search
    };
}

addDebouncedEventListener('#type_filter, #client_filter, #created_by_filter, #filter_starts_at, #filter_ends_at, #user_creators_filter, #client_creators_filter', 'change', function (e, refreshTable) {
    e.preventDefault();
    if (typeof refreshTable === 'undefined' || refreshTable) {
        $('#table').bootstrapTable('refresh');
    }
});

$(document).on('click', '.clear-estimates-invoices-filters', function (e) {
    e.preventDefault();
    $('#ie_date_between').val('');
    $('#date_between_from').val('');
    $('#date_between_to').val('');
    $('#start_date_from').val('');
    $('#start_date_to').val('');
    $('#end_date_from').val('');
    $('#end_date_to').val('');
    $('#start_date_between').val('');
    $('#end_date_between').val('');
    $('#client_filter').val('').trigger('change', [0]);
    $('#user_creators_filter').val('').trigger('change', [0]);
    $('#client_creators_filter').val('').trigger('change', [0]);
    $('#type_filter').val('').trigger('change', [0]);
    $('#table').bootstrapTable('refresh');
})


$('#start_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#start_date_from').val(startDate);
    $('#start_date_to').val(endDate);

    $('#table').bootstrapTable('refresh');
});

$('#start_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#start_date_from').val('');
    $('#start_date_to').val('');
    $('#start_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#table').bootstrapTable('refresh');
});

$('#end_date_between').on('apply.daterangepicker', function (ev, picker) {
    var startDate = picker.startDate.format('YYYY-MM-DD');
    var endDate = picker.endDate.format('YYYY-MM-DD');

    $('#end_date_from').val(startDate);
    $('#end_date_to').val(endDate);

    $('#table').bootstrapTable('refresh');
});
$('#end_date_between').on('cancel.daterangepicker', function (ev, picker) {
    $('#end_date_from').val('');
    $('#end_date_to').val('');
    $('#end_date_between').val('');
    picker.setStartDate(moment());
    picker.setEndDate(moment());
    picker.updateElement();
    $('#table').bootstrapTable('refresh');
});

$(document).ready(function () {
    $('#ie_date_between').on('apply.daterangepicker', function (ev, picker) {
        var startDate = picker.startDate.format('YYYY-MM-DD');
        var endDate = picker.endDate.format('YYYY-MM-DD');
        $('#date_between_from').val(startDate);
        $('#date_between_to').val(endDate);
        $('#table').bootstrapTable('refresh');
    });

    // Cancel event to clear values
    $('#ie_date_between').on('cancel.daterangepicker', function (ev, picker) {
        $('#date_between_from').val('');
        $('#date_between_to').val('');
        $(this).val('');
        picker.setStartDate(moment());
        picker.setEndDate(moment());
        picker.updateElement();
        $('#table').bootstrapTable('refresh');
    });
});


window.icons = {
    refresh: 'bx-refresh',
    toggleOn: 'bx-toggle-right',
    toggleOff: 'bx-toggle-left'
}

function loadingTemplate(message) {
    return '<i class="bx bx-loader-alt bx-spin bx-flip-vertical" ></i>'
}

function idFormatter(value, row, index) {
    var idPrefix = (row.type == 'Estimate') ? label_estimate_id_prefix : (row.type == 'Invoice') ? label_invoice_id_prefix : '';
    return [
        '<a href="' + baseUrl + '/estimates-invoices/view/' + row.id + '">' + idPrefix + row.id + '</a>'
    ];
}

$(document).on('click', '.status-badge', function (e) {
    var status = $(this).data('status');
    var type = $(this).data('type');
    $('#hidden_status').val(status);
    $('#type_filter').val(type).trigger('change', [0]);
    $('#table').bootstrapTable('refresh');
});

// Define status options for each type
const statusOptions = {
    'estimate': [
        { value: 'sent', text: label_sent },
        { value: 'accepted', text: label_accepted },
        { value: 'draft', text: label_draft },
        { value: 'declined', text: label_declined },
        { value: 'expired', text: label_expired }
    ],
    'invoice': [
        { value: 'fully_paid', text: label_fully_paid },
        { value: 'partially_paid', text: label_partially_paid },
        { value: 'draft', text: label_draft },
        { value: 'cancelled', text: label_cancelled },
        { value: 'due', text: label_due }
    ]
};

// Function to update status dropdown options
function updateStatusOptions(type) {
    const statusSelect = $('#status');

    // Clear all options except the empty option if type is empty
    if (!type) {
        statusSelect.empty().append('<option></option>'); // Keep the empty option
        return; // Exit the function
    }

    // Clear existing options but retain the empty option
    statusSelect.empty().append('<option></option>');

    // Add new options based on selected type
    const options = statusOptions[type] || [];
    options.forEach(function (option) {
        statusSelect.append($('<option></option>').attr('value', option.value).text(option.text));
    });
}

// Event listener for type selection change
$('#type').on('change', function (e) {
    const selectedType = $(this).val();
    updateStatusOptions(selectedType);
});


$('#client_id').on('change', function (e) {

    var client_id = $('#client_id').val();
    if (client_id != '') {
        $.ajax({
            url: baseUrl + '/clients/get/' + client_id,
            type: 'get',
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
            },
            success: function (result) {
                $('.billing_name').html(result.client.first_name + ' ' + result.client.last_name);
                $('#update_name').val(result.client.first_name + ' ' + result.client.last_name);
                $('#name').val(result.client.first_name + ' ' + result.client.last_name);

                if (result.address) {
                    $('.billing_address').html(result.client.address);
                    $("textarea#update_address").val(result.client.address);
                    $('#address').val(result.client.address);
                }

                if (result.client.phone) {
                    $('.billing_contact').html(result.client.phone);
                    $('#update_contact').val(result.client.phone);
                    $('#contact').val(result.client.phone);
                }

                if (result.client.city) {
                    $('.billing_city').html(result.client.city);
                    $('#update_city').val(result.client.city);
                    $('#city').val(result.client.city);
                }

                if (result.client.state) {
                    $('.billing_state').html(result.client.state);
                    $('#update_state').val(result.client.state);
                    $('#state').val(result.client.state);
                }

                if (result.client.country) {
                    $('.billing_country').html(result.client.country);
                    $('#update_country').val(result.client.country);
                    $('#country').val(result.client.country);
                }

                if (result.client.zip) {
                    $('.billing_zip').html(result.client.zip);
                    $('#update_zip_code').val(result.client.zip);
                    $('#zip_code').val(result.client.zip);
                }
            }
        });
    } else {
        $('.billing_name').html('--');
        $('.billing_address').html('--');
        $('.billing_city').html('--');
        $('.billing_state').html('--');
        $('.billing_country').html('--');
        $('.billing_zip').html('--');
        $('.billing_contact').html('--');

        $('#update_name').val('');
        $("textarea#update_address").val('');
        $('#update_city').val('');
        $('#update_state').val('');
        $('#update_zip_code').val('');
        $('#update_country').val('');
        $('#update_contact').val('');

        $('#name').val('');
        $("textarea#address").val('');
        $('#contact').val('');
    }

});

$(document).on('click', '.edit-billing-details', function () {
    $('#edit-billing-address').modal('show');
});

$(document).on('click', '#apply_billing_details', function (e) {
    e.preventDefault();

    var name = $('#update_name').val();
    var address = $("textarea#update_address").val();
    var city = $('#update_city').val();
    var state = $('#update_state').val();
    var country = $('#update_country').val();
    var zip_code = $('#update_zip_code').val();
    var contact = $('#update_contact').val();
    if (name) {
        $('#apply_billing_details').html(label_please_wait).attr('disabled', true);
        $('.billing_name').html(name);
        $('#name').val(name);

        if (address) {
            $('.billing_address').html(address);
            $("#address").val(address);
        } else {
            $('.billing_address').html('--');
            $('#address').val('');
        }

        if (city) {
            $('.billing_city').html(city);
            $('#city').val(city);
        } else {
            $('.billing_city').html('--');
            $('#city').val('');
        }

        if (state) {
            $('.billing_state').html(state);
            $('#state').val(state);
        } else {
            $('.billing_state').html('--');
            $('#state').val('');
        }

        if (country) {
            $('.billing_country').html(country);
            $('#country').val(country);
        } else {
            $('.billing_country').html('--');
            $('#country').val('');
        }

        if (zip_code) {
            $('.billing_zip').html(zip_code);
            $('#zip_code').val(zip_code);
        } else {
            $('.billing_zip').html('--');
            $('#zip_code').val('');
        }

        if (contact) {
            $('.billing_contact').html(contact);
            $('#contact').val(contact);
        } else {
            $('.billing_contact').html('--');
            $('#contact').val('');
        }

        $('#apply_billing_details').html(label_apply).attr('disabled', false);
        $('#edit-billing-address').modal('hide');
        toastr.success(label_billing_details_updated_successfully);
    } else {
        toastr.error(label_please_enter_name);
    }
});


$('#item_id').on('change', function (e) {

    var item_id = $('#item_id').val();
    if (item_id !== null && item_id !== '') {
        $.ajax({
            type: 'get',
            url: baseUrl + '/items/get/' + item_id,
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').attr('value') // Replace with your method of getting the CSRF token
            },
            success: function (result) {
                $('#item_0_title').val(result.item.title);
                $("textarea#item_0_description").val(result.item.description);
                $('#item_0_rate').val(result.item.price);
                $('#item_0_quantity').val(1).trigger('change');
                $('#item_0_unit').val(result.item.unit_id);
            }
        });
    } else {
        $('#item_0_title').val('');
        $("textarea#item_0_description").val('');
        $('#item_0_quantity').val('');
        $('#item_0_unit').val('');
        $('#item_0_rate').val('');
        $('#item_0_tax').val('');
        $('#item_0_amount').val('');
    }
});
$('#item_id').on('select2:clear', function () {
    $('#item_0_title').val('');
    $("textarea#item_0_description").val('');
    $('#item_0_quantity').val('');
    $('#item_0_unit').val('');
    $('#item_0_rate').val('');
    $('#item_0_tax').val('');
    $('#item_0_amount').val('');
});


$('#add-item').on('click', function (e) {
    e.preventDefault();
    var html = '';

    var title = $("#item_0_title").val();
    var quantity = $("#item_0_quantity").val();
    var rate = $("#item_0_rate").val();
    var amount = $("#item_0_amount").val();
    var description = $("#item_0_description").val();
    var unit = $("#item_0_unit").val();
    var tax = $("#item_0_tax").val();
    var tax_title = $(".item_0_tax_title").text();
    var tax_percentage = $("#item_0_tax option:selected").text();
    if (title != '' && quantity != '' && rate != '' && amount != '') {
        var item_id = $("#item_id").val();
        var item_ids = $("#item_ids").val();

        item_ids = item_ids.split(',');

        var exists = item_ids.includes(item_id);

        if (!exists) {
            $('#item_id').val('').trigger('change');
            items_count++
            item_ids = item_ids.toString();
            if (item_ids != '') {
                item_ids = item_ids + ',' + item_id;
            } else {
                item_ids = item_id;
            }
            $("#item_ids").val(item_ids)
            if (amount == '') {
                amount = rate * quantity;
            }
            amount = +amount + +0;
            amount = amount.toFixed(decimal_points);
            html = '<div class="estimate-invoice-item"><div class="d-flex">' +
                '<input type="hidden" id=item_' + items_count + ' name="item[]">' +
                '<div class="mb-3 col-md-2 mx-1">' +
                '<input type="text" id="item_' + items_count + '_title" name="title[]" class="form-control" readonly>' +
                '</div>' +
                '<div class="mb-3 col-md-2 mx-1">' +
                '<textarea class="form-control" id="item_' + items_count + '_description" name="description[]" readonly></textarea>' +
                '</div>' +
                '<div class="mb-3 col-md-1 mx-1">' +
                '<input type="number" name="quantity[]" step="0.25" id="item_' + items_count + '_quantity" onchange="update_amount(' + items_count + ')" class="form-control" min="0.25">' +
                '</div>' +
                '<div class="mb-3 col-md-1 mx-1">' +
                '<select class="form-select" name="unit[]" id="item_' + items_count + '_unit">' +
                units +
                '</select>' +
                '</div>' +
                '<div class="mb-3 col-md-2 mx-1">' +
                '<input type="text" name="rate[]" id="item_' + items_count + '_rate" onchange="update_amount(' + items_count + ')" class="form-control decimal-currency">' +
                '</div>' +
                '<div class="mb-3 col-md-1 mx-1">' +
                '<select class="form-select" name="tax[]" id="item_' + items_count + '_tax" onchange="update_amount(' + items_count + ');">' +
                taxes +
                '</select>' +
                '<div class="item_' + items_count + '_tax_title"></div>' +
                '<input class="item_' + items_count + '_tax_title" type="hidden" name="tax_amount[]"></input>' +
                '</div>' +
                '<div class="mb-3 col-md-2 mx-1">' +
                '<input type="text" id="item_' + items_count + '_amount" class="form-control decimal-currency" name="amount[]" onchange="updateTotals()">' +
                '</div>' +
                '<div class="mx-1">' +
                '<button type="button" class="btn btn-sm btn-danger my-1 remove-estimate-invoice-item" data-count=' + items_count + '><i class="bx bx-trash"></i></button>' +
                '</div>' +
                '</div></div>';

            $('#estimate-invoice-items').append(html);
            $('#item_' + items_count).val(item_id);
            $('#item_' + items_count + '_title').val(title);
            $('#item_' + items_count + '_description').val(description);
            $('#item_' + items_count + '_quantity').val(quantity);
            $('#item_' + items_count + '_unit').val(unit);
            $('#item_' + items_count + '_rate').val(rate);
            $('#item_' + items_count + '_tax').val(tax);
            $('#item_' + items_count + '_amount').val(amount);
            $('.item_' + items_count + '_tax_title').text(tax_title);
            updateTotals();

            $("#item_0_title").val('');
            $("#item_0_description").val('');
            $("#item_0_quantity").val('');
            $("#item_0_unit").val('');
            $("#item_0_rate").val('');
            $("#item_0_tax").val('');
            $("#item_0_amount").val('');
            $(".item_0_tax_title").text('');

        } else {
            toastr.error('Item already added.');
        }

    } else {
        toastr.error('Please fill all required fields.');
    }

});

function updateTotals() {
    var subTotalWithTax = 0;
    var totalTaxAmount = 0;
    var finalTotal = 0;

    // Loop through items and calculate subTotalWithTax
    $("input[name='amount[]']").each(function () {
        var amount = parseFloat($(this).val()) || 0;
        subTotalWithTax += amount;
    });

    // Loop through items and calculate totalTaxAmount
    $("input[name='tax_amount[]']").each(function () {
        var taxAmount;
        if ($(this).attr('value') !== undefined) {
            taxAmount = parseFloat($(this).attr('value')) || 0;
        } else {
            taxAmount = parseFloat($(this).text()) || 0;
        }
        totalTaxAmount += taxAmount;
    });

    // Calculate subTotal by deducting totalTaxAmount from subTotalWithTax
    var subTotal = subTotalWithTax - totalTaxAmount;

    // Update sub_total
    $("#sub_total").val(subTotal.toFixed(decimal_points));

    // Update total_tax
    $("#total_tax").val(totalTaxAmount.toFixed(decimal_points));

    // Calculate finalTotal by adding subTotal and totalTaxAmount
    finalTotal = subTotalWithTax;
    $("#final_total").val(finalTotal.toFixed(decimal_points));
}


function update_amount(itemIndex, isUpdateTotals = 1) {

    // Get values from input fields
    var quantity = parseFloat($("#item_" + itemIndex + "_quantity").val()) || 0;
    var rate = parseFloat(removeCommas($("#item_" + itemIndex + "_rate").val())) || 0;
    var disp_tax = '';
    var tax_id = $('#item_' + itemIndex + '_tax').val();
    if (tax_id != '') {
        $.ajax({
            url: baseUrl + '/taxes/get/' + tax_id,
            headers: {
                'X-CSRF-TOKEN': $('input[name="_token"]').val() // Replace with your method of getting the CSRF token
            },
            success: function (result) {
                // Calculate tax amount based on the retrieved tax rate
                var taxRate = parseFloat(result.tax.amount) || 0;
                var taxType = result.tax.type; // Assuming tax type is provided in the response
                var taxAmount = 0;
                if (taxType == 'percentage') {
                    taxAmount = ((quantity * rate) * result.tax.percentage) / 100;
                    disp_tax = taxAmount.toFixed(decimal_points) + '(' + result.tax.percentage + '%)';
                } else if (taxType == 'amount') {
                    taxAmount = taxRate;
                    disp_tax = taxAmount.toFixed(decimal_points);
                }
                // Update tax amount field
                // $("#item_" + itemIndex + "_tax_amount").val(taxAmount.toFixed(decimal_points));
                // Update tax title display
                $('.item_' + itemIndex + '_tax_title').text(disp_tax);
                $('.item_' + itemIndex + '_tax_amount').val(taxAmount);
                // Update item amount
                var amount = quantity * rate + taxAmount;
                $("#item_" + itemIndex + "_amount").val(amount.toFixed(decimal_points));
                if (isUpdateTotals) {
                    // Update sub_total, total_tax, and final_total
                    updateTotals();
                }
            }
        });
    } else {
        // Clear tax details if no tax selected
        $('.item_' + itemIndex + '_tax_title').text('');
        $('.item_' + itemIndex + '_tax_amount').val('');
        // $("#item_" + itemIndex + "_tax_amount").val('0');
        // Calculate amount
        var amount = quantity * rate;
        // Update item amount
        $("#item_" + itemIndex + "_amount").val(amount.toFixed(decimal_points));
        if (isUpdateTotals) {
            // Update sub_total, total_tax, and final_total
            updateTotals();
        }
    }
}




function updateFinalTotal() {
    var finalTotal = 0;

    var taxAmountField = $("#total_tax");
    var Taxamount = parseFloat(taxAmountField.val()) || 0;

    var subTotalField = $("#sub_total");
    var subTotal = parseFloat(subTotalField.val()) || 0;

    finalTotal = subTotal += Taxamount;
    $("#final_total").val(finalTotal.toFixed(decimal_points));
}

// Helper function to remove commas from a string
function removeCommas(str) {
    return str.replace(/,/g, '');
}

$(document).on('click', '.remove-estimate-invoice-item', function (e) {
    e.preventDefault();
    var count = $(this).data('count');
    var item_id = $("#item_" + count).val();
    var item_ids = $("#item_ids").val().split(','); // Split the string into an array
    var index = $.inArray(item_id.toString(), item_ids);

    if (index !== -1) {
        // Remove the item_id from the array
        item_ids.splice(index, 1);
        // Update the #item_ids input value with the modified string
        $("#item_ids").val(item_ids.join(',')); // Join the array back into a string
    }

    $(this).closest('.estimate-invoice-item').remove();
    updateTotals();
    items_count--;

    // Reassign indices to the remaining items
    $('.estimate-invoice-item').each(function (index) {
        var displayIndex = index + 1; // Increment the index by 1 for display

        // Update IDs and names
        $(this).find('input[name="item[]"]').attr('id', 'item_' + displayIndex);
        $(this).find('input[name="title[]"]').attr('id', 'item_' + displayIndex + '_title');
        $(this).find('textarea[name="description[]"]').attr('id', 'item_' + displayIndex + '_description');
        $(this).find('input[name="quantity[]"]').attr('id', 'item_' + displayIndex + '_quantity').attr('onchange', 'update_amount(' + displayIndex + ')');
        $(this).find('input[name="rate[]"]').attr('id', 'item_' + displayIndex + '_rate').attr('onchange', 'update_amount(' + displayIndex + ')');
        $(this).find('input[name="amount[]"]').attr('id', 'item_' + displayIndex + '_amount');
        $(this).find('select[name="unit[]"]').attr('id', 'item_' + displayIndex + '_unit');
        $(this).find('select[name="tax[]"]').attr('id', 'item_' + displayIndex + '_tax').attr('onchange', 'update_amount(' + displayIndex + ')');
        $(this).find('input[name="tax_amount[]"]').attr('name', 'tax_amount[]');

        // Update tax title classes
        $(this).find('.item_' + (displayIndex + 1) + '_tax_title').each(function () {
            $(this).removeClass('item_' + (displayIndex + 1) + '_tax_title').addClass('item_' + displayIndex + '_tax_title');
        });

        // Update the data-count attribute for remove buttons
        $(this).find('.remove-estimate-invoice-item').data('count', displayIndex);
    });
});
