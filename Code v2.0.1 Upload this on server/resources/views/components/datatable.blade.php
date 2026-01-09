@props([
    'title' => '',
    'tableId' => 'datatable',
    'ajaxUrl' => null,
    'columns' => [],
    'showCheckbox' => false,
    'showExport' => true,
    'showAddNew' => false,
    'addNewText' => 'Add New',
    'addNewUrl' => '#',
    'customButtons' => [],
    'pageLength' => 10,
    'extraOptions' => [],
    'filters' => [],
    'showColumnVisibility' => true,
    'showRefresh' => true,
    'showSearch' => true,
    'wrapCard' => true,
])

@php
    $tableConfig = [
        'tableId' => $tableId,
        'ajaxUrl' => $ajaxUrl,
        'columns' => $columns,
        'showCheckbox' => $showCheckbox,
        'showExport' => $showExport,
        'showAddNew' => $showAddNew,
        'addNewText' => $addNewText,
        'addNewUrl' => $addNewUrl,
        'customButtons' => $customButtons,
        'pageLength' => $pageLength,
        'extraOptions' => $extraOptions,
        'filters' => $filters,
        'showColumnVisibility' => $showColumnVisibility,
        'showRefresh' => $showRefresh,
        'showSearch' => $showSearch,
    ];
@endphp

<div class="">


    <!-- Advanced Search Filters -->
    <div class="">
        <form class="dt_adv_search" method="GET">
            <div class="row g-3">
                @foreach ($filters as $filter)
                    <div class="col-12 col-sm-6 col-lg-{{ $filter['width'] ?? 4 }}">
                        <label class="form-label">{{ $filter['placeholder'] ?? ucfirst($filter['id']) }}</label>

                        @if ($filter['type'] === 'date_range')
                            <div>
                                <input type="text" class="form-control dt-date flatpickr-range dt-input"
                                    id="{{ $filter['id'] }}"
                                    placeholder="{{ $filter['placeholder'] ?? 'Select Date Range' }}" readonly>
                                <input type="hidden" id="{{ $filter['id'] }}_from">
                                <input type="hidden" id="{{ $filter['id'] }}_to">
                            </div>
                        @elseif($filter['type'] === 'select')
                            <select id="{{ $filter['id'] }}" class="form-select dt-input {{ $filter['class'] ?? '' }}"
                                {{ isset($filter['multiple']) && $filter['multiple'] ? 'multiple' : '' }}
                                data-placeholder="{{ $filter['placeholder'] }}">
                                @if (isset($filter['options']))
                                    @foreach ($filter['options'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                @endif
                            </select>
                        @else
                            <input type="text" class="form-control dt-input" id="{{ $filter['id'] }}"
                                placeholder="{{ $filter['placeholder'] ?? ucfirst($filter['id']) }}">
                        @endif
                    </div>
                @endforeach
            </div>
        </form>
    </div>


    <!-- DataTable with Right-Aligned Buttons -->
    <div class="card-datatable table-responsive p-2">
        <div class="d-flex justify-content-end align-items-center mb-2 flex-wrap gap-2"
            id="{{ $tableId }}-buttons-container">
            <!-- DataTables injects buttons here automatically -->
        </div>

        <table id="{{ $tableId ?? 'datatable' }}" class="table-bordered table-hover dt-advanced-search w-100 table">
            <thead class="table-light">
                <tr>
                    @if ($showCheckbox)
                        <th class="text-center" style="width: 40px;">
                            <input type="checkbox" id="select-all-{{ $tableId }}" class="form-check-input">
                        </th>
                    @endif
                    @foreach ($columns as $column)
                        <th class="{{ $column['class'] ?? '' }}"
                            style="{{ isset($column['width']) ? 'width: ' . $column['width'] : '' }}">
                            {{ $column['title'] ?? ucfirst(str_replace('_', ' ', $column['data'])) }}
                        </th>
                    @endforeach
                    <th class="text-center" style="width: 120px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <!-- Loaded via AJAX -->
            </tbody>
        </table>
    </div>

</div>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">{{ $title ?? 'Data Table' }}</h5>
        <div class="d-flex align-items-center">
            @isset($filters)
                @foreach ($filters as $key => $filter)
                    <div class="me-2">
                        @if ($filter['type'] === 'select')
                            <select id="filter-{{ $key }}" class="form-select form-select-sm">
                                <option value="">All {{ $filter['label'] }}</option>
                                @foreach ($filter['options'] as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                        @elseif ($filter['type'] === 'date')
                            <input type="text" id="filter-{{ $key }}"
                                class="form-control form-control-sm flatpickr-range" placeholder="Select date range">
                        @endif
                    </div>
                @endforeach
            @endisset
            @isset($headerButtons)
                @foreach ($headerButtons as $btn)
                    <a href="{{ $btn['url'] }}" class="btn btn-sm btn-{{ $btn['type'] ?? 'primary' }} ms-2">
                        @if (isset($btn['icon']))
                            <i class="{{ $btn['icon'] }} me-1"></i>
                        @endif
                        {{ $btn['label'] }}
                    </a>
                @endforeach
            @endisset
        </div>
    </div>

    <div class="card-datatable px-2 pt-2">
        <table class="table-bordered datatable-component w-100 table">
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column['data'] }}</th>
                    @endforeach
                </tr>
            </thead>
        </table>
    </div>
</div>



<!-- Hidden inputs for date_range filters -->
@foreach ($filters as $filter)
    @if ($filter['type'] === 'date_range')
        <input type="hidden" id="{{ $filter['id'] }}_from">
        <input type="hidden" id="{{ $filter['id'] }}_to">
    @endif
@endforeach

<!-- CSS Dependencies -->

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    window.datatablesConfig = window.datatablesConfig || {};
    window.datatablesConfig["{{ $tableId }}"] = @json($tableConfig);
</script>
<script src="{{ asset('assets/js/datatable-component.js') }}"></script>
