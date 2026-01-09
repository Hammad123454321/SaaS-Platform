@extends('layout')

@section('title')
    {{ get_label('pwa_settings', 'PWA Settings') }}
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
                            <span>{{ get_label('settings', 'Settings') }}</span>
                        </li>
                        <li class="breadcrumb-item active">
                            {{ get_label('pwa_settings', 'PWA Settings') }}
                        </li>
                    </ol>
                </nav>
            </div>
        </div>


        <div class="card rounded-4 border-0 shadow-sm">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-4">{{ get_label('pwa_settings', 'PWA Settings') }}</h4>

                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif

                <form method="POST" action="{{ route('pwa-settings.update') }}" enctype="multipart/form-data"
                    class="needs-validation" novalidate data-original-name="{{ $pwaSettings['name'] ?? 'Taskify' }}"
                    data-original-short-name="{{ $pwaSettings['short_name'] ?? 'Taskify' }}"
                    data-original-theme-color="{{ $pwaSettings['theme_color'] ?? '#000000' }}"
                    data-original-background-color="{{ $pwaSettings['background_color'] ?? '#ffffff' }}"
                    data-original-description="{{ $pwaSettings['description'] ?? 'A task management app to boost productivity' }}">

                    @csrf

                    <div class="row g-4">
                        {{-- Name --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium" for="name">Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="name" id="name" class="form-control rounded-3"
                                value="{{ old('name', $pwaSettings['name'] ?? 'Taskify') }}" required>
                            @error('name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Short Name --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium" for="short_name">Short Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="short_name" id="short_name" class="form-control rounded-3"
                                value="{{ old('short_name', $pwaSettings['short_name'] ?? 'Taskify') }}" required>
                            @error('short_name')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Theme Color --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium" for="theme_color">Theme Color<span
                                    class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="theme_color" id="theme_color"
                                    class="form-control form-control-color rounded-3"
                                    value="{{ old('theme_color', $pwaSettings['theme_color'] ?? '#000000') }}" required>
                                <span
                                    class="text-muted small">{{ old('theme_color', $pwaSettings['theme_color'] ?? '#000000') }}</span>
                            </div>
                            @error('theme_color')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Background Color --}}
                        <div class="col-md-6">
                            <label class="form-label fw-medium" for="background_color">Background Color<span
                                    class="text-danger">*</span></label>
                            <div class="d-flex align-items-center gap-2">
                                <input type="color" name="background_color" id="background_color"
                                    class="form-control form-control-color rounded-3"
                                    value="{{ old('background_color', $pwaSettings['background_color'] ?? '#ffffff') }}"
                                    required>
                                <span
                                    class="text-muted small">{{ old('background_color', $pwaSettings['background_color'] ?? '#ffffff') }}</span>
                            </div>
                            @error('background_color')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Description --}}
                        <div class="col-12">
                            <label class="form-label fw-medium" for="description">Description <span
                                    class="text-danger">*</span></label>
                            <textarea name="description" id="description" rows="3" class="form-control rounded-3" required>{{ old('description', $pwaSettings['description'] ?? 'A task management app to boost productivity') }}</textarea>
                            @error('description')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Logo --}}
                        <div class="col-12">
                            <label class="form-label fw-medium" for="logo">Logo <span
                                    class="text-danger">*</span></label>
                            <p class="text-danger small mb-2">Please upload minimum <strong>512x512 PNG</strong> logo or
                                else it will not work.</p>

                            <div class="rounded-3 border border-dashed p-4 text-center">
                                <input type="file" name="logo" id="logo" class="form-control text-center"
                                    accept="image/png">
                                <p class="text-muted small mb-0 mt-2">Recommended Size: larger than 512 x 512</p>
                                <p class="text-muted small mt-1">Current:
                                    <code>{{ $pwaSettings['logo'] ?? '/images/icons/logo-512x512.png' }}</code></p>
                            </div>

                            @error('logo')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Buttons --}}
                        <div class="col-12 d-flex justify-content-end gap-2 pt-3">
                           <button type="button" class="btn btn-outline-dark rounded-3" onclick="resetForm()">Reset</button>
                            <button type="submit" class="btn btn-primary rounded-3 fw-semibold">Update Settings</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>

    <script src="{{ asset('assets/js/pages/pwa-settings.js') }}"></script>
@endsection
