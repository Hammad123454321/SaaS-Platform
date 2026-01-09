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
                        <a href="{{ url('interviews/index') }}">{{ get_label('interviews', 'Interviews') }}</a>
                    </li>

                </ol>
            </nav>
        </div>
        <div>
            <!-- No default view preference logic anymore -->
        </div>
        <div>
            <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#createInterviewModal">
                <button type="button" class="btn btn-sm btn-primary action_create_template" data-bs-toggle="tooltip" data-bs-placement="left" data-bs-original-title="{{ get_label('schedule_interview', 'Schedule Interview') }}">
                    <i class='bx bx-plus'></i>
                </button>
            </a>
        </div>
    </div>
    <x-interview-card :interviews="$interviews" :candidates="$candidates" :users="$users" />


</div>

<script src="{{ asset('assets/js/pages/interview.js') }}"></script>
@endsection
