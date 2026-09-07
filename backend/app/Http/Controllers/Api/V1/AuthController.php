<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RegisterOrganizationOwner;
use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterOrganizationOwner $register): JsonResponse
    {
        $user = $register->handle($request->validated());

        return $this->tokenResponse($user, $request->string('name').' device', 201);
    }

    /**
     * Organization sign-in. Platform administrators must use the admin portal.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = $this->authenticate($request);

        if ($user->is_admin) {
            throw ValidationException::withMessages([
                'email' => 'Platform administrators sign in through the admin portal.',
            ]);
        }

        // The org ResolveTenant would resolve for this user on every request.
        $organization = $user->currentOrganization ?? $user->organizations()->first();

        if ($organization?->status === OrganizationStatus::Disabled) {
            throw ValidationException::withMessages([
                'email' => 'Your organization has been disabled. Contact your administrator.',
            ]);
        }

        $user->loadMissing('currentOrganization');

        return $this->tokenResponse($user, $request->input('device_name', 'api'));
    }

    /**
     * Platform administrator sign-in. Rejects non-admin accounts.
     */
    public function adminLogin(LoginRequest $request): JsonResponse
    {
        $user = $this->authenticate($request);

        if (! $user->is_admin) {
            throw ValidationException::withMessages([
                'email' => 'This account does not have platform administrator access.',
            ]);
        }

        return $this->tokenResponse($user, $request->input('device_name', 'admin'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->loadMissing('currentOrganization'));
    }

    private function authenticate(LoginRequest $request): User
    {
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    private function tokenResponse(User $user, string $deviceName, int $status = 200): JsonResponse
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user),
        ], $status);
    }
}
