@extends('layout')

@section('title')
    <?= get_label('candidate_status', 'Candidate Status') ?>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ url('home') }}">{{ get_label('home', 'Home') }}</a>
                    </li>
                    <li class="breadcrumb-item active">
                        {{ get_label('candidates', 'Candidates') }}
                    </li>
                    <li class="breadcrumb-item active">
                        {{ get_label('candidates_status', 'Candidates Status') }}
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#createStatusModal">
                <button type="button" class="btn btn-sm btn-primary action_create_template" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('create_candidate_status', 'Create Candidate Status') }}">
                    <i class='bx bx-plus'></i>
                </button>
            </a>
        </div>
    </div>

    {{-- @dd($candidate_statuses); --}}

    <x-candidate-status-card :candidate_statuses="$candidate_statuses"/>


</div>
{{-- @include('modals',['candidateStatuses'=>$statuses]); --}}
<script src="{{ asset('assets/js/jquery-ui.js') }}"></script>
 <script src="{{ asset('assets/js/pages/candidate-status.js') }}"></script>

@endsection
