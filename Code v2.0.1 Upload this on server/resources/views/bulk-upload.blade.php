@extends('layout')
@section('title')
<?= get_label($entity . '_bulk_upload', ucfirst($entity) . ' Bulk Upload') ?>
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
                    <li class="breadcrumb-item">
                        <a href="{{ $entity === 'projects' || $entity === 'tasks' ? url(getUserPreferences($entity, 'default_view')) : url('/' . $entity) }}">
                            <?= get_label($entity, ucfirst($entity)) ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active">
                        <?= get_label('bulk_upload', 'Bulk Upload') ?>
                    </li>
                </ol>
            </nav>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="text-center mt-3">
                        <a href="{{ $sample_file_url }}" class="btn btn-success" download>
                            <i class="bx bx-download"></i> <?= get_label('download_sample_file', 'Download Sample File') ?>
                        </a>
                        <a href="{{ $help_url }}" class="btn btn-info" download>
                            <i class="bx bx-download"></i> <?= ($entity === 'projects' || $entity === 'tasks') ? get_label('instructions', 'Instructions') : get_label('help_instructions', 'Help & Instructions') ?>
                        </a>
                    </div>

                    <form class="form-horizontal form-submit-event mt-4" action="{{ $form_action }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="card-body">
                            <div class="dropzone dz-clickable bulk-upload-dropzone" id="bulk-upload-dropzone">
                            </div>
                            <div class="form-group mt-4 text-center">
                                <button class="btn btn-primary" type="submit" id="submit_btn"><?= get_label('upload', 'Upload') ?></button>
                            </div>
                            <div id="validation-errors" class="text-danger text-center mt-3"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection