// Ajax Modal Load Script
document.addEventListener('click', function (e) {
    const trigger = e.target.closest('.ajax-modal');
    if (!trigger) {
        return;
    }
    e.preventDefault();

    // Prefer data-modal-url, fallback to href
    const modalUrl = trigger.dataset.modalUrl || trigger.getAttribute('href') || '#';
    const modalSize = trigger.dataset.modalSize || 'md';
    const modalId = 'ajaxModal_' + Date.now();

    // If no usable URL, bail
    if (!modalUrl || modalUrl === '#') {
        console.warn('ajax-modal: No modal URL found on trigger:', trigger);
        return;
    }

    const host = document.querySelector('.app-main') || document.body;

    // Show loading spinner while fetching content
    const spinner = document.createElement('div');
    spinner.id = 'modal-loading-spinner';
    spinner.className = 'text-center p-5';
    spinner.innerHTML = '<i class="fas fa-spinner fa-spin fa-2x text-muted"></i>';
    host.appendChild(spinner);

    function clearSpinner() {
        const el = document.getElementById('modal-loading-spinner');
        if (el) {
            el.remove();
        }
    }

    fetch(modalUrl, {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.json();
        })
        .then(function (response) {
            clearSpinner();

            if (response.error) {
                alert(response.error);
                return;
            }

            const wrapper = document.createElement('div');
            wrapper.className = 'modal fade';
            wrapper.id = modalId;
            wrapper.tabIndex = -1;
            wrapper.innerHTML =
                '<div class="modal-dialog modal-' + modalSize + '">' +
                    '<div class="modal-content border-dark">' +
                        response.content +
                    '</div>' +
                '</div>';
            host.appendChild(wrapper);

            // innerHTML does not execute <script> tags. The modal payload ends
            // with modal_footer.php, which re-runs app.js to wire up Tom Select,
            // IMask, flatpickr and friends - so re-inject them by hand.
            wrapper.querySelectorAll('script').forEach(function (old) {
                const s = document.createElement('script');
                for (const attr of old.attributes) {
                    s.setAttribute(attr.name, attr.value);
                }
                s.textContent = old.textContent;
                old.replaceWith(s);
            });

            bootstrap.Modal.getOrCreateInstance(wrapper).show();

            wrapper.addEventListener('hidden.bs.modal', function () {
                wrapper.remove();
            });
        })
        .catch(function (error) {
            clearSpinner();
            alert('Error loading modal content. Please try again.');
            console.error('Modal AJAX Error:', error);
        });
});
