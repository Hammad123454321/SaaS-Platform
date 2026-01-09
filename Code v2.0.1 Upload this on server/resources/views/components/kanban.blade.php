@props(['task'])
@php
$user = getAuthenticatedUser();
$showSettings = $user->can('edit_tasks') || $user->can('delete_tasks') || $user->can('create_tasks');
$webGuard = Auth::guard('web')->check();
$canEditTasks = $user->can('edit_tasks');
$canDeleteTasks = $user->can('delete_tasks');
$canDuplicateTasks = $user->can('create_tasks');
@endphp
<div class="card m-2 shadow" data-task-id="{{$task->id}}">
    <div class="card-body card-body-task-draggable">
        <div class="task-item">
            <h6 class="card-title mb-1">
                <a href="{{ url('tasks/information/' . $task->id) }}">
                    <strong>{{ $task->title }}</strong>
                </a>
            </h6>
            <div class="d-flex align-items-center mb-1">
                <a href="javascript:void(0);" class="quick-view me-2" data-id="{{ $task->id }}" data-type="task">
                    <i class='bx bx-info-circle text-info' data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ get_label('quick_view', 'Quick View') }}"></i>
                </a>
                <a href="javascript:void(0);" class="favorite-icon me-2">
                    <i class='bx {{ getFavoriteStatus($task->id, \App\Models\Task::class) ? "bxs" : "bx" }}-star text-warning' data-id="{{ $task->id }}" data-type="tasks" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ getFavoriteStatus($task->id, \App\Models\Task::class) ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite') }}" data-favorite="{{ getFavoriteStatus($task->id, \App\Models\Task::class) ? 1 : 0 }}"></i>
                </a>
                <a href="javascript:void(0);" class="pinned-icon me-2">
                    <i class='bx {{ getPinnedStatus($task->id, \App\Models\Task::class) ? "bxs" : "bx" }}-pin text-success' data-id="{{ $task->id }}" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ getPinnedStatus($task->id, \App\Models\Task::class) ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin') }}" data-pinned="{{ getPinnedStatus($task->id, \App\Models\Task::class) }}" data-type="tasks"></i>
                </a>
                @if(Auth::guard('web')->check() || $task->client_can_discuss)
                <a href="{{ route('tasks.info', ['id' => $task->id]) }}#navs-top-discussions" class="me-2">
                    <i class='bx bx-message-rounded-dots text-danger' data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-original-title="{{ get_label('discussions', 'Discussions') }}"></i>
                </a>
                @endif
                @if ($showSettings)
                <div class="dropdown me-2">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class='bx bx-cog'></i>
                    </a>
                    <ul class="dropdown-menu">
                        @if ($canEditTasks)
                        <a href="javascript:void(0);" class="edit-task" data-id="{{ $task->id }}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-edit text-primary'></i> {{ get_label('update', 'Update') }}
                            </li>
                        </a>
                        @endif
                        @if ($canDeleteTasks)
                        <a href="javascript:void(0);" class="delete" data-reload="true" data-type="tasks" data-id="{{ $task->id }}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-trash text-danger'></i> {{ get_label('delete', 'Delete') }}
                            </li>
                        </a>
                        @endif
                        @if ($canDuplicateTasks)
                        <a href="javascript:void(0);" class="duplicate" data-reload="true" data-type="tasks" data-id="{{ $task->id }}" data-title="{{ $task->title }}">
                            <li class="dropdown-item">
                                <i class='menu-icon tf-icons bx bx-copy text-warning'></i> {{ get_label('duplicate', 'Duplicate') }}
                            </li>
                        </a>
                        @endif
                    </ul>
                </div>
                @endif
            </div>
        </div>
        <a href="{{ route('projects.info', ['id' => $task->project->id]) }}">
            {{ $task->project->title }}
        </a>

        <div class="row mt-2">
            <div class="col-md-12">
                <p class="card-text mb-1">
                    <?= get_label('users', 'Users') ?>
                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                    <?php
                    $users = $task->users;
                    $count = count($users);
                    $displayed = 0;
                    if ($count > 0) {
                        foreach ($users as $user) {
                            if ($displayed < 9) { ?>
                                <li class="avatar avatar-sm pull-up" title="<?= $user->first_name ?> <?= $user->last_name ?>">
                                    <a href="{{ url('/users/profile/' . $user->id) }}">
                                        <img src="<?= $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg') ?>" class="rounded-circle" alt="<?= $user->first_name ?> <?= $user->last_name ?>">
                                    </a>
                                </li>
                    <?php
                                $displayed++;
                            } else {
                                $remaining = $count - $displayed;
                                echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                break;
                            }
                        }
                        // Add edit option at the end
                        echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a>';
                    } else {
                        echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                        // Add edit option at the end
                        echo '<a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-task update-users-clients" data-id="' . $task->id . '"><span class="bx bx-edit"></span></a>';
                    }
                    ?>
                </ul>
                </p>
            </div>
            <div class="col-md-12">
                <p class="card-text mb-1">
                    {{get_label('clients','Clients')}}
                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                    <?php
                    $clients = $task->project->clients;
                    $count = $clients->count();
                    $displayed = 0;
                    if ($count > 0) {
                        foreach ($clients as $client) {
                            if ($displayed < 10) { ?>
                                <li class="avatar avatar-sm pull-up" title="<?= $client->first_name ?> <?= $client->last_name ?>">
                                    <a href="{{ url('/clients/profile/' . $client->id) }}">
                                        <img src="<?= $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg') ?>" class="rounded-circle" alt="<?= $client->first_name ?> <?= $client->last_name ?>">
                                    </a>
                                </li>
                    <?php
                                $displayed++;
                            } else {
                                $remaining = $count - $displayed;
                                echo '<span class="badge badge-center rounded-pill bg-primary mx-1">+' . $remaining . '</span>';
                                break;
                            }
                        }
                    } else {
                        // Display "Not assigned" badge
                        echo '<span class="badge bg-primary">' . get_label('not_assigned', 'Not assigned') . '</span>';
                    }
                    ?>
                </ul>
                </p>
            </div>
        </div>
        <div class="d-flex flex-column">
            <label for="statusSelect"><?= get_label('status', 'Status') ?></label>
            <div class="d-flex align-items-center mb-3">
                <!-- Status select -->
                <select class="form-select form-select-sm select-bg-label-{{$task->status->color}}" id="statusSelect" data-id="{{ $task->id }}" data-original-status-id="{{ $task->status->id }}" data-original-color-class="select-bg-label-{{$task->status->color}}" data-type="task" data-reload="true">
                    @foreach($statuses as $status)
                    @php
                    $disabled = canSetStatus($status) ? '' : 'disabled';
                    @endphp
                    <option value="{{ $status->id }}" class="badge bg-label-{{ $status->color }}" {{ $task->status->id == $status->id ? 'selected' : '' }} {{ $disabled }}>
                        {{ $status->title }}
                    </option>
                    @endforeach
                </select>
                @if($task->note)
                <i class='bx bx-notepad ms-1 text-primary' data-bs-toggle="tooltip" data-bs-offset="0,4" data-bs-placement="top" title="" data-bs-original-title="{{$task->note}}" data-bs-toggle="tooltip" data-bs-placement="top"></i>
                @endif
            </div>

            <label for="prioritySelect"><?= get_label('priority', 'Priority') ?></label>
            <select class="form-select form-select-sm select-bg-label-{{$task->priority ? $task->priority->color : 'secondary'}} mb-3" id="prioritySelect" data-id="{{ $task->id }}" data-original-priority-id="{{$task->priority ? $task->priority->id : ''}}" data-original-color-class="select-bg-label-{{$task->priority ? $task->priority->color : 'secondary'}}" data-type="task">
                <option value="" class="badge bg-label-secondary">-</option>
                @foreach($priorities as $priority)
                <option value="{{$priority->id}}" class="badge bg-label-{{$priority->color}}" {{ $task->priority && $task->priority->id == $priority->id ? 'selected' : '' }}>
                    {{$priority->title}}
                </option>
                @endforeach
            </select>
            @if ($task->start_date)
            <p class="card-text mb-3">
                {{get_label('starts_at', 'Starts at')}}:
                {{ format_date($task->start_date)}}
            </p>
            @endif
            @if ($task->due_date)
            <p class="card-text mb-3">
                {{get_label('ends_at', 'Ends at')}}:
                {{ format_date($task->due_date)}}
            </p>
            @endif
            <small class="text-muted"><?= get_label('created_at', 'Created At') ?>: {{ format_date($task->created_at) }}</small>
        </div>
    </div>
</div>