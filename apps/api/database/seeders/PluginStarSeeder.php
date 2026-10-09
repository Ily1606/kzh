<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PluginStarSeeder extends Seeder
{
    public function run(): void
    {
        $userIds = DB::table('users')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $pluginIds = DB::table('plugins')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($userIds) || empty($pluginIds)) {
            $this->command->error(
                'Không tìm thấy user hoặc plugin.'
            );

            return;
        }

        $now = now();
        $startDate = $now->copy()->subDays(89)->startOfDay();
        $startTimestamp = $startDate->getTimestamp();
        $nowTimestamp = $now->getTimestamp();

        $minStars = (int) ceil(count($userIds) * 0.6);
        $maxStars = (int) floor(count($userIds) * 0.9);

        // Lấy những cặp user-plugin đã tồn tại để tránh duplicate.
        $existingPairs = DB::table('stars')
            ->select('user_id', 'plugin_id')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->user_id . ':' . $row->plugin_id => true,
            ])
            ->all();

        $batch = [];
        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($pluginIds as $pluginId) {
            // Mỗi plugin có số lượt star ngẫu nhiên.
            $targetStars = random_int($minStars, $maxStars);

            // Chỉ chọn user chưa star plugin này.
            $availableUsers = [];

            foreach ($userIds as $userId) {
                $key = $userId . ':' . $pluginId;

                if (!isset($existingPairs[$key])) {
                    $availableUsers[] = $userId;
                }
            }

            $targetStars = min(
                $targetStars,
                count($availableUsers)
            );

            if ($targetStars === 0) {
                $totalSkipped++;
                continue;
            }

            // Xáo trộn user để chọn ngẫu nhiên.
            shuffle($availableUsers);

            $selectedUsers = array_slice(
                $availableUsers,
                0,
                $targetStars
            );

            foreach ($selectedUsers as $userId) {
                $createdTimestamp = random_int(
                    $startTimestamp,
                    $nowTimestamp
                );

                $createdAt = now()->setTimestamp($createdTimestamp);

                // updated_at luôn >= created_at và <= hiện tại.
                $updatedTimestamp = random_int(
                    $createdTimestamp,
                    $nowTimestamp
                );

                $updatedAt = now()->setTimestamp($updatedTimestamp);

                $batch[] = [
                    'user_id' => $userId,
                    'plugin_id' => $pluginId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];

                // Đánh dấu ngay để không tạo trùng trong lần chạy này.
                $existingPairs[$userId . ':' . $pluginId] = true;

                if (count($batch) >= 500) {
                    DB::table('stars')->insert($batch);

                    $totalCreated += count($batch);
                    $batch = [];
                }
            }
        }

        if (!empty($batch)) {
            DB::table('stars')->insert($batch);
            $totalCreated += count($batch);
        }

        $this->command->info(
            "Đã tạo {$totalCreated} lượt star mới."
        );

        $this->command->info(
            "Số plugin không thể thêm star mới: {$totalSkipped}."
        );
    }
}
