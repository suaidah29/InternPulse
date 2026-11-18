<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TwoFactorChallengeController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return to_route('dashboard');
        }

        $id = $request->session()->get('login.two_factor_user_id');
        $user = $id ? User::find($id) : null;
        if (! $user) {
            $request->session()->forget('login.two_factor_user_id');

            return to_route('login');
        }

        return view('auth.two-factor', ['email' => $user->email]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => 'required|string|max:24']);

        $id = $request->session()->get('login.two_factor_user_id');
        $user = $id ? User::where('id', $id)->where('is_active', true)->first() : null;

        if (! $user || ! $user->hasTwoFactor()) {
            $request->session()->forget('login.two_factor_user_id');

            return to_route('login')->withErrors(['email' => 'Your sign-in session expired. Enter your password again. / Sesi tamat. Masukkan kata laluan semula.']);
        }

        $code = (string) $request->input('code');
        $usedRecoveryCode = false;

        if (! TwoFactor::verify($user->two_factor_secret, $code)) {
            $usedRecoveryCode = $user->consumeRecoveryCode($code);
            if (! $usedRecoveryCode) {
                return back()->withErrors(['code' => 'That code is not valid. Check your app or use a recovery code. / Kod tidak sah. Semak aplikasi atau guna kod pemulihan.']);
            }
        }

        Auth::login($user);
        $request->session()->forget('login.two_factor_user_id');
        $request->session()->regenerate();

        if ($usedRecoveryCode) {
            $remaining = count($user->recoveryCodes());
            $request->session()->flash('warning', $remaining === 0
                ? 'Recovery code used. You have no codes left — generate new ones in your profile. / Kod pemulihan digunakan. Tiada kod tinggal — jana kod baharu dalam profil anda.'
                : 'Recovery code used and deleted. '.$remaining.' left. / Kod pemulihan digunakan dan dipadam. '.$remaining.' tinggal.');
        }

        return to_route('dashboard');
    }

    /** Abandon the challenge and return to the password form. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('login.two_factor_user_id');

        return to_route('login');
    }
}
