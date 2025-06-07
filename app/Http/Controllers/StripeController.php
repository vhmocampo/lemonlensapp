<?php

namespace App\Http\Controllers;

use App\Events\PaymentSuccessful;
use Illuminate\Container\Attributes\Log;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class StripeController extends Controller
{

    /**
     * Handle Stripe webhook events.
     *
     * @param Request $request
     * @return void
     */
    public function handleWebhook(Request $request)
    {
        // Set your Stripe secret key
        Stripe::setApiKey(config('services.stripe.secret'));

        // Retrieve the event from Stripe
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = config('services.stripe.webhook_secret');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            return response()->json(['error' => 'Invalid signature'], 400);
        }
        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object; // contains a \Stripe\Checkout\Session
                \Log::info('Checkout session completed', ['session' => $session]);

                // Extract metadata from the session
                $metadata = $session->metadata;

                if (isset($metadata->user_id, $metadata->user_email, $metadata->price_id)) {
                    // Dispatch the PaymentSuccessful event
                    PaymentSuccessful::dispatch(
                        $metadata->price_id,
                        (int) $metadata->user_id,
                        $metadata->user_email,
                        $session->id
                    );

                    \Log::info('PaymentSuccessful event dispatched', [
                        'user_id' => $metadata->user_id,
                        'price_id' => $metadata->price_id,
                        'session_id' => $session->id
                    ]);
                } else {
                    \Log::warning('Checkout session completed but missing required metadata', [
                        'session_id' => $session->id,
                        'metadata' => $metadata
                    ]);
                }
                break;
            default:
                // Unexpected event type
                return response()->json(['error' => 'Unhandled event type'], 200);
        }
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Create a Stripe Checkout session for purchasing credits.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCheckoutSession(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string'
        ]);

        // Optional: validate price_id is allowed for security
        $allowedPrices = [
            config('services.stripe.single_price_id'),
            config('services.stripe.bundle_price_id'),
        ];

        if (!in_array($validated['price_id'], $allowedPrices)) {
            return response()->json(['error' => 'Invalid price_id'], 400);
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $session = Session::create([
            'payment_method_types' => ['card'],
            'mode' => 'payment',
            'line_items' => [[
                'price' => $validated['price_id'],
                'quantity' => 1,
            ]],
            'metadata' => [
                'user_id' => $request->user()->id,
                'user_email' => $request->user()->email,
                'price_id' => $validated['price_id'],
            ],
            'success_url' => config('services.frontend.url') . '/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('services.frontend.url') . '/',
        ]);

        return response()->json(['session_url' => $session->url]);
    }
}
