# 酒蔵出荷管理システムのB1-A-B2移行

## 目的

Itaro-XP Accessを業務原本（B1）、ローカルの検証環境をA、本番候補環境をB2として扱い、酒蔵出荷管理システムを安全に移行・検証・本番化するための再実行可能な運用パッケージである。

このパッケージの正本は、移行のたびに新しい証跡を作る。過去のバッチ番号、ハッシュ、金額、承認は今回限りの記録であり、次回へ流用しない。

## 環境と役割

| 環境 | 役割 | 業務データの正 |
| --- | --- | --- |
| B1 | Itaro-XP Accessを使う現行業務環境 | 切替まで常にB1 |
| A | 抽出、差分移行、月次・帳票・業務照合を行う検証環境 | B1の読取専用スナップショットと検証済みの変更 |
| B2 | Aを丸ごと昇格して受入試験を行う環境 | 検証中はAの完全な複製。B2単独の恒久修正は残さない |

## パッケージの使い方

1. [実行手順](runbook.md) の「開始判定」を満たすことを確認する。
2. B1から読取専用のスナップショットを取得し、既存のAccess抽出・差分計画をAで実行する。
3. [照合表](verification-matrix.md) の全対象について、B1とAを照合する。
4. `scripts/migration/New-BreweryB1AB2ReleaseManifest.ps1` で、A→B2昇格に使う成果物とハッシュを固定する。
5. AをB2へ完全昇格し、B2で受入試験を行う。
6. 例外、停止、差異は [例外記録テンプレート](templates/exception-record.md) に残す。

実行中に新しい差異が見つかった場合は、B2だけで直さない。Aで再現し、コード、スキーマ、移行規則または照合規則として修正してから、Aを再昇格する。

## 既存実装との対応

| 用途 | 既存の実装・資料 |
| --- | --- |
| B1 Accessの読取専用抽出とmanifest作成 | `scripts/access/Export-AccessMigrationPackage.ps1` |
| Aへのステージング | `scripts/access/Stage-AccessMigration.ps1` |
| 差分の計画と承認後の適用 | `scripts/access/Invoke-AccessDeltaMigration.ps1` |
| Access移行仕様 | [../access-migration.md](../access-migration.md) |
| B1-A-B2の変更統制 | [../b1-a-b2-migration-security-policy.md](../b1-a-b2-migration-security-policy.md) |
| A-B2のゲート | [../a-b2-release-readiness-gates.md](../a-b2-release-readiness-gates.md) |
| 過去の差分移行の実例 | [../g3-batch40-delta-review-20260908.md](../g3-batch40-delta-review-20260908.md) |

## このパッケージで固定した原則

- B1はAIを含めて読取専用とする。
- B1→Aは、原本ハッシュ、行数、抽出ファイルのハッシュ、バッチIDを残す。
- `changed`、`deleted`、締め済み月へ影響する`new`は自動適用しない。
- A→B2は差分同期ではなく、コード、DB、帳票・添付を同じ時点の一組として完全昇格する。
- B2の本番化前は、外部連携、通知、印刷、ジョブが実取引へ影響しないようにする。
- 本番B2では、復元可能な事前バックアップと復元確認を必須とする。テストB2で省略する場合も、対象・理由・有効期間を明示した例外記録を残す。

## 関連資料

- [実行手順](runbook.md)
- [照合表](verification-matrix.md)
- [今回までの知見](lessons-learned.md)
- [リリース記録テンプレート](templates/release-record.md)
- [例外記録テンプレート](templates/exception-record.md)
