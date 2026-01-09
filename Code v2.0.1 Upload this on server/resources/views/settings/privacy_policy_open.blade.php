@extends('layout')
@section('title',get_label('privacy_policy','Privacy Policy'))
@section('content')
@php
     $privacy_policy = get_settings('privacy_policy');

@endphp
<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-4">
                <span class="d-inline-block bg-primary bg-opacity-10 rounded-circle p-3 mb-2">
                    <i class='bx bx-shield-quarter fs-1 text-white' ></i>
                </span>
                <h2 class="mb-2">{{ get_label('privacy_policy', 'Privacy Policy') }}</h2>
                <p class="text-muted">Your privacy is important to us. Please read our policy below.</p>
            </div>
            <div class="card shadow-sm border-0">
                <div class="card-body" style="background: #f8f9fa;">
                    {!! $privacy_policy['privacy_policy'] !!}
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
