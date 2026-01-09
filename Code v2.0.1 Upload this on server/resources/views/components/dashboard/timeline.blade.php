<ul class="timeline mb-0">
    @forelse ($activities as $activity)
    <li class="timeline-item timeline-item-transparent">
        <span class="timeline-point
            @switch($activity->activity)
                @case('created') timeline-point-success @break
                @case('updated') timeline-point-info @break
                @case('deleted') timeline-point-danger @break
                @case('updated status') timeline-point-warning @break
                @default timeline-point-primary
            @endswitch">
        </span>
        <div class="timeline-event">
            <div class="timeline-header d-flex justify-content-between align-items-center">
                <h6 class="fw-semibold mb-1">{{ $activity->message }}</h6>
                <small class="text-muted">{{ $activity->created_at->diffForHumans() }}</small>
            </div>
            <div class="timeline-body">
                <p class="text-muted">{{ format_date($activity->created_at, true) }}</p>
            </div>
        </div>
    </li>
    @empty
    <li class="timeline-item timeline-item-transparent text-center">
        <span class="timeline-point timeline-point-primary"></span>
        <div class="timeline-event">
            <div class="timeline-header">
                <h6 class="text-muted mb-0">{{ get_label('no_activities', 'No recent activities') }}</h6>
            </div>
        </div>
    </li>
    @endforelse
</ul>

