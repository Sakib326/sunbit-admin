<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    /**
     * Perform an agent-specific action
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function agentAction(Request $request)
    {
        // Implement your agent-specific logic here
        return response()->json([
            'message' => 'Agent action performed successfully',
            'user' => $request->user()
        ]);
    }
}
