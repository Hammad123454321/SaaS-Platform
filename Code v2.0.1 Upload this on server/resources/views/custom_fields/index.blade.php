@extends('layout')

@section('title')
    <?= get_label('custom_fields', 'Custom Fields') ?>
@endsection
@php
    $visibleColumns = getUserPreferences('projects');
@endphp
@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ route('home.index') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item">
                            <?= get_label('settings', 'Settings') ?></a>
                        </li>
                        <li class="breadcrumb-item active"><?= get_label('custom_fields', 'Custom Fields') ?></li>
                    </ol>
                </nav>
            </div>
             <div>
                <a href="javascript:void(0);" data-bs-toggle="modal" data-bs-target="#add_field_modal"><button
                        type="button" class="btn btn-sm btn-primary action_create_items" data-bs-toggle="tooltip"
                        data-bs-placement="right"
                        data-bs-original-title=" <?= get_label('create_custom_field', 'Create Custom Field') ?>"><i
                            class="bx bx-plus"></i></button></a>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <div class="card">

                    <div class="card-body">
                        <div class="table-responsive text-nowrap">
                            <input type="hidden" id="data_type" value="settings/custom-fields">
                            <input type="hidden" id="data_table" value="table">

                        <table id="table" data-toggle="table" data-loading-template="loadingTemplate"
                            data-url="{{ route('custom_fields.list') }}" data-icons-prefix="bx" data-icons="icons"
                            data-show-refresh="true" data-total-field="total" data-trim-on-search="false"
                            data-data-field="rows" data-page-list="[5, 10, 20, 50, 100, 200]" data-search="true"
                            data-side-pagination="server" data-show-columns="true" data-pagination="true"
                            data-sort-name="id" data-sort-order="desc" data-mobile-responsive="true"
                            data-query-params="queryParams">
                            <thead>
                                <tr>
                                    <th data-checkbox="true"></th>
                                    <th data-field="module" data-sortable="true">{{ get_label('module','Module') }}</th>
                                    <th data-field="field_label" data-sortable="true">{{ get_label('field_label','Field Label') }}</th>
                                    <th data-field="field_type" data-sortable="true">{{ get_label('field_type','Field Type') }}</th>
                                    <th data-field="required" data-sortable="true">{{ get_label('required','Required') }}</th>
                                    <th data-field="visibility" data-sortable="true">{{ get_label('show_in_table','Show in Table') }}</th>
                                    <th data-field="actions" >{{ get_label('actions','Actions') }}</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ asset('assets/js/pages/custom-fields.js') }}"></script>
@endsection
