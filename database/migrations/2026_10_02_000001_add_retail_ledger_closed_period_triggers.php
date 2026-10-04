<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::connection('retail')->unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION retail_reject_closed_ledger_document_change()
RETURNS trigger AS $$
DECLARE
    target_company_id bigint;
    target_business_date date;
BEGIN
    IF TG_OP = 'DELETE' THEN
        target_company_id := OLD.retail_company_id;
        target_business_date := OLD.business_date;
    ELSE
        target_company_id := NEW.retail_company_id;
        target_business_date := NEW.business_date;
    END IF;

    IF EXISTS (
        SELECT 1 FROM retail_monthly_closings
        WHERE retail_company_id = target_company_id
          AND period = date_trunc('month', target_business_date)::date
          AND status = 'closed'
    ) THEN
        RAISE EXCEPTION '小売元帳の対象月は月次締め済みです。追加・変更・削除できません。';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION retail_reject_closed_ledger_line_change()
RETURNS trigger AS $$
DECLARE
    target_document_id bigint;
    target_company_id bigint;
    target_business_date date;
BEGIN
    IF TG_OP = 'DELETE' THEN
        target_document_id := OLD.retail_ledger_document_id;
    ELSE
        target_document_id := NEW.retail_ledger_document_id;
    END IF;
    SELECT retail_company_id, business_date
      INTO target_company_id, target_business_date
      FROM retail_ledger_documents
     WHERE id = target_document_id;

    IF EXISTS (
        SELECT 1 FROM retail_monthly_closings
        WHERE retail_company_id = target_company_id
          AND period = date_trunc('month', target_business_date)::date
          AND status = 'closed'
    ) THEN
        RAISE EXCEPTION '小売元帳の対象月は月次締め済みです。追加・変更・削除できません。';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION retail_reject_closed_monthly_snapshot_change()
RETURNS trigger AS $$
DECLARE
    target_closing_id bigint;
BEGIN
    IF TG_OP = 'DELETE' THEN
        target_closing_id := OLD.retail_monthly_closing_id;
    ELSE
        target_closing_id := NEW.retail_monthly_closing_id;
    END IF;

    IF EXISTS (
        SELECT 1 FROM retail_monthly_closings
        WHERE id = target_closing_id AND status = 'closed'
    ) THEN
        RAISE EXCEPTION '月次締め済みの残高スナップショットは変更できません。';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION retail_reject_reclosed_month_change()
RETURNS trigger AS $$
BEGIN
    IF TG_OP IN ('UPDATE', 'DELETE') AND OLD.status = 'closed' THEN
        RAISE EXCEPTION '月次締め済みの締め記録は変更できません。';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER retail_ledger_documents_closed_period_guard
BEFORE INSERT OR UPDATE OR DELETE ON retail_ledger_documents
FOR EACH ROW EXECUTE FUNCTION retail_reject_closed_ledger_document_change();

CREATE TRIGGER retail_ledger_lines_closed_period_guard
BEFORE INSERT OR UPDATE OR DELETE ON retail_ledger_lines
FOR EACH ROW EXECUTE FUNCTION retail_reject_closed_ledger_line_change();

CREATE TRIGGER retail_monthly_balances_closed_snapshot_guard
BEFORE INSERT OR UPDATE OR DELETE ON retail_monthly_balances
FOR EACH ROW EXECUTE FUNCTION retail_reject_closed_monthly_snapshot_change();

CREATE TRIGGER retail_monthly_closings_closed_record_guard
BEFORE UPDATE OR DELETE ON retail_monthly_closings
FOR EACH ROW EXECUTE FUNCTION retail_reject_reclosed_month_change();
SQL);
    }

    public function down(): void
    {
        DB::connection('retail')->unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS retail_ledger_lines_closed_period_guard ON retail_ledger_lines;
DROP TRIGGER IF EXISTS retail_ledger_documents_closed_period_guard ON retail_ledger_documents;
DROP TRIGGER IF EXISTS retail_monthly_balances_closed_snapshot_guard ON retail_monthly_balances;
DROP TRIGGER IF EXISTS retail_monthly_closings_closed_record_guard ON retail_monthly_closings;
DROP FUNCTION IF EXISTS retail_reject_closed_monthly_snapshot_change();
DROP FUNCTION IF EXISTS retail_reject_reclosed_month_change();
DROP FUNCTION IF EXISTS retail_reject_closed_ledger_line_change();
DROP FUNCTION IF EXISTS retail_reject_closed_ledger_document_change();
SQL);
    }
};
