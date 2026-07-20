<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $tableComments = [
        'employees' => '従業員マスタ',
        'users' => 'ユーザーマスタ',
        'roles' => 'ロールマスタ',
        'permissions' => '権限マスタ',
        'role_user' => 'ユーザーロール紐付',
        'permission_role' => 'ロール権限紐付',
        'price_lists' => '価格表マスタ',
        'price_rules' => '価格ルール',
        'sake_product_details' => '清酒商品詳細',
        'kasu_product_details' => '酒粕商品詳細',
        'food_product_details' => '食品商品詳細',
        'goods_product_details' => '物品商品詳細',
        'consumption_tax_categories' => '消費税区分マスタ',
        'consumption_tax_rates' => '消費税率マスタ',
        'liquor_tax_categories' => '酒税区分マスタ',
        'liquor_tax_rules' => '酒税ルール',
        'stock_locations' => '在庫場所マスタ',
        'product_production_lot' => '商品ロット候補紐付',
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
