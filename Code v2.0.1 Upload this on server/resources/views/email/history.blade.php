@extends('layout')

@section('title')
    <?= get_label('email_history', 'Email History') ?>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item"><a href="{{ url('home') }}">{{ get_label('home', 'Home') }}</a></li>
                    <li class="breadcrumb-item active">{{ get_label('email', 'Email') }}</li>
                </ol>
            </nav>
        </div>
        <div>
            <a href="{{ route('emails.send') }}" class="btn btn-primary d-flex align-items-center" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ get_label('send_email', 'Send Email') }}">
                <i class="bx bx-plus"></i>
            </a>
        </div>
    </div>

    <x-email-history-card :emails="$emails"  />

</div>

 <script src="{{ asset('assets/js/pages/email-history.js') }}"></script>
@endsection
