<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Policies;

use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Modules\Identity\Enums\Permission;
use Modules\Identity\Enums\Role;
use Modules\Identity\Policies\UserPolicy;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role as RoleModel;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @return array<string, array{Role, bool}>
     */
    public static function viewAnyMatrix(): array
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

    #[DataProvider('viewAnyMatrix')]
    public function test_view_any_follows_users_view_permission(Role $actorRole, bool $expected): void
    {
        $this->assertSame($expected, (new UserPolicy)->viewAny($this->createUser($actorRole)));
    }

    /**
     * @return array<string, array{Role, Role, bool}>
     */
    public static function updateStatusMatrix(): array
    {
        return [
            'manager on student' => [Role::AcademyManager, Role::Student, true],
            'manager on instructor' => [Role::AcademyManager, Role::Instructor, true],
            'manager on administrator' => [Role::AcademyManager, Role::SystemAdministrator, false],
            'administrator on administrator' => [Role::SystemAdministrator, Role::SystemAdministrator, true],
            'administrator on manager' => [Role::SystemAdministrator, Role::AcademyManager, true],
            'student on student' => [Role::Student, Role::Student, false],
            'supervisor on student' => [Role::Supervisor, Role::Student, false],
        ];
    }

    #[DataProvider('updateStatusMatrix')]
    public function test_update_status_matrix(Role $actorRole, Role $subjectRole, bool $expected): void
    {
        $actor = $this->createUser($actorRole);
        $subject = $this->createUser($subjectRole);

        $this->assertSame($expected, (new UserPolicy)->updateStatus($actor, $subject));
    }

    public function test_update_status_denies_acting_on_self_even_for_administrators(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);

        $this->assertFalse((new UserPolicy)->updateStatus($administrator, $administrator));
    }

    public function test_administrator_may_assign_any_role_including_to_self(): void
    {
        $administrator = $this->createUser(Role::SystemAdministrator);
        $policy = new UserPolicy;

        $this->assertTrue($policy->assignRoles($administrator, $this->createUser(), [Role::SystemAdministrator]));
        $this->assertTrue($policy->assignRoles($administrator, $administrator, [Role::Instructor]));
    }

    public function test_role_without_assign_permission_is_denied(): void
    {
        $this->assertFalse((new UserPolicy)->assignRoles(
            $this->createUser(Role::AcademyManager),
            $this->createUser(),
            [Role::Instructor],
        ));
    }

    /**
     * @return array<string, array{string, list<Role>, bool}>
     */
    public static function delegatedAssignMatrix(): array
    {
        return [
            'grant instructor to student' => ['student', [Role::Instructor], true],
            'grant administrator to student' => ['student', [Role::SystemAdministrator], false],
            'change an administrator' => ['administrator', [Role::Instructor], false],
            'change own roles' => ['self', [Role::Instructor], false],
        ];
    }

    /**
     * @param  list<Role>  $requested
     */
    #[DataProvider('delegatedAssignMatrix')]
    public function test_delegated_assign_permission_cannot_escalate(string $subjectKind, array $requested, bool $expected): void
    {
        RoleModel::findByName(Role::AcademyManager->value)->givePermissionTo(Permission::UsersAssignRoles->value);
        $actor = $this->createUser(Role::AcademyManager);
        $subject = match ($subjectKind) {
            'student' => $this->createUser(),
            'administrator' => $this->createUser(Role::SystemAdministrator),
            'self' => $actor,
            default => throw new InvalidArgumentException("Unknown subject kind [{$subjectKind}]."),
        };

        $this->assertSame($expected, (new UserPolicy)->assignRoles($actor, $subject, $requested));
    }
}
