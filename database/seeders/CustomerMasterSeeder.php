<?php

namespace Database\Seeders;

use App\Models\BillingCycle;
use App\Models\SettlementReceivableCategory;
use App\Models\TransactionCategory;
use Illuminate\Database\Seeder;

class CustomerMasterSeeder extends Seeder
{
    public function run(): void
    {
        TransactionCategory::updateOrCreate(
            ['code' => 'producer'],
            ['name' => '生産者価格', 'sort_order' => 10, 'is_active' => true],
        );

        TransactionCategory::updateOrCreate(
            ['code' => 'wholesale'],
            ['name' => '卸価格', 'sort_order' => 20, 'is_active' => true],
        );

        TransactionCategory::updateOrCreate(
            ['code' => 'retail'],
            ['name' => '小売価格', 'sort_order' => 30, 'is_active' => true],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'accounts_receivable_1'],
            [
                'name' => '売掛金1',
                'receivable_method' => 'accounts_receivable',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'taxable',
                'consumption_tax_type' => 'taxable',
                'invoice_required' => true,
                'reduces_stock' => true,
                'requires_tax_review' => false,
                'requires_evidence' => false,
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'self_consumption'],
            [
                'name' => '自家用',
                'receivable_method' => 'none',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'taxable',
                'consumption_tax_type' => 'non_taxable',
                'invoice_required' => false,
                'reduces_stock' => true,
                'requires_tax_review' => true,
                'requires_evidence' => false,
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'internal_balance'],
            [
                'name' => '社内残高',
                'receivable_method' => 'internal_balance',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'taxable',
                'consumption_tax_type' => 'non_taxable',
                'invoice_required' => true,
                'reduces_stock' => true,
                'requires_tax_review' => true,
                'requires_evidence' => false,
                'description' => '社内請求・社内残高として管理する。外部売掛、入金予定、入金消込の対象にはしない。',
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'export'],
            [
                'name' => '輸出',
                'receivable_method' => 'accounts_receivable',
                'export_type' => 'export',
                'liquor_tax_type' => 'exempt',
                'consumption_tax_type' => 'export_exempt',
                'invoice_required' => false,
                'reduces_stock' => true,
                'requires_tax_review' => true,
                'requires_evidence' => true,
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'tax_paid_storage_destination'],
            [
                'name' => '課税済み蔵置所搬出',
                'receivable_method' => 'none',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'taxable',
                'consumption_tax_type' => 'out_of_scope',
                'invoice_required' => false,
                'reduces_stock' => true,
                'requires_tax_review' => false,
                'requires_evidence' => false,
                'description' => '製造場搬出時に課税移出するが、売上・売掛・請求を発生させない蔵置所向け区分。',
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'untaxed_transfer'],
            [
                'name' => '未納税移出',
                'receivable_method' => 'accounts_receivable',
                'export_type' => 'domestic',
                'liquor_tax_type' => 'untaxed_transfer',
                'consumption_tax_type' => 'taxable',
                'invoice_required' => true,
                'reduces_stock' => true,
                'requires_tax_review' => true,
                'requires_evidence' => true,
                'description' => '他の酒類製造場への未納税移出。国内販売の消費税は課税。',
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'direct_export'],
            [
                'name' => '直接輸出', 'receivable_method' => 'accounts_receivable',
                'export_type' => 'export', 'liquor_tax_type' => 'exempt',
                'consumption_tax_type' => 'export_exempt', 'invoice_required' => true,
                'reduces_stock' => true, 'requires_tax_review' => true, 'requires_evidence' => true,
                'is_active' => true,
            ],
        );

        SettlementReceivableCategory::updateOrCreate(
            ['code' => 'indirect_export'],
            [
                'name' => '間接輸出', 'receivable_method' => 'accounts_receivable',
                'export_type' => 'export', 'liquor_tax_type' => 'exempt',
                'consumption_tax_type' => 'export_exempt', 'invoice_required' => true,
                'reduces_stock' => true, 'requires_tax_review' => true, 'requires_evidence' => true,
                'is_active' => true,
            ],
        );

        BillingCycle::updateOrCreate(
            ['code' => 'monthly_end_next_month_end'],
            [
                'name' => '月末締 翌月末入金',
                'closing_day' => 31,
                'payment_month_offset' => 1,
                'payment_day' => 31,
                'billing_method' => 'monthly_closing',
                'is_active' => true,
            ],
        );

        BillingCycle::updateOrCreate(
            ['code' => 'cash_immediate'],
            [
                'name' => '現金即時',
                'closing_day' => null,
                'payment_month_offset' => 0,
                'payment_day' => null,
                'billing_method' => 'cash_immediate',
                'is_active' => true,
            ],
        );
    }
};

