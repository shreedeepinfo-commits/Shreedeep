<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperAdmin extends Command
{
    protected $signature = 'admin:make {email} {--name=Super Admin} {--password=} {--remove}';

    protected $description = 'Make a user the platform super admin (creates the user when --password is given)';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();
        if (! $user && ! $this->option('password')) {
            $this->error('No user with this email. Pass --password=... to create one.');

            return self::FAILURE;
        }
        $user ??= User::create(['name' => $this->option('name'), 'email' => strtolower($this->argument('email')), 'password' => $this->option('password')]);
        $user->forceFill(['is_super_admin' => ! $this->option('remove')])->save();
        $this->info($user->email.($this->option('remove') ? ' is no longer' : ' is now').' a super admin.');

        return self::SUCCESS;
    }
}
