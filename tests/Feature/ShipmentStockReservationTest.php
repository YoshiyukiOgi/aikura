<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ShipmentStockReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shipment_stock_reservation_architecture_has_been_removed(): void
    {
        $this->assertFalse(Schema::hasTable('shipment_stock_reservations'));
        $this->assertFalse(class_exists(\App\Services\Shipment\ReserveShipmentLineStockService::class));
        $this->assertFalse(class_exists(\App\Exceptions\Shipment\ShipmentStockReservationException::class));
    }
}
