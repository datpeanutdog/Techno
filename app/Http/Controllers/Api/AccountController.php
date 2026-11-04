<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AccountController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($data);

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $user->createToken('pasabuy-mobile')->plainTextToken,
            'message' => 'Your account is ready.',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        $user = User::query()->where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Those login details did not match our records.'], 422);
        }

        return response()->json([
            'data' => $this->userPayload($user),
            'token' => $user->createToken('pasabuy-mobile')->plainTextToken,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'You are signed out.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset($data, function (User $user, string $password): void {
            $user->forceFill([
                'password' => $password,
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
        });

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['message' => __($status)], 422);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'bio' => ['nullable', 'string', 'max:500'],
            'campus' => ['nullable', 'string', 'max:100'],
            'avatar_url' => ['nullable', 'url', 'max:2048', 'regex:/^https:\/\//i'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();
        $user->name = $data['name'];
        $user->bio = $data['bio'] ?? null;
        $user->campus = $data['campus'] ?? null;

        if ($request->hasFile('avatar')) {
            $this->removeStoredAvatar($user);
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        } elseif (array_key_exists('avatar_url', $data) && $data['avatar_url']) {
            $this->removeStoredAvatar($user);
            $user->avatar_path = $data['avatar_url'];
        }

        $user->save();

        return response()->json(['data' => $this->userPayload($user)]);
    }

    /**
     * @return array{id: int, name: string, email: string, bio: ?string, campus: ?string, avatar_url: ?string}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'bio' => $user->bio,
            'campus' => $user->campus,
            'avatar_url' => $user->avatar_url,
        ];
    }

    private function removeStoredAvatar(User $user): void
    {
        if ($user->avatar_path && ! Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }
}
