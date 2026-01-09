@props(['active' => false, 'icon' => '', 'label' => '', 'target' => ''])

<li class="nav-item">
    <button
        type="button"
        class="nav-link parent-link {{ $active ? 'active' : '' }}"
        role="tab"
        data-bs-toggle="tab"
        data-bs-target="#{{ $target }}"
        aria-controls="{{ $target }}"
        aria-selected="{{ $active ? 'true' : 'false' }}">
        @if ($icon)
            <i class="menu-icon tf-icons {{ $icon }}"></i>
        @endif
        {{ $label }}
    </button>
</li>
