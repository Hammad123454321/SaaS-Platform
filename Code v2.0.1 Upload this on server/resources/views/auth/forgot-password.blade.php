@extends('layout')
<title>{{get_label('forgot_password','Forgot Password')}} - {{$general_settings['company_title']}}</title>
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
                                <img src="{{asset($general_settings['full_logo'])}}" width="300px"/>
                            </span>
                            <!-- <span class="app-brand-text demo menu-text fw-bolder ms-2">Taskify</span> -->
                        </a>
                    </div>
                    <!-- /Logo -->
                    <h4 class="mb-2">{{get_label('forgot_password','Forgot Password')}}? 🔒</h4>
                    <p>{{get_label('forgot_password_info','Enter your email and we\'ll send you password reset link')}}</p>
                    <form id="formAuthentication" class="mb-3 form-submit-event" action="{{url('forgot-password-mail')}}" method="POST">
                        <input type="hidden" name="dnr">
                        @csrf
                        <div class="mb-3">
                            <div class="btn-group btn-group d-flex justify-content-center" role="group" aria-label="Basic radio toggle button group">
                                <input type="radio" class="btn-check" id="account_type_user" name="account_type" value="user" checked>
                                <label class="btn btn-outline-primary" for="account_type_user"><?= get_label('user_account', 'User Account') ?></label>
                                <input type="radio" class="btn-check" id="account_type_client" name="account_type" value="client">
                                <label class="btn btn-outline-primary" for="account_type_client"><?= get_label('client_account', 'Client Account') ?></label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">{{get_label('email','Email')}} <span class="asterisk">*</span></label>
                            <input type="text" class="form-control" id="email" name="email" placeholder="<?= get_label('please_enter_email', 'Please enter email') ?>" value="{{ old('email') }}" autofocus />
                        </div>
                                                <button type="submit" id="submit_btn" class="btn btn-primary d-grid w-100">{{get_label('submit','Submit')}}</button>
                    </form>
                    <div class="text-center">
                        <a href="{{url('')}}" class="d-flex align-items-center justify-content-center">
                            <i class="bx bx-chevron-left scaleX-n1-rtl bx-sm"></i>
                            Back to login
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