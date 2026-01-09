/**
 * JavaScript for the Lead Form Edit page.
 * Handles dynamic field addition, options management, sorting, and form validation.
 */
let fieldIndex = document.querySelectorAll('#fieldsContainer .field-row').length + 5; // Account for existing fields + REQUIRED_FIELDS

function addField() {
    const container = document.getElementById('fieldsContainer');
    const fieldHtml = `
        <div class="field-row border rounded p-3 mb-3 bg-white" id="field-${fieldIndex}" data-id="${fieldIndex}">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Field Label <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" name="fields[${fieldIndex}][label]" required
                           placeholder="Enter a clear label for this field">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Field Type <span class="text-danger">*</span></label>
                    <select class="form-select" name="fields[${fieldIndex}][type]" onchange="toggleOptions(${fieldIndex})" required>
                        <option value="">Select Type</option>
                        <option value="text">Text</option>
                        <option value="email">Email</option>
                        <option value="tel">Phone</option>
                        <option value="textarea">Textarea</option>
                        <option value="select">Select Dropdown</option>
                        <option value="checkbox">Checkbox</option>
                        <option value="radio">Radio Button</option>
                        <option value="date">Date</option>
                        <option value="number">Number</option>
                        <option value="url">URL</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Map to Lead Field</label>
                    <select class="form-select" name="fields[${fieldIndex}][name]" onchange="toggleMapping(${fieldIndex})">
                        <option value="">Custom Field</option>
                        <option value="job_title">Job Title</option>
                        <option value="industry">Industry</option>
                        <option value="website">Website</option>
                        <option value="linkedin">LinkedIn</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="pinterest">Pinterest</option>
                        <option value="city">City</option>
                        <option value="state">State</option>
                        <option value="zip">ZIP</option>
                        <option value="country">Country</option>
                        <option value="country_code">Country Code</option>
                        <option value="country_iso_code">Country ISO Code</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Required</label>
                    <select class="form-select" name="fields[${fieldIndex}][is_required]">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div class="col-md-2 text-end">
                    <label class="form-label"> </label>
                    <div>
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeField(${fieldIndex})">
                            <i class="bx bx-trash"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Options Container -->
            <div class="options-container mt-3" id="options_container_${fieldIndex}" style="display: none;">
                <div class="row">
                    <div class="col-md-12">
                        <label class="form-label">Options</label>
                        <div class="options-list mb-2" id="options_list_${fieldIndex}"></div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="option_input_${fieldIndex}"
                                   placeholder="Add option">
                            <button class="btn btn-outline-primary" type="button" onclick="addOption(${fieldIndex})">
                                Add
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <input type="hidden" name="fields[${fieldIndex}][is_mapped]" value="0" id="is_mapped_${fieldIndex}">
            <div class="options-hidden" id="options_hidden_${fieldIndex}"></div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', fieldHtml);
    fieldIndex++;
}

function addOption(index) {
    const input = document.getElementById(`option_input_${index}`);
    const optionsList = document.getElementById(`options_list_${index}`);
    const optionsHidden = document.getElementById(`options_hidden_${index}`);
    const optionValue = input.value.trim();

    if (optionValue) {
        const optionHtml = `
            <span class="badge bg-light text-dark me-2 mb-2" data-value="${optionValue}">
                ${optionValue}
                <button type="button" class="btn-close btn-sm ms-1" onclick="removeOption(${index}, '${optionValue}')" aria-label="Remove option"></button>
            </span>
        `;
        optionsList.insertAdjacentHTML('beforeend', optionHtml);

        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = `fields[${index}][options][]`;
        hiddenInput.value = optionValue;
        optionsHidden.appendChild(hiddenInput);

        input.value = '';
    }
}

function removeOption(index, optionValue) {
    const optionsList = document.getElementById(`options_list_${index}`);
    const optionsHidden = document.getElementById(`options_hidden_${index}`);
    const optionItem = optionsList.querySelector(`[data-value="${optionValue}"]`);
    if (optionItem) {
        optionItem.remove();
    }
    const hiddenInput = optionsHidden.querySelector(`input[value="${optionValue}"]`);
    if (hiddenInput) {
        hiddenInput.remove();
    }
}

function validateFields() {
    const form = document.getElementById('leadFormEdit');
    const fields = form.querySelectorAll('[name^="fields["]');
    const fieldMap = new Map();

    for (const field of fields) {
        const matches = field.name.match(/fields\[(\d+)\]\[(\w+(\[\])?)\]/);
        if (matches) {
            const [, index, key] = matches;
            if (!fieldMap.has(index)) {
                fieldMap.set(index, {});
            }
            if (key === 'options[]') {
                fieldMap.get(index).options = fieldMap.get(index).options || [];
                fieldMap.get(index).options.push(field.value);
            } else {
                fieldMap.get(index)[key] = field.value;
            }
        }
    }

    let isValid = true;
    fieldMap.forEach((field, index) => {
        if (field.is_mapped === '1' && (!field.name || !field.type)) {
            isValid = false;
            alert(`Field ${parseInt(index) + 1}: Mapped fields must have both name and type selected`);
        } else if (!field.type && field.label) {
            isValid = false;
            alert(`Field ${parseInt(index) + 1}: Type is required when label is provided`);
        } else if (['select', 'radio', 'checkbox'].includes(field.type) && (!field.options || field.options.length === 0)) {
            isValid = false;
            alert(`Field ${parseInt(index) + 1}: At least one option is required for select, radio, and checkbox fields`);
        }
    });

    return isValid;
}

function toggleOptions(index) {
    const typeSelect = document.querySelector(`select[name="fields[${index}][type]"]`);
    const optionsContainer = document.querySelector(`#options_container_${index}`);
    const optionsHidden = document.getElementById(`options_hidden_${index}`);
    const optionsList = document.getElementById(`options_list_${index}`);

    if (!typeSelect || !optionsContainer || !optionsHidden || !optionsList) {
        console.error(`Elements for index ${index} not found`);
        return;
    }

    if (['select', 'radio', 'checkbox'].includes(typeSelect.value)) {
        optionsContainer.style.display = 'block';
    } else {
        optionsContainer.style.display = 'none';
        optionsHidden.innerHTML = '';
        optionsList.innerHTML = '';
    }
}

