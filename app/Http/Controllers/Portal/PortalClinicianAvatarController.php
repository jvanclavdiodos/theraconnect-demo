<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Clinician;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PortalClinicianAvatarController extends Controller
{
    public function __invoke(Request $request, Clinician $clinician): StreamedResponse
    {
        $patient = $request->user()->patient;

        abort_unless($patient && $patient->isAssignedTo($clinician), 403);

        $user = $clinician->user;
        abort_unless($user?->avatar_path && Storage::disk()->exists($user->avatar_path), 404);

        return Storage::disk()->response($user->avatar_path);
    }
}
