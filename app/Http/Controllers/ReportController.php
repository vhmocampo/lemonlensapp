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

class ReportController extends Controller
{
    /**
     * Store a new report request and dispatch generation job
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

        if ($request->user()) {
            $userId = $request->user()->id;
        } elseif ($request->has('session_id')) {
            $sessionId = $request->input('session_id');
            if (!Cache::has('guest_session:' . $sessionId)) {
                return response()->json([
                    'error' => 'Invalid session ID. Please provide a valid session ID.'
                ], 401);
            }
        } else {
            return response()->json([
                'error' => 'Authentication required. Please provide a session_id or log in.'
            ], 401);
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
        // GenerateReportJob::dispatch($report);

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
}