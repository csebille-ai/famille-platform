<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class AvatarAstroImageController
{
    public function show(Request $request)
    {
        abort(410);
    }

    public function showForUser(Request $request, User $user)
    {
        abort(410);
    }

    public function showForUserPublic(Request $request, User $user)
    {
        abort(410);
    }
}
