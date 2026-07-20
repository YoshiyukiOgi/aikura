<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\FoundationPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminUser extends Command
{
    protected $signature = 'users:create-admin {email : Login email address} {name : Display name} {--password= : Login password}';

    protected $description = 'Create or update an active administrator user.';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password');
        if (! is_string($password) || $password === '') {
            $this->error('Password is required.');

            return self::FAILURE;
        }

        $this->callSilently('db:seed', ['--class' => FoundationPermissionSeeder::class]);
        $adminRole = Role::query()->where('code', 'admin')->firstOrFail();
        $user = User::updateOrCreate(
            ['email' => (string) $this->argument('email')],
            [
                'name' => (string) $this->argument('name'),
                'password' => Hash::make($password),
                'is_active' => true,
                'disabled_at' => null,
            ],
        );
        $user->roles()->syncWithoutDetaching([$adminRole->id]);

        $this->info("Administrator [{$user->email}] is ready.");

        return self::SUCCESS;
    }
}
