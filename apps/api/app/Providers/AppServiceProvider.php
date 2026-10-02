<?php

namespace App\Providers;

use App\Contracts\AuthRepositoryInterface;
use App\Contracts\CommentRepositoryInterface;
use App\Contracts\PluginRepositoryInterface;
use App\Contracts\UserRepositoryInterface;
use App\Repositories\AuthRepository;
use App\Repositories\CommentRepository;
use App\Repositories\PluginRepository;
use App\Repositories\UserRepository;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(PluginRepositoryInterface::class, PluginRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('submit-plugin', fn(Request $request): Limit => Limit::perMinute($this->resolveRateLimit('rate_limits.submit_plugin_per_minute'))
            ->by($this->userOrIpKey($request)));

        RateLimiter::for('register', fn(Request $request): array => [
            Limit::perMinute(config('auth.limiters.register_per_email'))->by($this->authThrottleKey($request)),
            Limit::perMinute(config('auth.limiters.register_per_account'))->by('account:' . $this->normalizedEmail($request)),
            Limit::perMinute(config('auth.limiters.register_per_ip'))->by($request->ip()),
        ]);
        RateLimiter::for('login', fn(Request $request): array => [
            Limit::perMinute(config('auth.limiters.login_per_email'))->by($this->authThrottleKey($request)),
            Limit::perMinute(config('auth.limiters.login_per_ip'))->by($request->ip()),
        ]);
        RateLimiter::for('password-reset-link', fn(Request $request): array => [
            Limit::perMinute(config('auth.limiters.password_reset_link_per_email'))->by($this->authThrottleKey($request)),
            Limit::perMinute(config('auth.limiters.password_reset_link_per_account'))->by('account:' . $this->normalizedEmail($request)),
            Limit::perMinute(config('auth.limiters.password_reset_link_per_ip'))->by($request->ip()),
        ]);
        RateLimiter::for('password-reset', fn(Request $request): array => [
            Limit::perMinute(config('auth.limiters.password_reset_per_email'))->by($this->authThrottleKey($request)),
            Limit::perMinute(config('auth.limiters.password_reset_per_account'))->by('account:' . $this->normalizedEmail($request)),
            Limit::perMinute(config('auth.limiters.password_reset_per_ip'))->by($request->ip()),
        ]);

        RateLimiter::for('api', fn(Request $request): Limit => Limit::perMinute(config('app.limiters.api'))->by($this->userOrIpKey($request)));

        RateLimiter::for('strict', fn(Request $request): Limit => Limit::perMinute(config('app.limiters.strict'))->by($this->userOrIpKey($request)));

        RateLimiter::for('auth', function (Request $request): Limit {
            return Limit::perMinute($this->resolveRateLimit('rate_limits.auth_per_minute'))
                ->by((string) $request->ip());
        });

        RateLimiter::for('create-comment', fn(Request $request): Limit => Limit::perMinute($this->resolveRateLimit('comments.create_per_minute'))
            ->by($this->userOrIpKey($request)));

        Scramble::configure()
            ->withDocumentTransformers(function (OpenApi $openApi): void {
                $openApi->secure(SecurityScheme::http('bearer'));
            });

        Scramble::routes(function (Route $route): bool {
            return Str::startsWith($route->uri(), 'api/v1');
        });
    }

    private function userOrIpKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }

    private function normalizedEmail(Request $request): string
    {
        $email = $request->input('email');

        return Str::lower(is_string($email) ? $email : '');
    }

    private function authThrottleKey(Request $request): string
    {
        return $this->normalizedEmail($request) . '|' . $request->ip();
    }

    /**
     * Read a rate limit from config, guaranteeing it is always greater than 0.
     *
     * A missing or invalid value (0, negative, empty string) would reject every
     * request through Limit::perMinute(0), so floor it at 1 to avoid taking the
     * endpoint down on a bad deploy.
     *
     * Minimum is 1
     */
    private function resolveRateLimit(string $configKey): int
    {
        return max(1, (int) config($configKey));
    }
}
