@extends('layout')
<title>{{ get_label('sign_up', 'Sign Up') }} - {{ $general_settings['company_title'] }}</title>
@section('content')
    <!-- Content -->
    <div class="container-fluid">
        <div class="authentication-wrapper authentication-basic container-p-y">
            <div class="authentication-inner signup-form py-4">
                <div class="card">
                    <div class="card-body">
                        <!-- Logo -->
                        <div class="app-brand justify-content-center">
                            <a href="{{ url('home') }}" class="app-brand-link">
                                <span class="app-brand-logo demo">
                                    <img src="{{ asset($general_settings['full_logo']) }}" width="300px" alt="" />
                                </span>
                                <!-- <span class="app-brand-text demo menu-text fw-bolder ms-2">Taskify</span> -->
                            </a>
                        </div>
                        <!-- /Logo -->
                        @if (!isEmailConfigured())
                            <div class="alert alert-info">
                                {{ get_label('email_not_configured_info', 'Email settings are not configured, which is required for the email verification process. Please contact the admin for assistance.') }}
                            </div>
                        @endif
                        @if (!hasPrimaryWorkspace())
                            <div class="alert alert-info">
                                {{ get_label('primary_workspace_not_set_info_signup', 'Primary workspace is not set, which is required for signup. Please contact the admin for assistance.') }}
                            </div>
                        @endif
                        <h4>{{ get_label('create_account', 'Create a new account') }}</h4>
                        <form class="form-submit-event mb-3" action="{{ url('create-account') }}" method="POST">
                            <input type="hidden" name="redirect_url" value="{{ url('/') }}">
                            @csrf
                            <div class="mb-3">
                                <div class="btn-group btn-group d-flex justify-content-center" role="group"
                                    aria-label="Basic radio toggle button group">
                                    <input type="radio" class="btn-check" id="type_client" name="type" value="client"
                                        checked>
                                    <label class="btn btn-outline-primary"
                                        for="type_client"><?= get_label('as_client', 'As a Client') ?></label>
                                    <input type="radio" class="btn-check" id="type_member" name="type" value="member">
                                    <label class="btn btn-outline-primary"
                                        for="type_member"><?= get_label('as_team_member', 'As a Team member') ?></label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ get_label('first_name', 'First name') }} <span
                                        class="asterisk">*</span></label>
                                <input type="text" class="form-control" name="first_name"
                                    placeholder="<?= get_label('please_enter_first_name', 'Please enter first name') ?>"
                                    autofocus />
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ get_label('last_name', 'Last name') }} <span
                                        class="asterisk">*</span></label>
                                <input type="text" class="form-control" name="last_name"
                                    placeholder="<?= get_label('please_enter_last_name', 'Please enter last name') ?>" />
                            </div>
                            <div class="mb-3" id="companyDiv">
                                <label class="form-label">{{ get_label('company', 'Company') }}</label>
                                <input type="text" class="form-control" name="company"
                                    placeholder="<?= get_label('please_enter_company_name', 'Please enter company name') ?>" />
                            </div>
                            <!-- <div class="d-none mb-3" id="roleDiv">
                                        <label class="form-label" for="role"><?= get_label('role', 'Role') ?> <span class="asterisk">*</span></label>
                                        <select class="form-select text-capitalize js-example-basic-multiple" id="role" name="role" data-placeholder="<?= get_label('Please select', 'Please select') ?>" data-allow-clear="false">
                                            <option></option>
                                            @foreach ($roles as $role)
    <option value="{{ $role->id }}">{{ ucfirst($role->name) }}</option>
    @endforeach
                                        </select>
                                    </div> -->
                            <div class="mb-3">
                                <label class="form-label">{{ get_label('email', 'Email') }} <span
                                        class="asterisk">*</span></label>
                                <input type="text" class="form-control" name="email"
                                    placeholder="<?= get_label('please_enter_email', 'Please enter email') ?>" />
                            </div>
                            <div class="form-password-toggle mb-3">
                                <label for="password" class="form-label"><?= get_label('password', 'Password') ?> <span
                                        class="asterisk">*</span></label>
                                <div class="input-group input-group-merge">
                                    <input class="form-control" type="password" id="password" name="password"
                                        placeholder="<?= get_label('please_enter_password', 'Please enter password') ?>"
                                        autocomplete="new-password">
                                    <span class="input-group-text toggle-password cursor-pointer"><i
                                            class="bx bx-hide"></i></span>
                                    <span class="input-group-text cursor-pointer" id="generate-password"><i
                                            class="bx bxs-magic-wand"></i></span>
                                </div>
                            </div>
                            <div class="form-password-toggle mb-3">
                                <label for="password_confirmation"
                                    class="form-label"><?= get_label('confirm_password', 'Confirm password') ?> <span
                                        class="asterisk">*</span></label>
                                <div class="input-group input-group-merge">
                                    <input class="form-control" type="password" id="password_confirmation"
                                        name="password_confirmation"
                                        placeholder="<?= get_label('please_re_enter_password', 'Please re enter password') ?>"
                                        autocomplete="new-password">
                                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                </div>
                            </div>
                            @php
                                $settings = get_settings('general_settings');

                            @endphp
                            @if (!empty($settings['recaptcha_enabled']) && $settings['recaptcha_enabled'])
                                <div class="mb-5">
                                    <label class="form-label d-block">{{ get_label('captcha', 'Captcha') }} <span
                                            class="asterisk">*</span></label>
                                    <div class="d-flex justify-content-start">
                                        {!! NoCaptcha::display() !!}
                                    </div>
                                    @if ($errors->has('g-recaptcha-response'))
                                        <span class="text-danger small d-block mt-1">
                                            {{ $errors->first('g-recaptcha-response') }}
                                        </span>
                                    @endif
                                </div>
                            @endif
                            <button type="submit" id="submit_btn"
                                class="btn btn-primary d-grid w-100">{{ get_label('submit', 'Submit') }}</button>
                        </form>
                        <div class="text-center">
                            <a href="{{ url('') }}" class="d-flex align-items-center justify-content-center">
                                <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                                {{ get_label('back_to_login', 'Back to login') }}
                            </a>
                        </div>
                    </div>
                </div>
                <!-- /Forgot Password -->
            </div>
        </div>
    </div>
    <!-- / Content -->
    <!-- / Content -->
    {!! NoCaptcha::renderJs() !!}
    <script src="{{ asset('assets/js/pages/signup.js') }}"></script>
@endsection
