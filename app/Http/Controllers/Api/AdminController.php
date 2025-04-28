<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Perform an admin-specific action
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminAction(Request $request)
    {
        // Implement your admin-specific logic here
        return response()->json([
            'message' => 'Admin action performed successfully',
            'user' => $request->user()
        ]);
    }
}
