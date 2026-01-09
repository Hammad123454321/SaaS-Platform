@extends('layout')
@section('title')
<?= get_label('not_authorized', 'Not authorized') ?>
@endsection
@section('content')
<div class="container-fluid">
    <div class="card text-center mt-4">
        <div class="card-body">
            <div class="misc-wrapper">
                <h2 class="mb-2 mx-2"><?= get_label('un_authorized_action', 'Unauthorized Action!') ?></h2>
                <p class="mb-4 mx-2"><?= get_label('verification_instructions', 'It looks like you\'re logged in as a different user. To verify the email associated with this link, please either:') ?></p>

                <p>&#8226; <?= get_label('log_out_and_try_again', 'Log out of your current account and try the verification link again, or') ?></p>
                <p>&#8226; <?= get_label('open_in_new_tab', 'Open the verification link in a new tab or an incognito window where no user is logged in.') ?></p>


                <div class="mt-3">
                    <img src="{{ asset('/storage/man-with-laptop-light.png') }}" alt="page-misc-error-light" width="500" class="img-fluid" data-app-dark-img="illustrations/page-misc-error-dark.png" data-app-light-img="illustrations/page-misc-error-light.png" />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection