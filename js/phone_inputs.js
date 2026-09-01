/*
 * ITFlow - intl-tel-input wiring, shared by the agent side and the client portal.
 *
 * Lived in js/app.js until the portal needed it too. app.js cannot be loaded in
 * the portal - it calls DataTables, TinyMCE, Tom Select, Flatpickr and IMask
 * unconditionally with no typeof guards, none of which the portal loads - so
 * the choice was to copy this or to split it out. Copying it would have left
 * two implementations of a subtle piece of logic to drift apart, so it is here.
 *
 * app.js still drives it on the agent side via itflowStep('phone-inputs', ...),
 * which is what re-runs it for ajax modals. The portal has no app.js, so this
 * file self-starts on DOMContentLoaded below. Both paths are safe together:
 * initOnePhoneInput() carries its own re-entry guard.
 */

/**
 * intl-tel-input on every phone field.
 *
 * ITFlow stores the dial code and the number in separate columns
 * (contact_phone_country_code / contact_phone and friends), so the library runs
 * in separateDialCode mode: its dropdown owns the dial code, the visible input
 * holds only the national number. That keeps the existing schema, the API and
 * every render site untouched.
 *
 * Markup contract:
 *   <input type="hidden" name="phone_country_code" value="1">
 *   <input type="tel" name="phone" data-itflow-phone="phone_country_code">
 *
 * Which country a field starts on:
 *
 *   A saved record ALWAYS keeps the dial code it was saved with. Anything else
 *   silently rewrites data - open a UK contact under a US client, close the
 *   modal, and its +44 would have been saved back as +1.
 *
 *   Context only decides WHICH country claims that code, since a code is not a
 *   country (+1 covers 25 of them). In order: the address Country picker named
 *   by data-itflow-phone-country-select on the input, then
 *   data-itflow-phone-country on the form (the contact modals use this for the
 *   client's country), then the same attribute on <body> (the company's).
 *   If none of them claims the stored code, the code's canonical country wins -
 *   priority 0 in the library's own data, i.e. US for +1 rather than whichever
 *   territory happens to sort first.
 *
 *   With nothing stored - a new record - context is the whole answer.
 */
function initPhoneInputs() {
    if (typeof window.intlTelInput !== 'function') {
        return;
    }

    document.querySelectorAll('input[data-itflow-phone]').forEach(function (el) {
        // One field must never take the rest down with it. The first version of
        // this called v17 API names that v29 dropped, and because the whole
        // sweep shared one try/catch the throw on the first phone field meant
        // every mobile and fax input after it silently never initialised.
        try {
            initOnePhoneInput(el);
        } catch (e) {
            console.error('itflow phone input failed:', el.name, e);
        }
    });
}

function initOnePhoneInput(el) {
    // modal_footer.php re-executes this file on every ajax modal open, so
    // without a guard each open would stack another instance on the input.
    if (el.dataset.itiReady) {
        return;
    }

    var form = el.form;
    var hidden = form ? form.querySelector('input[name="' + el.dataset.itflowPhone + '"]') : null;
    if (!hidden) {
        return;
    }
    el.dataset.itiReady = '1';

    var countrySelect = null;
    if (form && el.dataset.itflowPhoneCountrySelect) {
        countrySelect = form.querySelector('[name="' + el.dataset.itflowPhoneCountrySelect + '"]');
    }

    var stored = (hidden.value || '').replace(/[^0-9]/g, '');
    var context = isoFromSelect(countrySelect)
        || (form ? (form.dataset.itflowPhoneCountry || '') : '')
        || (document.body.dataset.itflowPhoneCountry || '');

    var initial = stored ? isoForDialCode(stored, context) : context;

    var iti = window.intlTelInput(el, {
        initialCountry: initial.toLowerCase(),
        separateDialCode: true,
        countrySearch: true,
        formatAsYouType: true
    });

    var sync = function () {
        var country = iti.getSelectedCountry();
        hidden.value = country && country.dialCode ? country.dialCode : '';
    };

    // Only write back on load when we know the stored code survived. If it
    // resolved to nothing - a code no country uses - leave the field exactly as
    // saved rather than blanking it; the user picking a country will set it.
    var selected = iti.getSelectedCountry();
    if (!stored || (selected && selected.dialCode === stored)) {
        sync();
    }

    el.addEventListener('countrychange', sync);

    // Follow the address country picker while the form is open.
    if (countrySelect) {
        countrySelect.addEventListener('change', function () {
            var iso2 = isoFromSelect(countrySelect);
            if (iso2) {
                iti.setSelectedCountry(iso2.toLowerCase());
                sync();
            }
        });
    }

    // Belt and braces for a form saved without ever opening the dropdown.
    if (form && !form.dataset.itiSyncBound) {
        form.dataset.itiSyncBound = '1';
        form.addEventListener('submit', function () {
            form.querySelectorAll('input[data-itflow-phone]').forEach(function (input) {
                var target = form.querySelector('input[name="' + input.dataset.itflowPhone + '"]');
                var inst = window.intlTelInput.getInstance(input);
                if (target && inst) {
                    var c = inst.getSelectedCountry();
                    target.value = c && c.dialCode ? c.dialCode : '';
                }
            });
        });
    }
}

/**
 * ISO2 for the country a <select> is currently on.
 *
 * Each <option> carries data-iso2, stamped by PHP from $country_iso2_array, so
 * the 194-entry name -> ISO2 map never has to be duplicated into JS or shipped
 * to the browser as a blob. It also sidesteps an inline <script>, which the
 * CSP work would have to unpick later.
 */
function isoFromSelect(select) {
    if (!select) {
        return '';
    }
    var option = select.selectedOptions ? select.selectedOptions[0] : null;
    return option && option.dataset ? (option.dataset.iso2 || '') : '';
}

/**
 * Which country to show for a stored dial code.
 *
 * Prefers the contextual country when it actually uses that code, otherwise the
 * canonical one. Plain .find() is wrong here - the library's data is in name
 * order, so +1 would resolve to American Samoa. Priority 0 is the library's own
 * marker for the country that owns a shared code.
 */
function isoForDialCode(dialCode, preferIso2) {
    if (!dialCode || typeof window.intlTelInput.getAllCountries !== 'function') {
        return '';
    }
    var matches = window.intlTelInput.getAllCountries().filter(function (c) {
        return c.dialCode === dialCode;
    });
    if (!matches.length) {
        return '';
    }
    if (preferIso2) {
        var preferred = matches.find(function (c) {
            return c.iso2 === preferIso2.toLowerCase();
        });
        if (preferred) {
            return preferred.iso2;
        }
    }
    return matches.reduce(function (best, c) {
        return (c.priority || 0) < (best.priority || 0) ? c : best;
    }).iso2;
}
/*
 * Self-start for pages with no app.js - the client portal. On the agent side
 * app.js has already run by the time this fires and every input carries its
 * data-itiReady guard, so this is a no-op there rather than a double init.
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPhoneInputs);
} else {
    initPhoneInputs();
}
