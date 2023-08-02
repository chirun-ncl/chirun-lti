function update_preview() {
    const format = format_select.value;

    const option = Array.from(format_select.querySelectorAll('option')).find(o => o.value == format);
    const url = option.getAttribute('data-url');

    preview_iframe.src = `${output_root_url}/${url}`;
}

const output_root_url = JSON.parse(document.getElementById('output_root_url').textContent);
const preview_iframe = document.querySelector('#preview iframe');

const format_select = document.getElementById('id_item_format');

if(format_select) {
    format_select.addEventListener('change', update_preview);
}
