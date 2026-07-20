<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $tableComments = [
        'customers' => '取引先マスタ',
        'transaction_categories' => '取引区分マスタ',
        'settlement_receivable_categories' => '売掛・精算区分マスタ',
        'billing_cycles' => '請求サイクルマスタ',
        'employees' => '従業員マスタ',
        'users' => 'ユーザーマスタ',
        'roles' => 'ロールマスタ',
        'permissions' => '権限マスタ',
        'role_user' => 'ユーザーロール紐付',
        'permission_role' => 'ロール権限紐付',
        'price_lists' => '価格表マスタ',
        'price_rules' => '価格ルール',
        'products' => '商品マスタ',
        'sake_product_details' => '清酒商品詳細',
        'kasu_product_details' => '酒粕商品詳細',
        'food_product_details' => '食品商品詳細',
        'goods_product_details' => '物品商品詳細',
        'units' => '単位マスタ',
        'unit_conversions' => '単位換算マスタ',
        'consumption_tax_categories' => '消費税区分マスタ',
        'consumption_tax_rates' => '消費税率マスタ',
        'liquor_tax_categories' => '酒税区分マスタ',
        'liquor_tax_rules' => '酒税ルール',
        'sales_orders' => '受注ヘッダ',
        'sales_order_lines' => '受注明細',
        'shipment_instructions' => '出荷指示ヘッダ',
        'shipment_instruction_lines' => '出荷指示明細',
        'shipment_picks' => '品出ヘッダ',
        'shipment_pick_lines' => '品出明細',
        'shipment_headers' => '出荷伝票ヘッダ',
        'shipment_lines' => '出荷伝票明細',
        'stock_locations' => '在庫場所マスタ',
        'stock_movements' => '在庫移動台帳',
        'stock_monthly_balances' => '月次在庫残高',
        'invoice_headers' => '請求ヘッダ',
        'invoice_lines' => '請求明細',
        'payment_schedules' => '入金予定',
        'payments' => '入金実績',
        'payment_allocations' => '入金充当',
        'receivable_monthly_balances' => '月次売掛残高',
        'liquor_tax_monthly_filings' => '月次酒税申告ヘッダ',
        'liquor_tax_monthly_filing_lines' => '月次酒税申告明細',
        'consumption_tax_monthly_filings' => '月次消費税集計ヘッダ',
        'consumption_tax_monthly_filing_lines' => '月次消費税集計明細',
        'production_lots' => '製造ロット',
        'product_production_lot' => '商品ロット候補紐付',
        'shipment_lot_allocations' => '出荷ロット引当',
        'shipment_stock_reservations' => '出荷在庫予約',
        'report_exports' => '帳票出力履歴',
        'audit_logs' => '監査ログ',
        'number_sequences' => '採番管理',
        'operation_jobs' => '運用ジョブ履歴',
        'failed_jobs' => '失敗ジョブ履歴',
        'approval_requests' => '承認依頼',
        'approval_request_actions' => '承認操作履歴',
    ];

    public function up(): void
    {
        foreach ($this->tableComments as $table => $comment) {
            $escapedComment = str_replace("'", "''", $comment);
            DB::statement("COMMENT ON TABLE {$table} IS '{$escapedComment}'");
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->tableComments) as $table) {
            DB::statement("COMMENT ON TABLE {$table} IS NULL");
        }
    }
};
