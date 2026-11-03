<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('management.profile', ['person' => $request->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => $request->input('email') !== $user->email ? 'required|current_password' : 'nullable',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'signature' => [$user->role === 'intern' ? 'prohibited' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);
        $user->name = $data['name'];
        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }
        $user->email = $data['email'];
        foreach (['photo', 'signature'] as $kind) {
            if ($request->hasFile($kind)) {
                $user->{$kind.'_path'} = $request->file($kind)->store('profiles/'.$user->id, 'local');
            }
        }
        $user->save();

        return back()->with('success', 'Profile updated. / Profil dikemas kini.');
    }

    public function image(Request $request, string $kind): Response
    {
        abort_unless(in_array($kind, ['photo', 'signature']), 404);
        abort_if($kind === 'signature' && $request->user()->role === 'intern', 403);
        $path = $request->user()->{$kind.'_path'};
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
