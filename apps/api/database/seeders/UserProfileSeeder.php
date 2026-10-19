<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UserProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Lấy danh sách ID user đã có profile để tránh duplicate
        $existingUserIds = DB::table('user_profiles')
            ->pluck('user_id')
            ->flip()
            ->all();

        // Lấy tất cả user chưa bị xóa mềm
        $users = DB::table('users')
            ->whereNull('deleted_at')
            ->select('id', 'name', 'email', 'created_at')
            ->get();

        $usersToSeed = [];
        foreach ($users as $user) {
            if (! isset($existingUserIds[$user->id])) {
                $usersToSeed[] = $user;
            }
        }

        if (empty($usersToSeed)) {
            $this->command->info('Tất cả người dùng đều đã có hồ sơ (profile). Không cần tạo thêm.');
            return;
        }

        $count = count($usersToSeed);
        $this->command->info("Đang tạo hồ sơ cho {$count} người dùng...");

        $batch = [];
        $totalCreated = 0;

        foreach ($usersToSeed as $user) {
            $userCreatedAt = $user->created_at ? Carbon::parse($user->created_at) : now();

            // 85% người dùng có tài khoản GitHub
            $hasGithub = random_int(1, 100) <= 85;
            $githubName = null;
            $githubLink = null;

            if ($hasGithub) {
                $cleanName = Str::slug($user->name, '');
                if (empty($cleanName)) {
                    $cleanName = Str::lower(Str::before($user->email, '@'));
                }
                $githubName = substr($cleanName, 0, 20).random_int(10, 9999);
                $githubLink = "https://github.com/{$githubName}";
            }

            /*
             * Sinh avatar_link phong phú, mô phỏng người dùng thực tế:
             * - 40%: Ảnh đại diện từ GitHub theo username
             * - 30%: Ảnh chân dung thật từ Pravatar
             * - 20%: Avatar đồ họa/vector từ Dicebear
             * - 10%: Để trống (null) - mô phỏng người dùng chưa tải lên avatar
             */
            $avatarRoll = random_int(1, 100);
            $avatarLink = null;

            if ($avatarRoll <= 40 && $githubName !== null) {
                $avatarLink = "https://github.com/{$githubName}.png";
            } elseif ($avatarRoll <= 70) {
                $avatarLink = 'https://i.pravatar.cc/150?u='.urlencode($user->id);
            } elseif ($avatarRoll <= 90) {
                $styles = ['bottts', 'identicon', 'avataaars', 'personas', 'thumbs'];
                $style = $styles[array_rand($styles)];
                $avatarLink = "https://api.dicebear.com/7.x/{$style}/svg?seed=".urlencode($user->id);
            } else {
                $avatarLink = null;
            }

            $batch[] = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'avatar_link' => $avatarLink,
                'github_name' => $githubName,
                'github_link' => $githubLink,
                'created_at' => $userCreatedAt,
                'updated_at' => $userCreatedAt,
            ];

            if (count($batch) >= 500) {
                DB::table('user_profiles')->insert($batch);
                $totalCreated += count($batch);
                $batch = [];
            }
        }

        if (! empty($batch)) {
            DB::table('user_profiles')->insert($batch);
            $totalCreated += count($batch);
        }

        $this->command->info("Đã tạo thành công {$totalCreated} hồ sơ người dùng mới!");
    }
}

