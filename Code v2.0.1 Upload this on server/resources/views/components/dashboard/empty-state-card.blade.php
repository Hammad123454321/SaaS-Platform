@props(['type' => 'Entity'])

<div class="text-center mt-4">
    <h5 class="text-muted">{{ get_label(strtolower($type) . '_not_found', $type . ' not found!') }}</h5>
</div>
