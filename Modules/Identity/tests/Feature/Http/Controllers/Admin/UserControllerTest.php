<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Identity\Enums\Role;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    private const string URL = '/api/v1/admin/users';

    public function test_student_gets_403(): void
    {
        Sanctum::actingAs($this->createUser());

        $this->getJson(self::URL)->assertForbidden();
    }

    public function test_lists_users_newest_first_with_roles(): void
    {
        $manager = $this->createUser(Role::AcademyManager);
        $older = $this->createUser(Role::Student);
        $newer = $this->createUser(Role::Instructor);
        Sanctum::actingAs($manager);

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.0.roles', ['instructor'])
            ->assertJsonPath('data.1.id', $older->id)
            ->assertJsonPath('data.2.id', $manager->id);
    }

    public function test_filters_by_status(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $disabled = $this->createUser(attributes: ['status' => 'disabled']);
        $this->createUser();

        $this->getJson(self::URL.'?status=disabled')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $disabled->id);
    }

    public function test_filters_by_role(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $evaluator = $this->createUser(Role::Evaluator);
        $this->createUser(Role::Student);

        $this->getJson(self::URL.'?role=evaluator')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $evaluator->id);
    }

    public function test_search_matches_name_or_email_fragment(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager, ['name' => 'المدير', 'email' => 'manager@example.com']));
        $byName = $this->createUser(attributes: ['name' => 'عبد الرحمن', 'email' => 'a@example.com']);
        $byEmail = $this->createUser(attributes: ['name' => 'خالد', 'email' => 'rahman.k@example.com']);
        $this->createUser(attributes: ['name' => 'سعيد', 'email' => 'saeed@example.com']);

        $ids = $this->getJson(self::URL.'?search=الرحمن')->assertOk()->json('data.*.id');
        $this->assertSame([$byName->id], $ids);

        $ids = $this->getJson(self::URL.'?search=rahman')->assertOk()->json('data.*.id');
        $this->assertSame([$byEmail->id], $ids);
    }

    public function test_cursor_paginates_without_skipping_or_repeating(): void
    {
        $manager = $this->createUser(Role::AcademyManager);
        $first = $this->createUser();
        $second = $this->createUser();
        Sanctum::actingAs($manager);

        $page1 = $this->getJson(self::URL.'?per_page=2')->assertOk();
        $this->assertSame([$second->id, $first->id], $page1->json('data.*.id'));

        $page2 = $this->getJson(self::URL.'?per_page=2&cursor='.$page1->json('meta.next_cursor'))->assertOk();
        $this->assertSame([$manager->id], $page2->json('data.*.id'));
        $this->assertNull($page2->json('meta.next_cursor'));
    }

    public function test_per_page_above_100_returns_422(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));

        $this->getJson(self::URL.'?per_page=101')->assertUnprocessable()->assertJsonValidationErrors(['per_page']);
    }

    public function test_show_returns_user_with_roles(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));
        $student = $this->createUser(attributes: ['email' => 'student@example.com']);

        $this->getJson(self::URL.'/'.$student->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'student@example.com')
            ->assertJsonPath('data.roles', ['student']);
    }

    public function test_show_returns_404_for_missing_user(): void
    {
        Sanctum::actingAs($this->createUser(Role::AcademyManager));

        $this->getJson(self::URL.'/999999')->assertNotFound();
    }

    public function test_show_for_student_returns_403(): void
    {
        $student = $this->createUser();
        Sanctum::actingAs($student);

        $this->getJson(self::URL.'/'.$student->id)->assertForbidden();
    }
}
