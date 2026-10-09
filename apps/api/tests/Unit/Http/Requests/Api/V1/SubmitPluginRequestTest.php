<?php

namespace Tests\Unit\Http\Requests\Api\V1;

use App\Http\Requests\Api\V1\SubmitPluginRequest;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises `SubmitPluginRequest` directly, without the HTTP kernel.
 *
 * The global `TrimStrings` middleware trims incoming input before it ever
 * reaches a FormRequest, so a feature test cannot tell whether the hand-written
 * `prepareForValidation()` works. Building the request here keeps these tests
 * pinned to the class's own logic.
 */
class SubmitPluginRequestTest extends TestCase
{
    /**
     * Builds the request without the HTTP kernel, merges a valid base payload
     * so a single-field override is still a complete submission, and returns
     * the validated data.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validatedInput(array $overrides): array
    {
        $request = SubmitPluginRequest::create('/api/v1/plugins', 'POST', array_merge([
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'category' => 'Developer tools',
            'source_link' => 'https://example.com/plugin',
        ], $overrides));

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));

        $request->validateResolved();

        return $request->validated();
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function paddedFieldProvider(): array
    {
        return [
            'name' => [['name' => '  Laravel Debugbar  ']],
            'title' => [['title' => "\t Debugbar for Laravel \n"]],
            'license' => [['license' => '  MIT  ']],
            'source_link' => [['source_link' => '  https://example.com/plugin  ']],
        ];
    }

    #[DataProvider('paddedFieldProvider')]
    public function test_surrounding_whitespace_is_trimmed_from_validated_input(array $overrides): void
    {
        $input = $this->validatedInput($overrides);

        foreach ($overrides as $field => $value) {
            $this->assertSame(trim($value), $input[$field]);
        }
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function whitespaceOnlyFieldProvider(): array
    {
        return [
            'name' => [['name' => '     ']],
            'name of tabs and newlines' => [['name' => "\t\n\r  "]],
            'title' => [['title' => '   ']],
            'license' => [['license' => '  ']],
            'source_link' => [['source_link' => '     ']],
        ];
    }

    #[DataProvider('whitespaceOnlyFieldProvider')]
    public function test_whitespace_only_values_fail_the_required_rule(array $overrides): void
    {
        $this->expectException(ValidationException::class);

        $this->validatedInput($overrides);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function nonStringFieldProvider(): array
    {
        return [
            'name as array' => [['name' => ['Laravel Debugbar']]],
            'name as integer' => [['name' => 12345]],
            'title as float' => [['title' => 1.5]],
            'title as boolean' => [['title' => true]],
            'license as array' => [['license' => ['MIT']]],
            'source_link as array' => [['source_link' => ['https://example.com/plugin']]],
        ];
    }

    /**
     * The trim must not coerce a non-string into a string, and the `string`
     * rule has to reject it.
     */
    #[DataProvider('nonStringFieldProvider')]
    public function test_non_string_values_are_left_untouched_and_rejected(array $overrides): void
    {
        $field = array_key_first($overrides);
        $value = $overrides[$field];

        $request = SubmitPluginRequest::create('/api/v1/plugins', 'POST', array_merge([
            'name' => 'Laravel Debugbar',
            'title' => 'Debugbar for Laravel',
            'license' => 'MIT',
            'source_link' => 'https://example.com/plugin',
        ], $overrides));

        $this->assertSame($value, $request->input($field));

        $this->expectException(ValidationException::class);

        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->validateResolved();
    }

    /**
     * `prepareForValidation()` must run before the `max` rule, otherwise a
     * padded value that fits the limit once trimmed would be rejected.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function maxLengthFieldProvider(): array
    {
        return [
            'name' => ['name', 255],
            'title' => ['title', 255],
            'source_link' => ['source_link', 2048],
        ];
    }

    #[DataProvider('maxLengthFieldProvider')]
    public function test_max_length_is_measured_after_trimming(string $field, int $max): void
    {
        $padded = $field === 'source_link'
            ? 'https://example.com/'.str_repeat('a', $max - strlen('https://example.com/'))
            : str_repeat('a', $max);

        $validated = $this->validatedInput([
            $field => '  '.$padded."\t\n",
        ]);

        $this->assertSame($padded, $validated[$field]);
        $this->assertSame($max, strlen($validated[$field]));
    }

    /**
     * A value that is still over the limit after trimming must be rejected, so
     * trimming cannot be used to slip past `max`.
     *
     * @return array<string, array{0: string, 1: int}>
     */
    public static function oversizedFieldProvider(): array
    {
        return [
            'name' => ['name', 255],
            'title' => ['title', 255],
            'source_link' => ['source_link', 2048],
        ];
    }

    #[DataProvider('oversizedFieldProvider')]
    public function test_max_length_is_still_enforced_after_trimming(string $field, int $max): void
    {
        $prefix = $field === 'source_link' ? 'https://example.com/' : '';
        $value = $prefix.str_repeat('a', $max + 1 - strlen($prefix));

        $this->expectException(ValidationException::class);

        $this->validatedInput([$field => '  '.$value.'  ']);
    }
}
