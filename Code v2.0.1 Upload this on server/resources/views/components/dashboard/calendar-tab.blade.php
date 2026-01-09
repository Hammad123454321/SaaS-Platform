@props(['alert' => '', 'alertUrl' => '', 'alertAction' => '', 'calendarId' => '', 'listComponent' => '', 'data' => []])

<ul class="nav nav-tabs justify-content-start gap-2 mb-3 rounded-pill" role="tablist">
    <li class="nav-item" role="presentation">
        <button
            type="button"
            class="nav-link active rounded-2 px-4 py-2 bg-primary text-white list-button"
            role="tab"
            data-bs-toggle="tab"
            data-bs-target="#{{ $calendarId }}-list"
            aria-controls="{{ $calendarId }}-list"
            aria-selected="true">
            {{ get_label('list', 'List') }}
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button
            type="button"
            class="nav-link rounded-2 px-4 py-2 calendar-button"
            role="tab"
            data-bs-toggle="tab"
            data-bs-target="#{{ $calendarId }}-calendar"
            aria-controls="{{ $calendarId }}-calendar"
            aria-selected="false">
            {{ get_label('calendar', 'Calendar') }}
        </button>
    </li>
</ul>

<div class="tab-content no-shadow p-0">
    <div class="tab-pane fade active show" id="{{ $calendarId }}-list" role="tabpanel">
        @if ($alert)
            <div class="alert alert-primary alert-dismissible" role="alert">
                {{ $alert }},
                <a href="{{ $alertUrl }}">{{ $alertAction }}</a>.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        <x-dynamic-component :component="$listComponent" :users="$data" />
    </div>
    <div class="tab-pane fade" id="{{ $calendarId }}-calendar" role="tabpanel">
        <div id="{{ $calendarId }}"></div>
    </div>
</div>
