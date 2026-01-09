@extends('layout')
@section('title')
    <?= get_label('security_settings', 'Security settings') ?>
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            <?= get_label('settings', 'Settings') ?>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= get_label('security', 'Security') ?>
                        </li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <form action="{{ url('settings/store_security') }}" class="form-submit-event" method="POST">
                    <input type="hidden" name="dnr">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-check-label"
                                for="allowSignup"><?= get_label('enable_disable_signup', 'Enable/Disable Signup') ?></label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('enable_disable_signup_info', 'If disabled, team member and client will not be able to create an account by themselves.') ?>"></i>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="allowSignup" name="allowSignup"
                                    @if (!isset($general_settings['allowSignup']) || $general_settings['allowSignup'] == 1) checked @endif>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="" class="form-label"><?= get_label('max_attempts', 'Max Attempts') ?> <small
                                    class="text-muted">(<?= get_label('max_attempts_info', 'Fill in if you want to set a limit; otherwise, leave it blank') ?>)</small></label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('max_attempts_info_1', 'The maximum number of login attempts allowed before the account is locked.') ?>"></i>
                            <input class="form-control" type="number" name="max_attempts" step="1" placeholder="5"
                                value="{{ $general_settings['max_attempts'] ?? 5 }}" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="" class="form-label"><?= get_label('lock_time', 'Lock Time (minutes)') ?>
                                <small
                                    class="text-muted">(<?= get_label('lock_time_info', 'This will not apply if Max Attempts is left blank') ?>)</small>
                            </label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('lock_time_info_1', 'The duration in minutes for which the account will be locked after exceeding the maximum login attempts.') ?>"></i>
                            <input class="form-control" type="number" name="lock_time" step="1" placeholder="1"
                                value="{{ $general_settings['lock_time'] ?? 1 }}" min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for=""
                                class="form-label"><?= get_label('allowed_max_upload_size_in_mb_default_512', 'Allowed Max Upload Size (MB) - Default: 512') ?></label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('allowed_max_upload_size_info', 'Also, set the `upload_max_filesize` and `post_max_size` PHP configurations on your server accordingly to ensure the maximum upload size works as expected.') ?>"></i>
                            <input class="form-control" type="number" name="allowed_max_upload_size" step="1"
                                placeholder="512"
                                value="{{ !isset($general_settings['allowed_max_upload_size']) ? '512' : $general_settings['allowed_max_upload_size'] }}"
                                min="1">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="max_files"
                                class="form-label"><?= get_label('max_files_allowed', 'Max Files Allowed') ?></label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('max_files_allowed_info', 'Set the maximum number of files that can be uploaded at a time. Also, set the `max_file_uploads` PHP configurations on your server accordingly to ensure the Max Files Allowed works as expected.') ?>"></i>
                            <input class="form-control" type="number" id="max_files" name="max_files" step="1"
                                placeholder="10"
                                value="{{ !isset($general_settings['max_files']) ? '10' : $general_settings['max_files'] }}"
                                min="1">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="allowed_file_types"
                                class="form-label"><?= get_label('allowed_file_types', 'Allowed File Types') ?></label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('allowed_file_types_info', 'Specify the file types allowed for upload, separated by commas. Default: .pdf, .doc, .docx, .png, .jpg, .xls, .xlsx, .zip, .rar, .txt.') ?>"></i>
                            <input class="form-control" type="text" id="allowed_file_types" name="allowed_file_types"
                                placeholder=".pdf, .doc, .docx, .png, .jpg, .xls, .xlsx, .zip, .rar, .txt"
                                value="{{ !isset($general_settings['allowed_file_types']) ? '.pdf, .doc, .docx, .png, .jpg, .xls, .xlsx, .zip, .rar, .txt' : $general_settings['allowed_file_types'] }}">
                        </div>
                        {{-- For reCAPATCHA --}}
                        <div class="col-md-12 mb-3">
                            <div class="alert alert-info">

                                    {!! get_label(
                                        'recaptcha_not_configured_info',
                                        'If Google reCAPTCHA is not configured Please generate your keys from the <a href="https://www.google.com/recaptcha/admin" target="_blank" rel="noopener noreferrer">Google reCAPTCHA Admin Console</a> or contact the admin.',
                                    ) !!}
                                </div>

                        </div>

                        <div class="col-md-12 mb-3">
                            <label class="form-check-label" for="recaptcha_enabled">
                                <?= get_label('enable_recaptcha', 'Enable Google reCAPTCHA') ?>
                            </label>
                            <i class='bx bx-info-circle text-primary' data-bs-toggle="tooltip" data-bs-placement="top"
                                title="<?= get_label('enable_recaptcha_info', 'If enabled, reCAPTCHA will be shown on login and signup pages.') ?>"></i>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="recaptcha_enabled"
                                    name="recaptcha_enabled" @if (!isset($general_settings['recaptcha_enabled']) || $general_settings['recaptcha_enabled'] == 1) checked @endif>
                            </div>
                        </div>



                        <div class="col-md-6 mb-3">
                            <label for="recaptcha_site_key"
                                class="form-label"><?= get_label('recaptcha_site_key', 'Google reCAPTCHA Site Key') ?></label>
                            <input class="form-control" type="text" id="recaptcha_site_key" name="recaptcha_site_key"
                                placeholder="Enter your site key"
                                value="{{ $general_settings['recaptcha_site_key'] ?? '' }}">
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="recaptcha_secret_key"
                                class="form-label"><?= get_label('recaptcha_secret_key', 'Google reCAPTCHA Secret Key') ?></label>
                            <input class="form-control" type="text" id="recaptcha_secret_key"
                                name="recaptcha_secret_key" placeholder="Enter your secret key"
                                value="{{ $general_settings['recaptcha_secret_key'] ?? '' }}">
                        </div>


                        <div class="mt-2">
                            <button type="submit" class="btn btn-primary me-2"
                                id="submit_btn"><?= get_label('update', 'Update') ?></button>
                            <button type="reset"
                                class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
