<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_foundation_tables_exist(): void
    {
        foreach ([
            'employees',
            'users',
            'roles',
            'permissions',
            'role_user',
            'permission_role',
            'audit_logs',
            'number_sequences',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Table [{$table}] does not exist.");
        }
    }

    public function test_employees_table_has_required_columns(): void
    {
        foreach ([
            'employee_code',
            'name',
            'name_kana',
            'email',
            'is_active',
            'disabled_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('employees', $column), "Column [employees.{$column}] does not exist.");
        }
    }

    public function test_audit_logs_table_has_required_columns(): void
    {
        foreach ([
            'occurred_at',
            'user_id',
            'approved_by_user_id',
            'event',
            'target_table',
            'target_id',
            'before_values',
            'after_values',
            'reason',
            'ip_address',
            'user_agent',
            'request_id',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('audit_logs', $column), "Column [audit_logs.{$column}] does not exist.");
        }
    }

    public function test_number_sequences_table_has_required_columns(): void
    {
        foreach ([
            'code',
            'name',
            'prefix',
            'suffix',
            'current_number',
            'padding_length',
            'reset_type',
            'last_reset_on',
            'is_active',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('number_sequences', $column), "Column [number_sequences.{$column}] does not exist.");
        }
    }
}

