$(document).ready(function () {
    tinymce.init({
        selector: '#content',
        height: 500,
        plugins: 'advlist autolink lists link image table code charmap print preview anchor searchreplace visualblocks fullscreen insertdatetime media table paste code help wordcount emoticons template',
        toolbar: 'undo redo | formatselect fontselect fontsizeselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | charmap emoticons media | code preview fullscreen | help',
        menubar: 'file edit view insert format tools table help',
        branding: false,
        font_formats: 'Andale Mono=andale mono,times;Arial=arial,helvetica,sans-serif;Arial Black=arial black,avant garde;Book Antiqua=book antiqua,palatino;Comic Sans MS=comic sans ms,sans-serif;Courier New=courier new,courier;Georgia=georgia,palatino;Helvetica=helvetica;Impact=impact,chicago;Symbol=symbol;Tahoma=tahoma,arial,helvetica,sans-serif;Terminal=terminal,monaco;Times New Roman=times new roman,times;Trebuchet MS=trebuchet ms,geneva;Verdana=verdana,geneva;Webdings=webdings;Wingdings=wingdings,zapf dingbats',
        fontsize_formats: '8pt 10pt 12pt 14pt 18pt 24pt 36pt',
        content_style: 'body { font-family:Arial,sans-serif; font-size:14pt }'
    });

    // Load sample content
    $('#load_sample_content').on('click', function () {
        let category = $('#category').val();
        if (!category) {
            toastr.warning('Please select a category first.');
            return;
        }
        $.get(sampleContentUrl, {
            category: category
        }, function (data) {
            tinymce.get('content').setContent(data.content);
            toastr.success('Sample content loaded.');
        });
    });

    // Variable filter
    $('#variable-search').on('keyup', function () {
        let query = $(this).val().toLowerCase();
        $('.variable-group button').each(function () {
            let text = $(this).text().toLowerCase();
            $(this).toggle(text.includes(query));
        });
    });

    // Copy variable
    $(document).on('click', '.copy-variable-btn', function () {
        let variable = $(this).data('variable');
        navigator.clipboard.writeText(variable).then(() => {
            toastr.success(variable + ' copied to clipboard.');
        });
    });
});
