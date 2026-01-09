// --- Floating Widget Preview Button Logic ---
let previewWidgetActive = false;

function previewFloatingWidget(btn) {
    const previewWidget = document.getElementById('previewFloatingWidget');
    const previewBtn = btn ? btn : document.getElementById('previewWidgetBtn');
    if (!previewWidgetActive) {
        previewWidget.style.display = 'block';
        previewBtn.innerHTML = '<i class="bx bx-hide me-2"></i>Hide Preview';
        previewBtn.classList.remove('btn-primary');
        previewBtn.classList.add('btn-secondary');
        previewWidgetActive = true;
    } else {
        previewWidget.style.display = 'none';
        previewBtn.innerHTML = '<i class="bx bx-show me-2"></i>Preview Widget';
        previewBtn.classList.remove('btn-secondary');
        previewBtn.classList.add('btn-primary');
        // Close the form if it's open
        const container = document.getElementById('previewLeadFormContainer');
        if (container) {
            container.classList.remove('active');
        }
        previewWidgetActive = false;
    }
}

// --- Preview Floating Widget Toggle ---
function togglePreviewLeadForm() {
    const container = document.getElementById('previewLeadFormContainer');
    if (container) {
        container.classList.toggle('active');
    }
}

// --- Floating Widget Button Logic ---
function toggleLeadForm() {
    const container = document.getElementById('leadFormContainer');
    if (container.classList.contains('active')) {
        container.classList.remove('active');
    } else {
        container.classList.add('active');
    }
}

// --- Click Outside to Close ---
document.addEventListener('click', function (event) {
    const container = document.getElementById('leadFormContainer');
    const icon = document.querySelector('.lead-form-icon');
    if (container && container.classList.contains('active')) {
        if (!container.contains(event.target) && (!icon || !icon.contains(event.target))) {
            container.classList.remove('active');
        }
    }
    // For preview widget
    const previewContainer = document.getElementById('previewLeadFormContainer');
    const previewWidget = document.getElementById('previewFloatingWidget');
    if (previewWidget && previewContainer && previewContainer.classList.contains('active')) {
        if (!previewContainer.contains(event.target) && !previewWidget.contains(event.target)) {
            previewContainer.classList.remove('active');
        }
    }
});

// --- Escape Key to Close ---
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        const container = document.getElementById('leadFormContainer');
        if (container && container.classList.contains('active')) {
            container.classList.remove('active');
        }
        const previewContainer = document.getElementById('previewLeadFormContainer');
        if (previewContainer && previewContainer.classList.contains('active')) {
            previewContainer.classList.remove('active');
        }
    }
});

// --- Copy Code Functions ---
function copyToClipboard(elementId, button) {
    const textarea = document.getElementById(elementId);
    textarea.select();
    textarea.setSelectionRange(0, 99999); // For mobile
    navigator.clipboard.writeText(textarea.value).then(function () {
        // Success UI
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="bx bx-check me-1"></i>Copied';
        button.classList.add('btn-success');
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('btn-success');
        }, 2000);
    }).catch(function (err) {
        // Fallback for old browsers
        textarea.select();
        document.execCommand('copy');
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="bx bx-check me-1"></i>Copied';
        button.classList.add('btn-success');
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('btn-success');
        }, 2000);
    });
}

function copyAllFloatingCode() {
    const htmlCode = document.getElementById('floatingHtmlCode').value;
    const cssCode = document.getElementById('floatingCssCode').value;
    const jsCode = document.getElementById('floatingJsCode').value;
    const allCode = `${htmlCode}\n\n${cssCode}\n\n${jsCode}`;
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    navigator.clipboard.writeText(allCode).then(function () {
        button.innerHTML = '<i class="bx bx-check me-2"></i>All Code Copied';
        button.classList.add('btn-success');
        setTimeout(() => {
            button.innerHTML = originalContent;
            button.classList.remove('btn-success');
        }, 2000);
    }).catch(function (err) {
        console.error('Failed to copy all code: ', err);
    });
}

// --- Bootstrap Tooltips (optional) ---
document.addEventListener('DOMContentLoaded', function () {
    if (typeof bootstrap !== 'undefined') {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});
