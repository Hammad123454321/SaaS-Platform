@extends('layout')

@section('title')
<?= get_label('terms_privacy_about', 'Terms, Privacy & About') ?>
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between mb-2 mt-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item">
                        <a href="{{ route('home.index') }}"><?= get_label('home', 'Home') ?></a>
                    </li>
                    <li class="breadcrumb-item">
                        <?= get_label('settings', 'Settings') ?>
                    </li>

                    <li class="breadcrumb-item active">
                        <?= get_label('terms_privacy_about', 'Terms, Privacy & About') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="list-group list-group-horizontal-md text-md-center">
                <a class="list-group-item list-group-item-action active" data-bs-toggle="list" href="#privacy-policy"><?= get_label('privacy_policy', 'Privacy Policy') ?></a>
                <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#terms-conditions"><?= get_label('terms_conditions', 'Terms and Conditions') ?></a>
                <a class="list-group-item list-group-item-action" data-bs-toggle="list" href="#about-us"><?= get_label('about_us', 'About Us') ?></a>
            </div>
            <div class="tab-content px-0">
                <!-- Privacy Policy Tab -->
                <div class="tab-pane fade show active" id="privacy-policy">
                    <form action="{{ route('terms_privacy_about.store') }}" class="form-submit-event" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="dnr">
                        <input type="hidden" name="variable" value="privacy_policy">
                        @csrf
                        @method('PUT')
                        <textarea class="form-control" name="value" id="privacy_policy">
                @isset($privacy_policy['privacy_policy'])
                    {!! $privacy_policy['privacy_policy'] !!}
                @endisset
            </textarea>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary me-2" id="submit_btn"><?= get_label('update', 'Update') ?></button>
                            <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                        </div>
                    </form>
                </div>

                <!-- Terms and Conditions Tab -->
                <div class="tab-pane fade" id="terms-conditions">
                    <form action="{{ route('terms_privacy_about.store') }}" class="form-submit-event" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="dnr">
                        <input type="hidden" name="variable" value="terms_conditions">
                        @csrf
                        @method('PUT')
                        <textarea class="form-control" name="value" id="terms_conditions">
                @isset($terms_conditions['terms_conditions'])
                    {!! $terms_conditions['terms_conditions'] !!}
                @endisset
            </textarea>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary me-2" id="submit_btn"><?= get_label('update', 'Update') ?></button>
                            <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                        </div>
                    </form>
                </div>

                <!-- About Us Tab -->
                <div class="tab-pane fade" id="about-us">
                    <form action="{{ route('terms_privacy_about.store') }}" class="form-submit-event" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="dnr">
                        <input type="hidden" name="variable" value="about_us">
                        @csrf
                        @method('PUT')
                        <textarea class="form-control" name="value" id="about_us">
                @isset($about_us['about_us'])
                    {!! $about_us['about_us'] !!}
                @endisset
            </textarea>
                        <div class="mt-3">
                            <button type="submit" class="btn btn-primary me-2" id="submit_btn"><?= get_label('update', 'Update') ?></button>
                            <button type="reset" class="btn btn-outline-secondary"><?= get_label('cancel', 'Cancel') ?></button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
@endsection