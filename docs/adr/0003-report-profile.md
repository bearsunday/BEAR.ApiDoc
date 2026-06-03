# ADR 0003: ApiDoc レポート profile と生成レポートの semantic 束縛（提案・レビュー用）

## Status

Proposed（codex レビュー待ち）

## Context

- `apidoc.xml` は **ApiDoc 出力要素の意味を定義する共通 profile**（アプリ非依存）。生成 HTML が `rel="profile"` で参照するのは正しく、固定の正準 URL でよい。
- 現状の profile は index.html 系の要素（`object` / `array` / `param` / `endpoint` / `response` / `method` / `rel` / `embed`）を定義するが、**terms / audit レポート固有の要素は未定義**。
- 1.11.0 の `terms.html` は `class` に **ドメイン descriptor id（`firstName` など）** を出しており、宣言している profile（レポート形式の語彙）と**不一致**。
- ☑ が**リテラルなグリフとしてマークアップに焼き込まれて**いる（意味ではなく装飾が構造に混入）。

## Decision（提案）

ApiDoc の生成レポート（terms / audit）の要素を、**ApiDoc レポート profile の descriptor に束縛**する。原則は **「意味は `class`、表現は CSS」**。

### 作業項目
1. **レポート profile の定義** — descriptor 例: `termUsageIndex` / `term` / `usage` / `coverage` / `alpsDescriptorBinding`（または被覆状態）/ `reservedRepresentationField` / `apiDocumentationAudit` / `operation` / `finding` / `findingType`。
2. **terms.html 再束縛** — `class=ドメインid` → `class="term"`（usages→`usage`、coverage→`coverage`、予約→`reservedRepresentationField`）。term 名は要素の中身として残す。
3. **ALPS 被覆の表現** — 被覆を**意味の `class`** で宣言し、☑ は **CSS（`::after`）でその結果として描画**。マークアップに ☑ グリフを持たない。
4. **audit.html 新規** — `AuditHtmlRenderer` ＋ `ApiDocAudit::generateHtml()`。装飾は最小。所見を endpoint / finding type でグルーピング。同 profile に束縛。`audit` 指定時に `audit.md` ＋ `audit.html` の両建て。
5. **中間レポートモデル** — md / html（将来 json）が同一収集データを共有。
6. **テスト・ドキュメント** — `rel=profile` 宣言＋代表的 descriptor 束縛＋決定論出力、README。

## Open Questions（codex レビュー対象）

1. **profile を1本にするか分けるか** — 既存 `apidoc.xml` を拡張するか、`apidoc-report.xml` を新設するか（#91 は新設案）。
2. **terms の class 置換の是非** — 1.11.0 の `class=ドメインid` を意図的にレポート語彙へ置換する（後方互換は出力 snapshot のみ、API 破壊なし）。良いか。
3. **rel=profile は1ドキュメント1語彙** — `class` はレポート語彙に一本化し、ドメインの意味は `def` リンク（schema.org 等）で分離する。良いか。
4. **profile URL** — 正準ホスト固定でよいか（設定で上書き不要か）。
5. **audit.json** — 1.12.0 は html＋profile まで。json は対象外（将来）でよいか。
6. **ALPS 被覆状態のモデル化・命名** — ALPS 流の descriptor 名（`alpsDescriptorBinding`）か、状態フラグ風（`isAlpsCovered`）か。term 要素の `class` に付けるか子要素か。CSS `::after` の glyph がスクリーンリーダーに読まれない点（a11y）をどう扱うか。

## Non-Goals

- ドメイン語彙 profile の自動生成（別件）。
- `audit.json`（将来）。
- 装飾的 UI（方針: 構造 ＞ 装飾）。

## Risks

- profile 語彙設計を誤ると全束縛が無意味化する → **設計段階で codex レビューを通す**（本 ADR の主旨）。
- terms 出力変更で 1.11.0 と差分が出る → snapshot テスト更新で吸収。

## References

- #90（生成レポートの HTML 化）, #91（レポート profile）
- [ADR 0002](0002-lexical-is-not-semantic.md)
- [ALPS specification](http://alps.io/)
