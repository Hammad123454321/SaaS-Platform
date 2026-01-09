@extends('layout')
@section('title')
    {{ get_label('candidates', 'Candidates') }} - {{ get_label('kanban_view', 'Kanban View') }}11
@endsection
@php
    $user = getAuthenticatedUser();
@endphp
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('home')}}">{{ get_label('home', 'Home') }}</a>
                    </li>
                    <li class="breadcrumb-item">
                        <span>{{ get_label('candidates', 'Candidates') }}</span>
                    </li>
                    <li class="breadcrumb-item active">{{ get_label('kanban', 'Kanban') }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#candidateModal">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('create_candidate', 'Create candidate') }}">
                    <i class='bx bx-plus'></i>
                </button>
            </a>
            <a href="{{ url('candidate/index') }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('list_view', 'List view') }}">
                    <i class='bx bx-list-ul'></i>
                </button>
            </a>

        </div>
    </div>


    @if (is_countable($candidates) && count($candidates) > 0)
        @php
        $showSettings = $user->can('edit_candidate') || $user->can('delete_candidate') || $user->can('create_candidate');
        $canEditCandidates = $user->can('edit_candidate');
        $canDeleteCandidates = $user->can('delete_candidate');
        $canCreateCandidates = $user->can('create_candidate');
        @endphp

        <x-candidates-kanban-card
            :candidates="$candidates"
            :statuses="$statuses"
            :showSettings="$showSettings"
            :canEditCandidates="$canEditCandidates"
            :canDeleteCandidates="$canDeleteCandidates"
            :canCreateCandidates="$canCreateCandidates"
        />
    @else
        <?php $type = 'candidates'; ?>
        <x-empty-state-card :type="$type" />
    @endif
</div>

    <script src="{{ asset('assets/js/jquery-ui.js') }}"></script>
    <script src="{{ asset('assets/js/pages/candidate-kanban.js') }}"></script>

@endsection
