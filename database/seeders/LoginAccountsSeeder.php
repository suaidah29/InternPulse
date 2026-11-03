<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
class LoginAccountsSeeder extends Seeder {
 public function run():void {
 foreach(['superadmin','supervisor','intern'] as $role){
 $email=$role.'@internpulse.test';
 if(User::where('email',$email)->exists()) continue;
 $password=bin2hex(random_bytes(8));
 $user=new User; $user->name=ucfirst($role);$user->email=$email;$user->role=$role;$user->password=Hash::make($password);$user->save();
 $this->command->line($email.' : '.$password);
 }
 }
}
