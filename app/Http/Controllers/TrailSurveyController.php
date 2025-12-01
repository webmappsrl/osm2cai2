<?php

namespace App\Http\Controllers;

use App\Models\TrailSurvey;
use Illuminate\Http\JsonResponse;

class TrailSurveyController extends Controller
{
    /**
     * Get participants for a trail survey
     */
    public function getParticipants(int $id): JsonResponse
    {
        $trailSurvey = TrailSurvey::findOrFail($id);
        $participants = $trailSurvey->getParticipants();

        return response()->json([
            'participants' => $participants,
        ]);
    }
}
