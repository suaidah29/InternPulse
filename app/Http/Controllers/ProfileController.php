<?php

namespace App\Http\Controllers;

use App\Services\SignatureBackgroundRemover;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $person = $request->user();
        $supervisor = $person->role === 'intern' ? \App\Models\User::find($person->supervisor_id)?->name : null;

        return view('management.profile', compact('person', 'supervisor'));
    }

    public function update(Request $request, SignatureBackgroundRemover $signatureBackgroundRemover): RedirectResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users')->ignore($user->id)],
            'current_password' => $request->input('email') !== $user->email ? 'required|current_password' : 'nullable',
            'photo' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'signature' => [$user->role === 'intern' ? 'prohibited' : 'nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'university' => [$user->role === 'intern' ? 'nullable' : 'prohibited', 'string', 'max:255'],
            'course' => [$user->role === 'intern' ? 'nullable' : 'prohibited', 'string', 'max:255'],
        ]);
        if ($validator->fails()) {
            return to_route('profile.edit')->withErrors($validator)->withInput($request->except(['current_password', 'photo', 'signature']));
        }
        $data = $validator->validated();
        $user->name = $data['name'];
        if ($user->email !== $data['email']) {
            $user->email_verified_at = null;
        }
        $user->email = $data['email'];
        if ($user->role === 'intern') {
            foreach (['university', 'course'] as $field) {
                if (array_key_exists($field, $data)) {
                    $user->{$field} = $data[$field];
                }
            }
        }
        if ($request->hasFile('signature')) {
            try {
                $user->signature_path = $signatureBackgroundRemover->store($request->file('signature'), $user->id);
            } catch (\Illuminate\Validation\ValidationException $error) {
                return to_route('profile.edit')->withErrors($error->errors())->withInput($request->except(['current_password', 'photo', 'signature']));
            }
        }
        if ($request->hasFile('photo')) {
            $user->photo_path = $request->file('photo')->store('profiles/'.$user->id, 'local');
        }
        $user->save();

        return to_route('profile.edit')->with('success', 'Profile updated. / Profil dikemas kini.');
    }

    /**
     * Reminder emails are opted out per type, so an intern can keep the logbook
     * nudge but stop the overdue-task mail (or the other way round).
     */
    public function reminders(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->validate([
            'email_logbook_reminders' => 'nullable|boolean',
            'email_task_reminders' => 'nullable|boolean',
        ]);

        $preferences = ['email_task_reminders' => $request->boolean('email_task_reminders')];
        if ($user->role === 'intern') {
            $preferences['email_logbook_reminders'] = $request->boolean('email_logbook_reminders');
        }

        $user->forceFill($preferences)->save();

        return to_route('profile.edit')->with('success', 'Reminder emails updated. / E-mel peringatan dikemas kini.');
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
