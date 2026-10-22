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
}
