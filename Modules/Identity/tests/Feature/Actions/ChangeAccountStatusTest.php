<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Actions;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Identity\Actions\ChangeAccountStatus;
use Modules\Identity\Enums\AccountStatus;
use Modules\Identity\Enums\Role;
use Modules\Identity\Exceptions\IdentityRuleViolation;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

/**
 * Over HTTP the policy already prevents this sequence; the action must still
 * hold the invariant on its own (e.g. against concurrent requests).
 */
class ChangeAccountStatusTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    public function test_disabling_the_only_active_administrator_is_refused(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $actor = $this->createUser(Role::AcademyManager);

        try {
            $this->app->make(ChangeAccountStatus::class)->handle($actor, $administrator, AccountStatus::Disabled);
            $this->fail('Expected IdentityRuleViolation.');
        } catch (IdentityRuleViolation $violation) {
            $this->assertSame('last_system_administrator', $violation->errorCode());
        }

        $this->assertSame(AccountStatus::Active, $administrator->fresh()?->status);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_disabling_one_of_two_active_administrators_succeeds(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $other = $this->createUser(Role::SystemAdministrator);

        $this->app->make(ChangeAccountStatus::class)->handle($administrator, $other, AccountStatus::Disabled);

        $this->assertSame(AccountStatus::Disabled, $other->fresh()?->status);
    }
}
