class LabelSaver {
    constructor(formSelector, saveUrl, langCode) {
        this.$form = $(formSelector);
        this.saveUrl = saveUrl;
        this.langCode = langCode;
        this.labels = {};
        this.chunks = [];
        this.chunkSize = 500; // You can change this
        this.currentChunk = 0;

        this.$progressContainer = $('#progressContainer');
        this.$progressBar = $('#progressBar');

        this.init();
    }

    init() {
        if (!this.$form.length) {
            console.error('Form not found!');
            return;
        }

        this.$form.on('submit', (e) => {
            e.preventDefault();
            this.collectLabels();
            this.splitChunks();
            this.showProgress();
            this.saveNextChunk();
        });
    }

    collectLabels() {
        let formArray = this.$form.serializeArray();
        formArray.forEach(field => {
            if (field.name !== '_token' && field.name !== '_method') {
                this.labels[field.name] = field.value;
            }
        });
    }

    splitChunks() {
        const keys = Object.keys(this.labels);
        for (let i = 0; i < keys.length; i += this.chunkSize) {
            let chunk = {};
            keys.slice(i, i + this.chunkSize).forEach(key => {
                chunk[key] = this.labels[key];
            });
            this.chunks.push(chunk);
        }
    }

    showProgress() {
        this.$progressContainer.show();
        this.updateProgress();
    }

    updateProgress() {
        const percent = Math.floor((this.currentChunk / this.chunks.length) * 100);
        this.$progressBar.css('width', percent + '%').text(percent + '%');
    }

    saveNextChunk() {
        if (this.currentChunk >= this.chunks.length) {
            this.updateProgress();
            toastr.success('Labels saved successfully!');
            this.$progressBar.css('width', '100%').text('100% Complete');
            location.reload(); // Reload the page to see changes
            return;
        }

        let chunkData = {
            ...this.chunks[this.currentChunk],
            _token: $('meta[name="csrf-token"]').attr('content'),
            langcode: this.langCode
        };

        $.ajax({
            url: this.saveUrl,
            type: 'PUT',
            data: chunkData,
            success: (response) => {
                this.currentChunk++;
                this.updateProgress();
                this.saveNextChunk();
            },
            error: (xhr) => {
                console.error('❌ Error saving chunk:', xhr.responseText);
                toastr.error('❌ Error saving labels! Please try again.');
                this.$progressBar.css('width', '0%').text('0%');
                this.$progressContainer.hide();
                location.reload(); // Reload the page to see changes
            }
        });
    }
}

// Initialize example
// new LabelSaver('#labelsForm', '/admin/save-labels', 'en');
