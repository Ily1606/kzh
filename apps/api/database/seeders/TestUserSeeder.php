<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $faker = fake('vi_VN');
        $passwordHash = Hash::make('Password123!');
        $now = now();
        $runId = Str::lower(Str::random(6));

        for ($start = 1; $start <= 5000; $start += 500) {
            $users = [];

            $end = min($start + 499, 5000);

            for ($i = $start; $i <= $end; $i++) {
                $users[] = [
                    'id' => (string) Str::uuid(),
                    'name' => $faker->name(),
                    'email' => "test_{$runId}_{$i}@example.test",
                    'email_verified_at' => null,
                    'password' => $passwordHash,
                    'is_admin' => false,
                    'locked_at' => null,
                    'deleted_at' => null,
                    'remember_token' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('users')->insert($users);
        }
    }
}
