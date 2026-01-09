<div class="row">
    <div class="col-md-4 mb-3">
        <div class="input-group input-group-merge">
            <input type="text" class="form-control" id="candidate_date_between"
                placeholder="<?= get_label('date_between', 'Date Between') ?>" autocomplete="off">

        </div>
        <input type="hidden" id="candidate_date_between_from" name="startDate" />
        <input type="hidden" id="candidate_date_between_to" name="EndDate" />
    </div>
    <div class="col-md-3 mb-3">
        <select class="form-select js-example-basic-multiple" id="sort" name="sort"
            aria-label="Default select example" data-placeholder="<?= get_label('select_sort_by', 'Select Sort By') ?>"
            data-allow-clear="true">
            <option></option>
            <option value="newest" <?= request()->sort && request()->sort == 'newest' ? 'selected' : '' ?>>
                <?= get_label('newest', 'Newest') ?></option>
            <option value="oldest" <?= request()->sort && request()->sort == 'oldest' ? 'selected' : '' ?>>
                <?= get_label('oldest', 'Oldest') ?></option>
            <option value="recently-updated"
                <?= request()->sort && request()->sort == 'recently-updated' ? 'selected' : '' ?>>
                <?= get_label('most_recently_updated', 'Most recently updated') ?></option>
            <option value="earliest-updated"
                <?= request()->sort && request()->sort == 'earliest-updated' ? 'selected' : '' ?>>
                <?= get_label('least_recently_updated', 'Least recently updated') ?></option>
        </select>
    </div>

    <div class="col-md-4 mb-3">
        <select class="form-select" id="select_candidate_statuses" name="statuses[]" aria-label="Default select example"
            data-placeholder="<?= get_label('filter_by_statuses', 'Filter by statuses') ?>" data-allow-clear="true"
            multiple>
        </select>
    </div>

    <div class="col-md-1">
        <div>
            <button type="button" id="filter" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                data-bs-placement="left" data-bs-original-title="<?= get_label('filter', 'Filter') ?>"><i
                    class='bx bx-filter-alt'></i></button>
        </div>
    </div>
</div>

<!-- resources/views/components/candidates-kanban-card.blade.php -->
<div class="kanban-board d-flex bg-body flex-nowrap gap-3 p-3"
    style="min-height: calc(100vh - 220px); overflow-x: auto;">
    @foreach ($statuses as $status)
        <div class="kanban-column card" data-status-id="{{ $status->id }}"
            style="min-width: 300px; max-width: 300px; height: calc(100vh - 180px);">
            <div
                class="kanban-column-header card-header bg-label-{{ $status->color }} d-flex justify-content-between align-items-center p-3">
                <div class="fw-semibold text-truncate" style="max-width: 80%;">
                    {{ $status->name }}
                </div>
                <div class="column-count badge text-{{ $status->color }} bg-white">
                    {{ $candidates->where('status_id', $status->id)->count() }}/{{ $candidates->count() }}
                </div>
            </div>
            <div class="kanban-column-body card-body bg-body h-100 overflow-auto p-3">
                @foreach ($candidates->where('status_id', $status->id) as $candidate)
                    <div class="kanban-card card mb-3" data-card-id="{{ $candidate->id }}">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="card-title text-truncate mb-0" style="max-width: 100%;">
                                    <a href="{{ route('candidate.show', $candidate->id) }}"
                                        class="text-body text-primary view-candidate-details"
                                        data-id="{{ $candidate->id }}">
                                        {{ ucfirst($candidate->name) }}
                                    </a>
                                </h5>
                            </div>
                            <div class="mb-2">
                                <span class="badge bg-label-primary text-truncate me-1"
                                    style="max-width: 100%;">{{ $candidate->position }}</span>
                            </div>
                            <div class="text-truncate mb-2">
                                <i class='bx bx-envelope'></i> {{ $candidate->email }}
                            </div>
                            @if ($candidate->phone)
                                <div class="text-truncate mb-2">
                                    <i class='bx bx-phone'></i> {{ $candidate->phone }}
                                </div>
                            @endif
                            <div class="text-truncate mb-2">
                                <i class='bx bx-search'></i> Source: {{ $candidate->source }}
                            </div>
                            <div class="card-actions d-flex align-items-center">
                                <a href="javascript:void(0);" class="quick-candidate-view"
                                    data-id="{{ $candidate->id }}" data-type="candidate">
                                    <i class='bx bx-info-circle text-info' data-bs-toggle="tooltip"
                                        data-bs-placement="right"
                                        data-bs-original-title="{{ get_label('quick_view', 'Quick View') }}"></i>
                                </a>
                                @if ($showSettings)
                                    <a href="javascript:void(0);" class="ms-2" data-bs-toggle="dropdown"
                                        aria-expanded="false">
                                        <i class='bx bx-cog' id="settings-icon"></i>
                                    </a>
                                    <ul class="dropdown-menu">
                                        @if ($canEditCandidates)
                                            <li class="dropdown-item">
                                                <a href="javascript:void(0);"
                                                    class="edit-candidate-btn text-primary d-flex align-items-center"
                                                    data-candidate='@json($candidate)'
                                                    title="{{ get_label('update', 'Update') }}">
                                                    <i class="bx bx-edit me-2"></i> {{ get_label('update', 'Update') }}
                                                </a>
                                            </li>
                                        @endif
                                        @if ($canDeleteCandidates)
                                            <li class="dropdown-item">
                                                <a href="javascript:void(0);"
                                                    class="delete text-danger d-flex align-items-center"
                                                    data-reload="true" data-type="candidate"
                                                    data-id="{{ $candidate->id }}">
                                                    <i class="bx bx-trash me-2"></i>
                                                    {{ get_label('delete', 'Delete') }}
                                                </a>
                                            </li>
                                        @endif
                                    </ul>
                                @endif
                            </div>
                            <div class="text-truncate mt-2">
                                <i class='bx bx-calendar text-success'></i> {{ format_date($candidate->created_at) }}
                            </div>
                        </div>
                    </div>
                @endforeach
                @if ($canCreateCandidates)
                    <a href="javascript:void(0);"
                        class="btn btn-outline-secondary btn-sm d-block create-candidate-btn text-truncate"
                        data-bs-toggle="modal" data-bs-target="#candidateModal"
                        data-status-id="{{ $status->id }}">
                        <i class='bx bx-plus me-1'></i>{{ get_label('create_candidate', 'Create candidate') }}
                    </a>
                @endif
            </div>
        </div>
    @endforeach
</div>
