@extends('layout')
<title>{{get_label('reset_password','Reset Password')}} - {{$general_settings['company_title']}}</title>
@section('content')
<!-- Content -->
<div class="container-fluid">
    <div class="authentication-wrapper authentication-basic container-p-y">
        <div class="authentication-inner py-4">
            <!-- Forgot Password -->
            <div class="card">
                <div class="card-body">
                    <!-- Logo -->
                    <div class="app-brand justify-content-center">
                        <a href="{{ url('home') }}" class="app-brand-link">
                            <span class="app-brand-logo demo">
                                <img src="{{asset($general_settings['full_logo'])}}" width="300px" alt="" />
                            </span>
                            <!-- <span class="app-brand-text demo menu-text fw-bolder ms-2">Taskify</span> -->
                        </a>
                    </div>
                    <!-- /Logo -->
                    <h4 class="mb-2">{{get_label('reset_password','Reset Password')}} 🔒</h4>
                    <p class="mb-4">{{get_label('reset_password_info','Enter details and hit submit to reset your password')}}</p>
                    <form id="formAuthentication" class="mb-3 form-submit-event" action="{{url('reset-password')}}" method="POST">
                        <input type="hidden" name="token" value="{{ request()->route('token') }}">
                        <input type="hidden" name="account_type" value="{{ request()->query('account_type') }}">
                        <input type="hidden" name="redirect_url" value="{{ url('/') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">{{get_label('email','Email')}} <span class="asterisk">*</span></label>
                            <input type="text" class="form-control" id="email" name="email" placeholder="{{get_label('please_enter_email', 'Please enter email')}}" value="{{ request()->query('email') }}" readonly />
                        </div>
                        <div class="form-password-toggle mb-3">
                            <label for="" class="form-label">{{get_label('new_password','New password')}} <span class="asterisk">*</span></label>
                            <div class="input-group input-group-merge">
                                <input type="password" class="form-control" id="password" name="password" placeholder="{{get_label('please_enter_new_password', 'Please enter new password')}}" autofocus />
                                <span class="input-group-text cursor-pointer toggle-password"><i class="bx bx-hide"></i></span>
                                <span class="input-group-text cursor-pointer" id="generate-password"><i class="bx bxs-magic-wand"></i></span>
                            </div>
                        </div>

                        <div class="form-password-toggle mb-3">
                            <label for="" class="form-label">{{get_label('confirm_new_password','Confirm new password')}} <span class="asterisk">*</span></label>
                            <div class="input-group input-group-merge">
                                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" placeholder="{{get_label('please_enter_confirm_new_password', 'Please enter confirm new password')}}" />
                                <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                            </div>
                        </div>
                        <button class="btn btn-primary d-grid w-100" id="submit_btn">{{get_label('submit', 'Submit')}}</button>
                    </form>
                    <div class="text-center">
                        <a href="{{url('forgot-password')}}" class="d-flex align-items-center justify-content-center">
                            <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                            {{get_label('back_to_forgot_password', 'Back to forgot password')}}
                        </a>
                    </div>
                </div>
            </div>
            <!-- /Forgot Password -->
        </div>
    </div>
</div>
<!-- / Content -->
@endsection