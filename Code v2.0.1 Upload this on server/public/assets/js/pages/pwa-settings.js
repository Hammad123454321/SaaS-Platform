function resetForm() {

        const form = document.querySelector('form');

        const name = form.dataset.originalName;
        const shortName = form.dataset.originalShortName;
        const themeColor = form.dataset.originalThemeColor;
        const backgroundColor = form.dataset.originalBackgroundColor;
        const description = form.dataset.originalDescription;

        document.getElementById('name').value = name || '';
        document.getElementById('name').placeholder = name ? '' : 'Enter app name';

        document.getElementById('short_name').value = shortName || '';
        document.getElementById('short_name').placeholder = shortName ? '' : 'Enter short name';

        document.getElementById('theme_color').value = themeColor || '#000000';
        document.querySelector('#theme_color').nextElementSibling.textContent = themeColor || '#000000';

        document.getElementById('background_color').value = backgroundColor || '#ffffff';
        document.querySelector('#background_color').nextElementSibling.textContent = backgroundColor || '#ffffff';

        document.getElementById('description').value = description || '';
        document.getElementById('description').placeholder = description ? '' : 'Enter description';

        document.getElementById('logo').value = '';

        // Clear validation errors
        document.querySelectorAll('.text-danger.small.mt-1').forEach(el => el.remove());

}
