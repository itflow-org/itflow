/*
 * Client portal - taking a card through Stripe. Loaded only on the pages
 * that set $portal_stripe (client/includes/footer.php), after Stripe's own
 * script, and kept out of the page itself for the portal's
 * Content-Security-Policy:
 *
 *  - Checkout (client/pay.php): Stripe's card form in form[data-stripe-pay],
 *    on the PaymentIntent the page set up; paying sends the contact to
 *    Stripe and back to the return URL, which books it
 *  - Cards & autopay (client/saved_payment_methods.php): Add a card opens
 *    Stripe's embedded Checkout to save one - straight away when the page
 *    asks with data-autostart
 *
 * If Stripe's script didn't load, each says so instead of sitting there.
 */
(function () {
    'use strict';

    var dark = document.documentElement.getAttribute('data-bs-theme') === 'dark';

    function show(el, on) {
        if (el) {
            el.hidden = !on;
        }
    }

    function unavailable(scope) {
        show(scope.querySelector('.itflow-portal-stripe-element'), false);
        show(scope.querySelector('[data-stripe-loading]'), false);
        show(scope.querySelector('[data-stripe-unavailable]'), true);
    }

    // ---------- Checkout ----------
    var form = document.querySelector('form[data-stripe-pay]');
    if (form) {
        var submit = form.querySelector('[data-stripe-submit]');
        var errorBox = form.querySelector('[data-pay-error]');

        var busy = function (on) {
            submit.disabled = on;
            show(form.querySelector('[data-stripe-busy]'), on);
            show(form.querySelector('[data-stripe-idle]'), !on);
        };

        if (typeof window.Stripe !== 'function') {
            unavailable(form);
        } else {
            var stripe = window.Stripe(form.getAttribute('data-stripe-key'));
            var elements = stripe.elements({
                clientSecret: form.getAttribute('data-client-secret'),
                appearance: {theme: dark ? 'night' : 'stripe'}
            });
            var payment = elements.create('payment', {layout: 'tabs'});
            payment.on('ready', function () {
                show(form.querySelector('[data-stripe-loading]'), false);
                submit.disabled = false;
            });
            payment.on('loaderror', function () {
                unavailable(form);
            });
            payment.mount('#payment-element');

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                show(errorBox, false);
                busy(true);
                stripe.confirmPayment({
                    elements: elements,
                    confirmParams: {return_url: form.getAttribute('data-return-url')}
                }).then(function (result) {
                    // Only reached when the payment could not go ahead - otherwise
                    // the browser is already on its way to the return URL
                    if (result.error) {
                        errorBox.textContent = result.error.type === 'card_error' || result.error.type === 'validation_error'
                            ? result.error.message
                            : 'Something went wrong taking the payment - nothing has been charged. Try again.';
                        show(errorBox, true);
                    }
                    busy(false);
                });
            });
        }
    }

    // ---------- Adding a card ----------
    var holder = document.querySelector('[data-stripe-add-card-holder]');
    if (holder) {
        var started = false;
        var start = function () {
            if (started) {
                return;
            }
            started = true;
            show(holder, true);
            document.querySelectorAll('[data-stripe-add-card]').forEach(function (button) {
                show(button, false);
            });
            if (typeof window.Stripe !== 'function') {
                unavailable(holder);
                return;
            }
            var stripe = window.Stripe(holder.getAttribute('data-stripe-key'));
            stripe.initEmbeddedCheckout({
                fetchClientSecret: function () {
                    return fetch('/client/post.php?create_stripe_checkout', {method: 'POST', credentials: 'same-origin'})
                        .then(function (response) { return response.json(); })
                        .then(function (data) {
                            if (!data.clientSecret) {
                                throw new Error(data.error || 'No checkout');
                            }
                            return data.clientSecret;
                        });
                }
            }).then(function (checkout) {
                show(holder.querySelector('[data-stripe-loading]'), false);
                checkout.mount('#checkout');
            }).catch(function () {
                unavailable(holder);
            });
        };
        document.querySelectorAll('[data-stripe-add-card]').forEach(function (button) {
            button.addEventListener('click', start);
        });
        if (holder.hasAttribute('data-autostart')) {
            start();
        }
    }
})();
