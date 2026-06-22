<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClinicianResource;
use App\Models\Clinician;
use Illuminate\Http\JsonResponse;

class ClinicianController extends Controller
{
    /**
     * Patient-facing list of clinicians for the clinician-first booking flow.
     * Only safe, public-facing fields are exposed (id, name, specialization).
     */
    public function index(): JsonResponse
    {
        $clinicians = Clinician::with('user')
            ->join('users', 'users.id', '=', 'clinicians.user_id')
            ->orderBy('users.name')
            ->select('clinicians.*')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => ClinicianResource::collection($clinicians),
        ]);
    }
}
