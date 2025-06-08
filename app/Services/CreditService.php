<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    /**
     * Deduct credits from a user's account
     *
     * @param User $user
     * @param int $amount
     * @param string $description
     * @param array $metadata
     * @return bool
     * @throws \Exception
     */
    public function deductCredits(User $user, int $amount, string $description, array $metadata = []): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        return DB::transaction(function () use ($user, $amount, $description, $metadata) {
            // Lock the user record to prevent race conditions
            $user = User::where('id', $user->id)->lockForUpdate()->first();
            
            if (!$user) {
                throw new \Exception('User not found');
            }

            if ($user->credits < $amount) {
                throw new \Exception('Insufficient credits');
            }

            // Capture original balance before modification
            $originalBalance = $user->credits;

            // Deduct credits
            $user->credits -= $amount;
            if ($user->credits < 0) {
                $user->credits = 0; // Ensure credits do not go negative
            }
            $user->save();

            // Log the transaction with original balance
            $this->logTransaction($user, -$amount, 'deduction', $description, $metadata, $originalBalance);

            Log::info('Credits deducted', [
                'user_id' => $user->id,
                'amount' => $amount,
                'remaining_credits' => $user->credits,
                'description' => $description
            ]);

            return true;
        });
    }

    /**
     * Add credits to a user's account
     *
     * @param User $user
     * @param int $amount
     * @param string $description
     * @param array $metadata
     * @return bool
     * @throws \Exception
     */
    public function addCredits(User $user, int $amount, string $description, array $metadata = []): bool
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive');
        }

        return DB::transaction(function () use ($user, $amount, $description, $metadata) {
            // Lock the user record to prevent race conditions
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Capture original balance before modification
            $originalBalance = $user->credits;

            // Add credits
            $user->credits += $amount;
            $user->save();

            // Log the transaction with original balance
            $this->logTransaction($user, $amount, 'addition', $description, $metadata, $originalBalance);

            Log::info('Credits added', [
                'user_id' => $user->id,
                'amount' => $amount,
                'new_balance' => $user->credits,
                'description' => $description
            ]);

            return true;
        });
    }

    /**
     * Check if user has sufficient credits
     *
     * @param User $user
     * @param int $amount
     * @return bool
     */
    public function hasCredits(User $user, int $amount): bool
    {
        return $user->credits >= $amount;
    }

    /**
     * Get user's current credit balance
     *
     * @param User $user
     * @return int
     */
    public function getBalance(User $user): int
    {
        return $user->fresh()->credits;
    }

    /**
     * Log transaction to MongoDB for audit purposes
     *
     * @param User $user
     * @param int $amount
     * @param string $type
     * @param string $description
     * @param array $metadata
     * @param int $originalBalance
     */
    private function logTransaction(User $user, int $amount, string $type, string $description, array $metadata = [], int $originalBalance = null): void
    {
        try {
            Transaction::create([
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'description' => $description,
                'balance_before' => $originalBalance ?? $user->getOriginal('credits'),
                'balance_after' => $user->credits,
                'metadata' => $metadata,
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to log transaction to MongoDB', [
                'user_id' => $user->id,
                'amount' => $amount,
                'type' => $type,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get transaction history for a user
     *
     * @param User $user
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getTransactionHistory(User $user, int $limit = 50): \Illuminate\Support\Collection
    {
        try {
            return Transaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            Log::error('Failed to fetch transaction history', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return collect();
        }
    }

    /**
     * Calculate total credits used by a user
     *
     * @param User $user
     * @param \DateTime|null $from
     * @param \DateTime|null $to
     * @return int
     */
    public function getTotalCreditsUsed(User $user, ?\DateTime $from = null, ?\DateTime $to = null): int
    {
        try {
            $query = Transaction::where('user_id', $user->id)
                ->where('type', 'deduction');

            if ($from) {
                $query->where('created_at', '>=', $from);
            }

            if ($to) {
                $query->where('created_at', '<=', $to);
            }

            return abs($query->sum('amount'));
        } catch (\Exception $e) {
            Log::error('Failed to calculate total credits used', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}
