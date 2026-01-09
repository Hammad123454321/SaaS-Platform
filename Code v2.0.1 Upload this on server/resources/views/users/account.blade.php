@extends('layout')
@section('title')
<?= get_label('update_profile', 'Update profile') ?>
@endsection
@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{url('home')}}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item active">
                        <?= get_label('profile', 'Profile') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <h5 class="card-header"><?= get_label('profile_details', 'Profile details') ?></h5>
                <!-- Account -->
                <div class="card-body">
                    <form action="{{url('profile/update_photo')}}" class="form-submit-event" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="redirect_url" value="{{ url('/account/' . $user->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="d-flex align-items-start align-items-sm-center gap-4">
                            <img src="{{$user->photo ? asset('storage/' . $user->photo) : asset('storage/photos/no-image.jpg')}}" alt="user-avatar" class="d-block rounded" height="100" width="100" id="uploadedAvatar" />
                            <div class="button-wrapper">
                                <div class="input-group d-flex">
                                    <input type="file" class="form-control" id="inputGroupFile02" name="upload" accept="image/*">
                                    <button class="btn btn-outline-primary" type="submit" id="submit_btn"><?= get_label('update_profile_photo', 'Update profile photo') ?></button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <hr class="my-0" />
                <div class="card-body">
                    <form id="formAccountSettings" method="POST" class="form-submit-event" action="{{ url('/profile/update/' . $user->id) }}">
                        <input type="hidden" name="redirect_url" value="{{ url('/account/' . $user->id) }}">
                        @csrf
                        @method('PUT')
                        <div class="row">
                            <div class="mb-3 col-md-6">
                                <label for="firstName" class="form-label"><?= get_label('first_name', 'First name') ?> <span class="asterisk">*</span></label>
                                <input class="form-control" type="text" id="first_name" name="first_name" placeholder="<?= get_label('please_enter_first_name', 'Please enter first name') ?>" value="{{ $user->first_name }}" autofocus />
                            </div>
                            <div class="mb-3 col-md-6">
                                <label for="lastName" class="form-label"><?= get_label('last_name', 'Last name') ?> <span class="asterisk">*</span></label>
                                <input class="form-control" type="text" name="last_name" placeholder="<?= get_label('please_enter_last_name', 'Please enter last name') ?>" id="last_name" value="{{$user->last_name}}" />
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label">{{ get_label('country_code_and_phone_number', 'Country code and phone number') }}</label>
                                <div class="input-group">
                                    <input type="tel" name="phone" id="phone" class="form-control" value="{{ $user->phone }}">
                                    <span class="clear-input">×</span>
                                </div>
                                <input type="hidden" name="country_code" id="country_code" value="{{ $user->country_code }}">
                                <input type="hidden" name="country_iso_code" id="country_iso_code" value="{{ $user->country_iso_code }}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="email"><?= get_label('email', 'E-mail') ?> <span class="asterisk">*</span></label>
                                <input class="form-control" type="text" name="email" placeholder="<?= get_label('please_enter_email', 'Please enter email') ?>" value="{{$user->email}}" @if(!isAdminOrHasAllDataAccess()) readonly @endif>
                            </div>
                            <div class="mb-3 col-md-6 form-password-toggle">
                                <label for="password" class="form-label"><?= get_label('password', 'Password') ?> <small class="text-muted"> ({{get_label('leave_blank_if_no_change', 'Leave it blank if no change')}})</small></label>
                                <div class="input-group input-group-merge">
                                    <input class="form-control" type="password" id="password" name="password" placeholder="<?= get_label('please_enter_password', 'Please enter password') ?>" autocomplete="new-password">
                                    <span class="input-group-text cursor-pointer toggle-password"><i class="bx bx-hide"></i></span>
                                    <span class="input-group-text cursor-pointer" id="generate-password"><i class="bx bxs-magic-wand"></i></span>
                                </div>
                            </div>
                            <div class="mb-3 col-md-6 form-password-toggle">
                                <label for="password_confirmation" class="form-label"><?= get_label('confirm_password', 'Confirm password') ?></label>
                                <div class="input-group input-group-merge">
                                    <input class="form-control" type="password" id="password_confirmation" name="password_confirmation" placeholder="<?= get_label('please_re_enter_password', 'Please re enter password') ?>" autocomplete="new-password">
                                    <span class="input-group-text cursor-pointer"><i class="bx bx-hide"></i></span>
                                </div>
                            </div>
                            @if($user->getRoleNames()->first() == 'admin')
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="role"><?= get_label('role', 'Role') ?> <span class="asterisk">*</span></label>
                                <select class="form-select text-capitalize" id="role" name="role">
                                    @foreach ($roles as $role)
                                    <option value="{{$role->id}}" <?php if ($user->getRoleNames()->first() == $role->name) {
                                                                        echo 'selected';
                                                                    }  ?>>{{ucfirst($role->name)}}</option>
                                    @endforeach
                                </select>
                            </div>
                            @else
                            <div class="mb-3 col-md-6">
                                <input type="hidden" name="role" value="<?= $user->roles->pluck('id')[0] ?>">
                                <label class="form-label" for="role"><?= get_label('role', 'Role') ?> <span class="asterisk">*</span></label>
                                <input class="form-control" type="text" id="exampleFormControlReadOnlyInput1" value="{{ucfirst($user->getRoleNames()->first())}}" readonly="">
                            </div>
                            @endif
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="address"><?= get_label('address', 'Address') ?></label>
                                <input class="form-control" type="text" id="address" placeholder="<?= get_label('please_enter_address', 'Please enter address') ?>" name="address" value="{{$user->address}}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="city"><?= get_label('city', 'City') ?></label>
                                <input class="form-control" type="text" id="city" placeholder="<?= get_label('please_enter_city', 'Please enter city') ?>" name="city" value="{{$user->city}}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="state"><?= get_label('state', 'State') ?></label>
                                <input class="form-control" type="text" id="state" placeholder="<?= get_label('please_enter_state', 'Please enter state') ?>" name="state" value="{{$user->state}}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="country"><?= get_label('country', 'Country') ?></label>
                                <input class="form-control" type="text" id="country" placeholder="<?= get_label('please_enter_country', 'Please enter country') ?>" name="country" value="{{$user->country}}">
                            </div>
                            <div class="mb-3 col-md-6">
                                <label class="form-label" for="zip"><?= get_label('zip_code', 'ZIP code') ?></label>
                                <input class="form-control" type="text" id="zip" placeholder="<?= get_label('please_enter_zip_code', 'Please enter ZIP code') ?>" name="zip" value="{{$user->zip}}">
                            </div>
                            <div class="mt-2">
                                <button type="submit" id="submit_btn" class="btn btn-primary me-2"><?= get_label('update', 'Update') ?></button>
                                <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                            </div>
                    </form>
                </div>
                <!-- /Account -->
            </div>
            @if((getGuardName() == 'client') || (getGuardName() == 'web' && $user->id != getMainAdminId()))
            <div class="card">
                <h5 class="card-header"><?= get_label('delete_account', 'Delete account') ?></h5>
                <div class="card-body">
                    <div class="mb-3 col-12 mb-0">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading fw-bold mb-1"><?= get_label('delete_account_alert', 'Are you sure you want to delete your account?') ?></h6>
                            <p class="mb-0"><?= get_label('delete_account_alert_sub_text', 'Once you delete your account, there is no going back. Please be certain.') ?></p>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-danger" id="deleteAccount"><?= get_label('delete_account', 'Delete account') ?></button>
                </div>
            </div>
            @endif

        </div>
    </div>
</div>
@endsection
