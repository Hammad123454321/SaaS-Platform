@extends('layout')
@section('title')
    <?= get_label('candidate_profile', 'Candidate Profile') ?>
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
                        <li class="breadcrumb-item">
                            <a href="{{ url('candidate/index') }}"><?= get_label('candidates', 'Candidates') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            {{ $candidate->name }}
                        </li>
                    </ol>
                </nav>
            </div>
            <!-- Align Edit Button to Top-Right -->
            <button type="button" class="btn btn-primary btn-sm edit-candidate-btn"
                data-candidate='@json($candidate)' title="{{ get_label('update', 'Update') }}"
                data-bs-dismiss="modal">
                <i class="bx bx-edit mx-1"></i> {{ get_label('edit', 'Edit') }}
            </button>
        </div>

        <div class="row">
            <div class="col-md-12">
                <div class="card mb-4 shadow-sm">
                    <!-- Candidate -->
                    <div class="card-body">
                        @php
                            $initials = collect(explode(' ', $candidate->name))
                                ->map(fn($n) => strtoupper($n[0]))
                                ->implode('');
                            $colors = ['#f8d7da', '#d1ecf1', '#d4edda', '#fff3cd', '#e2e3e5'];
                            $bgColor = $colors[$candidate->id % count($colors)];
                        @endphp

                        <div class="d-flex align-items-center gap-4">
                            <!-- Candidate Initials Avatar -->
                            <div class="rounded-circle d-flex align-items-center justify-content-center"
                                style="background-color: {{ $bgColor }}; color: #333; width: 100px; height: 100px; font-size: 36px; font-weight: bold;">
                                {{ $initials }}
                            </div>
                            <div>
                                <h4 class="card-title fw-bold">{{ $candidate->name }}</h4>
                                <div class="d-flex align-items-center">
                                    <span class="badge bg-primary">{{ $candidate->position }}</span>
                                    <span class="badge bg-info ms-2">{{ $candidate->status->name ?? 'No Status' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Candidate Details Section -->
                <div class="card mb-4 shadow-sm">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('phone_number', 'Phone Number') ?></label>
                                <input type="tel" class="form-control" value="{{ $candidate->phone ?? '-' }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('email', 'E-mail') ?></label>
                                <input class="form-control" type="text" value="{{ $candidate->email }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('position', 'Position') ?></label>
                                <input class="form-control" type="text" value="{{ $candidate->position }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('source', 'Source') ?></label>
                                <input class="form-control" type="text" value="{{ $candidate->source }}" readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('status', 'Status') ?></label>
                                <input class="form-control" type="text" value="{{ $candidate->status->name ?? '-' }}"
                                    readonly>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?= get_label('created_at', 'Created At') ?></label>
                                <input class="form-control" value="{{ format_date($candidate->created_at) }}" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attachments Section -->
                <div class="card mb-4 shadow-sm">
                    <h5 class="card-header"><?= get_label('attachments', 'Attachments') ?></h5>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <h6><?= get_label('documents', 'Documents') ?></h6>
                            <span data-bs-toggle="tooltip" data-bs-placement="left"
                                data-bs-original-title="{{ get_label('upload', 'Upload') }}">
                                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal"
                                    data-bs-target="#uploadAttachmentModal">
                                    <i class="bx bx-upload"></i>
                                </button>
                            </span>
                        </div>

                        @if ($candidate->getMedia('candidate-media')->count() > 0)
                            <div class="table-responsive text-nowrap">
                                <input type="hidden" id="attachment_type" value="media">
                                <table id="table" data-toggle="table"
                                    data-url="{{ route('candidate.attachments.list', $candidate->id) }}"
                                    data-icons-prefix="bx" data-icons="icons" data-show-refresh="true"
                                    data-total-field="total" data-data-field="rows" data-page-list="[5, 10, 20, 50, 100]"
                                    data-search="true" data-side-pagination="server" data-pagination="true"
                                    data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                                    data-query-params="queryParams">
                                    <thead>
                                        <tr>
                                            <th data-field="id" data-sortable="true">{{ get_label('id', 'ID') }}</th>
                                            <th data-field="name" data-sortable="true">{{ get_label('name', 'Name') }}</th>
                                            <th data-field="type">{{ get_label('type', 'Type') }}</th>
                                            <th data-field="size" data-escap="false">{{ get_label('size', 'Size') }}</th>
                                            <th data-field="created_at" data-sortable="true">
                                                {{ get_label('uploaded_at', 'Uploaded At') }}</th>
                                            <th data-field="actions">{{ get_label('actions', 'Actions') }}</th>
                                        </tr>
                                    </thead>
                                </table>
                            </div>
                        @else
                            <div class="py-5 text-center">
                                <div class="mb-3">
                                    <i class="bx bx-file text-primary" style="font-size: 3.5rem;"></i>
                                </div>
                                <h6><?= get_label('no_attachments', 'No Attachments Found') ?></h6>
                                <p class="text-muted">
                                    <?= get_label('no_attachments_desc', 'Upload documents related to this candidate') ?>
                                </p>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Interviews Section -->
                <div class="card mt-4 shadow-sm">
                    <div class="card-header">
                        <h4>{{ get_label('interviews', 'Interviews') }}</h4>
                        @if (isAdminOrHasAllDataAccess())
                            <!-- Align Interview Buttons to Top-Right -->
                            <div class="d-flex justify-content-end">
                                <a href="javascript:void(0);" data-bs-toggle="modal"
                                    data-bs-target="#createInterviewModal">
                                    <button type="button" class="btn btn-sm btn-primary action_create_template"
                                        data-bs-toggle="tooltip" data-bs-placement="left"
                                        data-bs-original-title="{{ get_label('schedule_interview', 'Schedule Interview') }}">
                                        <i class='bx bx-plus'></i>
                                    </button>
                                </a>
                            </div>
                        @endif
                    </div>
                    <div class="card-body">
                        @if ($candidate->interviews->isEmpty())
                            <p>{{ get_label('no_interviews', 'No interviews scheduled.') }}</p>
                        @else
                            <div class="table-responsive text-nowrap">
                                <input type="hidden" id="data_type" value="interviews_in_profile">
                                <table class="table" id="profile_interview_table">
                                    <thead>
                                        <tr>
                                            <th>{{ get_label('id', 'ID') }}</th>
                                            <th>{{ get_label('candidate', 'Candidate') }}</th>
                                            <th>{{ get_label('interviewer', 'Interviewer') }}</th>
                                            <th>{{ get_label('round', 'Round') }}</th>
                                            <th>{{ get_label('scheduled_at', 'Scheduled At') }}</th>
                                            <th>{{ get_label('status', 'Status') }}</th>
                                            <th>{{ get_label('location', 'Location') }}</th>
                                            <th>{{ get_label('mode', 'Mode') }}</th>
                                            <th>{{ get_label('created_at', 'Created At') }}</th>
                                            <th>{{ get_label('updated_at', 'Updated At') }}</th>
                                            <th>{{ get_label('actions', 'Actions') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($candidate->interviews as $interview)
                                            <tr>
                                                <td>{{ $interview->id }}</td>
                                                <td>{{ $candidate->name }}</td>
                                                <td>{{ $interview->interviewer->first_name . ' ' . $interview->interviewer->last_name }}
                                                </td>
                                                <td>{{ $interview->round }}</td>
                                                <td>{{ $interview->scheduled_at }}</td>
                                                <td>{{ $interview->status }}</td>
                                                <td>{{ $interview->location }}</td>
                                                <td>{{ $interview->mode }}</td>
                                                <td>{{ $interview->created_at }}</td>
                                                <td>{{ $interview->updated_at }}</td>
                                                <td>
                                                    <a href="javascript:void(0);" class="edit-interview-btn"
                                                        data-interview="{{ json_encode($interview) }}"
                                                        title="{{ get_label('update', 'Update') }}">
                                                        <i class="bx bx-edit mx-1"></i>
                                                    </a>
                                                    <button type="button" class="btn delete"
                                                        data-id="{{ $interview->id }}" data-type="interviews"
                                                        title="{{ get_label('delete', 'Delete') }}">
                                                        <i class="bx bx-trash text-danger mx-1"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('assets/js/pages/candidate.js') }}"></script>
    <script src="{{ asset('assets/js/pages/candidate-profile.js') }}"></script>
@endsection
