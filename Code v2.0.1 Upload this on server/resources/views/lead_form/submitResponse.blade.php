@extends('layout') {{-- Or your base layout --}}

@section('title', 'Form Submitted')

@section('content')
<div class="container d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="text-center">
        <div class="mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="#28a745" class="bi bi-check-circle-fill" viewBox="0 0 16 16">
                <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM6.97 11.03a.75.75 0 0 0 1.07 0l4-4a.75.75 0 1 0-1.06-1.06L7.5 9.44 5.53 7.47a.75.75 0 0 0-1.06 1.06l2.5 2.5z"/>
            </svg>
        </div>
        <h2 class="text-success">Form Submitted Successfully!</h2>
        <p class="lead mt-3">Thank you for your submission. We will get right back to you.</p>
        <a href="{{ url()->previous() }}" class="btn btn-outline-success mt-4">Go Back</a>
    </div>
</div>
@endsection
