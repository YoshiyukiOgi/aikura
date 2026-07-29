<?php

namespace Tests\Feature;

use App\Models\BillingCycle;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\PriceList;
use App\Models\PriceRule;
use App\Models\Product;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\ShipmentHeader;
use App\Models\TransactionCategory;
use App\Models\Unit;
use App\Models\User;
use App\Services\Billing\ConfirmInvoiceService;
use App\Services\Billing\CreateInvoiceDraftData;
use App\Services\Billing\CreateInvoiceDraftService;
use App\Services\Shipment\ApplyDraftShipmentPricingService;
use App\Services\Shipment\ConfirmShipmentService;
use App\Services\Shipment\CreateDraftShipmentData;
use App\Services\Shipment\CreateDraftShipmentLineData;
use App\Services\Shipment\CreateDraftShipmentService;
use App\Services\Tax\ConfirmLiquorTaxMonthlyFilingService;
use App\Services\Tax\CreateLiquorTaxMonthlyFilingDraftService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_permission_can_create_confirm_and_view_tax_filings(): void
    {
        [$user, $shipment] = $this->prepareData();

        $liquorDraft = $this->actingAs($user)
            ->postJson('/api/v1/tax/liquor-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api liquor tax draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.liquor_tax_monthly_filing.status', 'draft')
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_taxable_kl', '0.002160')
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_gross_tax_amount', '216.00')
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_relief_amount', '43.00')
            ->assertJsonPath('data.liquor_tax_monthly_filing.net_payable_amount', '100.00');

        $liquorFilingId = $liquorDraft->json('data.liquor_tax_monthly_filing.id');

        $adjustment = $this->actingAs($user)
            ->postJson("/api/v1/tax/liquor-monthly-filings/{$liquorFilingId}/adjustments", [
                'adjustment_type' => 'calculation_correction',
                'liquor_tax_category_id' => null,
                'taxable_kl_adjustment' => '0.000000',
                'tax_amount_adjustment' => '100.00',
                'description' => 'rounding correction for API test',
            ])
            ->assertCreated()
            ->assertJsonPath('data.liquor_tax_adjustment.approval_required', true)
            ->assertJsonPath('data.liquor_tax_adjustment.approval_status', 'pending');

        $this->actingAs($user)
            ->postJson("/api/v1/tax/liquor-monthly-filings/{$liquorFilingId}/confirm", ['reason' => 'must wait for adjustment approval'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'business_rule_violation');

        $approver = $this->createUser('tax-approver@example.com');
        $approver->roles()->attach(Role::where('code', 'admin')->firstOrFail());
        $this->actingAs($approver)
            ->postJson('/api/v1/approval-requests/'.$adjustment->json('data.liquor_tax_adjustment.approval_request_id').'/approve', ['comment' => 'checked'])
            ->assertOk();

        $this->actingAs($user)
            ->postJson("/api/v1/tax/liquor-monthly-filings/{$liquorFilingId}/confirm", [
                'reason' => 'api liquor tax confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.liquor_tax_monthly_filing.status', 'confirmed')
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_adjustment_amount', '100.00')
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_confirmed_amount', '200.00');

        $this->createConfirmedInvoice($shipment);

        $consumptionDraft = $this->actingAs($user)
            ->postJson('/api/v1/tax/consumption-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api consumption tax draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.consumption_tax_monthly_filing.status', 'draft')
            ->assertJsonPath('data.consumption_tax_monthly_filing.total_taxable_amount', '4500.00')
            ->assertJsonPath('data.consumption_tax_monthly_filing.total_tax_amount', '450.00')
            ->assertJsonPath('data.consumption_tax_monthly_filing.total_amount', '4950.00');

        $consumptionFilingId = $consumptionDraft->json('data.consumption_tax_monthly_filing.id');

        $this->actingAs($user)
            ->postJson("/api/v1/tax/consumption-monthly-filings/{$consumptionFilingId}/confirm", [
                'reason' => 'api consumption tax confirm',
            ])
            ->assertOk()
            ->assertJsonPath('data.consumption_tax_monthly_filing.status', 'confirmed')
            ->assertJsonPath('data.consumption_tax_monthly_filing.total_confirmed_tax_amount', '450.00');

        $this->actingAs($user)
            ->getJson("/api/v1/tax/liquor-monthly-filings/{$liquorFilingId}")
            ->assertOk()
            ->assertJsonPath('data.liquor_tax_monthly_filing.id', $liquorFilingId)
            ->assertJsonPath('data.liquor_tax_monthly_filing.lines.0.tax_per_kl', '100000.0000');

        $this->actingAs($user)
            ->getJson('/api/v1/tax/consumption-monthly-filings')
            ->assertOk()
            ->assertJsonPath('data.consumption_tax_monthly_filings.0.id', $consumptionFilingId);
    }

    public function test_user_without_tax_permission_cannot_create_liquor_filing(): void
    {
        [, $shipment] = $this->prepareData();
        $user = $this->createUser('limited-tax@example.com');

        $this->actingAs($user)
            ->postJson('/api/v1/tax/liquor-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'api liquor tax draft',
            ])
            ->assertForbidden()
            ->assertJsonPath('permission', 'tax.liquor_filing.create');
    }

    public function test_tax_filing_api_validates_required_year(): void
    {
        [$user] = $this->prepareData();

        $this->actingAs($user)
            ->postJson('/api/v1/tax/consumption-monthly-filings', [
                'month' => 6,
                'reason' => 'api consumption tax draft',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['year']);
    }

    public function test_tax_user_can_confirm_export_evidence_and_rebuild_filing_sources(): void
    {
        [$user, $shipment] = $this->prepareData('direct_export', 'tax-evidence@example.com');

        $this->actingAs($user)
            ->putJson("/api/v1/tax/shipments/{$shipment->id}/liquor-tax-evidence", [
                'status' => 'confirmed',
                'evidence_reference' => 'EXP-API-001',
                'evidence_date' => '2026-06-25',
                'destination' => 'United States',
                'customs_office' => 'Yokohama Customs',
                'exporter_type' => 'direct',
                'note' => 'export permit checked',
            ])
            ->assertOk()
            ->assertJsonPath('data.shipment_liquor_tax_evidence.status', 'confirmed')
            ->assertJsonPath('data.shipment_liquor_tax_evidence.evidence_reference', 'EXP-API-001');

        $draft = $this->actingAs($user)
            ->postJson('/api/v1/tax/liquor-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'rebuild after export evidence',
            ])->assertCreated();

        $this->actingAs($user)
            ->getJson('/api/v1/tax/liquor-monthly-filings/'.$draft->json('data.liquor_tax_monthly_filing.id'))
            ->assertOk()
            ->assertJsonPath('data.liquor_tax_monthly_filing.lines.0.sources.0.requires_review', false)
            ->assertJsonPath('data.liquor_tax_monthly_filing.lines.0.sources.0.evidence_status', 'confirmed')
            ->assertJsonPath('data.liquor_tax_monthly_filing.lines.0.sources.0.evidence_reference', 'EXP-API-001');
    }

    public function test_tax_user_can_create_and_download_liquor_tax_confirmation_sheets(): void
    {
        [$user] = $this->prepareData(email: 'tax-export@example.com');
        $draft = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6, 'api export draft');
        $confirmed = app(ConfirmLiquorTaxMonthlyFilingService::class)->confirm($draft->year, $draft->month, 'api export confirm');

        foreach (['xlsx', 'pdf'] as $format) {
            $response = $this->actingAs($user)
                ->postJson("/api/v1/tax/liquor-monthly-filings/{$confirmed->id}/exports", [
                    'format' => $format,
                    'reason' => 'api confirmation sheet',
                ])
                ->assertCreated()
                ->assertJsonPath('data.report_export.format', $format);

            $this->actingAs($user)
                ->get($response->json('data.report_export.download_url'))
                ->assertOk()
                ->assertHeader('content-type', $format === 'xlsx'
                    ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                    : 'application/pdf');
        }
    }

    public function test_tax_user_can_reopen_latest_confirmed_liquor_filing(): void
    {
        [$user] = $this->prepareData(email: 'tax-reopen@example.com');
        $draft = app(CreateLiquorTaxMonthlyFilingDraftService::class)->create(2026, 6, 'api reopen draft');
        $confirmed = app(ConfirmLiquorTaxMonthlyFilingService::class)
            ->confirm($draft->year, $draft->month, 'api reopen confirmation');

        $this->actingAs($user)
            ->getJson("/api/v1/tax/liquor-monthly-filings/{$confirmed->id}")
            ->assertOk()
            ->assertJsonPath('data.liquor_tax_monthly_filing.can_reopen', true);

        $this->actingAs($user)
            ->postJson("/api/v1/tax/liquor-monthly-filings/{$confirmed->id}/reopen", [
                'reason' => 'correct confirmed filing through API',
            ])
            ->assertOk()
            ->assertJsonPath('data.liquor_tax_monthly_filing.status', 'draft')
            ->assertJsonPath('data.liquor_tax_monthly_filing.can_reopen', false)
            ->assertJsonPath('data.liquor_tax_monthly_filing.total_confirmed_amount', null)
            ->assertJsonPath('data.liquor_tax_monthly_filing.confirmed_at', null)
            ->assertJsonPath('data.liquor_tax_monthly_filing.reason', 'correct confirmed filing through API');
    }

    public function test_tax_user_can_recalculate_existing_liquor_tax_draft(): void
    {
        [$user] = $this->prepareData(email: 'tax-recalculate@example.com');

        $first = $this->actingAs($user)
            ->postJson('/api/v1/tax/liquor-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'first API draft',
            ])
            ->assertCreated()
            ->assertJsonPath('data.liquor_tax_monthly_filing.status', 'draft');

        $this->actingAs($user)
            ->postJson('/api/v1/tax/liquor-monthly-filings', [
                'year' => 2026,
                'month' => 6,
                'reason' => 'API draft recalculation',
            ])
            ->assertCreated()
            ->assertJsonPath('data.liquor_tax_monthly_filing.id', $first->json('data.liquor_tax_monthly_filing.id'))
            ->assertJsonPath('data.liquor_tax_monthly_filing.status', 'draft')
            ->assertJsonPath('data.liquor_tax_monthly_filing.reason', 'API draft recalculation');
    }

    /**
     * @return array{0: User, 1: ShipmentHeader}
     */
    private function prepareData(string $settlementCode = 'accounts_receivable_1', string $email = 'tax-admin@example.com'): array
    {
        $this->seed(DatabaseSeeder::class);

        $user = $this->createUser($email);
        $user->roles()->attach(Role::where('code', 'admin')->firstOrFail());

        $transactionCategory = TransactionCategory::where('code', 'wholesale')->firstOrFail();
        $settlementCategory = SettlementReceivableCategory::where('code', $settlementCode)->firstOrFail();
        $billingCycle = BillingCycle::where('code', 'monthly_end_next_month_end')->firstOrFail();
        $bottle = Unit::where('code', 'bottle')->firstOrFail();
        $milliliter = Unit::where('code', 'milliliter')->firstOrFail();

        $customer = Customer::create([
            'customer_code' => 'API-TAX-CUST-001',
            'name' => 'API Tax Customer',
            'transaction_category_id' => $transactionCategory->id,
            'settlement_receivable_category_id' => $settlementCategory->id,
            'billing_cycle_id' => $billingCycle->id,
        ]);

        $product = Product::create([
            'product_code' => 'API-TAX-SAKE-001',
            'product_type' => 'sake',
            'name' => 'API Tax Sake',
            'display_name' => 'API Tax Sake 720ml',
            'base_unit_id' => $bottle->id,
            'sales_unit_id' => $bottle->id,
            'inventory_unit_id' => $bottle->id,
            'capacity_value' => '720.0000',
            'capacity_unit_id' => $milliliter->id,
            'alcohol_percentage' => '15.50',
            'is_alcohol' => true,
            'is_inventory_managed' => false,
        ]);

        PriceRule::create([
            'price_list_id' => PriceList::where('code', 'common')->firstOrFail()->id,
            'product_id' => $product->id,
            'unit_id' => $bottle->id,
            'unit_price' => '1500.0000',
            'priority' => 300,
            'effective_from' => '2026-01-01',
        ]);

        $shipment = app(CreateDraftShipmentService::class)->create(new CreateDraftShipmentData(
            customerId: $customer->id,
            documentDate: '2026-06-20',
            billingTargetDate: '2026-06-20',
            liquorTaxTransferDate: '2026-06-20',
            lines: [
                new CreateDraftShipmentLineData($product->id, '3.0000', $bottle->id),
            ],
        ));
        $shipment = app(ApplyDraftShipmentPricingService::class)->apply($shipment);
        $shipment = app(ConfirmShipmentService::class)->confirm($shipment);

        return [$user, $shipment];
    }

    private function createConfirmedInvoice(ShipmentHeader $shipment): void
    {
        $invoice = app(CreateInvoiceDraftService::class)->create(new CreateInvoiceDraftData(
            customerId: $shipment->customer_id,
            invoiceDate: '2026-06-30',
            shipmentHeaderIds: [$shipment->id],
        ));

        app(ConfirmInvoiceService::class)->confirm($invoice);
    }

    private function createUser(string $email): User
    {
        $employee = Employee::create([
            'employee_code' => 'TAXAPI'.str_pad((string) (Employee::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Tax API Employee',
            'email' => 'employee-'.$email,
        ]);

        return User::create([
            'employee_id' => $employee->id,
            'name' => 'Tax API User',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
