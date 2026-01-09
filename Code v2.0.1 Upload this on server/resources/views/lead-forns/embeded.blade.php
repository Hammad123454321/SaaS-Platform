@extends('layout')

@section('title')
    {{ get_label('embed_code', 'Embed Code') }}
@endsection

@section('content')
    <div class="container-fluid">
        <!-- Breadcrumb and Actions -->
        <div class="d-flex justify-content-between mb-2 mt-4">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb breadcrumb-style1">
                    <li class="breadcrumb-item"><a href="{{ url('home') }}">{{ get_label('home', 'Home') }}</a></li>
                    <li class="breadcrumb-item"><span>{{ get_label('leads_management', 'Leads Management') }}</span></li>
                    <li class="breadcrumb-item">{{ get_label('lead_forms', 'Lead Forms') }}</li>
                    <li class="breadcrumb-item active">{{ get_label('embed_code', 'Embed code') }}</li>
                </ol>
            </nav>
        </div>

        <!-- Embed Code Card -->
        <div class="card rounded-4 border-0 shadow">
            <div class="card-body p-4">
                <h4 class="fw-semibold mb-4">{{ get_label('embed_your_form', 'Embed Your Form') }}</h4>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="embedTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="iframe-tab" data-bs-toggle="tab" data-bs-target="#iframe-content" type="button" role="tab">
                            <i class="bx bx-code-alt me-2"></i>{{ get_label('iframe_embed', 'iFrame Embed') }}
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="widget-tab" data-bs-toggle="tab" data-bs-target="#modal-content" type="button" role="tab">
                            <i class="bx bx-widget me-2"></i>{{ get_label('floating_widget', 'Floating Widget') }}
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content" id="embedTabContent">
                    <!-- iFrame Embed -->
                    <div class="tab-pane fade show active" id="iframe-content" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="mb-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <label class="form-label fw-semibold mb-0">{{ get_label('iframe_embed_code', 'iFrame Embed Code') }}</label>
                                        <button class="btn btn-sm btn-primary" data-copy-target="iframeEmbedCode">
                                            <i class="bx bx-copy me-1"></i>{{ get_label('copy', 'Copy') }}
                                        </button>
                                    </div>
                                    <textarea class="form-control bg-light rounded-3 border" id="iframeEmbedCode" rows="6" readonly>{!! $leadForm->embed_code !!}</textarea>
                                    <small class="text-muted mt-2 d-block">{{ get_label('iframe_description', 'Copy and paste this code into your website to embed the form as an iframe.') }}</small>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-3">{{ get_label('preview', 'Preview') }}</label>
                                    <div class="rounded-3 overflow-hidden border bg-white" style="height: 400px;">
                                        {!! str_replace('<iframe', '<iframe style="width:100%; height:100%; border:0;"', $leadForm->embed_code) !!}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Floating Widget -->
                    <div class="tab-pane fade" id="modal-content" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-6">
                                <!-- HTML -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold text-danger mb-0"><i class="bx bxl-html5 me-1"></i>HTML Code</label>
                                        <button class="btn btn-sm btn-outline-danger" data-copy-target="floatingHtmlCode">
                                            <i class="bx bx-copy me-1"></i>{{ get_label('copy', 'Copy') }}
                                        </button>
                                    </div>
                                    <textarea class="form-control bg-light rounded-3 border" id="floatingHtmlCode" rows="8" readonly><!-- Floating Form Button -->
<div data-toggle-lead-form>
    <span><a href="javascript:void(0);"><img src="https://via.placeholder.com/60x60/007bff/ffffff?text=📝" class="lead-form-icon" alt="{{ $leadForm->title }}"></a></span>
</div>
<!-- Floating Form Container -->
<div id="leadFormContainer" class="lead-form-container">
    <div class="lead-form-header">
        <h5>{{ $leadForm->title }}</h5>
        <button class="close-btn" data-close-lead-form>&times;</button>
    </div>
    <div class="lead-form-body">
        {!! str_replace(['<iframe', '</iframe>'], ['<iframe class="lead-form-iframe"', '</iframe>'], $leadForm->embed_code) !!}
    </div>
</div></textarea>
                                </div>

                                <!-- CSS -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold text-info mb-0"><i class="bx bxl-css3 me-1"></i>CSS Code</label>
                                        <button class="btn btn-sm btn-outline-info" data-copy-target="floatingCssCode">
                                            <i class="bx bx-copy me-1"></i>{{ get_label('copy', 'Copy') }}
                                        </button>
                                    </div>
                                   <textarea class="form-control bg-light rounded-3 border" id="floatingCssCode" rows="12" readonly><style>
