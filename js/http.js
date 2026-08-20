/**
 * Drop-in replacements for itflowGet() and itflowPost().
 *
 * These keep jQuery's exact call shape - itflowGet(url, data, success, error) -
 * so the ~28 existing call sites did not have to be restructured when jQuery
 * was removed. Every one of them uses the plain callback form; none chained
 * .done()/.fail() or passed a dataType, so nothing else is reproduced here.
 *
 * The success callback receives the raw response TEXT, matching jQuery's
 * behaviour when the server does not send a JSON content type. Several call
 * sites do their own JSON.parse(), so returning a parsed object here would
 * break them.
 */

/** Serialise to PHP-style bracket params, the way jQuery did. */
function itflowSerialize(data) {
    const params = new URLSearchParams();

    (function add(prefix, value) {
        if (Array.isArray(value)) {
            value.forEach(function (v, i) {
                add(prefix + '[' + i + ']', v);
            });
        } else if (value !== null && typeof value === 'object') {
            Object.keys(value).forEach(function (k) {
                add(prefix ? prefix + '[' + k + ']' : k, value[k]);
            });
        } else {
            params.append(prefix, value === true ? 'true' : String(value));
        }
    })('', data || {});

    return params.toString();
}

function itflowGet(url, data, success, error) {
    const query = itflowSerialize(data);
    const sep = url.indexOf('?') === -1 ? '?' : '&';

    return fetch(query ? url + sep + query : url, {
        method: 'GET',
        credentials: 'same-origin'
    })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        })
        .then(function (text) {
            if (typeof success === 'function') {
                success(text);
            }
            return text;
        })
        .catch(function (err) {
            if (typeof error === 'function') {
                error(err);
            } else {
                console.error('itflowGet ' + url + ':', err);
            }
        });
}

function itflowPost(url, data, success, error) {
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        credentials: 'same-origin',
        body: itflowSerialize(data)
    })
        .then(function (res) {
            if (!res.ok) {
                throw new Error('HTTP ' + res.status);
            }
            return res.text();
        })
        .then(function (text) {
            if (typeof success === 'function') {
                success(text);
            }
            return text;
        })
        .catch(function (err) {
            if (typeof error === 'function') {
                error(err);
            } else {
                console.error('itflowPost ' + url + ':', err);
            }
        });
}

/** Kept for the kanban and invoice item-order callers, which use this name. */
function itflowPostForm(url, data) {
    return itflowPost(url, data);
}
