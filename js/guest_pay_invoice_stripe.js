const stripe = Stripe(document.getElementById("stripe_publishable_key").value);

let elements;

initialize();
checkStatus();

document
  .querySelector("#payment-form")
  .addEventListener("submit", handleSubmit);

let emailAddress = '';
// Fetches a payment intent and captures the client secret
async function initialize() {
  const answer = await fetch("guest_ajax.php?stripe_create_pi=true", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
      invoice_id: document.getElementById("invoice_id").value,
      url_key: document.getElementById("url_key").value
    }),
  }).then((r) => r.json()).catch(() => ({}));

  // No payment to take - the invoice is paid, over the online limit, or Stripe is off; say why
  if (!answer.clientSecret) {
    showMessage(answer.error || "Paying online is not available just now.");
    return;
  }

  elements = stripe.elements({ clientSecret: answer.clientSecret });

  const paymentElementOptions = {
    layout: "tabs",
  };

  const paymentElement = elements.create("payment", paymentElementOptions);
  paymentElement.mount("#payment-element");

  // Unhide the submit button once everything has loaded
  document.getElementById("submit").hidden = false;
}

async function handleSubmit(e) {
  e.preventDefault();
  document.querySelector("#payment-message").classList.add("d-none");
  setLoading(true);

  const { error } = await stripe.confirmPayment({
    elements,
    confirmParams: {
      return_url: window.location.href,
      receipt_email: emailAddress,
    },
  });

  // This point will only be reached if there is an immediate error when
  // confirming the payment. Otherwise, your customer will be redirected to
  // your `return_url`. For some payment methods like iDEAL, your customer will
  // be redirected to an intermediate site first to authorize the payment, then
  // redirected to the `return_url`.
  if (error.type === "card_error" || error.type === "validation_error") {
    showMessage(error.message);
  } else {
    showMessage("An unexpected error occurred.");
  }

  setLoading(false);
}

// Fetches the payment intent status after payment submission
async function checkStatus() {
  const clientSecret = new URLSearchParams(window.location.search).get(
    "payment_intent_client_secret"
  );

  if (!clientSecret) {
    return;
  }

  const { paymentIntent } = await stripe.retrievePaymentIntent(clientSecret);

  switch (paymentIntent.status) {
    case "succeeded":
      showMessage("Payment succeeded!");
      break;
    case "processing":
      showMessage("Your payment is processing.");
      break;
    case "requires_payment_method":
      showMessage("Your payment was not successful, please try again.");
      break;
    default:
      showMessage("Something went wrong.");
      break;
  }
}

// ------- UI helpers -------

// Stays until the next try - a declined card's reason doesn't vanish before it is read
function showMessage(messageText) {
  const messageContainer = document.querySelector("#payment-message");

  messageContainer.textContent = messageText;
  messageContainer.classList.remove("d-none");
}

// Show a spinner on payment submission
function setLoading(isLoading) {
  if (isLoading) {
    // Disable the button and show a spinner
    document.querySelector("#submit").disabled = true;
    document.querySelector("#spinner").classList.remove("d-none");
    document.querySelector("#button-text").classList.add("d-none");
  } else {
    document.querySelector("#submit").disabled = false;
    document.querySelector("#spinner").classList.add("d-none");
    document.querySelector("#button-text").classList.remove("d-none");
  }
}
