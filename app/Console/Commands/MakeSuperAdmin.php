<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

/**
 * Promotes an account to platform operator, or creates one.
 *
 * How the first operator is made on a fresh installation, where there is no
 * console to sign in to yet.
 */
class MakeSuperAdmin extends Command
{
    protected $signature = 'user:super-admin
        {email? : The account to promote}
        {--name= : Used only when the account does not exist yet}
        {--password= : Used only when the account does not exist yet}
        {--revoke : Remove platform access instead of granting it}';

    protected $description = 'Grant (or revoke) platform operator access';

    public function handle(): int
    {
        $email = $this->argument('email') ?: text('Account email', required: true);

        $user = User::where('email', $email)->first();

        if ($user === null) {
            if ($this->option('revoke')) {
                $this->error("No account found for {$email}.");

                return self::FAILURE;
            }

            $password = $this->option('password') ?: Str::random(16);
            $generated = ! $this->option('password');

            $user = User::create([
                'name' => $this->option('name') ?: text('Name', required: true),
                'email' => $email,
                'password' => $password,
            ]);

            $this->info("Created {$user->name} <{$email}>.");

            if ($generated) {
                $this->line("Password: {$password}");
            }
        }

        $user->forceFill(['is_super_admin' => ! $this->option('revoke')])->save();

        $this->info($this->option('revoke')
            ? "{$email} is no longer a platform operator."
            : "{$email} is now a platform operator. Sign in and open Platform \u{2192} All societies.");

        return self::SUCCESS;
    }
}
