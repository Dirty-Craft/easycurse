<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/mod-packs');
        }

        return Inertia::render('Auth/Login');
    }

    /**
     * Handle a login request.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();

            return Inertia::location('/mod-packs');
        }

        throw ValidationException::withMessages([
            'email' => [__('messages.auth.credentials_invalid')],
        ]);
    }

    /**
     * Show the registration form.
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/mod-packs');
        }

        return Inertia::render('Auth/Register');
    }

    /**
     * Handle a registration request.
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        $user->sendEmailVerificationNotification();

        $request->session()->flash('status', __('messages.auth.verification_sent'));

        return Inertia::location(route('verification.notice'));
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location('/');
    }

    /**
     * Show the forgot password form.
     */
    public function showForgotPassword()
    {
        if (Auth::check()) {
            return redirect('/mod-packs');
        }

        return Inertia::render('Auth/ForgotPassword');
    }

    /**
     * Handle a forgot password request.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Show the reset password form.
     */
    public function showResetPassword(Request $request, string $token)
    {
        if (Auth::check()) {
            return redirect('/mod-packs');
        }

        return Inertia::render('Auth/ResetPassword', [
            'token' => $token,
            'email' => $request->email,
        ]);
    }

    /**
     * Handle a reset password request.
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
            function (User $user, string $password) {
                $user->password = Hash::make($password);
                $user->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return redirect()->route('login')->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Show the change password form.
     */
    public function showChangePassword()
    {
        return Inertia::render('Auth/ChangePassword');
    }

    /**
     * Handle a change password request.
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => [__('messages.auth.current_password_incorrect')],
            ]);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return back()->with('status', __('messages.auth.password_changed'));
    }

    /**
     * Show the profile page.
     */
    public function showProfile()
    {
        $user = Auth::user();

        return Inertia::render('Auth/Profile', [
            'user' => $user,
            'isPremium' => $user->isPremium(),
            'monthlyRunCount' => $user->getMonthlyRunCount(),
            'premiumUntil' => $user->premium_until?->toIso8601String(),
        ]);
    }

    /**
     * Handle a profile update request.
     */
    public function updateProfile(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->name = $request->name;
        $user->save();

        return back()->with('status', __('messages.auth.profile_updated'));
    }

    /**
     * Show the email verification notice page.
     */
    public function showVerificationNotice()
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            return redirect('/mod-packs');
        }

        return Inertia::render('Auth/VerifyEmail', [
            'user' => $user,
        ]);
    }

    /**
     * Mark the user's email as verified.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            abort(403);
        }

        if ($user->hasVerifiedEmail()) {
            // If user is authenticated and already verified, redirect to mod-packs
            if (Auth::check() && Auth::id() === $user->id) {
                return redirect('/mod-packs')->with('status', __('messages.auth.email_already_verified'));
            }

            return redirect()->route('login')->with('status', __('messages.auth.email_already_verified'));
        }

        if ($user->markEmailAsVerified()) {
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        // If user is authenticated, redirect to mod-packs, otherwise to login
        if (Auth::check() && Auth::id() === $user->id) {
            return redirect('/mod-packs')->with('status', __('messages.auth.email_verified'));
        }

        return redirect()->route('login')->with('status', __('messages.auth.email_verified'));
    }

    /**
     * Resend the email verification notification.
     */
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect('/mod-packs');
        }

        $request->user()->sendEmailVerificationNotification();

        return redirect()->route('verification.notice')->with('status', __('messages.auth.verification_sent'));
    }
}
