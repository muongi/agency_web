<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Create Muongi super admin user
        User::updateOrCreate(
            ['email' => 'muongi@agency.com'],
            [
                'name' => 'Muongi',
                'email' => 'muongi@agency.com',
                'password' => Hash::make('12345'),
                'is_super_admin' => true,
                'preferred_language' => 'en',
            ]
        );

        $this->command->info('Super admin user "Muongi" created successfully!');
        $this->command->info('Email: muongi@agency.com');
        $this->command->info('Password: 12345');
    }
}
