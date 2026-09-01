/**
 * Re-run <script> elements that arrived via innerHTML, strictly in order.
 *
 * External scripts are awaited before the next one starts; inline scripts run
 * synchronously. Order matters because a modal's own script is emitted before
 * modal_footer.php's http.js / autocomplete.js / app.js, and depends on them.
 */
function runScriptsInOrder(scripts) {
    return scripts.reduce(function (chain, old) {
        return chain.then(function () {
            return new Promise(function (resolve) {
                const s = document.createElement('script');
                for (const attr of old.attributes) {
                    s.setAttribute(attr.name, attr.value);
                }
                if (old.src) {
                    s.async = false;
                    s.onload = resolve;
                    s.onerror = function () {
                        console.error('ajax-modal: failed to load', old.src);
                        resolve();
                    };
                    old.replaceWith(s);
                } else {
                    s.textContent = old.textContent;
                    old.replaceWith(s);
                    resolve();
                }
            });
        });
    }, Promise.resolve());
}

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

            // innerHTML does not execute <script> tags, so they have to be
            // re-injected. They must also run IN ORDER: a modal payload loads
            // its own script first and modal_footer.php's http.js / app.js
            // after, and the modal script depends on helpers those define.
            // A dynamically created <script src> is async by default and would
            // run in completion order instead - jQuery's .append() loaded them
            // sequentially, which is the behaviour reproduced here.
            runScriptsInOrder(Array.from(wrapper.querySelectorAll('script')))
                .then(function () {
                    bootstrap.Modal.getOrCreateInstance(wrapper).show();
                });

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
