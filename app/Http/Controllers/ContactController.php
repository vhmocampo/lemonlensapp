<?php

namespace App\Http\Controllers;

use Laravel\Socialite\Facades\Socialite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Contact",
 *     description="API Endpoints for general contact"
 * )
 */
class ContactController extends Controller
{
    /**
     * Handle contact form submissions
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Post(
     *     path="/contact",
     *     summary="Submit contact form",
     *     tags={"Contact"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","subject","message"},
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="subject", type="string", example="Question about your service"),
     *             @OA\Property(property="message", type="string", example="I would like more information...")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Message sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="success")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to send message")
     *         )
     *     )
     * )
     */
    public function contact(Request $request)
    {
        // Validate incoming request
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'subject' => 'required|string|max:100',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get validated data
        $email = $request->input('email');
        $subject = $request->input('subject');
        $message = $request->input('message');

        try {
            // Placeholder for mail sending logic
            // In a real application, you would use:
            // Mail::to('your-email@example.com')->send(new ContactFormMail($email, $subject, $message));

            // For now, just simulate successful email sending

            return response()->json([
                'message' => 'success'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to send message'
            ], 500);
        }
    }

}