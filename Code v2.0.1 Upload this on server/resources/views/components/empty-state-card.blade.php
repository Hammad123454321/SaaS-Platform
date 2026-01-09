@php
$flag = (
(Request::segment(1) == 'home' || Request::segment(1) == 'users' || Request::segment(1) == 'clients')
&&
(strtolower($type) == 'projects' || strtolower($type) == 'tasks')
) ? 0 : 1;
$currentPath = request()->path();
$showCreateButton = !in_array($currentPath, ['projects/list/favorite', 'projects/favorite']);
@endphp
<div class="<?= $flag == 1 ? 'card ' : '' ?>text-center empty-state">
    @if($flag == 1)
    <div class="card-body">
        @endif
        <div class="misc-wrapper">
            <h2 class="mb-2 mx-2"><?= get_label(strtolower($type), $type) . ' ' . get_label('not_found', 'Not Found') ?></h2>
            <p class="mb-4 mx-2"><?= get_label('oops!', 'Oops!') ?> 😖 <?= get_label('data_does_not_exists', 'Data does not exists') ?>.</p>
            @if ($type!='Notifications' && $showCreateButton)
            @php
            $typeSlug = strtolower(str_replace(' ', '-', $type));
            $modalMap = [
            'todos' => '#create_todo_modal',
            'tags' => '#create_tag_modal',
            'status' => '#create_status_modal',
            'leave-requests' => '#create_leave_request_modal',
            'contract-types' => '#create_contract_type_modal',
            'contracts' => '#create_contract_modal',
            'payment-methods' => '#create_pm_modal',
            'allowances' => '#create_allowance_modal',
            'deductions' => '#create_deduction_modal',
            'notes' => '#create_note_modal',
            'timesheet' => '#timerModal',
            'taxes' => '#create_tax_modal',
            'units' => '#create_unit_modal',
            'items' => '#create_item_modal',
            'expense-types' => '#create_expense_type_modal',
            'expenses' => '#create_expense_modal',
            'payments' => '#create_payment_modal',
            'languages' => '#create_language_modal',
            'tasks' => '#create_task_modal',
            'priorities' => '#create_priority_modal',
            'projects' => '#create_project_modal',
            'workspaces' => '#createWorkspaceModal',
            'meetings' => '#createMeetingModal',
            'task-lists'=>'#create_task_list_modal',
            'lead-sources' => '#create_lead_source_modal',
            'lead-stages' =>'#create_lead_stage_modal',
            'candidates' =>'#candidateModal',
            'interview' => '#createInterviewModal',
            'email-templates' =>'#createTemplateModal',
            ];
            $hasModal = in_array($typeSlug, ['contracts', 'todos', 'tags', 'status', 'leave-requests', 'contract-types', 'payment-methods', 'allowances', 'deductions', 'notes', 'timesheet', 'taxes', 'units', 'items', 'expense-types', 'expenses', 'payments', 'languages', 'tasks', 'priorities', 'projects', 'workspaces', 'meetings','task-lists' ,'lead-sources','lead-stages','candidates','interview','email-templates']);
            $href = $hasModal ? 'javascript:void(0)' : ($link ?? url($typeSlug . '/create'));
            $modalAttribute = $modalMap[$typeSlug] ?? '';
            @endphp

            <a href="{{ $href }}" {!! $modalAttribute ? 'data-bs-toggle="modal" data-bs-target="' . $modalAttribute . '"' : '' !!} class="btn btn-primary m-1">
                {{ get_label('create_now', 'Create now') }}
            </a>

            @endif
            <div class="mt-3">
                <img src="{{asset('/storage/no-result.png')}}" alt="page-misc-error-light" width="500" class="img-fluid" data-app-dark-img="illustrations/page-misc-error-dark.png" data-app-light-img="illustrations/page-misc-error-light.png" />
            </div>
        </div>
        @if($flag == 1)
    </div>
    @endif
</div>
