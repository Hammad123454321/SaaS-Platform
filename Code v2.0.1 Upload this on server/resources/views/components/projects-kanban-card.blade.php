<div class="kanban-board d-flex bg-body gap-3 overflow-auto p-3">
    @foreach ($statuses as $status)
    <div class="kanban-column card" data-status-id="{{ $status->id }}">
        <div class="kanban-column-header card-header bg-label-{{ $status->color }} d-flex justify-content-between align-items-center p-3">
            <div class="fw-semibold">
                {{ $status->title }}
            </div>
            <div class="column-count badge text-{{ $status->color }} bg-white">
                {{ $projects->where('status_id', $status->id)->count() }}/{{ $projects->count() }}
            </div>
        </div>
        <div class="kanban-column-body card-body bg-body p-3">
            @foreach ($projects->where('status_id', $status->id) as $project)
            <div class="kanban-card card mb-3" data-card-id="{{ $project->id }}">
                <div class="card-body">
                    @if ($project->tags->isNotEmpty())
                    <div class="mb-3">
                        @foreach ($project->tags as $tag)
                        <span class="badge bg-{{$tag->color}} mt-1">{{$tag->title}}</span>
                        @endforeach
                    </div>
                    @endif
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="card-title mb-0">
                            <a href="{{ url('projects/information/' . $project->id) }}"
                                class="text-body text-primary" target="_blank">
                                {{ ucfirst(Str::limit($project->title, 15)) }}
                            </a>
                        </h5>
                        @if($project->priority)
                        <span class="badge bg-label-{{ $project->priority->color }}">
                            {{ $project->priority->title }}
                        </span>
                        @endif
                    </div>
                    <div class="mb-2">
                        @if ($project->budget != '')
                        <span class='badge bg-label-primary me-1'>{{ format_currency($project->budget) }}</span>
                        @endif
                    </div>
                    @if(filled($project->description))
                    <div class="text-light">
                        <p class="small mb-2">{!! Str::limit($project->description, 60) !!}</p>
                    </div>
                    @endif
                    <div class="card-actions d-flex align-items-center">
                        <a href="javascript:void(0);" class="quick-view" data-id="{{ $project->id }}" data-type="project">
                            <i class='bx bx bx-info-circle text-info' data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{ get_label('quick_view', 'Quick View') }}"></i>
                        </a>
                        <a href="javascript:void(0);" class="mx-2">
                            <i class='bx {{ getFavoriteStatus($project->id) ? 'bxs' : 'bx' }}-star favorite-icon text-warning' data-id="{{ $project->id }}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{ getFavoriteStatus($project->id) ? get_label('remove_favorite', 'Click to remove from favorite') : get_label('add_favorite', 'Click to mark as favorite') }}" data-favorite="{{ getFavoriteStatus($project->id) }}"></i>
                        </a>
                        <a href="javascript:void(0);">
                            <i class='bx {{getPinnedStatus($project->id) ? "bxs" : "bx"}}-pin pinned-icon text-success' data-id="{{$project->id}}" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{getPinnedStatus($project->id) ? get_label('click_unpin', 'Click to Unpin') : get_label('click_pin', 'Click to Pin')}}" data-pinned="{{getPinnedStatus($project->id)}}"></i>
                        </a>
                        @if($webGuard || $project->client_can_discuss)
                        <a href="{{ route('projects.info', ['id' => $project->id]) }}#navs-top-discussions" class="ms-2">
                            <i class='bx bx-message-rounded-dots text-danger' data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{ get_label('discussions', 'Discussions') }}"></i>
                        </a>
                        @endif
                        <a href="{{ url('projects/mind-map/' . $project->id) }}" class="@if($showSettings) mx-2 @else ms-2 @endif">
                            <i class="bx bx-sitemap text-primary" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="{{ get_label('mind_map', 'Mind Map') }}"></i>
                        </a>
                        @if ($showSettings)
                        <a href="javascript:void(0);" class="mr-2" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class='bx bx-cog' id="settings-icon"></i>
                        </a>
                        <ul class="dropdown-menu">
                            @if ($canEditProjects)
                            <a href="javascript:void(0);" class="edit-project" data-offcanvas="true" data-id="{{$project->id}}">
                                <li class="dropdown-item">
                                    <i class='menu-icon tf-icons bx bx-edit text-primary'></i>{{ get_label('update', 'Update') }}
                                </li>
                            </a>
                            @endif
                            @if ($canDeleteProjects)
                            <a href="javascript:void(0);" class="delete" data-reload="true" data-type="projects" data-id="{{$project->id}}">
                                <li class="dropdown-item">
                                    <i class='menu-icon tf-icons bx bx-trash text-danger'></i>{{ get_label('delete', 'Delete') }}
                                </li>
                            </a>
                            @endif
                            @if ($canDuplicateProjects)
                            <a href="javascript:void(0);" class="duplicate" data-type="projects" data-id="{{$project->id}}" data-title="{{$project->title}}" data-reload="true">
                                <li class="dropdown-item">
                                    <i class='menu-icon tf-icons bx bx-copy text-warning'></i>{{ get_label('duplicate', 'Duplicate') }}
                                </li>
                            </a>
                            @endif
                        </ul>
                        @endif
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-12">
                            <p class="card-text mb-1">
                                {{ get_label('users', 'Users') }}
                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                    @php
                                    $users = $project->users;
                                    $userCount = count($users);
                                    $displayedUsers = 0;
                                    $remainingUsers = 0;
                                    @endphp

                                    @if ($userCount > 0)
                                        @foreach ($users as $user)
                                            @if ($displayedUsers < 8)
                                            <li class="avatar avatar-sm pull-up" title="{{ $user->first_name }} {{ $user->last_name }}">
                                                <a href="{{ url('/users/profile/' . $user->id) }}">
                                                    <img src="{{ $user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg') }}" class="rounded-circle" alt="{{ $user->first_name }} {{ $user->last_name }}">
                                                </a>
                                            </li>
                                            @php $displayedUsers++; @endphp
                                            @else
                                                @php
                                                $remainingUsers = $userCount - $displayedUsers;
                                                break;
                                                @endphp
                                            @endif
                                        @endforeach

                                        @if ($remainingUsers > 0)
                                        <span class="badge badge-center rounded-pill bg-primary mx-1">+{{ $remainingUsers }}</span>
                                        @endif
                                    @else
                                    <span class="badge bg-primary">{{ get_label('not_assigned', 'Not assigned') }}</span>
                                    @endif
                                    <a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{ $project->id }}">
                                        <span class="bx bx-edit"></span>
                                    </a>
                                </ul>
                            </p>
                        </div>

                        <div class="col-md-12">
                            <p class="card-text mb-1">
                                {{ get_label('clients','Clients') }}
                                <ul class="list-unstyled users-list m-0 avatar-group d-flex align-items-center">
                                    @php
                                    $clients = $project->clients;
                                    $clientCount = $clients->count();
                                    $displayedClients = 0;
                                    $remainingClients = 0;
                                    @endphp

                                    @if ($clientCount > 0)
                                        @foreach ($clients as $client)
                                            @if ($displayedClients < 8)
                                            <li class="avatar avatar-sm pull-up" title="{{ $client->first_name }} {{ $client->last_name }}">
                                                <a href="{{ url('/clients/profile/' . $client->id) }}">
                                                    <img src="{{ $client->photo ? asset('storage/' . $client->photo) : asset('storage/photos/no-image.jpg') }}" class="rounded-circle" alt="{{ $client->first_name }} {{ $client->last_name }}">
                                                </a>
                                            </li>
                                            @php $displayedClients++; @endphp
                                            @else
                                                @php
                                                $remainingClients = $clientCount - $displayedClients;
                                                break;
                                                @endphp
                                            @endif
                                        @endforeach

                                        @if ($remainingClients > 0)
                                        <span class="badge badge-center rounded-pill bg-primary mx-1">+{{ $remainingClients }}</span>
                                        @endif
                                    @else
                                    <span class="badge bg-primary">{{ get_label('not_assigned', 'Not assigned') }}</span>
                                    @endif
                                    <a href="javascript:void(0)" class="btn btn-icon btn-sm btn-outline-primary btn-sm rounded-circle edit-project update-users-clients" data-offcanvas="true" data-id="{{ $project->id }}">
                                        <span class="bx bx-edit"></span>
                                    </a>
                                </ul>
                            </p>
                        </div>
                    </div>

                    @if($project->start_date)
                    <div class="mt-2">
                        <i class='bx bx-calendar text-success'></i> {{ format_date($project->start_date) }}
                    </div>
                    @endif
                    @if($project->end_date)
                    <div class="mt-2">
                        <i class='bx bx-calendar text-danger'></i> {{ format_date($project->end_date) }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
            @if (canSetStatus($status))
            <a href="javascript:void(0);" class="btn btn-outline-secondary btn-sm d-block create-project-btn"
                data-bs-toggle="modal" data-bs-target="#create_project_modal" data-status-id="{{ $status->id }}">
                <i class='bx bx-plus me-1'></i>{{ get_label('create_project', 'Create project') }}
            </a>
            @endif
        </div>
    </div>
    @endforeach
</div>
