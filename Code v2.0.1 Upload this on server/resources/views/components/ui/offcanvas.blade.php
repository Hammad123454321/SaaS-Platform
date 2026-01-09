@props([
    'id',
    'title' => 'Offcanvas Title',
    'size' => 'offcanvas-responsive', // Example: 'offcanvas-w-600', 'offcanvas-lg'
    'icon' => 'bx bx-menu', // Default icon
    'submitLabel' => 'Save',
    'submitIcon' => 'bx bx-check',
    'closeLabel' => 'Close',
    'closeIcon' => 'bx bx-x',
    'formId' => null,
    'formMethod' => 'POST',
    'formAction' => '#',
    'showFooter' => true,
])


<div class="offcanvas offcanvas-end {{ $size }}" tabindex="-1" id="{{ $id }}"
    aria-labelledby="{{ $id }}Label">
    <div class="offcanvas-header bg-dark">
        <h5 class="offcanvas-title text-white" id="{{ $id }}Label">
            @if ($icon)
                <i class="{{ $icon }} me-2"></i>
            @endif {{ $title }}
        </h5>
        <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body">
        <form class="new-form-submit-event" id="{{ $formId }}" action="{{ $formAction }}"
            method="{{ strtolower($formMethod) === 'get' ? 'GET' : 'POST' }}"
            @if (strtolower($formMethod) !== 'get') enctype="multipart/form-data" @endif>
            @csrf
            @if (!in_array(strtolower($formMethod), ['get', 'post']))
                @method($formMethod)
            @endif

            {{ $slot }}

            @if ($showFooter)
                <div class="d-flex justify-content-end mt-4 gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                        <i class="{{ $closeIcon }} me-1"></i>{{ $closeLabel }}
                    </button>
                    <button type="submit" id="submit_btn" class="btn btn-primary">
                        <i class="{{ $submitIcon }} me-1"></i>{{ $submitLabel }}
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
