<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Jobs\GenerateReportJob;
use App\Enums\ReportStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Reports",
 *     description="API Endpoints for vehicle reports"
 * )
 */
class ReportController extends Controller
{
    /**
     * List all reports for the authenticated user or session.
     *
     * @OA\Get(
     *     path="/reports",
     *     summary="Get all reports for the authenticated user or session",
     *     tags={"Reports"},
     *     @OA\Parameter(
     *         name="session_id",
     *         in="query",
     *         description="Session ID for anonymous users",
     *         required=false,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of reports",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="string", format="uuid"),
     *                 @OA\Property(property="year", type="integer"),
     *                 @OA\Property(property="make", type="string"),
     *                 @OA\Property(property="model", type="string"),
     *                 @OA\Property(property="mileage", type="integer"),
     *                 @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function index(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        // Validate request
        $validated = $request->validate([
            'session_id' => 'nullable|uuid',
        ]);

        // Middleware can be added here if needed
        // Check authentication - either user must be logged in or session_id must be provided
        $userId = null;
        $sessionId = null;

        try {
            $userOrSession = $this->getUserOrSession($request);
            $userId = $userOrSession['userId'];
            $sessionId = $userOrSession['sessionId'];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 401); // 401 Unauthorized
        }

        // Fetch reports based on user or session ID
        $query = Report::query();
        if ($userId) {
            $query->where('user_id', $userId);
        } elseif ($sessionId) {
            $query->where('session_uuid', $sessionId);
        }
        $reports = $query->orderBy('created_at', 'desc')->get();
        $response = [];
        foreach ($reports as $report) {
            $response[] = [
                'uuid' => $report->uuid,
                'make' => $report->make,
                'model' => $report->model,
                'year' => $report->year,
                'mileage' => $report->mileage,
                'status' => $report->status->value,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
            ];
        }
        return response()->json($response);
    }

    /**
     * Store a new report request and dispatch generation job
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Post(
     *     path="/reports",
     *     summary="Create a new vehicle report",
     *     tags={"Reports"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"make","model","year","mileage"},
     *             @OA\Property(property="make", type="string", example="Honda"),
     *             @OA\Property(property="model", type="string", example="Accord"),
     *             @OA\Property(property="year", type="integer", example=2020),
     *             @OA\Property(property="mileage", type="integer", example=45000),
     *             @OA\Property(property="session_id", type="string", format="uuid")
     *         )
     *     ),
     *     @OA\Response(
     *         response=202,
     *         description="Report generation queued",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Report generation has been queued"),
     *             @OA\Property(property="uuid", type="string", format="uuid"),
     *             @OA\Property(property="status", type="string", example="pending")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        $request->headers->set('Accept', 'application/json');

        // Validate request
        $validated = $request->validate([
            'make' => 'required|string|max:255',
            'model' => 'required|string|max:255',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'mileage' => 'required|integer|min:0|max:1000000',
            'session_id' => 'nullable|uuid',
        ]);

        // Check authentication - either user must be logged in or session_id must be provided
        $userId = null;
        $sessionId = null;

        try {
            $userOrSession = $this->getUserOrSession($request);
            $userId = $userOrSession['userId'];
            $sessionId = $userOrSession['sessionId'];
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 401); // 401 Unauthorized
        }

        // Create report
        $report = new Report();
        $report->uuid = Str::uuid();
        $report->make = $validated['make'];
        $report->model = $validated['model'];
        $report->year = $validated['year'];
        $report->mileage = $validated['mileage'];
        $report->status = ReportStatus::PENDING;
        $report->user_id = $userId;
        $report->session_uuid = $sessionId;
        $report->save();

        // Dispatch job to generate report
        GenerateReportJob::dispatch($report->id);

        return response()->json([
            'message' => 'Report generation has been queued',
            'uuid' => $report->uuid,
            'status' => $report->status->value,
        ], 202); // 202 Accepted
    }

    /**
     * Check the status of a report
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/reports/{uuid}/status",
     *     summary="Check the status of a report",
     *     tags={"Reports"},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="UUID of the report",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report status",
     *         @OA\JsonContent(
     *             @OA\Property(property="uuid", type="string", format="uuid"),
     *             @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed"}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Report not found")
     * )
     */
    public function status($uuid)
    {
        try {
            $report = Report::where('uuid', $uuid)->firstOrFail();

            return response()->json([
                'uuid' => $report->uuid,
                'status' => $report->status->value,
                'created_at' => $report->created_at,
                'updated_at' => $report->updated_at,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Report not found'
            ], 404);
        }
    }

    /**
     * Show the completed report
     *
     * @param string $uuid
     * @return \Illuminate\Http\JsonResponse
     * 
     * @OA\Get(
     *     path="/reports/{uuid}",
     *     summary="Get the completed report details",
     *     tags={"Reports"},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="UUID of the report",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Report details",
     *         @OA\JsonContent(
     *             @OA\Property(property="uuid", type="string", format="uuid"),
     *             @OA\Property(property="make", type="string"),
     *             @OA\Property(property="model", type="string"),
     *             @OA\Property(property="year", type="integer"),
     *             @OA\Property(property="mileage", type="integer"),
     *             @OA\Property(property="result", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="completed_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Report not found"),
     *     @OA\Response(response=409, description="Report is not yet complete")
     * )
     */
    public function show($uuid)
    {
        try {
            $report = Report::where('uuid', $uuid)->firstOrFail();

            if ($report->status !== ReportStatus::COMPLETED) {
                return response()->json([
                    'error' => 'Report is not yet complete',
                    'status' => $report->status->value,
                ], 409); // 409 Conflict - resource not in expected state
            }

            // Decode JSON result if it's stored as a string
            $result = $report->result;
            if (is_string($report->result)) {
                $result = json_decode($report->result, true);
            }

            return response()->json([
                'uuid' => $report->uuid,
                'make' => $report->make,
                'model' => $report->model,
                'year' => $report->year,
                'mileage' => $report->mileage, 
                'result' => $result,
                'created_at' => $report->created_at,
                'completed_at' => $report->updated_at,
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Report not found'
            ], 404);
        }
    }

    /**
     * Get the user or session ID from the request
     *
     * @param Request $request
     * @return void
     */
    private function getUserOrSession(Request $request)
    {
        if ($request->user()) {
            $userId = $request->user()->id;
        } elseif ($request->has('session_id')) {
            $sessionId = $request->input('session_id');
            if (!Cache::has('guest_session:' . $sessionId)) {
                throw new \Exception('Invalid session ID. Please provide a valid session ID.');
            }
        } else {
            throw new \Exception('Authentication required. Please provide a session_id or log in.');
        }

        return [
            'userId' => $userId ?? null,
            'sessionId' => $sessionId ?? null,
        ];
    }
}