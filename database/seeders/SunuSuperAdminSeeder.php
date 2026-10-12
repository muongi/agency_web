<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SunuSuperAdminSeeder extends Seeder
{
    public const EMAIL = 'sunu@gmail.com';

    /**
     * Create the SUNU super admin. Change the password after first login.
     *
     * @return void
     */
    public function run()
    {
        User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'SUNU Admin',
                'email' => self::EMAIL,
                'password' => Hash::make('123456789'),
                'is_super_admin' => true,
                'preferred_language' => 'fr',
            ]
        );

        $this->command?->info('Super admin user "SUNU Admin" created successfully!');
        $this->command?->info('Email: '.self::EMAIL);
        $this->command?->info('Password: 123456789');
    }
}
