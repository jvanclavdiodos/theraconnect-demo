<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\UserGuide;

class UserGuideController extends Controller
{
    public function __invoke()
    {
        return view('guide.clinician', ['sections' => UserGuide::forRole('clinician')]);
    }
}
