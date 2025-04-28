<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str; // Add this import

/**
 * @group Authentication
 *
 * APIs for managing user authentication
 */
class AuthController extends Controller
{
    /**
     * Register a new user
     *
     * This endpoint lets you register a new user in the system.
     *
     * @bodyParam name string required The user's full name. Example: John Doe
     * @bodyParam email string required The user's email address. Example: user@example.com
     * @bodyParam password string required The user's password (min 8 characters). Example: password123
     * @bodyParam password_confirmation string required Password confirmation. Example: password123
     * @bodyParam role string The user's role (admin/agent/user). Example: user
     *
     * @response 200 {
     *   "message": "Registration successful. Please verify your email."
     * }
     *
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "email": ["The email has already been taken."],
     *     "password": ["The password confirmation does not match."]
     *   }
     * }
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|confirmed|min:8',
            'role' => 'in:admin,agent,user',
        ]);


        $role = $request->role;
        if ($role === 'admin') {
            $role = 'user'; // Force regular user role
        }


        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role ?? 'user',
        ]);

        event(new Registered($user)); // Send verification email

        return response()->json(['message' => 'Registration successful. Please verify your email.']);
    }

    /**
     * Login
     *
     * Authenticate a user and generate an API token.
     *
     * @bodyParam email string required User's email address. Example: user@example.com
     * @bodyParam password string required User's password. Example: password123
     *
     * @response 200 {
     *   "token": "1|rtFcCPYcAGor6ZLnJX6RHGBjYzRd8JYfNC1Vdk8Z",
     *   "user": {
     *     "id": 1,
     *     "name": "John Doe",
     *     "email": "user@example.com",
     *     "role": "user",
     *     "email_verified_at": "2023-01-01T00:00:00.000000Z",
     *     "created_at": "2023-01-01T00:00:00.000000Z",
     *     "updated_at": "2023-01-01T00:00:00.000000Z"
     *   }
     * }
     *
     * @response 401 {
     *   "message": "Invalid credentials"
     * }
     *
     * @response 403 {
     *   "message": "Please verify your email first"
     * }
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Please verify your email first'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Request password reset
     *
     * Send a password reset link to the user's email.
     *
     * @bodyParam email string required User's email address. Example: user@example.com
     *
     * @response {
     *   "message": "Password reset link sent"
     * }
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'Password reset link sent']);
    }

    /**
     * Logout
     *
     * Revoke all user's tokens.
     *
     * @authenticated
     *
     * @response {
     *   "message": "Logged out"
     * }
     */
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Get authenticated user
     *
     * Returns the currently authenticated user's information.
     *
     * @authenticated
     *
     * @response {
     *   "id": 1,
     *   "name": "John Doe",
     *   "email": "user@example.com",
     *   "role": "user",
     *   "email_verified_at": "2023-01-01T00:00:00.000000Z",
     *   "created_at": "2023-01-01T00:00:00.000000Z",
     *   "updated_at": "2023-01-01T00:00:00.000000Z"
     * }
     */
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    /**
    * Reset password
    *
    * Process the password reset form submission.
    *
    * @bodyParam token string required The reset token from the email. Example: 1234abcd5678efgh
    * @bodyParam email string required The user's email address. Example: user@example.com
    * @bodyParam password string required The new password (min 8 characters). Example: newpassword123
    * @bodyParam password_confirmation string required Confirmation of the new password. Example: newpassword123
    *
    * @response {
    *   "message": "Password has been reset successfully"
    * }
    *
    * @response 400 {
    *   "message": "This password reset token is invalid."
    * }
    */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset successfully'])
            : response()->json(['message' => __($status)], 400);
    }
}
