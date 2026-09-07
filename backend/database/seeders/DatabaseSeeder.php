<?php

namespace Database\Seeders;

use App\Actions\RegisterOrganizationOwner;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with a demo account.
     */
    public function run(): void
    {
        $user = app(RegisterOrganizationOwner::class)->handle([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'organization_name' => 'Acme Inc',
        ]);

        Project::factory()->for($user->currentOrganization)->createMany([
            ['name' => 'Employee Handbook'],
            ['name' => 'Security Policies'],
        ]);

        // Platform administrator — no organization, signs in through the admin portal.
        User::factory()->admin()->create([
            'name' => 'Platform Admin',
            'email' => 'admin@raas.test',
        ]);
    }
}
