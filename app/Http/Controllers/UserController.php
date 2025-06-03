<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\CreditService;

/**
 * @OA\Tag(
 *     name="User",
 *     description="API Endpoints for user profile information"
 * )
 */
class UserController extends Controller
{
    protected CreditService $creditService;

    public function __construct(CreditService $creditService)
    {
        $this->creditService = $creditService;
    }

    /**
     * Get authenticated user's profile
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/me",
     *     summary="Get authenticated user's profile",
     *     tags={"User"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile information",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer"),
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="credits", type="integer"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function profile(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'credits' => $user->credits,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ]);
    }

    /**
     * Get user's credit transaction history
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/me/credits/history",
     *     summary="Get authenticated user's credit transaction history",
     *     tags={"User"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of transactions to return (max 100)",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credit transaction history",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_credits", type="integer"),
     *             @OA\Property(property="transactions", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="amount", type="integer"),
     *                     @OA\Property(property="type", type="string", enum={"addition", "deduction"}),
     *                     @OA\Property(property="description", type="string"),
     *                     @OA\Property(property="balance_before", type="integer"),
     *                     @OA\Property(property="balance_after", type="integer"),
     *                     @OA\Property(property="metadata", type="object"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function creditHistory(Request $request)
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100'
        ]);

        $user = $request->user();
        $limit = $request->input('limit', 50);
        
        $transactions = $this->creditService->getTransactionHistory($user, $limit);

        return response()->json([
            'current_credits' => $user->credits,
            'transactions' => $transactions
        ]);
    }

    /**
     * Get user's credit statistics
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/me/credits/stats",
     *     summary="Get authenticated user's credit usage statistics",
     *     tags={"User"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Credit usage statistics",
     *         @OA\JsonContent(
     *             @OA\Property(property="current_credits", type="integer"),
     *             @OA\Property(property="total_credits_used", type="integer"),
     *             @OA\Property(property="total_credits_used_this_month", type="integer"),
     *             @OA\Property(property="reports_generated", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function creditStats(Request $request)
    {
        $user = $request->user();
        
        $totalCreditsUsed = $this->creditService->getTotalCreditsUsed($user);
        $creditsUsedThisMonth = $this->creditService->getTotalCreditsUsed(
            $user,
            new \DateTime('first day of this month'),
            new \DateTime('last day of this month')
        );

        // Count reports generated by this user
        $reportsGenerated = \App\Models\Report::where('user_id', $user->id)->count();

        return response()->json([
            'current_credits' => $user->credits,
            'total_credits_used' => $totalCreditsUsed,
            'total_credits_used_this_month' => $creditsUsedThisMonth,
            'reports_generated' => $reportsGenerated
        ]);
    }
}
