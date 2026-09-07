<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Requests\Api\App\StoreSessionRequest;
use App\Models\AppUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Sign an employee in with the email + access code the owner set for them.
     * Employees are not self-registered — an unknown email is rejected.
     */
    public function store(StoreSessionRequest $request, string $slug): JsonResponse
    {
        $publication = $this->activePublication($slug);

        $employee = AppUser::query()
            ->where('app_publication_id', $publication->id)
            ->where('email', $request->string('email')->lower()->toString())
            ->first();

        $invalid = ValidationException::withMessages([
            'access_code' => 'That email and access code do not match an active employee.',
        ]);

        if ($employee === null || ! Hash::check($request->string('access_code'), $employee->access_code)) {
            throw $invalid;
        }
        if (! $employee->is_active) {
            throw ValidationException::withMessages([
                'access_code' => 'Your access to this app has been deactivated.',
            ]);
        }

        $employee->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'token' => $employee->createToken('employee-app')->plainTextToken,
            'user' => ['name' => $employee->name, 'email' => $employee->email],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }
}
