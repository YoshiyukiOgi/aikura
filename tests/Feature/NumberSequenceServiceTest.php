<?php

namespace Tests\Feature;

use App\Exceptions\NumberSequence\NumberSequenceException;
use App\Models\NumberSequence;
use App\Services\NumberSequence\NumberSequenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NumberSequenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_padded_number_with_prefix_and_suffix(): void
    {
        NumberSequence::create([
            'code' => 'shipment',
            'name' => '出荷伝票番号',
            'prefix' => 'S-{YYYY}{MM}-',
            'suffix' => '-A',
            'current_number' => 0,
            'padding_length' => 4,
            'reset_type' => 'none',
        ]);

        $result = app(NumberSequenceService::class)->next(
            code: 'shipment',
            issuedAt: Carbon::parse('2026-05-22'),
        );

        $this->assertSame('shipment', $result->sequenceCode);
        $this->assertSame(1, $result->number);
        $this->assertSame('S-202605-0001-A', $result->formatted);
    }

    public function test_it_increments_existing_sequence(): void
    {
        NumberSequence::create([
            'code' => 'invoice',
            'name' => '請求番号',
            'prefix' => 'I-',
            'current_number' => 9,
            'padding_length' => 3,
            'reset_type' => 'none',
        ]);

        $result = app(NumberSequenceService::class)->next('invoice');

        $this->assertSame(10, $result->number);
        $this->assertSame('I-010', $result->formatted);
        $this->assertDatabaseHas('number_sequences', [
            'code' => 'invoice',
            'current_number' => 10,
        ]);
    }

    public function test_it_resets_by_month(): void
    {
        NumberSequence::create([
            'code' => 'monthly_invoice',
            'name' => '月次請求番号',
            'prefix' => 'I-{YYYY}{MM}-',
            'current_number' => 25,
            'padding_length' => 3,
            'reset_type' => 'month',
            'last_reset_on' => '2026-04-01',
        ]);

        $result = app(NumberSequenceService::class)->next(
            code: 'monthly_invoice',
            issuedAt: Carbon::parse('2026-05-22'),
        );

        $this->assertSame(1, $result->number);
        $this->assertSame('I-202605-001', $result->formatted);
        $this->assertDatabaseHas('number_sequences', [
            'code' => 'monthly_invoice',
            'current_number' => 1,
            'last_reset_on' => '2026-05-22',
        ]);
    }

    public function test_it_rejects_inactive_sequence(): void
    {
        NumberSequence::create([
            'code' => 'inactive',
            'name' => '無効採番',
            'is_active' => false,
        ]);

        $this->expectException(NumberSequenceException::class);

        app(NumberSequenceService::class)->next('inactive');
    }

    public function test_it_rejects_missing_sequence(): void
    {
        $this->expectException(NumberSequenceException::class);

        app(NumberSequenceService::class)->next('missing');
    }
}

