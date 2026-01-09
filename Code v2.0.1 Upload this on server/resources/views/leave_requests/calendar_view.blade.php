@extends('layout')
@section('title')
    {{ get_label('leave_requests', 'Leave Requests') }} - {{ get_label('calendar_view', 'Calendar View') }}
@endsection
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
                            <a href="{{ route('leave_requests.index') }}">{{ get_label('leave_requests', 'Leave Requests') }}</a>

                        </li>
                        <li class="breadcrumb-item active">
                            {{ get_label('calendar_view', 'Calendar View') }}
                        </li>
                    </ol>
                </nav>
            </div>
            <div>
                @php
                    $leaveRequestDefaultView = getUserPreferences('leave_requests', 'default_view');
                @endphp
                @if ($leaveRequestDefaultView === 'calendar')
                    <span class="badge bg-primary"><?= get_label('default_view', 'Default View') ?></span>
                @else
                    <a href="javascript:void(0);"><span class="badge bg-secondary" id="set-default-view"
                            data-type="leave-requests"
                            data-view="calendar"><?= get_label('set_as_default_view', 'Set as Default View') ?></span></a>
                @endif
            </div>
            <div>
                <a href="{{ route('leave_requests.index') }}"><button type="button" class="btn btn-sm btn-primary"
                        data-bs-toggle="tooltip" data-bs-placement="left"
                        data-bs-original-title="{{ get_label('leave_requests', 'Leave Requests') }}"><i
                            class='bx bx-list-ul'></i></button></a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div id="leave_request_calendar_view"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
