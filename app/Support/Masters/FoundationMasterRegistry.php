<?php

namespace App\Support\Masters;

use App\Models\ConsumptionTaxCategory;
use App\Models\ConsumptionTaxRate;
use App\Models\LiquorTaxCategory;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\ProductionLot;
use App\Models\Role;
use App\Models\SettlementReceivableCategory;
use App\Models\StockLocation;
use App\Models\TransactionCategory;
use App\Models\Unit;

class FoundationMasterRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'consumption-tax-categories' => [
                'title' => '消費税区分マスタ',
                'short_title' => '消費税区分',
                'model' => ConsumptionTaxCategory::class,
                'permission' => 'tax_master',
                'order' => ['sort_order', 'code'],
                'search' => ['code', 'name', 'description'],
                'list_columns' => ['code', 'name', 'taxability', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'taxability', 'label' => '課税区分', 'type' => 'select', 'required' => true, 'options' => ['taxable' => '課税', 'exempt' => '免税', 'non_taxable' => '非課税', 'out_of_scope' => '対象外']],
                    ['name' => 'sort_order', 'label' => '表示順', 'type' => 'number', 'min' => 0],
                    ['name' => 'requires_tax_rate', 'label' => '税率を使用', 'type' => 'boolean'],
                    ['name' => 'is_reduced_rate', 'label' => '軽減税率対象', 'type' => 'boolean'],
                    ['name' => 'is_export_exempt', 'label' => '輸出免税', 'type' => 'boolean'],
                    ['name' => 'is_invoice_display_target', 'label' => 'インボイス表示対象', 'type' => 'boolean'],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'consumption-tax-rates' => [
                'title' => '消費税率マスタ',
                'short_title' => '消費税率',
                'model' => ConsumptionTaxRate::class,
                'permission' => 'tax_master',
                'order' => ['effective_from', 'id'],
                'search' => ['name', 'description'],
                'with' => ['consumptionTaxCategory:id,code,name'],
                'list_columns' => ['name', 'rate', 'effective_from', 'is_active'],
                'fields' => [
                    ['name' => 'consumption_tax_category_id', 'label' => '消費税区分', 'type' => 'relation', 'required' => true, 'source' => 'consumption-tax-categories'],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'rate', 'label' => '税率(%)', 'type' => 'decimal', 'required' => true, 'min' => 0, 'max_value' => 100],
                    ['name' => 'effective_from', 'label' => '適用開始日', 'type' => 'date', 'required' => true],
                    ['name' => 'effective_to', 'label' => '適用終了日', 'type' => 'date'],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'units' => [
                'title' => '単位マスタ',
                'short_title' => '単位',
                'model' => Unit::class,
                'permission' => 'unit_master',
                'order' => ['unit_type', 'code'],
                'search' => ['code', 'name', 'symbol', 'description'],
                'list_columns' => ['code', 'name', 'symbol', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'symbol', 'label' => '表示記号', 'type' => 'text', 'max' => 20],
                    ['name' => 'unit_type', 'label' => '単位種別', 'type' => 'select', 'required' => true, 'options' => ['volume' => '容量', 'weight' => '重量', 'count' => '数量', 'length' => '長さ', 'other' => 'その他']],
                    ['name' => 'decimal_scale', 'label' => '小数桁', 'type' => 'number', 'min' => 0, 'max_value' => 6],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'liquor-tax-categories' => [
                'title' => '酒税区分マスタ',
                'short_title' => '酒税区分',
                'model' => LiquorTaxCategory::class,
                'permission' => 'liquor_tax_master',
                'order' => ['print_order', 'code'],
                'search' => ['code', 'name', 'aggregate0', 'aggregate1', 'aggregate2', 'aggregate3', 'description'],
                'list_columns' => ['code', 'name', 'taxability', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'taxability', 'label' => '酒税区分', 'type' => 'select', 'required' => true, 'options' => ['taxable' => '課税', 'exempt' => '免税', 'untaxed' => '未納税', 'out_of_scope' => '対象外', 'unknown' => '未判定']],
                    ['name' => 'aggregate0', 'label' => '集計0', 'type' => 'text', 'max' => 120],
                    ['name' => 'aggregate1', 'label' => '集計1', 'type' => 'text', 'max' => 120],
                    ['name' => 'aggregate2', 'label' => '集計2', 'type' => 'text', 'max' => 120],
                    ['name' => 'aggregate3', 'label' => '集計3', 'type' => 'text', 'max' => 120],
                    ['name' => 'print_order', 'label' => '印字順', 'type' => 'number', 'min' => 0],
                    ['name' => 'reduction_rate', 'label' => '軽減率', 'type' => 'decimal', 'min' => 0, 'max_value' => 100],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'stock-locations' => [
                'title' => '在庫場所マスタ',
                'short_title' => '在庫場所',
                'model' => StockLocation::class,
                'permission' => 'stock_location_master',
                'order' => ['sort_order', 'code'],
                'search' => ['code', 'name', 'address1', 'description'],
                'with' => ['parent:id,code,name'],
                'list_columns' => ['code', 'name', 'location_type', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'location_type', 'label' => '場所種別', 'type' => 'select', 'required' => true, 'options' => ['brewery' => '蔵', 'warehouse' => '倉庫', 'shipping' => '出荷場', 'store' => '店舗', 'consignment' => '委託先', 'hold' => '保留', 'disposal' => '廃棄', 'external' => '外部', 'tank' => 'タンク', 'customer' => '得意先', 'other' => 'その他']],
                    ['name' => 'parent_stock_location_id', 'label' => '親在庫場所', 'type' => 'relation', 'source' => 'stock-locations'],
                    ['name' => 'postal_code', 'label' => '郵便番号', 'type' => 'text', 'max' => 20],
                    ['name' => 'address1', 'label' => '住所1', 'type' => 'text', 'max' => 255],
                    ['name' => 'address2', 'label' => '住所2', 'type' => 'text', 'max' => 255],
                    ['name' => 'phone', 'label' => '電話番号', 'type' => 'text', 'max' => 50],
                    ['name' => 'sort_order', 'label' => '表示順', 'type' => 'number', 'min' => 0],
                    ['name' => 'is_default_shipping_location', 'label' => '標準出荷場所', 'type' => 'boolean'],
                    ['name' => 'is_default_receiving_location', 'label' => '標準入庫場所', 'type' => 'boolean'],
                    ['name' => 'is_inventory_managed', 'label' => '在庫管理対象', 'type' => 'boolean'],
                    ['name' => 'is_shippable', 'label' => '出荷可能', 'type' => 'boolean'],
                    ['name' => 'is_sellable', 'label' => '販売可能', 'type' => 'boolean'],
                    ['name' => 'is_tax_relevant', 'label' => '税務関連', 'type' => 'boolean'],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'lots' => [
                'title' => 'ロットマスタ',
                'short_title' => 'ロット',
                'model' => ProductionLot::class,
                'permission' => 'product_master',
                'order' => ['lot_code'],
                'search' => ['lot_code', 'display_name', 'tank_code', 'rice_variety', 'production_method', 'external_system_code', 'legacy_lot_text', 'note'],
                'list_columns' => ['lot_code', 'display_name', 'production_date', 'is_active'],
                'fields' => [
                    ['name' => 'lot_code', 'label' => 'ロットコード', 'type' => 'text', 'required' => true, 'max' => 80, 'unique' => true],
                    ['name' => 'display_name', 'label' => '表示名', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['name' => 'stock_location_id', 'label' => '在庫場所', 'type' => 'relation', 'source' => 'stock-locations'],
                    ['name' => 'unit_id', 'label' => '在庫単位', 'type' => 'relation', 'source' => 'units'],
                    ['name' => 'capacity_value', 'label' => '容量', 'type' => 'decimal', 'min' => 0],
                    ['name' => 'capacity_unit_id', 'label' => '容量単位', 'type' => 'relation', 'source' => 'units'],
                    ['name' => 'alcohol_percentage', 'label' => 'アルコール度数', 'type' => 'decimal', 'min' => 0, 'max_value' => 100],
                    ['name' => 'sake_meter_value', 'label' => '日本酒度', 'type' => 'decimal', 'min' => -100, 'max_value' => 100],
                    ['name' => 'acidity', 'label' => '酸度', 'type' => 'decimal', 'min' => 0, 'max_value' => 100],
                    ['name' => 'amino_acidity', 'label' => 'アミノ酸度', 'type' => 'decimal', 'min' => 0, 'max_value' => 100],
                    ['name' => 'analysis_date', 'label' => '分析日', 'type' => 'date'],
                    ['name' => 'analysis_status', 'label' => '分析状態', 'type' => 'select', 'options' => ['provisional' => '仮値', 'confirmed' => '確定']],
                    ['name' => 'production_date', 'label' => '製造日', 'type' => 'date'],
                    ['name' => 'bottling_date', 'label' => '瓶詰日', 'type' => 'date'],
                    ['name' => 'best_before_date', 'label' => '賞味期限', 'type' => 'date'],
                    ['name' => 'tank_code', 'label' => 'タンクコード', 'type' => 'text', 'max' => 80],
                    ['name' => 'rice_variety', 'label' => '米品種', 'type' => 'text', 'max' => 120],
                    ['name' => 'rice_polishing_ratio', 'label' => '精米歩合', 'type' => 'decimal', 'min' => 0, 'max_value' => 100],
                    ['name' => 'production_method', 'label' => '製造方法', 'type' => 'text', 'max' => 120],
                    ['name' => 'storage_condition', 'label' => '保管条件', 'type' => 'text', 'max' => 120],
                    ['name' => 'external_system_code', 'label' => '外部連携コード', 'type' => 'text', 'max' => 120],
                    ['name' => 'legacy_lot_text', 'label' => '旧ロット表記', 'type' => 'text', 'max' => 160],
                    ['name' => 'search_key', 'label' => '検索キー', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'note', 'label' => '備考', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'transaction-categories' => [
                'title' => '取引区分マスタ',
                'short_title' => '取引区分',
                'model' => TransactionCategory::class,
                'permission' => 'transaction_category_master',
                'order' => ['sort_order', 'code'],
                'search' => ['code', 'name', 'description'],
                'list_columns' => ['code', 'name', 'sort_order', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'sort_order', 'label' => '表示順', 'type' => 'number', 'min' => 0],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'settlement-receivable-categories' => [
                'title' => '売掛精算区分マスタ',
                'short_title' => '売掛精算区分',
                'model' => SettlementReceivableCategory::class,
                'permission' => 'settlement_category_master',
                'order' => ['code'],
                'search' => ['code', 'name', 'description'],
                'list_columns' => ['code', 'name', 'receivable_method', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'receivable_method', 'label' => '売掛処理', 'type' => 'select', 'required' => true, 'options' => ['accounts_receivable' => '売掛', 'cash' => '現金', 'prepaid' => '前受', 'none' => '対象外']],
                    ['name' => 'export_type', 'label' => '出荷区分', 'type' => 'text', 'max' => 80],
                    ['name' => 'liquor_tax_type', 'label' => '酒税処理', 'type' => 'text', 'max' => 80],
                    ['name' => 'consumption_tax_type', 'label' => '消費税処理', 'type' => 'text', 'max' => 80],
                    ['name' => 'accounting_code', 'label' => '勘定科目', 'type' => 'text', 'max' => 80],
                    ['name' => 'sub_accounting_code', 'label' => '補助科目', 'type' => 'text', 'max' => 80],
                    ['name' => 'department_code', 'label' => '部門コード', 'type' => 'text', 'max' => 80],
                    ['name' => 'invoice_required', 'label' => '請求対象', 'type' => 'boolean'],
                    ['name' => 'reduces_stock', 'label' => '在庫を減らす', 'type' => 'boolean'],
                    ['name' => 'requires_tax_review', 'label' => '税務確認が必要', 'type' => 'boolean'],
                    ['name' => 'requires_evidence', 'label' => '証憑が必要', 'type' => 'boolean'],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'number-sequences' => [
                'title' => '採番マスタ',
                'short_title' => '採番',
                'model' => NumberSequence::class,
                'permission' => 'number_sequence_master',
                'order' => ['code'],
                'search' => ['code', 'name', 'prefix', 'description'],
                'list_columns' => ['code', 'name', 'current_number', 'is_active'],
                'warning' => '現在番号を変更すると次回以降の伝票番号に影響します。変更理由を必ず残してください。',
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'prefix', 'label' => '接頭辞', 'type' => 'text', 'max' => 50],
                    ['name' => 'suffix', 'label' => '接尾辞', 'type' => 'text', 'max' => 50],
                    ['name' => 'current_number', 'label' => '現在番号', 'type' => 'number', 'required' => true, 'min' => 0],
                    ['name' => 'padding_length', 'label' => 'ゼロ埋め桁数', 'type' => 'number', 'required' => true, 'min' => 0, 'max_value' => 20],
                    ['name' => 'reset_type', 'label' => 'リセット単位', 'type' => 'select', 'required' => true, 'options' => ['none' => 'なし', 'day' => '日次', 'month' => '月次', 'year' => '年次']],
                    ['name' => 'last_reset_on', 'label' => '最終リセット日', 'type' => 'date'],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
            ],
            'roles' => [
                'title' => '権限・ロール整理',
                'short_title' => 'ロール',
                'model' => Role::class,
                'permission' => 'role_master',
                'order' => ['code'],
                'search' => ['code', 'name', 'description'],
                'with' => ['permissions:id,code,name'],
                'list_columns' => ['code', 'name', 'is_system', 'is_active'],
                'fields' => [
                    ['name' => 'code', 'label' => 'コード', 'type' => 'text', 'required' => true, 'max' => 80],
                    ['name' => 'name', 'label' => '名称', 'type' => 'text', 'required' => true, 'max' => 120],
                    ['name' => 'description', 'label' => '説明', 'type' => 'textarea', 'full' => true, 'max' => 2000],
                    ['name' => 'is_system', 'label' => 'システムロール', 'type' => 'boolean'],
                    ['name' => 'permission_ids', 'label' => '付与権限', 'type' => 'permissions', 'full' => true],
                    ['name' => 'is_active', 'label' => '有効', 'type' => 'boolean'],
                ],
                'references' => [
                    'permissions' => Permission::class,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $master): array
    {
        abort_unless(array_key_exists($master, self::all()), 404);

        return self::all()[$master];
    }
}
