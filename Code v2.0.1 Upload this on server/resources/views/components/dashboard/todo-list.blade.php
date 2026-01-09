<ul class="p-0 m-0">
    @if (is_countable($todos) && count($todos) > 0)
    @foreach ($todos as $todo)
    <li class="d-flex mb-4 pb-1">
        <div class="avatar flex-shrink-0">
            <input type="checkbox" id="{{ $todo->id }}" onclick="update_status(this)" name="{{ $todo->id }}" class="form-check-input mt-0" {{ $todo->is_completed ? 'checked' : '' }}>
        </div>
        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
            <div class="me-2">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 {{ $todo->is_completed ? 'striked' : '' }}" id="{{ $todo->id }}_title">{{ $todo->title }}</h6>
                    <div class="user-progress d-flex align-items-center gap-1">
                        <a href="javascript:void(0);" class="edit-todo" data-bs-toggle="modal" data-bs-target="#edit_todo_modal" data-id="{{ $todo->id }}" title="{{ get_label('update', 'Update') }}">
                            <i class="bx bx-edit mx-1"></i>
                        </a>
                        <a href="javascript:void(0);" class="delete" data-id="{{ $todo->id }}" data-type="todos" title="{{ get_label('delete', 'Delete') }}">
                            <i class="bx bx-trash text-danger mx-1"></i>
                        </a>
                    </div>
                </div>
                <small class="text-muted d-block my-1">{{ format_date($todo->created_at, true) }}</small>
            </div>
        </div>
    </li>
    @endforeach
    @else
    <div class="h-100 d-flex justify-content-center align-items-center">
        <span>{{ get_label('todos_not_found', 'Todos not found!') }}</span>
    </div>
    @endif
</ul>
