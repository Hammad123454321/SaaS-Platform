<script>
    var label_please_wait = "{{ get_label('please_wait', 'Please wait...') }}";
    var label_please_select_records_to_delete = "{{ get_label('please_select_records_to_delete', 'Please select records to delete.') }}";
    var label_something_went_wrong = "{{ get_label('something_went_wrong', 'Something went wrong.') }}";
    var label_please_correct_errors = "{{ get_label('please_correct_errors', 'Please correct errors.') }}";
    var label_removed_from_favorite_successfully = "{{ get_label('removed_from_favorite_successfully', 'Removed from favorites successfully.') }}";
    var label_marked_as_favorite_successfully = "{{ get_label('marked_as_favorite_successfully', 'Marked as favorite successfully.') }}";
    var label_yes = "{{ get_label('yes', 'Yes') }}";
    var label_upload = "{{ get_label('upload', 'Upload') }}";
    var decimal_points = {{ intval($general_settings['decimal_points_in_currency'] ?? '2') }};
    var label_update = "{{ get_label('update', 'Update') }}";
    var label_delete = "{{ get_label('delete', 'Delete') }}";
    var label_view = "{{ get_label('view', 'View') }}";
    var label_not_assigned = "{{ get_label('not_assigned', 'Not assigned') }}";
    var label_delete_selected = "{{ get_label('delete_selected', 'Delete selected') }}";
    var label_search = "{{ get_label('search', 'Search') }}";
    var label_create = "{{ get_label('create', 'Create') }}";
    var label_users_associated_with_project = "{{ get_label('users_associated_with_project', 'Users associated with project') }}";
    var label_update_task = "{{ get_label('update_task', 'Update Task') }}";
    var label_quick_view = "{{ get_label('quick_view', 'Quick View') }}";
    var label_project = "{{ get_label('project', 'Project') }}";
    var label_task = "{{ get_label('task', 'Task') }}";
    var label_projects = "{{ get_label('projects', 'Projects') }}";
    var label_tasks = "{{ get_label('tasks', 'Tasks') }}";
    var label_clear_filters = "{{ get_label('clear_filters', 'Clear Filters') }}";
    var label_set_as_default_view = "{{ get_label('set_as_default_view', 'Set as Default View') }}";
    var label_default_view = "{{ get_label('default_view', 'Default View') }}";
    var label_save_column_visibility = "{{ get_label('save_column_visibility', 'Save Column Visibility') }}";
    var label_showing = "{{ get_label('showing', 'Showing') }}";
    var label_to_for_pagination = "{{ get_label('to_for_pagination', 'to') }}";
    var label_of = "{{ get_label('of', 'of') }}";
    var label_rows = "{{ get_label('rows', 'rows') }}";
    var label_rows_per_page = "{{ get_label('rows_per_page', 'rows per page') }}";
    var label_select = "{{ get_label('select', 'Select') }}";
    var label_or = "{{ get_label('or', 'or') }}";
    var label_drag_and_drop_files_here = "{{ get_label('drag_and_drop_files_here', 'Drag & Drop Files Here') }}";
    var label_drag_and_drop_update_zip_file_here = "{{ get_label('drag_and_drop_update_zip_file_here', 'Drag & Drop Update from vX.X.X to vX.X.X.zip file Here') }}";
    var label_only_one_file_can_be_uploaded_at_a_time = "{{ get_label('only_one_file_can_be_uploaded_at_a_time', 'Only 1 file can be uploaded at a time') }}";
    var label_please_enter_name = "{{ get_label('please_enter_name', 'Please enter name') }}";
    var label_update_the_system = "{{ get_label('update_the_system', 'Update the system') }}";
    var label_sending = "{{ get_label('sending', 'Sending...') }}";
    var label_submit = "{{ get_label('submit', 'Submit') }}";
    var label_allowed_max_upload_size = "{{ get_label('allowed_max_upload_size', 'Allowed max upload size') }}";
    var label_currency_restriction = "{{ get_label('currency_restriction', 'Only digits, commas as thousand separators, and a single decimal point are allowed.') }}";
    var label_currency_restriction_1 = "{{ get_label('currency_restriction_1', 'Only one decimal point is allowed.') }}";
    var label_currency_restriction_2 = "{{ get_label('currency_restriction_2', 'Only digits and a single decimal point are allowed.') }}";
    var label_invoice_id_prefix = "{{ get_label('invoice_id_prefix', 'INVC-') }}";
    var label_please_type_at_least_1_character = "{{ get_label('please_type_at_least_1_character', 'Please type at least 1 character') }}";
    var label_searching = "{{ get_label('searching', 'Searching...') }}";
    var label_no_results_found = "{{ get_label('no_results_found', 'No results found') }}";
    var label_max_files_allowed = "{{ get_label('max_files_allowed', 'Max Files Allowed') }}";
    var label_allowed_file_types = "{{ get_label('allowed_file_types', 'Allowed File Types') }}";
    var label_no_files_chosen = "{{ get_label('no_files_chosen', 'No file(s) chosen.') }}";
    var label_max_files_count_allowed = "{{ get_label('max_files_count_allowed', 'You can only upload :count file(s).') }}";
    var label_file_type_not_allowed = "{{ get_label('file_type_not_allowed', 'File type not allowed') }}";
    var label_mm_export_success = "{{ get_label('mm_export_success', 'Mind map exported successfully!') }}";
    var label_mm_export_failed = "{{ get_label('mm_export_failed', 'Failed to export mind map. Please try again.') }}";
    var label_no_projects_available = "{{ get_label('no_projects_available', 'No Projects Available') }}";
    var label_change_date_not_allowed = "{{ get_label('change_date_not_allowed', 'Change date is not allowed for this.') }}";
    var label_no_data_available = "{{ get_label('no_data_available', 'No Data Available') }}";
    var label_to = "{{ get_label('to', 'To') }}";
    var label_income = "{{ get_label('income', 'Income') }}";
    var label_expense = "{{ get_label('expense', 'Expense') }}";
    var label_amount = "{{ get_label('amount', 'Amount') }}";
    var label_total = "{{ get_label('total', 'Total') }}";
    var label_replies = "{{ get_label('replies', 'Replies') }}";
    var label_jump_to_comment = "{{ get_label('jump_to_comment', 'Jump to Comment') }}";
    var label_edit = "{{ get_label('edit', 'Edit') }}";
    var label_reply = "{{ get_label('reply', 'Reply') }}";
    var label_download = "{{ get_label('download', 'Download') }}";
    var label_preview_not_available = "{{ get_label('preview_not_available', 'Preview not available') }}";
    var label_err_try_again = "{{ get_label('err_try_again', 'An error occurred. Please try again.') }}";
    var label_pinned_successfully = "{{ get_label('pinned_successfully', 'Pinned Successfully.') }}";
    var label_unpinned_successfully = "{{ get_label('unpinned_successfully', 'Unpinned Successfully.') }}";
    var label_click_pin = "{{ get_label('click_pin', 'Click to Pin') }}";
    var label_click_unpin = "{{ get_label('click_unpin', 'Click to Unpin') }}";
    var add_favorite = "{{ get_label('add_favorite', 'Click to mark as favorite') }}";
    var remove_favorite = "{{ get_label('remove_favorite', 'Click to remove from favorite') }}";
    var label_drag_and_drop_file_here = "{{ get_label('drag_and_drop_file_here', 'Drag & Drop File Here') }}";
    var label_no_file_chosen = "{{ get_label('no_file_chosen', 'No file chosen.') }}";
    var label_import_leads = "{{ get_label('import_leads', 'Import Leads') }}";
    var label_enter_project_title_first = "{{ get_label('enter_project_title_first', 'Please enter the project title first.') }}";
    var label_enter_custom_prompt_first = "{{ get_label('enter_custom_prompt_first', 'Please enter a custom prompt first.') }}";
</script>
<script>
    function addDebouncedEventListener(selector, event, handler, delay = 300) {
        const debounce = (func, delay) => {
            let timer;
            return function(...args) {
                clearTimeout(timer);
                timer = setTimeout(() => func.apply(this, args), delay);
            };
        };

        $(selector).on(event, debounce(handler, delay));
    }
</script>
