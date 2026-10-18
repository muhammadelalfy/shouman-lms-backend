<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/me';

    public function test_returns_401_without_token(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_returns_current_user_with_roles_and_effective_permissions(): void
    {
        $manager = $this->createUser(Role::AcademyManager, ['email' => 'manager@example.com']);
        Sanctum::actingAs($manager);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.email', 'manager@example.com')
            ->assertJsonPath('data.roles', ['academy-manager'])
            ->assertJsonPath('data.permissions', ['offerings.manage', 'offerings.publish', 'offerings.view', 'users.manage-status', 'users.view']);
    }

    public function test_update_changes_name_and_phone(): void
    {
        $user = $this->createUser();
        Sanctum::actingAs($user);

        $this->patchJson(self::URL, ['name' => 'محمد علي', 'phone' => '+966500000000'])
            ->assertOk()
            ->assertJsonPath('data.name', 'محمد علي')
            ->assertJsonPath('data.phone', '+966500000000');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'محمد علي', 'phone' => '+966500000000']);
    }

    public function test_update_ignores_email_status_and_roles(): void
    {
        $user = $this->createUser(attributes: ['email' => 'student@example.com']);
        Sanctum::actingAs($user);

        $this->patchJson(self::URL, [
            'email' => 'hijack@example.com',
            'status' => 'disabled',
            'roles' => ['system-administrator'],
        ])->assertOk();

        $user->refresh();
        $this->assertSame('student@example.com', $user->email);
        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertSame(['student'], $user->getRoleNames()->all());
    }

    public function test_phone_used_by_another_account_returns_422(): void
    {
        $this->createUser(attributes: ['phone' => '+201001234567']);
        Sanctum::actingAs($this->createUser());

        $this->patchJson(self::URL, ['phone' => '+201001234567'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone' => 'رقم الهاتف مستخدم من قبل.']);
    }

    public function test_keeping_own_phone_passes_uniqueness(): void
    {
        $user = $this->createUser(attributes: ['phone' => '+201001234567']);
        Sanctum::actingAs($user);

        $this->patchJson(self::URL, ['phone' => '+201001234567'])->assertOk();
    }
}
