<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME');
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $name || ! $email || ! $password || strlen($password) < 12) {
            throw new \RuntimeException('Configura ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD (mínimo 12 caracteres) en .env antes de crear al administrador.');
        }

        User::updateOrCreate(['email' => $email], [
            'name' => $name,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
    }
}
