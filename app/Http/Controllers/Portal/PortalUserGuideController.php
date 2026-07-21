<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\UserGuide;

class PortalUserGuideController extends Controller
{
    public function __invoke()
    {
        return view('portal.guide.show', ['sections' => UserGuide::forRole('patient')]);
    }
}
