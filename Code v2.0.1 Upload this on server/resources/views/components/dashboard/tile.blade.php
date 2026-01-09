<div class="col-lg-3 col-md-6 col-6 mb-4">
    <div class="card border-0 shadow-sm">
        <div class="card-body d-flex align-items-start justify-content-between">
            <!-- Text Content -->
            <div>
                <h6 class="mb-1">{{ $label }}</h6>
                <h3 class="fw-bold my-2">{{ $count }}</h3>
                <a href="{{ $url }}" class="text-decoration-none {{ $linkColor }}">
                    <small><i class="bx bx-right-arrow-alt"></i> {{ __('View more') }}</small>
                </a>
            </div>
            <!-- Icon Wrapper -->
            <div class="avatar">
                <span class="avatar-initial rounded {{ $iconBg }}">
                    <i class="icon-base bx-sm {{ $icon }}"></i>
                </span>
            </div>
        </div>
    </div>
</div>
