@extends('layout')
@section('title')
    <?= get_label('todo_list', 'Todo list') ?>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= get_label('todos', 'Todos') ?>
                        </li>
                    </ol>
                </nav>
            </div>
            <div>
                <span data-bs-toggle="modal" data-bs-target="#create_todo_modal"><a href="javascript:void(0);"
                        class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="<?= get_label('create_todo', 'Create todo') ?>"><i
                            class='bx bx-plus'></i></a></span>
            </div>
        </div>
        <div class="todo-progress">
            <div class="todo-progress-label">
                <span>{{ get_label('todo_completion', 'Todo Completion') }}</span>
                @php
                    $total_todos = $todos->count();
                    $completed_todos = $todos->where('is_completed', '1')->count();
                    $progress = $total_todos > 0 ? ($completed_todos / $total_todos) * 100 : 0;
                    $progress = number_format($progress, 2);
                @endphp
                <span class="todo-progress-value">{{ $completed_todos }} / {{ $total_todos }} ({{ $progress }}%)</span>
            </div>
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: {{ $progress }}%"
                    aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        </div>
        <div class="row g-4">
            <!-- Unfinished Tasks Column -->
            <div class="col-lg-6">
                <div class="todo-card">
                    <div class="todo-card-header todo-gradient-primary">
                        <div class="todo-header-decoration"></div>
                        <div class="d-flex justify-content-between align-items-center position-relative z-2">
                            <div class="d-flex align-items-center">
                                <div class="todo-header-icon">
                                    <i class="bx bx-list-check"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-white">
                                    {{ get_label('incomplete_todos', 'Incomplete Todo\'s') }}</h5>
                            </div>
                            <span class="todo-counter">{{ $todos->where('is_completed', 0)->count() }}</span>
                        </div>
                    </div>
                    <div class="todo-card-body">
                        <div class="todo-list-container">
                            @if ($todos->where('is_completed', 0)->count() > 0)
                                @foreach ($todos->where('is_completed', 0) as $incomplete_todo)
                                    <div class="todo-item todo-priority-{{ $incomplete_todo->priority }} d-flex align-items-center"
                                        data-todo-id="{{ $incomplete_todo->id }}">
                                        <div class="todo-drag-handle me-2">
                                            <i class="bx bx-menu"></i>
                                        </div>
                                        <div class="todo-check me-3">
                                            <input type="checkbox" class="todo-check-input border-2"
                                                id="{{ $incomplete_todo->id }}" onclick='update_status(this)'
                                                name="{{ $incomplete_todo->id }}">
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="todo-title">{{ $incomplete_todo->title }}</h6>
                                            <div class="todo-meta">
                                                <span class="todo-meta-item"><i class="bx bx-calendar-alt"></i>
                                                    {{ format_date($incomplete_todo->created_at) }}
                                                </span>
                                                <span
                                                    class="todo-priority-badge todo-bg-{{ config('taskhub.priority_labels')[$incomplete_todo->priority] }}-subtle">
                                                    {{ ucfirst($incomplete_todo->priority) }}</span>
                                            </div>
                                        </div>
                                        <div class="todo-actions-container">
                                            <div class="d-flex">
                                                <a href="javascript:void(0);" class="edit-todo" data-bs-toggle="modal"
                                                    data-bs-target="#edit_todo_modal" data-id="{{ $incomplete_todo->id }}"
                                                    title="<?= get_label('update', 'Update') ?>" class="card-link"><i
                                                        class='bx bx-edit mx-1'></i></a>
                                                <a href="javascript:void(0);" type="button"
                                                    data-id="{{ $incomplete_todo->id }}" data-type="todos"
                                                    data-reload="true" title="<?= get_label('delete', 'Delete') ?>"
                                                    class="card-link delete mx-4"><i
                                                        class='bx bx-trash text-danger mx-1'></i></a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-muted py-3 text-center">No incomplete todos</div>
                            @endif
                            <div class="todo-add-wrapper mt-3">
                                <div class="todo-add-item d-flex align-items-center border-light bg-light hover-shadow-sm rounded border border-2 p-2"
                                    data-list="incomplete">
                                    <div class="todo-drag-handle text-muted me-2">
                                        <i class="bx bx-menu fs-5"></i>
                                    </div>
                                    <div class="todo-check me-3"></div>
                                    <div class="flex-grow-1">
                                        <input type="text"
                                            class="new-todo-title form-control form-control-sm border-0 bg-transparent shadow-none"
                                            placeholder="{{ get_label('add_todo_info', 'Add todo (Enter to save)') }}"
                                            data-list="incomplete">
                                    </div>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            <!-- Completed Tasks Column -->
            <div class="col-lg-6">
                <div class="todo-card">
                    <div class="todo-card-header todo-gradient-success text-white">
                        <div class="todo-header-decoration"></div>
                        <div class="d-flex justify-content-between align-items-center position-relative z-2">
                            <div class="d-flex align-items-center">
                                <div class="todo-header-icon">
                                    <i class="bx bx-check-double"></i>
                                </div>
                                <h5 class="fw-bold mb-0 text-white">
                                    {{ get_label('completed_todos', 'Completed Todo\'s') }}</h5>
                            </div>
                            <span class="todo-counter">{{ $todos->where('is_completed', '1')->count() }}</span>
                        </div>
                    </div>
                    <div class="todo-card-body">
                        <div class="todo-list-container">
                            @if ($todos->where('is_completed', '1')->count() > 0)
                                @foreach ($todos->where('is_completed', '1') as $completed_todo)
                                    <div class="todo-item todo-completed todo-priority-{{ $completed_todo->priority }} d-flex align-items-center"
                                        data-todo-id="{{ $completed_todo->id }}">
                                        <div class="todo-drag-handle me-2">
                                            <i class="bx bx-menu"></i>
                                        </div>
                                        <div class="todo-check me-3">
                                            <input type="checkbox" class="todo-check-input border-2"
                                                id="{{ $completed_todo->id }}" onclick='update_status(this)'
                                                name="{{ $completed_todo->id }}" checked>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="todo-title">{{ $completed_todo->title }}</h6>
                                            <div class="todo-meta">
                                                <span class="todo-meta-item"><i class="bx bx-calendar-alt"></i>
                                                    {{ format_date($completed_todo->created_at) }}
                                                </span>
                                                <span class="todo-completed-tag"><i class="bx bx-check-double me-1"></i>
                                                    {{ get_label('completed', 'Completed') }}</span>
                                            </div>
                                        </div>
                                        <div class="todo-actions-container">
                                            <div class="d-flex">
                                                <a href="javascript:void(0);" class="edit-todo" data-bs-toggle="modal"
                                                    data-bs-target="#edit_todo_modal" data-id="{{ $completed_todo->id }}"
                                                    title="<?= get_label('update', 'Update') ?>" class="card-link"><i
                                                        class='bx bx-edit mx-1'></i></a>
                                                <a href="javascript:void(0);" type="button"
                                                    data-id="{{ $completed_todo->id }}" data-type="todos"
                                                    data-reload="true" title="<?= get_label('delete', 'Delete') ?>"
                                                    class="card-link delete mx-4"><i
                                                        class='bx bx-trash text-danger mx-1'></i></a>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="text-muted py-3 text-center">No completed todos</div>
                            @endif
                            <div class="todo-add-wrapper mt-3">
                                <div class="todo-add-item d-flex align-items-center border-light bg-light hover-shadow-sm rounded border border-2 p-2"
                                    data-list="complete">
                                    <div class="todo-drag-handle text-muted me-2">
                                        <i class="bx bx-menu fs-5"></i>
                                    </div>
                                    <div class="todo-check me-3"></div>
                                    <div class="flex-grow-1">
                                        <input type="text"
                                            class="new-todo-title form-control form-control-sm border-0 bg-transparent shadow-none"
                                            placeholder="{{ get_label('add_todo_info', 'Add todo (Enter to save)') }}"
                                            data-list="complete">
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/js/Sortable.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/todos.js') }}"></script>
@endsection
