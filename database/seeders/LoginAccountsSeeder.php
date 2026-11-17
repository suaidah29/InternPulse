<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LoginAccountsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['superadmin', 'supervisor', 'intern'] as $role) {
            $email = $role.'@internpulse.test';
            if (User::where('email', $email)->exists()) {
                continue;
            }
            $password = bin2hex(random_bytes(8));
            $user = new User;
            $user->name = ucfirst($role);
            $user->email = $email;
            $user->role = $role;
            $user->password = Hash::make($password);
            $user->department = 'IT';
            $user->save();
            $this->command->line($email.' : '.$password);
        }

        // Keep the demo accounts wired to each other so every role opens with a
        // populated workspace instead of an "unassigned intern" dashboard.
        $supervisor = User::where('email', 'supervisor@internpulse.test')->first();
        $intern = User::where('email', 'intern@internpulse.test')->first();

        if ($supervisor && $intern && ! $intern->supervisor_id) {
            $intern->supervisor_id = $supervisor->id;
            $intern->save();
            $this->command->line('intern@internpulse.test linked to supervisor@internpulse.test');
        }
    }
}
