<?php

declare(strict_types=1);

namespace Modules\Identity\Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;
use Modules\Identity\Enums\Role;
use Modules\Identity\Models\User;
use Modules\Identity\Tests\Concerns\CreatesUsers;
use Tests\TestCase;

class CreateSystemAdministratorCommandTest extends TestCase
{
    use CreatesUsers, RefreshDatabase;

    /**
     * @param  array<string, string>  $arguments
     */
    private function command(array $arguments): PendingCommand
    {
        $command = $this->artisan('identity:create-admin', $arguments);
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function test_creates_verified_administrator_with_prompted_password(): void
    {
        $this->command(['email' => 'Root@Example.com', '--name' => 'مدير النظام'])
            ->expectsQuestion('Password', 'Secret123')
            ->assertSuccessful();

        $user = User::query()->where('email', 'root@example.com')->sole();
        $this->assertSame('مدير النظام', $user->name);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->hasRole(Role::SystemAdministrator));
        $this->assertTrue(Hash::check('Secret123', $user->getAuthPassword()));
    }

    public function test_weak_password_fails_without_creating_account(): void
    {
        $this->command(['email' => 'root@example.com'])
            ->expectsQuestion('Password', 'short')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'root@example.com']);
    }

    public function test_existing_email_fails(): void
    {
        $this->createUser(attributes: ['email' => 'root@example.com']);

        $this->command(['email' => 'root@example.com'])
            ->expectsQuestion('Password', 'Secret123')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
    }
}
