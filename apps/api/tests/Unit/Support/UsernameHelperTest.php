<?php

namespace Tests\Unit\Support;

use App\Support\UsernameHelper;
use Tests\TestCase;

class UsernameHelperTest extends TestCase
{
    public function test_it_generates_a_username_with_a_readable_shape(): void
    {
        $this->assertMatchesRegularExpression('/^user-[a-z0-9]{8}$/', UsernameHelper::generate());
    }

    public function test_it_leaves_out_the_characters_that_are_easily_mistaken(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $this->assertDoesNotMatchRegularExpression('/[lo01]/', UsernameHelper::generate());
        }
    }

    public function test_is_uuid_format_returns_true_for_valid_uuids(): void
    {
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '550E8400-E29B-41D4-A716-446655440000', // uppercase
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'f47ac10b-58cc-4372-a567-0e02b2c3d479',
        ];

        foreach ($uuids as $uuid) {
            $this->assertTrue(UsernameHelper::isUuidFormat($uuid), "Expected {$uuid} to be recognized as UUID format");
        }
    }

    public function test_is_uuid_format_returns_false_for_valid_usernames(): void
    {
        $usernames = [
            'user-abc123',
            'john-doe',
            'jane_doe',  // underscore not in pattern but let's test
            'myusername',
            'user-name-123',
            'abc',
            'a-b-c',
            'user-abcdefgh', // auto-generated format
        ];

        foreach ($usernames as $username) {
            $this->assertFalse(UsernameHelper::isUuidFormat($username), "Expected {$username} to NOT be recognized as UUID format");
        }
    }

    public function test_is_uuid_format_returns_false_for_auto_generated_usernames(): void
    {
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $username = UsernameHelper::generate();
            $this->assertFalse(UsernameHelper::isUuidFormat($username), "Auto-generated username {$username} should not be UUID format");
        }
    }

    public function test_uuid_pattern_matches_rfc4122_format(): void
    {
        // Test the pattern directly
        $this->assertMatchesRegularExpression(UsernameHelper::UUID_PATTERN, '550e8400-e29b-41d4-a716-446655440000');
        $this->assertDoesNotMatchRegularExpression(UsernameHelper::UUID_PATTERN, 'user-abc123');
        $this->assertDoesNotMatchRegularExpression(UsernameHelper::UUID_PATTERN, 'john-doe');
        $this->assertDoesNotMatchRegularExpression(UsernameHelper::UUID_PATTERN, '550e8400-e29b-41d4-a716'); // too short
        $this->assertDoesNotMatchRegularExpression(UsernameHelper::UUID_PATTERN, '550e8400-e29b-41d4-a716-446655440000-extra'); // too long
    }
}
