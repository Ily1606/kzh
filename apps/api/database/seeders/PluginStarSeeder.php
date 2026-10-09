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

        $userCount = count($userIds);

        $minStars = (int) ceil($userCount * 0.6);
        $maxStars = (int) floor($userCount * 0.9);

        /*
         * Tạo trọng số cho 90 ngày.
         *
         * 10%: không có lượt star
         * 25%: ngày ít lượt star
         * 40%: ngày bình thường
         * 20%: ngày cao điểm
         * 5%: ngày đột biến
         */
        $dailyWeights = [];

        for ($day = 0; $day < 90; $day++) {
            $roll = random_int(1, 100);

            if ($roll <= 10) {
                $dailyWeights[$day] = 0;
            } elseif ($roll <= 35) {
                $dailyWeights[$day] = random_int(1, 10);
            } elseif ($roll <= 75) {
                $dailyWeights[$day] = random_int(20, 60);
            } elseif ($roll <= 95) {
                $dailyWeights[$day] = random_int(80, 150);
            } else {
                $dailyWeights[$day] = random_int(200, 400);
            }
        }

        // Bảo đảm tổng trọng số lớn hơn 0.
        if (array_sum($dailyWeights) === 0) {
            $dailyWeights[random_int(0, 89)] = 100;
        }

        // Xây dựng danh sách ngày có trọng số để chọn ngẫu nhiên.
        $weightedDays = [];

        foreach ($dailyWeights as $day => $weight) {
            for ($i = 0; $i < $weight; $i++) {
                $weightedDays[] = $day;
            }
        }

        /*
         * Lấy những cặp user-plugin đã tồn tại.
         * Không tạo trùng lượt star.
         */
        $existingPairs = [];

        DB::table('stars')
            ->select('user_id', 'plugin_id')
            ->orderBy('user_id')
            ->chunk(5000, function ($rows) use (&$existingPairs) {
                foreach ($rows as $row) {
                    $existingPairs[
                        $row->user_id . ':' . $row->plugin_id
                    ] = true;
                }
            });

        $batch = [];
        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($pluginIds as $pluginId) {
            // Số lượt star mục tiêu cho plugin này.
            $targetStars = random_int($minStars, $maxStars);

            // Chọn user chưa star plugin này.
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

            shuffle($availableUsers);

            $selectedUsers = array_slice(
                $availableUsers,
                0,
                $targetStars
            );

            foreach ($selectedUsers as $userId) {
                /*
                 * Chọn ngày theo trọng số:
                 * ngày cao điểm có xác suất được chọn lớn hơn.
                 */
                $day = $weightedDays[
                    array_rand($weightedDays)
                ];

                $dayStart = $startDate->copy()->addDays($day);
                $dayStartTimestamp = $dayStart->getTimestamp();

                $dayEndTimestamp = min(
                    $dayStart->copy()->endOfDay()->getTimestamp(),
                    $nowTimestamp
                );

                $createdTimestamp = random_int(
                    $dayStartTimestamp,
                    $dayEndTimestamp
                );

                $createdAt = $now->copy()->setTimestamp(
                    $createdTimestamp
                );

                // updated_at từ created_at đến hiện tại.
                $updatedTimestamp = random_int(
                    $createdTimestamp,
                    $nowTimestamp
                );

                $updatedAt = $now->copy()->setTimestamp(
                    $updatedTimestamp
                );

                $batch[] = [
                    'user_id' => $userId,
                    'plugin_id' => $pluginId,
                    'created_at' => $createdAt,
                    'updated_at' => $updatedAt,
                ];

                $existingPairs[
                    $userId . ':' . $pluginId
                ] = true;

                if (count($batch) >= 500) {
                    DB::table('stars')->insert($batch);

                    $totalCreated += count($batch);
                    $batch = [];
                }
            }
        }

        if ($batch !== []) {
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
