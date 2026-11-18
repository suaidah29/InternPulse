<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        return Auth::check() ? to_route('dashboard') : view('login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);

        if (! Auth::attempt($credentials + ['is_active' => true])) {
            return back()
                ->withErrors(['email' => 'Invalid email or password. / E-mel atau kata laluan tidak tepat.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        // No session is issued until the second factor is verified: the password
        // alone leaves the visitor in a pending, unauthenticated state.
        if ($user->hasTwoFactor()) {
            Auth::logout();
            $request->session()->regenerate();
            $request->session()->put('login.two_factor_user_id', $user->id);

            return to_route('login.two-factor');
        }

        $request->session()->regenerate();

        return to_route('dashboard');
    }
}
