<?php

namespace App\Http\Controllers;

use App\Services\PasswordPolicy;
use App\Services\TwoFactor;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProfileSecurityController extends Controller
{
    private const PENDING_SECRET = 'security.pending_two_factor_secret';

    public function password(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password' => PasswordPolicy::rulesForUser($user),
        ]);

        $user->password = $data['password'];
        $user->setRememberToken(Str::random(60));
        $user->save();

        // Every other browser is signed out; this one stays usable.
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();
        event(new PasswordReset($user));

        return to_route('profile.edit')->with('success', 'Password changed. Other devices were signed out. / Kata laluan ditukar. Peranti lain telah dilog keluar.');
    }

    /** Step one: hold a fresh secret in the session until the person proves their app works. */
    public function start(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => 'required|current_password']);

        if ($request->user()->hasTwoFactor()) {
            return to_route('profile.edit')->withErrors(['two_factor' => 'Two-factor authentication is already on. Turn it off first to set up a new device. / Pengesahan dua faktor sudah aktif. Matikannya dahulu untuk peranti baharu.']);
        }

        $request->session()->put(self::PENDING_SECRET, TwoFactor::generateSecret());

        return to_route('profile.edit')->with('success', 'Scan or enter the key shown below, then confirm the 6-digit code. / Imbas atau masukkan kunci di bawah, kemudian sahkan kod 6 digit.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::PENDING_SECRET);

        return to_route('profile.edit');
    }

    /** Step two: only an app that produces a valid code turns two-factor on. */
    public function enable(Request $request): RedirectResponse
    {
        $secret = $request->session()->get(self::PENDING_SECRET);
        if (! is_string($secret) || $secret === '') {
            return to_route('profile.edit')->withErrors(['two_factor' => 'Setup expired. Start again. / Persediaan tamat tempoh. Mula semula.']);
        }

        $request->validate(['code' => 'required|string|max:12']);
        if (! TwoFactor::verify($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'That code did not match. Check the time on your phone and try again. / Kod tidak sepadan. Semak masa telefon anda dan cuba lagi.']);
        }

        $codes = TwoFactor::recoveryCodes();
        $request->user()->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => TwoFactor::hashRecoveryCodes($codes),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $request->session()->forget(self::PENDING_SECRET);
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return to_route('profile.edit')->with('success', 'Two-factor authentication is on. Save your recovery codes now. / Pengesahan dua faktor kini aktif. Simpan kod pemulihan anda.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => 'required|current_password']);

        $request->user()->disableTwoFactor();
        $request->session()->forget(self::PENDING_SECRET);

        return to_route('profile.edit')->with('success', 'Two-factor authentication is off. / Pengesahan dua faktor dimatikan.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => 'required|current_password']);

        $user = $request->user();
        if (! $user->hasTwoFactor()) {
            return to_route('profile.edit')->withErrors(['two_factor' => 'Two-factor authentication is not on. / Pengesahan dua faktor belum aktif.']);
        }

        $codes = TwoFactor::recoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => TwoFactor::hashRecoveryCodes($codes)])->save();
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return to_route('profile.edit')->with('success', 'New recovery codes created. The old ones no longer work. / Kod pemulihan baharu dijana. Kod lama tidak lagi berfungsi.');
    }
}
