<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Support\Facades\Log;

class IncrementUserCredits
{
    protected CreditService $creditService;

    /**
     * Create the event listener.
     */
    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * Handle the event.
     */
    public function handle(PaymentSuccessful $event): void
    {
        try {
            $user = User::find($event->userId);

            if (!$user) {
                Log::error('User not found for payment successful event', [
                    'user_id' => $event->userId,
                    'session_id' => $event->sessionId
                ]);
                return;
            }

            // Determine credit amount based on price_id
            $creditsToAdd = $this->getCreditAmountFromPriceId($event->priceId);

            if ($creditsToAdd <= 0) {
                Log::error('Invalid credit amount for price_id', [
                    'price_id' => $event->priceId,
                    'user_id' => $event->userId
                ]);
                return;
            }

            // Add credits to user account
            $this->creditService->addCredits(
                $user,
                $creditsToAdd,
                'Payment successful - Stripe checkout',
                [
                    'stripe_session_id' => $event->sessionId,
                    'price_id' => $event->priceId,
                    'user_email' => $event->userEmail,
                    'payment_method' => 'stripe'
                ]
            );

            Log::info('Credits added successfully after payment', [
                'user_id' => $event->userId,
                'credits_added' => $creditsToAdd,
                'session_id' => $event->sessionId,
                'price_id' => $event->priceId
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to add credits after payment', [
                'user_id' => $event->userId,
                'session_id' => $event->sessionId,
                'error' => $e->getMessage()
            ]);

            // Re-throw to trigger retry if using queues
            throw $e;
        }
    }

    /**
     * Determine the number of credits to add based on the price_id
     *
     * @param string $priceId
     * @return int
     */
    private function getCreditAmountFromPriceId(string $priceId): int
    {
        // Map price IDs to credit amounts
        $priceCreditsMap = [
            config('services.stripe.single_price_id') => 1,  // Single credit
            config('services.stripe.bundle_price_id') => 30, // Bundle of 30 credits
        ];

        return $priceCreditsMap[$priceId] ?? 0;
    }
}
