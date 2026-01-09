<ul class="p-0 m-0">
    @foreach ($statusCounts as $statusId => $count)
    @php $status = $statuses->where('id', $statusId)->first(); @endphp
    <li class="d-flex mb-4 pb-1">
        <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary">
                <i class="bx bx-{{ $type === 'projects' ? 'briefcase-alt-2' : 'task' }} text-{{ $status->color }}"></i>
            </span>
        </div>
        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
            <div class="me-2">
                <a href="{{ url(getUserPreferences($type, 'default_view') . '?status=' . $status->id) }}">
                    <h6 class="mb-0">{{ $status->title }}</h6>
                </a>
            </div>
            <div class="user-progress">
                <small class="fw-semibold">{{ $count }}</small>
            </div>
        </div>
    </li>
    @endforeach
    <li class="d-flex mb-4 pb-1">
        <div class="avatar flex-shrink-0 me-3">
            <span class="avatar-initial rounded bg-label-primary"><i class="bx bx-menu"></i></span>
        </div>
        <div class="d-flex w-100 flex-wrap align-items-center justify-content-between gap-2">
            <div class="me-2">
                <h5 class="mb-0">{{ get_label('total', 'Total') }}</h5>
            </div>
            <div class="user-progress">
                <h5 class="mb-0">{{ $totalCount }}</h5>
            </div>
        </div>
    </li>
</ul>
