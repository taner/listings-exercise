<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The user a user-scoped action is for.
     *
     * Auth is stubbed (see ActAsDemoUser), so the `auth` middleware cannot
     * guard these routes: it asks the session guard, which never sees the demo
     * user. This is the stand-in. The stub resolves nobody until the database
     * is seeded, and that is treated as not being allowed in.
     */
    protected function currentUser(Request $request): User
    {
        return $request->user() ?? abort(403);
    }
}
