<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userIds = DB::table('users')
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        $plugins = DB::table('plugins')
            ->whereNull('deleted_at')
            ->select('id', 'user_id', 'name', 'created_at')
            ->get();

        if (empty($userIds) || $plugins->isEmpty()) {
            $this->command->error('No users or plugins found in database to seed comments.');
            return;
        }

        $this->command->warn('Truncating existing comments to seed a fresh English dataset...');
        DB::statement('TRUNCATE TABLE comments CASCADE');

        $faker = fake('en_US');
        $now = now();
        $nowTimestamp = $now->timestamp;

        $level1Comments = [];
        $level2Comments = [];
        $level3Comments = [];

        $totalLevel1 = 0;
        $totalLevel2 = 0;
        $totalLevel3 = 0;

        $this->command->info("Seeding comments for {$plugins->count()} plugins...");

        foreach ($plugins as $plugin) {
            $pluginCreatedAt = $plugin->created_at ? Carbon::parse($plugin->created_at) : $now->copy()->subDays(30);
            $pluginTimestamp = $pluginCreatedAt->timestamp;

            if ($pluginTimestamp >= $nowTimestamp) {
                $pluginTimestamp = $nowTimestamp - 3600;
            }

            /*
             * Random number of Level 1 (root) comments per plugin:
             * - ~30%: Low engagement / only a few comments (1 - 5 root comments)
             * - ~35%: Medium engagement (7 - 18 root comments)
             * - ~35%: High engagement / popular (> 20 root comments: 21 - 36)
             */
            $rollPlugin = random_int(1, 100);
            if ($rollPlugin <= 30) {
                $rootCommentCount = random_int(1, 5);
            } elseif ($rollPlugin <= 65) {
                $rootCommentCount = random_int(7, 18);
            } else {
                $rootCommentCount = random_int(21, 36);
            }

            for ($i = 0; $i < $rootCommentCount; $i++) {
                // ==========================================
                // LEVEL 1: ROOT COMMENTS (parent_comment_id = null)
                // ==========================================
                $rootId = (string) Str::uuid();
                $authorId = $userIds[array_rand($userIds)];
                $rootContent = $this->generateRandomContent($faker);

                $rootTimestamp = random_int($pluginTimestamp, $nowTimestamp);
                $rootCreatedAt = Carbon::createFromTimestamp($rootTimestamp);
                $rootUpdatedAt = (random_int(1, 100) <= 10)
                    ? Carbon::createFromTimestamp(random_int($rootTimestamp, $nowTimestamp))
                    : $rootCreatedAt;

                $level1Comments[] = [
                    'id' => $rootId,
                    'plugin_id' => $plugin->id,
                    'author_id' => $authorId,
                    'parent_comment_id' => null,
                    'content' => $rootContent,
                    'hidden_at' => null,
                    'created_at' => $rootCreatedAt,
                    'updated_at' => $rootUpdatedAt,
                    'deleted_at' => null,
                ];
                $totalLevel1++;

                // Probability of root comment receiving replies (Level 2): ~55%
                if (random_int(1, 100) <= 55) {
                    $replyCountL2 = random_int(1, 4);

                    for ($j = 0; $j < $replyCountL2; $j++) {
                        // ==========================================
                        // LEVEL 2: REPLIES TO ROOT (parent_comment_id = $rootId)
                        // ==========================================
                        $level2Id = (string) Str::uuid();

                        // 25% chance plugin author responds, otherwise random community user
                        $isAuthor = random_int(1, 100) <= 25 && !empty($plugin->user_id);
                        $l2AuthorId = $isAuthor ? $plugin->user_id : $userIds[array_rand($userIds)];

                        $l2Content = $this->generateRandomReplyContent($faker, 2, $isAuthor);
                        $l2Timestamp = random_int($rootTimestamp, $nowTimestamp);
                        $l2CreatedAt = Carbon::createFromTimestamp($l2Timestamp);
                        $l2UpdatedAt = (random_int(1, 100) <= 10)
                            ? Carbon::createFromTimestamp(random_int($l2Timestamp, $nowTimestamp))
                            : $l2CreatedAt;

                        $level2Comments[] = [
                            'id' => $level2Id,
                            'plugin_id' => $plugin->id,
                            'author_id' => $l2AuthorId,
                            'parent_comment_id' => $rootId,
                            'content' => $l2Content,
                            'hidden_at' => null,
                            'created_at' => $l2CreatedAt,
                            'updated_at' => $l2UpdatedAt,
                            'deleted_at' => null,
                        ];
                        $totalLevel2++;

                        // Probability of Level 2 receiving replies (Level 3 - MAX DEPTH): ~40%
                        if (random_int(1, 100) <= 40) {
                            $replyCountL3 = random_int(1, 3);

                            for ($k = 0; $k < $replyCountL3; $k++) {
                                // ==========================================
                                // LEVEL 3: REPLIES TO LEVEL 2 (parent_comment_id = $level2Id)
                                // ==========================================
                                $level3Id = (string) Str::uuid();

                                // 40% chance the original root commenter replies back, or another user
                                $l3AuthorId = (random_int(1, 100) <= 40)
                                    ? $authorId
                                    : $userIds[array_rand($userIds)];

                                $l3Content = $this->generateRandomReplyContent($faker, 3, false);
                                $l3Timestamp = random_int($l2Timestamp, $nowTimestamp);
                                $l3CreatedAt = Carbon::createFromTimestamp($l3Timestamp);
                                $l3UpdatedAt = (random_int(1, 100) <= 10)
                                    ? Carbon::createFromTimestamp(random_int($l3Timestamp, $nowTimestamp))
                                    : $l3CreatedAt;

                                $level3Comments[] = [
                                    'id' => $level3Id,
                                    'plugin_id' => $plugin->id,
                                    'author_id' => $l3AuthorId,
                                    'parent_comment_id' => $level2Id,
                                    'content' => $l3Content,
                                    'hidden_at' => null,
                                    'created_at' => $l3CreatedAt,
                                    'updated_at' => $l3UpdatedAt,
                                    'deleted_at' => null,
                                ];
                                $totalLevel3++;

                                // STOP AT LEVEL 3: Maximum 3 levels of comments as required
                            }
                        }
                    }
                }
            }
        }

        // Insert records level by level in chunks to preserve foreign key constraints
        $this->insertInChunks('comments', $level1Comments, 500);
        $this->insertInChunks('comments', $level2Comments, 500);
        $this->insertInChunks('comments', $level3Comments, 500);

        $totalComments = $totalLevel1 + $totalLevel2 + $totalLevel3;
        $this->command->info("Seeding completed successfully! Total: {$totalComments} comments.");
        $this->command->info("- Level 1 (Root): {$totalLevel1}");
        $this->command->info("- Level 2 (Replies): {$totalLevel2}");
        $this->command->info("- Level 3 (Sub-replies): {$totalLevel3}");
    }

    /**
     * Insert records in chunks for optimal performance.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    private function insertInChunks(string $table, array $records, int $chunkSize = 500): void
    {
        if (empty($records)) {
            return;
        }

        foreach (array_chunk($records, $chunkSize) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }

    /**
     * Generate Level 1 root comment content with random length (100% English).
     */
    private function generateRandomContent(\Faker\Generator $faker): string
    {
        $roll = random_int(1, 100);

        // 1. Very short (10 - 45 chars) - 20%
        if ($roll <= 20) {
            $shortList = [
                'Awesome plugin! Exactly what I was looking for.',
                'Works smoothly on macOS and Ubuntu.',
                'Super clean and lightweight. 10/10!',
                'Great work! Thanks for open-sourcing this.',
                'Does anyone know if this supports Neovim 0.10+?',
                'Simple, fast, and does the job perfectly.',
                'Been using this all week, absolutely love it.',
                'Bookmarked! Looking forward to upcoming features.',
                'Nice UI and seamless configuration.',
                'Super helpful tool, saved me so much time today.',
                'Any chance to add custom keybinding support?',
                'Works like a charm out of the box!',
            ];

            return $shortList[array_rand($shortList)];
        }

        // 2. Short (45 - 150 chars) - 35%
        if ($roll <= 55) {
            $shortTexts = [
                'Installed this on my dev machine and tested it against a large codebase. Memory consumption stayed very low.',
                'The default settings are well thought out. One small feature request: could we have an option to export logs?',
                'Does this plugin work well with multi-monitor setups? Experiencing a minor rendering delay on 4K screens.',
                'Hands down one of the most reliable plugins in this ecosystem. Documentation is clear and setup took under 2 minutes.',
                'I ran into a minor configuration hiccup with the cache directory, but setting correct permissions resolved it quickly.',
                'Really sleek implementation. Would love to see integration with the new API endpoints announced recently.',
            ];

            if (random_int(1, 2) === 1) {
                return $shortTexts[array_rand($shortTexts)];
            }

            return $faker->sentences(random_int(2, 3), true);
        }

        // 3. Medium (150 - 400 chars) - 25%
        if ($roll <= 80) {
            return $faker->paragraph(random_int(3, 5));
        }

        // 4. Long (400 - 800 chars) - 15%
        if ($roll <= 95) {
            return $faker->paragraphs(random_int(2, 3), true);
        }

        // 5. Very long (800 - 1600 chars) - 5%
        $content = $faker->paragraphs(random_int(4, 6), true);

        return mb_substr($content, 0, 1800);
    }

    /**
     * Generate Level 2 & Level 3 reply content with random length (100% English).
     */
    private function generateRandomReplyContent(\Faker\Generator $faker, int $level, bool $isAuthor): string
    {
        $roll = random_int(1, 100);

        // When the author of the plugin replies
        if ($isAuthor) {
            // Random length for author replies: Short (35%), Medium (40%), Long (25%)
            if ($roll <= 35) {
                $authorShort = [
                    'Thanks for the positive feedback! Glad it helped your workflow.',
                    'Good catch! We just pushed a patch addressing this in the latest release.',
                    'Check out the configuration guide in the README; this is toggleable via the settings flag.',
                    'Thanks for reporting. Could you share your OS version and terminal logs?',
                    'We are currently planning this feature for the next major version.',
                    'Appreciate the suggestion! Feel free to open a PR on our GitHub repository.',
                ];

                return $authorShort[array_rand($authorShort)];
            }

            if ($roll <= 75) {
                $authorMedium = [
                    "Thanks for bringing this up! The underlying issue was caused by an unhandled promise rejection in the background worker. We've pushed a fix to the main branch that safely falls back to default settings whenever the cache is invalid.",
                    "Appreciate the detailed breakdown! This behavior is actually intentional to preserve backwards compatibility with older configurations, but we're planning to introduce an opt-in toggle in the next minor release.",
                    "Thank you for testing this on different environments! We've noticed similar reports on Wayland sessions and are working on an updated display handler to eliminate the frame drops.",
                ];

                return (random_int(1, 2) === 1)
                    ? $authorMedium[array_rand($authorMedium)]
                    : $faker->paragraph(random_int(3, 5));
            }

            // Long author reply (multi-paragraph explanation or walkthrough)
            return $faker->paragraphs(random_int(2, 4), true);
        }

        // For regular user replies (Level 2 & Level 3) with completely random lengths:
        // 1. Very short (10 - 45 chars): 25%
        if ($roll <= 25) {
            $shortReplies = ($level === 3) ? [
                'Thanks a lot, that fixed it for me!',
                'Confirmed! Works like a charm now.',
                'Awesome, appreciate the quick tip.',
                'Same here, totally agree.',
                'Good to know, will try that tonight.',
                'Worked for me on Arch Linux too!',
            ] : [
                'Same issue here on macOS Sonoma.',
                'You can fix this by clearing the cache.',
                'Have you checked the permission settings?',
                'Works fine on my end with Node 20.',
                '+1 to this suggestion.',
                'I experienced the exact same behavior.',
            ];

            return $shortReplies[array_rand($shortReplies)];
        }

        // 2. Short (45 - 150 chars): 35%
        if ($roll <= 60) {
            $shortReplies = ($level === 3) ? [
                'Just followed your instructions and it resolved the startup crash immediately. Thanks for taking the time to share!',
                'Glad to hear that worked! Let me know if you run into any performance throttling under heavy load.',
                'That makes total sense now. I had misconfigured the environment variable in my local profile.',
            ] : [
                'Try restarting the daemon service with `--verbose` flag to see if there are any socket connection timeouts.',
                'I had the same problem yesterday. Upgrading the dependencies and re-running the build command resolved it.',
                'You can customize the keybindings by creating a local config override file in your home directory.',
            ];

            if (random_int(1, 2) === 1) {
                return $shortReplies[array_rand($shortReplies)];
            }

            return $faker->sentences(random_int(2, 3), true);
        }

        // 3. Medium (150 - 400 chars): 25%
        if ($roll <= 85) {
            return $faker->paragraph(random_int(2, 4));
        }

        // 4. Long (400 - 800 chars): 12%
        if ($roll <= 97) {
            return $faker->paragraphs(random_int(2, 3), true);
        }

        // 5. Very long (800 - 1500 chars): 3%
        $content = $faker->paragraphs(random_int(4, 5), true);
        return mb_substr($content, 0, 1800);
    }
}