function toggleMapping(index) {
    const nameSelect = document.querySelector(`select[name="fields[${index}][name]"]`);
    const isMappedInput = document.getElementById(`is_mapped_${index}`);
    if (!nameSelect || !isMappedInput) {
        console.error(`Mapping elements for index ${index} not found`);
        return;
    }
    isMappedInput.value = nameSelect.value ? '1' : '0';
}

function removeField(index) {
    const fieldElement = document.getElementById(`field-${index}`);
    if (fieldElement) {
        fieldElement.remove();
    }
}

// Initialize SortableJS for drag-and-drop
new Sortable(document.getElementById('fieldsContainer'), {
    animation: 150,
    handle: '.field-row',
    onEnd: function (evt) {
        const fields = document.querySelectorAll('#fieldsContainer .field-row');
        fields.forEach((field, index) => {
            const inputs = field.querySelectorAll('input, select');
            inputs.forEach(input => {
                const name = input.name;
                if (name) {
                    input.name = name.replace(/fields\[(\d+)\]/, `fields[${fieldIndex + index}]`);
                }
            });
            field.id = `field-${fieldIndex + index}`;
            field.dataset.id = fieldIndex + index;
        });
        fieldIndex += fields.length;
    }
});

// Client-side form validation
document.getElementById('leadFormEdit').addEventListener('submit', function (e) {
    if (!validateFields()) {
        e.preventDefault();
    }
});

// Initialize Select2
$(document).ready(function () {
    $('.select2').select2({
        width: '100%'
    });
});
