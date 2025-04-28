<?php

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AgentController;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Verified;
// Add these imports
use Illuminate\Support\Facades\Route as FacadesRoute;
use Illuminate\Support\Facades\URL;

/**
 * @group Public Endpoints
 *
 * Endpoints that don't require authentication
 */

/**
 * Register a new user
 *
 * @bodyParam name string required The user's full name
 * @bodyParam email string required The user's email address
 * @bodyParam password string required The user's password (min 8 characters)
 * @bodyParam password_confirmation string required Password confirmation
 * @bodyParam role string The user's role (admin/agent/user)
 *
 * @response {
 *   "message": "Registration successful. Please verify your email."
 * }
 */
Route::post('/register', [AuthController::class, 'register']);

/**
 * Login
 *
 * @bodyParam email string required User's email address
 * @bodyParam password string required User's password
 *
 * @response {
 *   "token": "1|rtFcCPYcAGor6ZLnJX6RHGBjYzRd8JYfNC1Vdk8Z",
 *   "user": {
 *     "id": 1,
 *     "name": "John Doe",
 *     "email": "user@example.com",
 *     "role": "user"
 *   }
 * }
 */
Route::post('/login', [AuthController::class, 'login']);

/**
 * Request password reset
 *
 * @bodyParam email string required User's email address
 *
 * @response {
 *   "message": "Password reset link sent"
 * }
 */
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);

/**
 * Reset password
 *
 * @bodyParam token string required Password reset token
 * @bodyParam email string required User's email address
 * @bodyParam password string required New password
 * @bodyParam password_confirmation string required New password confirmation
 *
 * @response {
 *   "message": "Password has been reset"
 * }
 */

Route::get('/reset-password/{token}', function (Request $request, $token) {
    // For a SPA or frontend app, redirect to its reset password page
    // Change the URL to your frontend application URL
    return redirect('http://localhost:3000/reset-password?token=' . $token . '&email=' . $request->email);
})->middleware('guest')->name('password.reset');


// Add this after your existing reset-password GET route
Route::post('/reset-password', [AuthController::class, 'resetPassword']);


/**
 * Verify email address
 *
 * @urlParam id integer required User ID
 * @urlParam hash string required Verification hash
 *
 * @response {
 *   "message": "Email verified successfully"
 * }
 */
Route::get('/email/verify/{id}/{hash}', function (Request $request) {
    $user = User::findOrFail($request->route('id'));

    if (! hash_equals(sha1($user->getEmailForVerification()), $request->route('hash'))) {
        return response()->json([
            'message' => 'Invalid verification link'
        ], 403);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json([
            'message' => 'Email already verified'
        ]);
    }

    if ($user->markEmailAsVerified()) {
        event(new Verified($user));
    }

    return response()->json([
        'message' => 'Email verified successfully'
    ]);
})->middleware(['signed'])->name('verification.verify');


/**
 * @group Protected Endpoints
 *
 * Endpoints that require authentication
 */
Route::middleware('auth:sanctum')->group(function () {
    /**
     * Logout
     *
     * @authenticated
     *
     * @response {
     *   "message": "Logged out"
     * }
     */
    Route::post('/logout', [AuthController::class, 'logout']);

    /**
     * Get authenticated user
     *
     * @authenticated
     *
     * @response {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "user@example.com",
     *   "role": "user"
     * }
     */
    Route::get('/me', [AuthController::class, 'me']);

    /**
     * Resend verification email
     *
     * @authenticated
     *
     * @response {
     *   "message": "Verification link sent"
     * }
     */
    Route::post('/email/verification-notification', function (Request $request) {
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent']);
    });
});

/**
 * @group Admin Endpoints
 *
 * Endpoints that require admin privileges
 */
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    /**
     * Perform admin action
     *
     * @authenticated
     *
     * @response {
     *   "message": "Admin action performed successfully",
     *   "user": {
     *     "id": 1,
     *     "name": "Admin User",
     *     "email": "admin@example.com"
     *   }
     * }
     */
    Route::post('/admin-action', [AdminController::class, 'adminAction']);
});

/**
 * @group Agent Endpoints
 *
 * Endpoints that require agent privileges
 */
Route::middleware(['auth:sanctum', 'role:agent'])->group(function () {
    /**
     * Perform agent action
     *
     * @authenticated
     *
     * @response {
     *   "message": "Agent action performed successfully",
     *   "user": {
     *     "id": 2,
     *     "name": "Agent User",
     *     "email": "agent@example.com"
     *   }
     * }
     */
    Route::post('/agent-action', [AgentController::class, 'agentAction']);
});
