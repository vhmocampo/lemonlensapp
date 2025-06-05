<?php

namespace App\Http\Controllers;

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
        Stripe::setApiKey(env('STRIPE_SECRET'));

        // Retrieve the event from Stripe
        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $endpoint_secret = env('STRIPE_WEBHOOK_SECRET');
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
                break;
            default:
                // Unexpected event type
                return response()->json(['error' => 'Unhandled event type'], 400);
        }
        return response()->json(['status' => 'success'], 200);
    }

    /**
     * Create a Stripe Checkout session for purchasing credits.
     *
     * @param Request $request
     *
     * @param Request $request
     * @return void
     */
    public function createCheckoutSession(Request $request)
    {
        $validated = $request->validate([
            'price_id' => 'required|string'
        ]);

        // Optional: validate price_id is allowed for security
        $allowedPrices = [
            env('BUNDLE_PRICE_ID'),
            env('SINGLE_PRICE_ID'),
        ];

        if (!in_array($validated['price_id'], $allowedPrices)) {
            return response()->json(['error' => 'Invalid price_id' . env('BUNDLE_PRICE_ID')], 400);
        }

        Stripe::setApiKey(env('STRIPE_SECRET'));

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
            'success_url' => env('FRONTEND_URL') . '/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => env('FRONTEND_URL') . '/',
        ]);

        return response()->json(['session_url' => $session->url]);
    }
}
