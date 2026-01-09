{{-- Taskify Ecosystem Drawer Blade Component --}}
{{-- Usage: @include('components.taskify-ecosystem-drawer') --}}
@php
    $taskifyProducts = [
        [
            'name' => 'Taskify Core',
            'price' => "$39",
            'description' =>
                'Organize your workflow effortlessly by breaking projects into manageable tasks with clear deadlines, progress tracking, and team accountability tools.',
            'features' => ['CRM', 'Project & Task Management', 'Team Collaboration', 'Custom Workflows'],
            'badge' => ['text' => 'Most Popular', 'class' => 'bg-primary'],
            'icon' => 'bx bx-task text-primary',
            'button' => ['text' => 'Get Taskify', 'class' => 'btn btn-primary', 'icon' => 'bx bx-cart'],
            'color' => 'text-primary',
            'image_url' =>
                'http://market-resized.envatousercontent.com/codecanyon.net/files/642341747/Taskify%20regular%20promo.png?auto=format&q=94&cf_fit=crop&gravity=top&h=8000&w=590&s=4229edeb5163ddfe49cb801e8db755c5694db7e6371a1c611f155153ace54d6d',
            'product_url' =>
                'https://codecanyon.net/item/taskify-project-management-task-management-productivity-tool/48903161?s_rank=7',
        ],
        [
            'name' => 'Taskify SaaS',
            'price' => "$49",
            'description' =>
                'Launch your own SaaS business with Taskify, the robust, feature-rich project and task management tool designed for seamless scalability, white-labeling, and recurring revenue.',
            'features' => ['SaaS', 'CRM', 'Project & Task Management', 'White-label Options'],
            'badge' => ['text' => 'Enterprise', 'class' => 'bg-info'],
            'icon' => 'bx bx-cloud text-info',
            'button' => ['text' => 'Get Taskify SaaS', 'class' => 'btn btn-info', 'icon' => 'bx bx-play-circle'],
            'color' => 'text-info',
            'image_url' =>
                'https://market-resized.envatousercontent.com/codecanyon.net/files/642341776/taskify%20saas.png?auto=format&q=94&cf_fit=crop&gravity=top&h=8000&w=590&s=c66d177e341e5772e65e673c6eaeb63be9596ed729e09df9e437609ac855029c',
            'product_url' =>
                'https://codecanyon.net/item/taskify-saas-project-management-system-in-laravel/52126963?s_rank=6',
        ],
        [
            'name' => 'Taskify Flutter App',
            'price' => "$19",
            'description' =>
                'Take Taskify on the go with our Flutter app for Android and iOS, enabling mobile project and task management anytime, anywhere, with a clean, intuitive interface.',
            'features' => ['Android', 'iOS', 'Mobile App', 'Project & Task Management'],
            'badge' => ['text' => 'Mobile App', 'class' => 'bg-warning'],
            'icon' => 'bx bxl-flutter text-warning',
            'button' => [
                'text' => 'Get Taskify Flutter App',
                'class' => 'btn btn-warning',
                'icon' => 'bx bxl-play-store',
            ],
            'color' => 'text-warning',
            'image_url' =>
                'https://market-resized.envatousercontent.com/codecanyon.net/files/643134271/Group%201000007409.png?auto=format&q=94&cf_fit=crop&gravity=top&h=8000&w=590&s=3a1f29e3d42aea6397b6b6a6fd2faa2deb0c349ce588c6bfcedab24f8c66ac13',
            'product_url' =>
                'https://codecanyon.net/item/taskify-flutter-app-project-management-task-manager-and-productivity-tool/57033235?s_rank=2',
        ],
    ];
@endphp
<!-- Floating Action Button -->
<button type="button" class="taskify-fab btn btn-primary" data-bs-toggle="offcanvas"
    data-bs-target="#taskifyEcosystemDrawer" aria-controls="taskifyEcosystemDrawer">
    <i class="bx bx-store-alt fs-4" data-bs-toggle="tooltip" data-bs-placement="left"
        title="Discover the Taskify Ecosystem"></i>
</button>
<!-- Offcanvas Drawer -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="taskifyEcosystemDrawer"
    aria-labelledby="taskifyEcosystemDrawerLabel">
    <!-- Header -->
    <div class="offcanvas-header bg-primary text-white">
        <h5 class="offcanvas-title fw-bold text-white" id="taskifyEcosystemDrawerLabel">
            <i class="bx bx-store-alt me-2 text-white"></i>
            Discover the Taskify Ecosystem
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
            aria-label="Close"></button>
    </div>
    <!-- Body -->
    <div class="offcanvas-body p-0">
        <div class="p-4">
            @foreach ($taskifyProducts as $product)
                <div class="border-label-dark card taskify-product-card mb-4 border border-2 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2 flex-wrap gap-2">
                                    <h6 class="card-title fw-semibold mb-0">{{ $product['name'] }}</h6>
                                    @if (isset($product['badge']))
                                        <span class="badge {{ $product['badge']['class'] }} rounded-pill">
                                            {{ $product['badge']['text'] }}
                                        </span>
                                    @endif
                                </div>
                                <h4 class="{{ $product['color'] ?? 'text-primary' }} fw-bold mb-0">
                                    {{ $product['price'] }}
                                </h4>
                            </div>
                            <div class="ms-3">
                                <i class="{{ $product['icon'] ?? '' }} taskify-product-icon opacity-75"></i>
                            </div>
                        </div>
                        <div class="rounded-3 mb-3 overflow-hidden border">
                            <img src="{{ $product['image_url'] }}" alt="{{ $product['name'] }} Preview"
                                class="img-fluid w-100 d-block taskify-product-image">
                        </div>
                        <p class="card-text text-muted lh-base mb-3">
                            {{ $product['description'] }}
                        </p>
                        <div class="mb-4">
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($product['features'] as $feature)
                                    <span class="badge bg-light text-dark rounded-pill border px-3 py-2">
                                        <i class="bx bx-check-circle text-success me-1"></i>
                                        <small>{{ $feature }}</small>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="d-grid">
                            <a href="{{ $product['product_url'] }}" target="_blank" rel="noopener noreferrer"
                                class="{{ $product['button']['class'] }} btn text-decoration-none">
                                <i class="{{ $product['button']['icon'] }} me-2"></i>
                                {{ $product['button']['text'] }}
                                <i class="bx bx-external-link ms-2"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <!-- Footer CTA -->
        <div class="bg-light border-top mt-auto p-4">
            <div class="text-center">
                <div class="mb-3">
                    <i class="bx bx-support text-primary fs-1 mb-2"></i>
                    <h6 class="fw-semibold mb-2">Need Help Choosing?</h6>
                    <p class="text-muted small lh-base mb-0">
                        Get personalized recommendations based on your specific needs and requirements
                    </p>
                </div>
                <div class="d-grid d-sm-flex justify-content-sm-center gap-2">
                    <a href="https://wa.me/919227025305" target="_blank" rel="noopener noreferrer"
                        class="btn btn-success">
                        <i class="bx bxl-whatsapp me-2"></i> Contact Sales
                    </a>
                    <a href="https://teams.live.com/l/invite/FEADpduIPYZUtss7w4" target="_blank"
                        rel="noopener noreferrer" class="btn btn-outline-primary">
                        <i class="bx bx-chat me-2"></i>
                        Live Chat
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
