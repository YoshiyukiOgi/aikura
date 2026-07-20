<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\NonSalesStockOperationHeader;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\StockLocation;
use App\Models\StockLotMonthlyBalance;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NonSalesStockOperationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_period_operation_uses_append_only_movements_for_update_and_cancellation(): void
    {
        [$user, $lot, $location] = $this->prepareData();

        $created = $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $this->payload($lot, $location, 'disposal', '2026-07-10', '-2'))
            ->assertCreated()
            ->assertJsonPath('data.non_sales_stock_operation.is_editable', true)
            ->json('data.non_sales_stock_operation');
        $operationId = $created['id'];
        $originalMovementId = $created['lines'][0]['stock_movement_id'];

        $this->actingAs($user)->getJson('/api/v1/non-sales-stock-operations')
            ->assertOk()
            ->assertJsonPath('data.non_sales_stock_operations.0.id', $operationId);
        $this->actingAs($user)->getJson('/api/v1/inventory/movements?year=2026&month=7&movement_type=non_sales_disposal')
            ->assertOk()
            ->assertJsonCount(1, 'data.stock_movements')
            ->assertJsonPath('data.stock_movements.0.id', $originalMovementId);
        $this->actingAs($user)->get('/inventory/movements/print?year=2026&month=7&movement_type=non_sales_disposal')
            ->assertOk()
            ->assertSee('在庫移動履歴')
            ->assertSee($created['operation_number']);

        $this->actingAs($user)->putJson("/api/v1/non-sales-stock-operations/{$operationId}", $this->payload($lot, $location, 'adjustment', '2026-07-11', '3'))
            ->assertOk()
            ->assertJsonPath('data.non_sales_stock_operation.id', $operationId)
            ->assertJsonPath('data.non_sales_stock_operation.operation_type', 'adjustment')
            ->assertJsonPath('data.non_sales_stock_operation.lines.0.quantity', '3.0000');

        $this->assertDatabaseHas('stock_movements', ['id' => $originalMovementId, 'quantity' => '-2.0000']);
        $this->assertDatabaseHas('stock_movements', ['related_stock_movement_id' => $originalMovementId, 'movement_type' => 'non_sales_revision_reversal', 'quantity' => '2.0000']);
        $updatedMovement = StockMovement::query()->where('source_document_number', $created['operation_number'])->where('movement_type', 'non_sales_adjustment')->sole();
        $this->assertSame('non_sales_adjustment', $updatedMovement->movement_type);
        $this->assertSame('3.0000', $updatedMovement->quantity);

        $this->actingAs($user)->postJson("/api/v1/non-sales-stock-operations/{$operationId}/cancel", ['reason' => '入力誤りのため取消'])
            ->assertOk()
            ->assertJsonPath('data.non_sales_stock_operation.status', 'cancelled')
            ->assertJsonPath('data.non_sales_stock_operation.revision_no', 3);

        $this->assertDatabaseHas('non_sales_stock_operation_headers', ['id' => $operationId, 'status' => 'cancelled', 'revision_no' => 3]);
        $this->assertDatabaseHas('stock_movements', ['id' => $updatedMovement->id, 'quantity' => '3.0000']);
        $this->assertDatabaseHas('stock_movements', ['related_stock_movement_id' => $updatedMovement->id, 'movement_type' => 'non_sales_cancellation', 'quantity' => '-3.0000']);
        $this->assertDatabaseHas('non_sales_stock_operation_revisions', ['non_sales_stock_operation_header_id' => $operationId, 'revision_no' => 1, 'action' => 'created']);
        $this->assertDatabaseHas('non_sales_stock_operation_revisions', ['non_sales_stock_operation_header_id' => $operationId, 'revision_no' => 2, 'action' => 'updated']);
        $this->assertDatabaseHas('non_sales_stock_operation_revisions', ['non_sales_stock_operation_header_id' => $operationId, 'revision_no' => 3, 'action' => 'cancelled']);
    }

    public function test_closed_period_operation_cannot_be_updated_or_deleted(): void
    {
        [$user, $lot, $location] = $this->prepareData();
        $created = $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $this->payload($lot, $location, 'disposal', '2026-06-10', '-1'))
            ->assertCreated()
            ->json('data.non_sales_stock_operation');

        StockLotMonthlyBalance::create([
            'status' => 'confirmed',
            'year' => 2026,
            'month' => 6,
            'period_start' => '2026-06-01',
            'period_end' => '2026-06-30',
            'production_lot_id' => $lot->id,
            'stock_location_id' => $location->id,
            'unit_id' => $lot->unit_id,
            'closing_quantity' => '-1',
            'confirmed_at' => now(),
        ]);

        $this->actingAs($user)->getJson("/api/v1/non-sales-stock-operations/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('data.non_sales_stock_operation.is_editable', false);
        $this->actingAs($user)->getJson('/api/v1/non-sales-stock-operations')
            ->assertOk()
            ->assertJsonMissing(['id' => $created['id']]);
        $this->actingAs($user)->putJson("/api/v1/non-sales-stock-operations/{$created['id']}", $this->payload($lot, $location, 'disposal', '2026-07-01', '-2'))
            ->assertStatus(409);
        $this->actingAs($user)->postJson("/api/v1/non-sales-stock-operations/{$created['id']}/cancel", ['reason' => '取消'])
            ->assertStatus(409);

        $this->assertDatabaseHas('non_sales_stock_operation_headers', ['id' => $created['id']]);
    }

    public function test_reason_is_optional_only_for_new_bottling_and_repackaging_operations(): void
    {
        [$user, $lot, $location] = $this->prepareData();

        foreach (['repackaging', 'bottling'] as $index => $type) {
            $payload = $this->payload($lot, $location, $type, '2026-07-'.(20 + $index), '1');
            unset($payload['reason']);
            if ($type === 'repackaging') {
                $payload['alcohol_warning_acknowledged'] = true;
            }

            $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $payload)
                ->assertCreated()
                ->assertJsonPath('data.non_sales_stock_operation.operation_type', $type)
                ->assertJsonPath('data.non_sales_stock_operation.reason', '');
        }

        $payload = $this->payload($lot, $location, 'breakage', '2026-07-22', '-1');
        unset($payload['reason']);
        $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $bottling = NonSalesStockOperationHeader::query()->where('operation_type', 'bottling')->latest('id')->firstOrFail();
        $updatePayload = $this->payload($lot, $location, 'bottling', '2026-07-23', '2');
        unset($updatePayload['reason']);
        $this->actingAs($user)->putJson("/api/v1/non-sales-stock-operations/{$bottling->id}", $updatePayload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    }

    public function test_repackaging_alcohol_warning_must_be_acknowledged(): void
    {
        [$user, $sourceLot, $location] = $this->prepareData();
        $sourceLot->update(['alcohol_percentage' => '15.00', 'analysis_status' => 'confirmed']);
        $destinationLot = ProductionLot::create([
            'lot_code' => 'NS-API-LOT-DEST',
            'display_name' => '詰替先ロット',
            'stock_location_id' => $location->id,
            'unit_id' => $sourceLot->unit_id,
            'alcohol_percentage' => '18.00',
            'analysis_status' => 'confirmed',
        ]);
        $lines = [
            ['production_lot_id' => $sourceLot->id, 'stock_location_id' => $location->id, 'quantity' => '-2'],
            ['production_lot_id' => $destinationLot->id, 'stock_location_id' => $location->id, 'quantity' => '2'],
        ];

        $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations/repackaging-alcohol-check', ['lines' => $lines])
            ->assertOk()
            ->assertJsonPath('data.alcohol_check.has_warning', true)
            ->assertJsonPath('data.alcohol_check.source_average', '15.00')
            ->assertJsonPath('data.alcohol_check.warnings.0.code', 'out_of_range');

        $payload = [
            'operation_type' => 'repackaging',
            'operation_date' => '2026-07-24',
            'lines' => $lines,
        ];
        $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $payload)
            ->assertConflict();

        $payload['alcohol_warning_acknowledged'] = true;
        $this->actingAs($user)->postJson('/api/v1/non-sales-stock-operations', $payload)
            ->assertCreated()
            ->assertJsonPath('data.non_sales_stock_operation.operation_type', 'repackaging');
    }

    /** @return array{0: User, 1: ProductionLot, 2: StockLocation} */
    private function prepareData(): array
    {
        $this->seed(DatabaseSeeder::class);
        $employee = Employee::create(['employee_code' => 'NSAPI001', 'name' => '販売外API担当', 'email' => 'ns-api-employee@example.com']);
        $user = User::create(['employee_id' => $employee->id, 'name' => '販売外API担当', 'email' => 'ns-api@example.com', 'password' => 'password', 'is_active' => true]);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $location = StockLocation::where('code', 'main_brewery')->firstOrFail();
        $unit = Unit::where('code', 'bottle')->firstOrFail();
        $lot = ProductionLot::create([
            'lot_code' => 'NS-API-LOT-001',
            'display_name' => '販売外API確認ロット',
            'stock_location_id' => $location->id,
            'unit_id' => $unit->id,
        ]);

        return [$user, $lot, $location];
    }

    /** @return array<string, mixed> */
    private function payload(ProductionLot $lot, StockLocation $location, string $type, string $date, string $quantity): array
    {
        return [
            'operation_type' => $type,
            'operation_date' => $date,
            'reason' => 'API入力確認',
            'lines' => [[
                'production_lot_id' => $lot->id,
                'stock_location_id' => $location->id,
                'quantity' => $quantity,
            ]],
        ];
    }
}
