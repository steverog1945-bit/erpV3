<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Karyawan>
 */
class KaryawanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama_karyawan' => fake()->name(),
            'bagian' => fake()->randomElement(['Admin', 'Desain', 'Produksi', 'Kasir', 'Gudang']),
            'telepon' => fake()->numerify('08##########'),
            'status_aktif' => 1,
        ];
    }
}
