@props(['active' => false, 'id' => ''])

<div
    class="tab-pane fade {{ $active ? 'active show' : '' }}"
    id="{{ $id }}"
    role="tabpanel">
    {{ $slot }}
</div>
