// Initialize Stripe.js
const stripe = Stripe(document.getElementById("stripe_publishable_key").value);

initialize();

// Fetch Checkout Session and retrieve the client secret
async function initialize() {
    const fetchClientSecret = async () => {
        const response = await fetch("/portal/portal_post.php?create_stripe_checkout", {
            method: "POST",
        });
        const { clientSecret } = await response.json();
        return clientSecret;
    };

    // Initialize Checkout
    const checkout = await stripe.initEmbeddedCheckout({
        fetchClientSecret,
    });

    // Mount Checkout
    checkout.mount('#checkout');
}
