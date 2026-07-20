<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use App\Services\Audit\AuditLogData;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_audit_log_for_model_change(): void
    {
        $employee = Employee::create([
            'employee_code' => 'EMP001',
            'name' => '山田 太郎',
            'email' => 'employee@example.com',
        ]);

        $user = User::create([
            'employee_id' => $employee->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $service = app(AuditLogService::class);

        $auditLog = $service->record(new AuditLogData(
            event: 'employee.updated',
            auditable: $employee,
            beforeValues: ['name' => '山田 太郎'],
            afterValues: ['name' => '山田 一郎'],
            reason: '氏名変更のため',
            user: $user,
            ipAddress: '127.0.0.1',
            userAgent: 'FeatureTest',
            requestId: 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ));

        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'user_id' => $user->id,
            'event' => 'employee.updated',
            'auditable_type' => Employee::class,
            'auditable_id' => (string) $employee->id,
            'target_table' => 'employees',
            'target_id' => (string) $employee->id,
            'reason' => '氏名変更のため',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'FeatureTest',
            'request_id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        ]);

        $this->assertSame(['name' => '山田 太郎'], $auditLog->before_values);
        $this->assertSame(['name' => '山田 一郎'], $auditLog->after_values);
    }

    public function test_request_id_header_is_attached_to_web_response(): void
    {
        $response = $this->withHeader('X-Request-Id', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb')
            ->get('/');

        $response->assertOk();
        $response->assertHeader('X-Request-Id', 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb');
    }
}

