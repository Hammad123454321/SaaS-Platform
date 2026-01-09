@props(['title', 'icon' => null, 'cardId' => null, 'chartId' => null, 'header' => null])

<div class="card overflow-hidden mb-4 statisticsDiv" id="{{ $cardId ?? '' }}">
    <div class="card-header pt-3 pb-1">
        <div class="card-title d-flex justify-content-between align-items-center">
            <h5 class="m-0 me-2">{{ $title }}</h5>
            @isset($icon)
                <i class="{{ $icon }} bx-sm text-body me-4"></i>
            @endisset
        </div>

        @isset($chartId)
            <div class="my-3">
                <div id="{{ $chartId }}"></div>
            </div>
        @endisset

        {{ $header ?? '' }}
    </div>

    <div class="card-body">
        {{ $slot }}
    </div>

    @if($cardId == 'recent-activity')
        <div class="card-footer">
            <div class="text-start text-sm">
                <a href="{{ route('activity_log.index') }}" class="btn btn-outline-primary btn-sm mt-3">
                    {{ get_label('view_all', 'View All') }}
                </a>
            </div>
        </div>
    @endif
</div>
