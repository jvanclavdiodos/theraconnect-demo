<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\TermsOfService;
use Illuminate\Http\JsonResponse;

class LegalDocumentController extends Controller
{
    public function terms(): JsonResponse
    {
        return response()->json([
            'data' => [
                'version' => TermsOfService::currentVersion(),
                'content' => TermsOfService::plainText(),
            ],
        ]);
    }
}
