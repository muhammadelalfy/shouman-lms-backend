<?php

declare(strict_types=1);

namespace Modules\Course\Tests\Feature\Policies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Course\Models\Offering;
use Modules\Course\Policies\OfferingPolicy;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfferingPolicyTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @return array<string, array{Role, bool}>
     */
    public static function authoringRoles(): array
    {
        return [
            'student' => [Role::Student, false],
            'instructor' => [Role::Instructor, false],
            'supervisor' => [Role::Supervisor, false],
            'evaluator' => [Role::Evaluator, false],
            'academy manager' => [Role::AcademyManager, true],
            'system administrator' => [Role::SystemAdministrator, true],
        ];
    }

    #[DataProvider('authoringRoles')]
    public function test_every_ability_follows_the_default_role_grants(Role $role, bool $expected): void
    {
        $actor = $this->createUser($role);
        $offering = Offering::factory()->create();
        $policy = new OfferingPolicy;

        $this->assertSame($expected, $policy->viewAny($actor));
        $this->assertSame($expected, $policy->view($actor, $offering));
        $this->assertSame($expected, $policy->create($actor));
        $this->assertSame($expected, $policy->update($actor, $offering));
        $this->assertSame($expected, $policy->delete($actor, $offering));
        $this->assertSame($expected, $policy->publish($actor, $offering));
    }
}
