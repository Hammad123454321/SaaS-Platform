@extends('layout')
@section('title', 'Purchase Code Validator')

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">

        {{-- Purchase Code Validator --}}
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-label-primary py-3">
                        <h5 class="text-primary fw-bold mb-0">
                            <i class="bx bx-check-shield me-2"></i>Purchase Code Validator
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('system.validate') }}" method="POST" class="form-submit-event" id="purchaseCodeForm">
                            @csrf
                            <input type="hidden" name="redirect_url" value="{{ url('/home') }}">
                            <div class="mb-3">
                                <label for="purchase_code" class="form-label fw-semibold">Enter Your CodeCanyon Purchase
                                    Code</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text"><i class="bx bx-key"></i></span>
                                    <input type="text" id="purchase_code" name="health_code" class="form-control"
                                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                                </div>
                                <div class="form-text text-muted">Format: 36 characters with dashes</div>
                            </div>
                            <button type="submit" id="submit_btn"class="btn btn-primary w-100">
                                <i class="bx bx-check-circle me-1"></i> Validate Code
                            </button>
                        </form>

                        {{-- Validation Result --}}
                        <div id="purchaseCodeResult" class="alert d-none mt-3"></div>
                    </div>
                </div>
            </div>
        </div>


        {{-- FAQ Section --}}
        <div class="mt-5">
            <h4 class="fw-bold mb-4"><i class="bx bx-help-circle me-2"></i>General FAQs</h4>
            <div class="accordion" id="faqAccordion">

                {{-- FAQ 1 --}}
                <div class="accordion-item mb-2 rounded border">
                    <h2 class="accordion-header" id="faq1">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faqCollapse1" aria-expanded="false" aria-controls="faqCollapse1">
                            Where can I find my purchase code?
                        </button>
                    </h2>
                    <div id="faqCollapse1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            Log in to your <a href="https://codecanyon.net/downloads" target="_blank"
                                class="link-primary">CodeCanyon account</a> → Downloads → Click on the product → Download
                            “License Certificate & Purchase Code”.
                            <br><br>
                            For more details, check <a
                                href="https://help.market.envato.com/hc/en-us/articles/202822600-Where-Is-My-Purchase-Code"
                                target="_blank" class="link-primary">Envato's official guide</a>.
                        </div>
                    </div>
                </div>

                {{-- FAQ 2 - License Comparison --}}
                <div class="accordion-item mb-2 rounded border">
                    <h2 class="accordion-header" id="faq2">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faqCollapse2" aria-expanded="false" aria-controls="faqCollapse2">
                            What’s the difference between Regular and Extended License?
                        </button>
                    </h2>
                    <div id="faqCollapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <div class="row g-4 text-center">
                                <div class="col-md-6 border-end">
                                    <h6 class="fw-bold text-muted">Regular License</h6>
                                    <ul class="list-unstyled mt-3 text-start">
                                        <li><i class="bx bx-x-circle text-danger me-2"></i> Admin Panel FREE Installation
                                        </li>
                                        <li><i class="bx bx-x-circle text-danger me-2"></i> Priority Support</li>
                                        <li><i class="bx bx-x-circle text-danger me-2"></i> AnyDesk Support</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-bold text-success">Extended License</h6>
                                    <ul class="list-unstyled mt-3 text-start">
                                        <li><i class="bx bx-check-circle text-success me-2"></i> Admin Panel FREE
                                            Installation</li>
                                        <li><i class="bx bx-check-circle text-success me-2"></i> Priority Support</li>
                                        <li><i class="bx bx-check-circle text-success me-2"></i> AnyDesk Support</li>
                                    </ul>

                                </div>
                            </div>
                            <br>
                            Read full license terms here: <a href="https://codecanyon.net/licenses/standard" target="_blank"
                                class="link-primary">Envato License Guide</a>
                        </div>
                    </div>
                </div>

                {{-- FAQ 3 --}}
                <div class="accordion-item rounded border">
                    <h2 class="accordion-header" id="faq3">
                        <button class="accordion-button collapsed fw-semibold" type="button" data-bs-toggle="collapse"
                            data-bs-target="#faqCollapse3" aria-expanded="false" aria-controls="faqCollapse3">
                            Can I use one purchase code for multiple domains?
                        </button>
                    </h2>
                    <div id="faqCollapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            No. Each domain installation requires its own valid purchase code unless you have an extended
                            license.
                            <br><br>
                            Check the full details on your product page:
                            <a href="https://codecanyon.net/item/taskify-project-management-task-management-productivity-tool/48903161?s_rank=8"
                                target="_blank" class="link-primary">
                                Taskify on CodeCanyon
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script src="{{ asset('assets/js/system-health.js') }}"></script>
@endsection
