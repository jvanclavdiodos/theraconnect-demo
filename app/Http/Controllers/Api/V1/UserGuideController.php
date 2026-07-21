<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\UserGuide;

class UserGuideController extends Controller
{
    public function __invoke()
    {
        return response()->json(['data' => ['version' => UserGuide::VERSION, 'sections' => UserGuide::forRole('patient')]]);
    }
}
