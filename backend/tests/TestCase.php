<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost:5173');
        $this->withHeader('Referer', 'http://localhost:5173/');
        $this->withHeader('Accept', 'application/json');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (Schema::hasTable('roles')) {
            foreach (['admin', 'landlord', 'seeker'] as $role) {
                Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
            }
        }
    }
}
