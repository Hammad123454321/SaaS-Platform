@extends('layout')
@section('title')
    <?= get_label('notes', 'Notes') ?>
@endsection
@section('content')
    <!-- Add this in your head section -->
    <!-- js-draw Styles -->


    <div class="container-fluid">
        <div class="d-flex justify-content-between mb-2 mt-4">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-style1">
                        <li class="breadcrumb-item">
                            <a href="{{ url('/home') }}"><?= get_label('home', 'Home') ?></a>
                        </li>
                        <li class="breadcrumb-item active">
                            <?= get_label('notes', 'Notes') ?>
                        </li>
                    </ol>
                </nav>
            </div>
            <div>
                <span data-bs-toggle="modal" data-bs-target="#create_note_modal">
                    <a href="javascript:void(0);" class="btn btn-sm btn-primary" data-bs-toggle="tooltip"
                        data-bs-placement="left" data-bs-original-title="<?= get_label('create_note', 'Create note') ?>">
                        <i class='bx bx-plus'></i>
                    </a>
                </span>
            </div>
        </div>
        @if ($notes->count() > 0)
            <div class="card">
                <div class="card-body">
                    <button type="button" id="delete-selected" class="btn btn-outline-danger mx-4" data-type="notes">
                        <i class="bx bx-trash"></i> {{ get_label('delete_selected', 'Delete Selected') }}
                    </button>
                    <div class="form-check mx-4 mt-3">
                        <input type="checkbox" id="select-all" class="form-check-input">
                        <label for="select-all" class="form-check-label">{{ get_label('select_all', 'Select All') }}</label>
                    </div>
                    <div class="row sticky-notes mt-3">
                        @foreach ($notes as $note)
                            <div class="col-md-4 sticky-note">
                                <div class="sticky-content sticky-note-bg-<?= $note->color ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <input type="checkbox" class="selected-items mx-2 ms-0"
                                                value="{{ $note->id }}">
                                            <span class="note-id">#{{ $note->id }}</span>
                                        </div>
                                        <div class="text-end">
                                            <a href="javascript:void(0);" class="btn btn-primary btn-xs edit-note"
                                                data-id="{{ $note->id }}" data-bs-toggle="tooltip"
                                                data-bs-placement="left"
                                                data-bs-original-title="{{ get_label('update', 'Update') }}">
                                                <i class="bx bx-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" class="btn btn-danger btn-xs delete mx-1"
                                                data-id="{{ $note->id }}" data-type="notes" data-reload="true"
                                                data-bs-toggle="tooltip" data-bs-placement="left"
                                                data-bs-original-title="{{ get_label('delete', 'Delete') }}">
                                                <i class="bx bx-trash"></i>
                                            </a>
                                        </div>
                                    </div>

                                    <h4><?= $note->title ?></h4>
                                    @if ($note->note_type == 'text')
                                        <p><?= $note->description ?></p>
                                    @else
                                        {!! $note->drawing_data !!}
                                    @endif
                                    <b><?= get_label('created_at', 'Created at') ?> : </b><span
                                        class="text-primary">{{ format_date($note->created_at, true) }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            <?php
            $type = 'Notes';
            ?>
            <x-empty-state-card :type="$type" />
        @endif
    </div>
    <script src="{{ asset('assets/js/pages/notes.js') }}"></script>
    <style>
        .imageEditorContainer {
            /* Deafult colors for the editor -- light mode */

            /* Used for unselected buttons and dialog text. */
            --background-color-1: white;
            --foreground-color-1: black;

            /* Used for some menu/toolbar backgrounds. */
            --background-color-2: #f5f5f5;
            --foreground-color-2: #2c303a;

            /* Used for other menu/toolbar backgrounds. */
            --background-color-3: #e5e5e5;
            --foreground-color-3: #1c202a;

            /* Used for selected buttons. */
            --selection-background-color: #cbdaf1;
            --selection-foreground-color: #2c303a;

            /* Used for dialog backgrounds */
            --background-color-transparent: rgba(105, 100, 100, 0.5);

            /* Used for shadows */
            --shadow-color: rgba(0, 0, 0, 0.5);

            /* Color used for some button/input foregrounds */
            --primary-action-foreground-color: #15b;
        }

        @media (prefers-color-scheme: dark) {
            .imageEditorContainer {
                /* Default colors for the editor -- dark mode */
                --background-color-1: #ffffff;
                --foreground-color-1: rgb(0, 0, 0);

                --background-color-2: #222;
                --foreground-color-2: #efefef;

                --background-color-3: #272627;
                --foreground-color-3: #eee;

                --selection-background-color: #607;
                --selection-foreground-color: white;
                --shadow-color: rgba(250, 250, 250, 0.5);
                --background-color-transparent: rgba(50, 50, 50, 0.5);

                --primary-action-foreground-color: #7ae;
            }
        }

        #clr-picker {
            z-index: 1092 !important;
        }

        .toolbar--pen-tool-toggle-buttons {
            display: none !important;
        }

        .toolbar-help-overlay-button {
            display: none !important;
        }

        .pipetteButton {
            display: none !important;
        }

        .sticky-content svg {
            max-width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .sticky-content {
            overflow: hidden;
            /* Prevents drawing overflow */
            padding: 10px;
            /* Optional: adds spacing around drawing */
        }
    </style>
@endsection
