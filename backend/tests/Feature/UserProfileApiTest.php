<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_identifier_is_not_allowed_for_public_profile_route(): void
    {
        $this->getJson('/api/v1/users/guest')
            ->assertNotFound();
    }
}
