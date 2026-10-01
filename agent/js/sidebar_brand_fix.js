function fitBrandText() {
    text.style.transform = 'none';
    // getBoundingClientRect gives real on-screen edges, unlike
    // offsetLeft which is unreliable for inline text
    var available = link.getBoundingClientRect().right - text.getBoundingClientRect().left - 8;
    var natural = text.scrollWidth;
    // Scale rather than step font-size down.
    var scale = natural > available ? available / natural : 1;
    text.style.transform = 'scale(' + scale.toFixed(4) + ')';
}

var text = document.getElementById('sidebar-brand-text');

if (text) {
    var link = text.closest('.brand-link');

    // Fires once on observe as well as any later width change
    new ResizeObserver(fitBrandText).observe(link);
}