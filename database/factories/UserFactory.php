<?php

namespace Database\Factories;

use App\Models\Karyawan;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Sebelumnya factory bawaan Laravel default (field name/email/
 * email_verified_at) yang tidak cocok sama sekali dengan skema `users`
 * app ini (username, id_karyawan, id_role - tidak ada name/email). Tidak
 * pernah disesuaikan sejak awal, jadi tidak bisa dipakai sama sekali untuk
 * testing. Diperbaiki mengikuti struktur asli.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'id_karyawan' => Karyawan::factory(),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'id_role' => Role::factory(),
        ];
    }
}
