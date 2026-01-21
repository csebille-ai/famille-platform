<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AvatarAstroController extends Controller
{
    public function generate(Request $request)
    {
        return response()->json([
            'status' => 'error',
            'message' => 'This feature has been removed.',
        ], 410);
    }

    public function status(Request $request)
    {
        return response()->json([
            'status' => 'error',
            'message' => 'This feature has been removed.',
        ], 410);
    }
}
