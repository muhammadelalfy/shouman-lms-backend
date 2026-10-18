<?php

declare(strict_types=1);

namespace Modules\Administration\Tests\Feature\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Modules\Administration\Actions\RecordAuditEntry;
use Modules\Administration\Models\AuditLog;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): AuditLog
    {
        return AuditLog::query()->create([
            'actor_id' => 1,
            'action' => 'identity.user.status_changed',
            'subject_type' => 'user',
            'subject_id' => '2',
            'old_values' => ['status' => 'active'],
            'new_values' => ['status' => 'disabled'],
        ]);
    }

    public function test_entries_cannot_be_updated(): void
    {
        $entry = $this->entry();

        $this->expectException(LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_entries_cannot_be_deleted(): void
    {
        $entry = $this->entry();

        try {
            $entry->delete();
            $this->fail('Expected LogicException.');
        } catch (LogicException) {
            $this->assertDatabaseHas('audit_logs', ['id' => $entry->id]);
        }
    }

    public function test_entry_captures_request_id_and_client_ip_from_the_request(): void
    {
        $this->withHeader('X-Request-Id', 'req-12345678')->getJson('/health');

        $entry = $this->app->make(RecordAuditEntry::class)->handle(1, 'identity.user.status_changed', 'user', 2);

        $this->assertSame('req-12345678', $entry->request_id);
        $this->assertSame('127.0.0.1', $entry->ip_address);
    }
}
