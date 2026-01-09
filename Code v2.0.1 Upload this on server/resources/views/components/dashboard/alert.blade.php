<div role="alert" class="alert alert-{{ $type ?? 'info' }} {{ $classes ?? '' }} alert-dismissible">
    <span class="fw-semibold">
        <i class="{{ $icon ?? 'bx bx-info-circle' }} me-2 bx-md"></i>
        {{ $message }}
    </span>
    @if ($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
