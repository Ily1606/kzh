<?php

namespace Database\Seeders;

use App\Enums\PluginLicense;
use App\Enums\PluginStatus;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PluginSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command?->info('1. Ensuring categories and tags exist...');
        if (Category::count() === 0 || Tag::count() === 0) {
            $this->call(CategoryAndTagSeeder::class);
        }

        $categories = Category::all();
        $tags = Tag::all();

        $this->command?->info('2. Seeding users and user profiles...');
        $users = $this->seedUsers();

        $this->command?->info('3. Seeding diverse plugins...');
        $plugins = $this->seedPlugins($users, $categories, $tags);

        $this->command?->info('4. Seeding engagement (views, stars, comments)...');
        $this->seedEngagement($plugins, $users);

        $this->command?->info('5. Syncing trending data to Redis and building Trending ZSET...');
        try {
            Artisan::call('plugins:recover-trending');
            $this->command?->line(Artisan::output());
        } catch (\Throwable $e) {
            $this->command?->warn('Could not recover trending stats to Redis: ' . $e->getMessage());
        }

        $this->command?->info('PluginSeeder completed successfully!');
    }

    /**
     * Seed or retrieve random developer users with avatars.
     *
     * @return array<int, array{id: string, name: string, email: string}>
     */
    private function seedUsers(): array
    {
        $seedUsersData = [
            ['name' => 'Alex Johnson', 'email' => 'alex.johnson@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Alex'],
            ['name' => 'Sarah Connor', 'email' => 'sarah.connor@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Sarah'],
            ['name' => 'David Miller', 'email' => 'david.miller@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=David'],
            ['name' => 'Elena Rostova', 'email' => 'elena.rostova@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Elena'],
            ['name' => 'Michael Chang', 'email' => 'michael.chang@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Michael'],
            ['name' => 'Sophia Chen', 'email' => 'sophia.chen@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Sophia'],
            ['name' => 'Marcus Aurelius', 'email' => 'marcus@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Marcus'],
            ['name' => 'Emily Watson', 'email' => 'emily.watson@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Emily'],
            ['name' => 'Lucas Silva', 'email' => 'lucas.silva@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Lucas'],
            ['name' => 'Chloe Martin', 'email' => 'chloe.martin@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Chloe'],
            ['name' => 'Daniel Kim', 'email' => 'daniel.kim@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Daniel'],
            ['name' => 'Jessica Taylor', 'email' => 'jessica.taylor@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Jessica'],
            ['name' => 'Liam O\'Connor', 'email' => 'liam.oconnor@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Liam'],
            ['name' => 'Amara Okafor', 'email' => 'amara.okafor@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Amara'],
            ['name' => 'Kenji Sato', 'email' => 'kenji.sato@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Kenji'],
            ['name' => 'Nina Patel', 'email' => 'nina.patel@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Nina'],
            ['name' => 'Oliver Queen', 'email' => 'oliver.queen@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Oliver'],
            ['name' => 'Freja Lind', 'email' => 'freja.lind@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Freja'],
            ['name' => 'Hassan Ali', 'email' => 'hassan.ali@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Hassan'],
            ['name' => 'Isabella Santos', 'email' => 'isabella.santos@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Isabella'],
            ['name' => 'Grace Hopper', 'email' => 'grace.hopper@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Grace'],
            ['name' => 'Alan Turing', 'email' => 'alan.turing@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Alan'],
            ['name' => 'Ada Lovelace', 'email' => 'ada.lovelace@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Ada'],
            ['name' => 'Linus Torvalds', 'email' => 'linus.torvalds@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Linus'],
            ['name' => 'Guido van Rossum', 'email' => 'guido@example.com', 'avatar' => 'https://api.dicebear.com/7.x/identicon/svg?seed=Guido'],
        ];

        $users = [];
        $passwordHash = Hash::make('password');
        $now = now();

        foreach ($seedUsersData as $u) {
            $user = User::query()->firstOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => $passwordHash,
                    'is_admin' => false,
                    'email_verified_at' => $now,
                ]
            );

            DB::table('user_profiles')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'id' => (string) Str::uuid(),
                    'avatar_link' => $u['avatar'],
                    'github_name' => Str::slug($u['name']),
                    'github_link' => 'https://github.com/' . Str::slug($u['name']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            $users[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ];
        }

        $existing = User::whereIn('email', ['admin@example.com', 'user@example.com'])->get();
        foreach ($existing as $ex) {
            $users[] = ['id' => $ex->id, 'name' => $ex->name, 'email' => $ex->email];
        }

        return $users;
    }

    /**
     * @param array<int, array{id: string, name: string, email: string}> $users
     * @param \Illuminate\Database\Eloquent\Collection<int, Category> $categories
     * @param \Illuminate\Database\Eloquent\Collection<int, Tag> $tags
     * @return array<int, array{id: string, name: string, title: string, created_at: Carbon, status: string}>
     */
    private function seedPlugins(array $users, $categories, $tags): array
    {
        $categoryMap = $categories->keyBy('name');

        $pluginTemplates = [
            [
                'name' => 'git-lens-pro',
                'title' => 'Supercharge Git inside your IDE with interactive rebase, file history, and blame annotations.',
                'category' => 'Developer tools',
                'tag_names' => ['May read files', 'May run commands'],
            ],
            [
                'name' => 'docker-compose-companion',
                'title' => 'Manage multi-container Docker applications, inspect logs, and execute commands seamlessly.',
                'category' => 'Developer tools',
                'tag_names' => ['May run commands', 'May access network'],
            ],
            [
                'name' => 'tailwind-css-intellisense',
                'title' => 'Intelligent Tailwind CSS tooling for class autocompletion, syntax highlighting, and hover preview.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'prisma-query-studio',
                'title' => 'Interactive visual browser and query editor for Prisma ORM schemas and database records.',
                'category' => 'Developer tools',
                'tag_names' => ['May access network', 'Needs credentials'],
            ],
            [
                'name' => 'graphql-inspector',
                'title' => 'GraphQL schema validation, autocompletion, and query runner with response tree inspection.',
                'category' => 'Integrations',
                'tag_names' => ['May access network'],
            ],
            [
                'name' => 'redis-commander',
                'title' => 'Fast and lightweight Redis key-value manager with cluster, stream, and pipeline monitoring.',
                'category' => 'Developer tools',
                'tag_names' => ['May access network', 'Needs credentials'],
            ],
            [
                'name' => 'k8s-pod-monitor',
                'title' => 'Real-time Kubernetes cluster dashboard with live pod logs, event streaming, and metrics.',
                'category' => 'Security & diagnostics',
                'tag_names' => ['May run commands', 'May access network'],
            ],
            [
                'name' => 'vim-motion-suite',
                'title' => 'Seamless Vim emulation with custom macros, easy-motion jumping, and surround bindings.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'prettier-plus',
                'title' => 'Opinionated code formatter supporting TypeScript, Vue, Markdown, CSS, and blade templates.',
                'category' => 'Developer tools',
                'tag_names' => ['May write files'],
            ],
            [
                'name' => 'copilot-deep-assistant',
                'title' => 'Autonomous AI coding companion powered by DeepSeek and Claude with multi-file refactoring.',
                'category' => 'Multi-agent',
                'tag_names' => ['May access network', 'Needs credentials'],
            ],
            [
                'name' => 'eslint-turbo-runner',
                'title' => 'Blazing fast ESLint execution utilizing worker threads for instant code feedback on save.',
                'category' => 'Developer tools',
                'tag_names' => ['May read files'],
            ],
            [
                'name' => 'markdown-magic-preview',
                'title' => 'Synchronized live Markdown previewer with KaTeX math rendering, Mermaid diagrams, and PDF export.',
                'category' => 'Vision',
                'tag_names' => ['May read files'],
            ],
            [
                'name' => 'postgres-explain-visualizer',
                'title' => 'Turn complex PostgreSQL EXPLAIN ANALYZE queries into intuitive visual execution flamegraphs.',
                'category' => 'Security & diagnostics',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'jwt-auth-debugger',
                'title' => 'Decode, sign, and verify JSON Web Tokens directly without leaving your editor workspace.',
                'category' => 'Security & diagnostics',
                'tag_names' => ['Needs credentials'],
            ],
            [
                'name' => 'thunder-rest-client',
                'title' => 'A lightweight and clean REST API client with environment variables, cookies, and mock server.',
                'category' => 'Developer tools',
                'tag_names' => ['May access network'],
            ],
            [
                'name' => 'code-spell-checker-pro',
                'title' => 'Intelligent spelling checker for camelCase, snake_case, Vietnamese, and English comments.',
                'category' => 'Developer tools',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'auto-rename-html-tag',
                'title' => 'Automatically rename paired HTML, XML, JSX, and Vue template tags simultaneously.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'path-alias-navigator',
                'title' => 'Smart path autocompletion supporting tsconfig, Vite, and Webpack module path aliases.',
                'category' => 'Developer tools',
                'tag_names' => ['May read files'],
            ],
            [
                'name' => 'bracket-rainbow-pairs',
                'title' => 'Rainbow colorizer for brackets, parentheses, curly braces, and indentation guides.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'catppuccin-palette-theme',
                'title' => 'Soothing pastel theme for modern coders with Latte, Frappé, Macchiato, and Mocha flavors.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'dracula-official-theme',
                'title' => 'Famous dark theme crafted for developers who work late into the night with high contrast syntax.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'github-pull-requests-suite',
                'title' => 'Review pull requests, comment on git diffs, resolve discussions, and merge branches directly.',
                'category' => 'Integrations',
                'tag_names' => ['May access network', 'Needs credentials'],
            ],
            [
                'name' => 'remote-ssh-manager',
                'title' => 'Connect to remote production servers, Docker containers, and WSL environments over SSH.',
                'category' => 'Developer tools',
                'tag_names' => ['May run commands', 'May access network'],
            ],
            [
                'name' => 'todo-tree-analyzer',
                'title' => 'Aggregate, highlight, and filter TODO, FIXME, BUG, and NOTE tags across your entire codebase.',
                'category' => 'Developer tools',
                'tag_names' => ['May read files'],
            ],
            [
                'name' => 'live-browser-sync',
                'title' => 'Local development web server with live reload and multi-device scroll and click sync.',
                'category' => 'UI & workspace',
                'tag_names' => ['May access network'],
            ],
            [
                'name' => 'color-highlight-pro',
                'title' => 'Underline and preview HEX, RGB, HSL, and CSS color variables inline within code files.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'peacock-workspace-colors',
                'title' => 'Subtly color your workspace header and title bar to quickly distinguish project windows.',
                'category' => 'UI & workspace',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'turbo-console-log',
                'title' => 'Generate meaningful, informative console.log statements with variable names and line references.',
                'category' => 'Developer tools',
                'tag_names' => ['May write files'],
            ],
            [
                'name' => 'database-diagram-generator',
                'title' => 'Generate interactive Entity-Relationship (ERD) diagrams from PostgreSQL schemas on the fly.',
                'category' => 'Vision',
                'tag_names' => ['May read files'],
            ],
            [
                'name' => 'vitest-test-runner',
                'title' => 'Ultra-fast unit and integration test explorer with coverage reports for Vitest and Jest.',
                'category' => 'Developer tools',
                'tag_names' => ['May run commands'],
            ],
            [
                'name' => 'regex-live-evaluator',
                'title' => 'Interactive regular expression testing sandbox with match explanations and syntax coloring.',
                'category' => 'Developer tools',
                'tag_names' => ['Manifest checked'],
            ],
            [
                'name' => 'api-load-tester',
                'title' => 'Stress test your HTTP endpoints using k6 scripts directly with live latency graphs.',
                'category' => 'Security & diagnostics',
                'tag_names' => ['May access network', 'May run commands'],
            ],
            [
                'name' => 'bun-runtime-toolkit',
                'title' => 'Run, debug, and package Bun scripts with native TypeScript and JSX execution support.',
                'category' => 'Developer tools',
                'tag_names' => ['May run commands'],
            ],
            [
                'name' => 'ai-code-reviewer',
                'title' => 'Automated pull request code reviewer that detects security vulnerabilities and code smells.',
                'category' => 'Multi-agent',
                'tag_names' => ['May access network'],
            ],
            [
                'name' => 'vision-ui-analyzer',
                'title' => 'Inspect UI component snapshots and detect visual regressions using computer vision.',
                'category' => 'Vision',
                'tag_names' => ['May read files'],
            ],
        ];

        $licenses = [
            PluginLicense::MIT->value,
            PluginLicense::Apache20->value,
            PluginLicense::Bsd3Clause->value,
            PluginLicense::Gpl30->value,
        ];

        $now = now();
        $createdPlugins = [];

        foreach ($pluginTemplates as $index => $tmpl) {
            $author = $users[$index % count($users)];
            $pluginId = (string) Str::uuid();

            // Created 5 to 45 days ago
            $daysAgo = random_int(5, 45);
            $createdAt = $now->copy()->subDays($daysAgo)->subHours(random_int(1, 23))->subMinutes(random_int(1, 59));
            $approvedAt = $createdAt->copy()->addHours(random_int(1, 12));

            // 95% approved, few pending
            $status = ($index < 33) ? PluginStatus::Approved->value : PluginStatus::Pending->value;
            if ($status !== PluginStatus::Approved->value) {
                $approvedAt = null;
            }

            $license = $licenses[array_rand($licenses)];
            $sourceLink = "https://github.com/" . Str::slug($author['name']) . "/" . $tmpl['name'];

            // Upsert plugin
            $existing = DB::table('plugins')->where('user_id', $author['id'])->where('name', $tmpl['name'])->first();

            if ($existing) {
                $pluginId = $existing->id;
                DB::table('plugins')->where('id', $pluginId)->update([
                    'title' => $tmpl['title'],
                    'license' => $license,
                    'status' => $status,
                    'approved_at' => $approvedAt,
                    'source_link' => $sourceLink,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('plugins')->insert([
                    'id' => $pluginId,
                    'name' => $tmpl['name'],
                    'user_id' => $author['id'],
                    'title' => $tmpl['title'],
                    'license' => $license,
                    'approved_at' => $approvedAt,
                    'status' => $status,
                    'source_link' => $sourceLink,
                    'view_count' => 0,
                    'created_at' => $createdAt,
                    'updated_at' => $now,
                ]);
            }

            // Sync category
            $category = $categoryMap->get($tmpl['category']) ?? $categories->first();
            if ($category) {
                DB::table('category_plugin')->updateOrInsert(
                    ['plugin_id' => $pluginId],
                    ['category_id' => $category->id]
                );
            }

            // Sync tags
            DB::table('plugin_tag')->where('plugin_id', $pluginId)->delete();
            $tagRecords = [];
            foreach ($tmpl['tag_names'] as $tagName) {
                $tag = $tags->firstWhere('name', $tagName);
                if ($tag) {
                    $tagRecords[] = [
                        'plugin_id' => $pluginId,
                        'tag_id' => $tag->id,
                    ];
                }
            }
            if (! empty($tagRecords)) {
                DB::table('plugin_tag')->insert($tagRecords);
            }

            $createdPlugins[] = [
                'id' => $pluginId,
                'name' => $tmpl['name'],
                'title' => $tmpl['title'],
                'created_at' => $createdAt,
                'status' => $status,
                'user_id' => $author['id'],
            ];
        }

        return $createdPlugins;
    }

    /**
     * Seed views, stars, and nested comments with tiered engagement.
     *
     * @param array<int, array{id: string, name: string, title: string, created_at: Carbon, status: string, user_id: string}> $plugins
     * @param array<int, array{id: string, name: string, email: string}> $users
     */
    private function seedEngagement(array $plugins, array $users): void
    {
        $now = now();
        $daysLimit = (int) config('plugins.trending.days_limit', 30);

        // Pre-clean engagement tables for fresh, consistent data
        $pluginIds = array_column($plugins, 'id');
        DB::table('plugin_daily_views')->whereIn('plugin_id', $pluginIds)->delete();
        DB::table('stars')->whereIn('plugin_id', $pluginIds)->delete();
        DB::table('comments')->whereIn('plugin_id', $pluginIds)->delete();

        $commentSamples = [
            'This extension is an absolute game changer! Boosted our team productivity instantly.',
            'Clean, responsive, and works out of the box with zero configuration needed.',
            'Does this work nicely with multi-root workspaces in VS Code?',
            'Yes, just make sure to add "multiRoot": true in your settings.json.',
            'Thanks! That resolved the issue for me.',
            'Any plans to support Linux ARM64 in the upcoming release?',
            'We are already working on ARM64 support, expected in v1.2 next week!',
            'I love the UI minimalism and fast search response time.',
            'Found a minor parsing issue with large JSON files, opened an issue on GitHub.',
            'Fixed in the latest patch, please update to the latest version!',
            'Great tool, highly recommended for any full-stack developer.',
            'The dark mode styling matches Dracula theme perfectly.',
            'Super fast and lightweight, uses almost no background memory.',
            'Could you add support for TypeScript 5.5 syntax?',
            'TypeScript 5.5 support is already included in the latest v2.1 release.',
            'A must-have extension for my daily development workflow.',
            'Reduced my debugging time by half, thank you so much!',
            'Is there any keyboard shortcut to toggle the panel?',
            'Yes, use Ctrl+Shift+P and search for Toggle Panel.',
            'Brilliant work, giving 5 stars without hesitation.',
        ];

        foreach ($plugins as $index => $plugin) {
            $pluginId = $plugin['id'];
            $createdAt = $plugin['created_at'];

            // Only generate high engagement for approved plugins
            if ($plugin['status'] !== PluginStatus::Approved->value) {
                continue;
            }

            // Tiering: Top 6 (viral), next 12 (popular), remaining (average)
            if ($index < 6) {
                $tier = 1; // Viral
                $minDailyViews = 30;
                $maxDailyViews = 120;
                $starRatio = random_int(60, 85); // 60-85% of users star
                $commentCount = random_int(12, 22);
            } elseif ($index < 18) {
                $tier = 2; // Popular
                $minDailyViews = 8;
                $maxDailyViews = 35;
                $starRatio = random_int(25, 55); // 25-55% of users star
                $commentCount = random_int(4, 10);
            } else {
                $tier = 3; // Average
                $minDailyViews = 1;
                $maxDailyViews = 10;
                $starRatio = random_int(5, 20); // 5-20% of users star
                $commentCount = random_int(0, 3);
            }

            // 1. Seed Views in plugin_daily_views
            $totalViews = 0;
            $dailyViewRows = [];

            for ($i = 0; $i < $daysLimit; $i++) {
                $dayDate = $now->copy()->subDays($i)->startOfDay();
                if ($dayDate->lt($createdAt->copy()->startOfDay())) {
                    continue;
                }

                $dailyCount = random_int($minDailyViews, $maxDailyViews);
                $totalViews += $dailyCount;

                $dailyViewRows[] = [
                    'plugin_id' => $pluginId,
                    'date' => $dayDate->format('Y-m-d'),
                    'views_count' => $dailyCount,
                    'created_at' => $dayDate,
                    'updated_at' => $dayDate,
                ];
            }

            if (! empty($dailyViewRows)) {
                DB::table('plugin_daily_views')->insert($dailyViewRows);
            }

            // Update total view_count on plugin
            DB::table('plugins')->where('id', $pluginId)->update(['view_count' => $totalViews]);

            // 2. Seed Stars
            $targetStarCount = (int) round(count($users) * ($starRatio / 100));
            $shuffledUsers = $users;
            shuffle($shuffledUsers);
            $starringUsers = array_slice($shuffledUsers, 0, $targetStarCount);

            $starRows = [];
            foreach ($starringUsers as $starrer) {
                $starDaysAgo = random_int(0, min($daysLimit, (int) $createdAt->diffInDays($now)));
                $starDate = $now->copy()->subDays($starDaysAgo)->subHours(random_int(1, 23));

                $starRows[] = [
                    'plugin_id' => $pluginId,
                    'user_id' => $starrer['id'],
                    'created_at' => $starDate,
                    'updated_at' => $starDate,
                ];
            }

            if (! empty($starRows)) {
                DB::table('stars')->insert($starRows);
            }

            // 3. Seed Nested Comments
            $rootCommentIds = [];
            for ($c = 0; $c < $commentCount; $c++) {
                $author = $users[array_rand($users)];
                $commentDaysAgo = random_int(0, min($daysLimit, (int) $createdAt->diffInDays($now)));
                $commentDate = $now->copy()->subDays($commentDaysAgo)->subHours(random_int(1, 23));

                $isReply = (! empty($rootCommentIds) && random_int(1, 100) <= 40);
                $parentId = $isReply ? $rootCommentIds[array_rand($rootCommentIds)] : null;

                $commentId = (string) Str::uuid();
                $content = $commentSamples[array_rand($commentSamples)];

                DB::table('comments')->insert([
                    'id' => $commentId,
                    'plugin_id' => $pluginId,
                    'author_id' => $author['id'],
                    'parent_comment_id' => $parentId,
                    'content' => $content,
                    'hidden_at' => null,
                    'created_at' => $commentDate,
                    'updated_at' => $commentDate,
                ]);

                if ($parentId === null) {
                    $rootCommentIds[] = $commentId;
                }
            }
        }
    }
}