/* Floating Form Button */
.lead-form-icon {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.3);
    transition: all 0.3s ease;
    z-index: 1000;
}
.lead-form-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
}
.lead-form-container {
    position: fixed;
    bottom: 90px;
    right: 20px;
    width: 400px;
    height: 500px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    display: none;
    flex-direction: column;
    z-index: 1001;
    overflow: hidden;
}
.lead-form-container.active {
    display: flex;
}
.lead-form-header {
    padding: 15px 20px;
    background: #007bff;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.lead-form-header h5 {
    margin: 0;
    font-size: 16px;
}
.close-btn {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: background-color 0.2s;
}
.close-btn:hover {
    background-color: rgba(255, 255, 255, 0.2);
}
.lead-form-body {
    flex: 1;
    overflow: hidden;
}
.lead-form-iframe {
    width: 100%;
    height: 100%;
    border: none;
}
@media (max-width: 768px) {
    .lead-form-container {
        width: calc(100vw - 40px);
        height: 70vh;
        bottom: 90px;
        right: 20px;
        left: 20px;
    }
    .lead-form-icon {
        width: 50px;
        height: 50px;
        bottom: 15px;
        right: 15px;
    }
}
</style></textarea>

                                </div>

                                <!-- JavaScript -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-semibold text-warning mb-0"><i class="bx bxl-javascript me-1"></i>JavaScript Code</label>
                                        <button class="btn btn-sm btn-outline-warning" data-copy-target="floatingJsCode">
                                            <i class="bx bx-copy me-1"></i>{{ get_label('copy', 'Copy') }}
                                        </button>
                                    </div>
                                    <textarea class="form-control bg-light rounded-3 border" id="floatingJsCode" rows="8" readonly><script>
function toggleLeadForm() {
    const container = document.getElementById('leadFormContainer');
    container.classList.toggle('active');
}
document.addEventListener('click', function(event) {
    const container = document.getElementById('leadFormContainer');
    const icon = document.querySelector('.lead-form-icon');
    if (container && container.classList.contains('active') && !container.contains(event.target) && !icon.contains(event.target)) {
        container.classList.remove('active');
    }
});
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        document.getElementById('leadFormContainer')?.classList.remove('active');
    }
});
</script></textarea>
                                </div>

                                <div class="text-center">
                                    <button class="btn btn-success" id="copyAllFloatingCodeBtn">
                                        <i class="bx bx-copy-alt me-2"></i>{{ get_label('copy_all_code', 'Copy All Code') }}
                                    </button>
                                </div>
                            </div>

                            <!-- Widget Description & Preview Button -->
                            <div class="col-lg-6">
                                <div class="mb-4">
                                    <label class="form-label fw-semibold mb-3">{{ get_label('preview', 'Preview') }}</label>
                                    <div class="mb-3 text-center">
                                        <button type="button" class="btn btn-primary" onclick="togglePreviewLeadForm()">
                                            <i class="bx bx-show me-2"></i>{{ get_label('preview_widget', 'Preview Widget') }}
                                        </button>
                                    </div>
                                    <div class="alert alert-info">
                                        <i class="bx bx-info-circle me-2"></i>
                                        <strong>{{ get_label('widget_info', 'Widget Information') }}:</strong><br>
                                        {{ get_label('widget_description', 'This creates a floating button (like a chat widget) that toggles a form container.') }}
                                    </div>
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">{{ get_label('features', 'Features') }}:</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><i class="bx bx-check text-success me-2"></i>{{ get_label('floating_button', 'Floating Button') }}</li>
                                                <li><i class="bx bx-check text-success me-2"></i>{{ get_label('responsive_design', 'Responsive Design') }}</li>
                                                <li><i class="bx bx-check text-success me-2"></i>{{ get_label('click_outside_close', 'Click Outside to Close') }}</li>
                                                <li><i class="bx bx-check text-success me-2"></i>{{ get_label('escape_key_close', 'Escape Key to Close') }}</li>
                                                <li><i class="bx bx-check text-success me-2"></i>{{ get_label('customizable_icon', 'Customizable Icon') }}</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Live Widget Preview Section -->
                        <div class="mt-5">
                            <div class="card border-0 shadow-sm rounded-4">
                                <div class="card-header bg-primary text-white rounded-top-4">
                                    <h5 class="mb-0">{{ get_label('widget_live_preview', 'Live Widget Preview') }}</h5>
                                </div>
                                <div class="card-body position-relative bg-white" style="min-height: 200px;">
                                    <div class="position-relative" onclick="togglePreviewLeadForm()">
                                        <img src="{{ asset('assets/img/form.png') }}" class="lead-form-icon" alt="{{ $leadForm->title }}" />
                                    </div>
                                    <div id="previewLeadFormContainer" class="lead-form-container mt-3" style="display: none;">
                                        <div class="lead-form-header">
                                            <h5>{{ $leadForm->title }}</h5>
                                            <button class="close-btn" onclick="togglePreviewLeadForm()">&times;</button>
                                        </div>
                                        <div class="lead-form-body">
                                            {!! str_replace(['<iframe', '</iframe>'], ['<iframe class="lead-form-iframe"', '</iframe>'], $leadForm->embed_code) !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div> <!-- end tab-content -->
            </div>
        </div>
    </div>

    <!-- Script for Preview Widget -->
    <script>
        function togglePreviewLeadForm() {
            const container = document.getElementById('previewLeadFormContainer');
            if (container.style.display === 'none' || !container.style.display) {
                container.style.display = 'flex';
            } else {
                container.style.display = 'none';
            }
        }
    </script>
@endsection
