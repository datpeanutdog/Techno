<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function registerForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $user = User::create($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('marketplace.index'));
    }

    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Those login details did not match our records.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('marketplace.index'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('marketplace.index');
    }

    public function forgotPasswordForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendPasswordResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        $status = Password::sendResetLink($request->only('email'));

        return back()->with('status', __($status));
    }

    public function resetPasswordForm(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function resetPassword(Request $request): RedirectResponse
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
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function editProfile(): View
    {
        return view('account.profile');
    }

    public function updateProfile(Request $request): RedirectResponse
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
            if ($user->avatar_path && ! Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $request->file('avatar')->store('avatars', 'public');
        } elseif (array_key_exists('avatar_url', $data) && $data['avatar_url']) {
            if ($user->avatar_path && ! Str::startsWith($user->avatar_path, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $data['avatar_url'];
        }

        $user->save();

        return back()->with('status', 'Your profile has been updated.');
    }
}
