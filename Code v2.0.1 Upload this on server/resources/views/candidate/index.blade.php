@extends('layout')

@section('title')
    {{ get_label('candidates', 'Candidates') }}
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
                    <li class="breadcrumb-item">
                       <span>{{ get_label('candidates', 'Candidates') }}</span>
                    </li>
                    <li class="breadcrumb-item active">
                        {{ get_label('list', 'List') }}
                    </li>
                </ol>
            </nav>
        </div>
        <div>
            <!-- No default view preference logic anymore -->
        </div>
        <div>
            @php $kanbanUrl = route('candidate.kanban_view'); @endphp

            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#candidateModal">
                <button type="button" class="btn btn-sm btn-primary action_create_template" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('create_candidate', 'Create Candidate') }}">
                    <i class='bx bx-plus'></i>
                </button>
            </a>

            <a href="{{ $kanbanUrl }}">
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('kanban_view', 'Kanban View') }}">
                    <i class='bx bx-layout'></i>
                </button>
            </a>
        </div>
    </div>

    <x-candidate-card :candidates="$candidates" />

</div>



<script src="{{ asset('assets/js/pages/candidate.js') }}"></script>
@endsection
