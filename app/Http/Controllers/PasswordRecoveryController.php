<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordRecoveryController extends Controller
{
    public function request(): View
    {
        return view('management.password-recovery', ['reset' => false]);
    }

    public function send(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);
        try {
            Password::sendResetLink(['email' => $request->input('email'), 'is_active' => true]);
        } catch (\Throwable $error) {
            report($error);

            return back()->withErrors(['email' => 'Email delivery is unavailable. Please contact Management. / E-mel gagal dihantar. Hubungi Pengurusan.']);
        }

        return back()->with('success', 'If this active account exists, a reset link has been sent. Check your inbox. / Jika akaun aktif wujud, pautan telah dihantar.');
    }

    public function form(Request $request, string $token): View
    {
        return view('management.password-recovery', ['reset' => true, 'token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate(['token' => 'required', 'email' => 'required|email', 'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(12)->mixedCase()->numbers()->symbols()]]);
        $status = Password::reset($data + ['is_active' => true], function (User $user, string $password): void {
            $user->password = $password;
            $user->setRememberToken(Str::random(60));
            $user->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET ? to_route('login')->with('password_reset', true) : back()->withErrors(['email' => 'This reset link is invalid or expired. Request a new link. / Pautan tidak sah atau tamat tempoh.']);
    }
}
