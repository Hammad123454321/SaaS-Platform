{{-- @if (is_countable($candidate_statuses) && count($candidate_statuses) > 0) --}}
<div class="card">
    <div class="card-body">
        <div class="alert alert-primary d-flex align-items-center">
            <i class="bx bx-move fs-4 me-2"></i>
            <span
                class="fw-semibold">{{ get_label('candidate_status_reorder_info','Drag and drop the rows below to change the order of your candidate status.'
                ) }}</span>
        </div>
        {{ $slot }}
        <div class="table-responsive text-nowrap">
            <input type="hidden" id="data_type" value="candidate_status">
            {{-- <input type="hidden" id="data_reload" value="0"> --}}
            <table id="table" data-toggle="table" data-url="{{ route('candidate.status.list') }}"
                data-icons-prefix="bx" data-icons="icons" data-show-refresh="true"a data-total-field="total"
                data-data-field="rows" data-page-list="[5, 10, 20, 50, 100]" data-search="true"
                data-side-pagination="server" data-pagination="true" data-sort-name="id" data-sort-order="desc"
                data-mobile-responsive="true" data-query-params="queryParams">
                <thead>
                    <tr>
                        <th data-checkbox="true"></th>
                        <th data-field="id" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                        <th data-field="name" data-sortable="true">{{ get_label('name', 'Name') }}</th>
                        <th data-field="order" data-sortable="true">{{ get_label('order', 'Order') }}</th>
                        <th data-field="color" data-sortable="true">{{ get_label('color', 'Color') }}</th>
                        <th data-field="created_at" data-sortable="true">{{ get_label('created_at', 'Created At') }}
                        </th>
                        <th data-field="updated_at" data-sortable="true">{{ get_label('updated_at', 'Updated At') }}
                        </th>
                        <th data-field="actions">{{ get_label('actions', 'Actions') }}</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
{{-- @else
<?php $type = 'Candidate Statuses'; ?>
<x-empty-state-card :type="$type" />
@endif --}}
