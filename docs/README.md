# 文書案内

## 読む順番

1. `current-state.md`: コードとテストで確認した現行機能、制約、未実装事項。
2. `manifest.md`: システム全体の業務原則と、完成時に守るべき規約。
3. `database.md`、`state-machine.md`、`monthly-flow.md`: DB、状態、月次処理の設計規約。
4. `screens.md`、`reports.md`: 画面と帳票の設計方針。
5. `tax-export-rules.md`、`returns-and-tax-adjustments.md`: 税務上の追加要件と実装課題。
6. `operation-test-plan.md`、`testing-rules.md`: 運用試験と自動テストの基準。
7. `g3-b1-a-validation-20260906.md`: B1 Access原本の抽出、Aステージング、警告隔離の検証記録。
8. `g4-release-candidate-precheck-20260906.md`: Aをリリース候補として固定する前の判定と停止条件。
9. `itaro-xp-cutover-migration-standard.md`: Itaro-XPからの初回移行、白紙化再移行、過去履歴追加の標準手順。
10. `b1-a-b2-migration-security-policy.md`: B1からA、B2を経て本番化する移行サイクル、変更統制、承認、復旧、AI利用のセキュリティ方針。
11. `lot-inventory-architecture.md`: ロット中心在庫を唯一の正規在庫とし、旧商品在庫を照合・監査専用へ分離する現行設計。
12. `a-b2-release-readiness-gates.md`: AからB2への完全移行を開始するための段階ゲートと承認条件。

## 文書の優先順位

* 業務規約が衝突する場合は `manifest.md` を優先する。
* 実装済みかどうかの判断は `current-state.md` とコード・自動テストを優先する。
* `stage*-check.md` と段階名を含む説明は、当時の確認記録であり、現在の仕様や実装状況を保証しない。
* 税務上の最終判断は税理士等の確認を前提とする。文書はシステム要件を定義するもので、個別取引の法的助言ではない。

## 用語

* **実装済み**: 現在のコードと自動テストで確認できる機能。
* **一部実装**: データ構造または基本処理はあるが、業務フローを完結できない機能。
* **未実装**: 業務要件としては定義するが、画面・API・Serviceのいずれかが不足している機能。
