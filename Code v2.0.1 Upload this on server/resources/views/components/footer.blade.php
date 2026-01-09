<!-- Footer -->
<div id="section-not-to-print">
    <footer class="content-footer footer bg-footer-theme mt-4">
        <div class="container-fluid d-flex flex-wrap justify-content-between flex-md-row flex-column">
            <div class="mb-md-0 d-flex align-items-start justify-content-between">
                ©
                <script>
                    document.write(new Date().getFullYear());
                </script>
                , <?= $general_settings['footer_text'] ?>
                <p class="mx-4 fw-bolder">v{{get_current_version()}}</p>

                @if (config('constants.ALLOW_MODIFICATION') === 0)
                 <span class="badge bg-danger demo-mode">Demo mode</span>
                @endif
            </div>
        </div>
    </footer>
</div>
<!-- / Footer -->
