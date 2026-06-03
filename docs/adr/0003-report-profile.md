# ADR 0003: 生成レポートの per-report profile と semantic 束縛

## Status

Accepted（旧 Proposed 版を全面改訂。terms は #98 で実装済、audit は本 ADR で実装）

## Context

- `apidoc.xml` は **ApiDoc の本体ドキュメント（index.html 系）の要素**を定義する共通 profile（`object` / `array` / `param` / `endpoint` / `response` / `method` / `rel` / `embed`）。アプリ非依存で、正準 URL 固定でよい。
- ApiDoc は本体とは別に **生成レポート**を出す: `terms`（語句使用インデックス）と `audit`（ドキュメント不足の監査）。これらは本体とは**別の語彙**を持つメタ文書であり、`apidoc.xml` では記述されていなかった。
- 1.11.0 の `terms.html` は `class` に**ドメイン descriptor id（`firstName` 等）**を出していた。宣言している profile はレポート形式の語彙のはずで、ドメイン id を class に出すのは層の取り違え。`rel="profile"` も本体用 `apidoc.xml` を指しており、宣言と中身が機械的に束縛できなかった。
- ☑（ALPS 被覆マーク）が**リテラルなグリフとしてマークアップに焼き込まれて**いた。意味ではなく装飾が構造に混入している。

## Decision

### 原則

1. **レポートは自分の profile を持つ（per-report）。** 共通の `apidoc-report.xml` に terms と audit を混ぜない。terms と audit は別語彙のメタ文書であり、混ぜると「宣言した profile に何が束縛されるか」が曖昧になる。
   - `terms.html` → `terms.xml`（実装済 / #98）
   - `audit.html` → `audit.xml`（本 ADR）
2. **レポート profile はドメイン非依存。** レポートが語るのは*レポートの構造*だけ——「これは term だ」「パラメータとして使われている」「これは finding でその型は X」。term が *firstName という意味かどうか*はレポートの関心事ではない。
3. **ドメイン同一性は不透明な外部ポインタとして置く。** 必要なとき、各要素は `data-alps` 属性でアプリ ALPS descriptor の id を指すだけにする。これは「アプリ側に同名 descriptor がある」という事実の参照であって、意味の主張ではない。
   - 補足: ALPS の `def` は外部定義（schema.org／他 ALPS／別定義）への**参照リンク**であり、意味の埋め込みではない。アプリ ALPS から借用した `def` をレポートに**表示する**のは可（典拠はレポートではない、と明示した上で）。レポート profile 自身の descriptor は def を持たず、ドメイン非依存を保つ。
4. **意味は `class`、表現は CSS。** ☑ はマークアップに glyph を持たず、ALPS 被覆という*状態を `class` で宣言*し、☑ はその状態の結果として **CSS（`::after`）で描画**する。

### 作業項目

1. **terms.xml（実装済 / #98）** — descriptor: `termUsageIndex` / `term` / `alpsBacked` / `borrowedDescriptor` / `usage`（子 `parameterUsage` / `schemaPropertyUsage`）/ `reservedField` / `termsUsedCount` / `alpsMatchedCount` / `lexicalCoverage` / `reservedCount`。各要素を class で束縛、ドメイン参照は `data-alps`。
2. **☑ の CSS 化** — `TermUsageHtmlRenderer` から `<span>` の ☑ glyph を除去。被覆は `dt.alpsBacked` の class で宣言済なので、☑ は `dt.alpsBacked` に対する CSS `::after` で描画する。
3. **audit.xml（新規）** — audit レポートの語彙を定義する per-report profile。descriptor 案: `apiDocumentationAudit`（文書全体）/ `operation`（監査対象の endpoint×method）/ `finding`（不足の指摘）/ `findingType`（response-schema / request-schema / class-summary / operation-summary / alps の別）/ サマリー指標（`resourceCount` / `operationCount` / `responseSchemaCount` / `requestSchemaCount` / `alpsAttributeCount`）。ドメイン非依存。
4. **audit.html（新規）** — `AuditHtmlRenderer` ＋ `ApiDocAudit::generateHtml()`。装飾は最小。所見を operation でグルーピングし、各要素を `audit.xml` の descriptor に class で束縛。`format="audit"` のとき `audit.md` ＋ `audit.html` の両建て。`audit.md` は維持。
5. **テスト・ゴールデン** — `rel="profile"` 宣言・代表的 descriptor 束縛・決定論出力をテスト。`docs/audit.md` ＋ `docs/audit.html` を再生成。`docs/alps/audit.xml` を追加。

### a11y

CSS `::after` の glyph はスクリーンリーダーに読まれないため、ALPS 被覆の意味が支援技術に伝わらない。被覆エントリにアクセシブルな代替（`title` 属性、または視覚的に隠したテキスト）を持たせ、意味が CSS だけに依存しないようにする。

## Open Questions（解決済み）

1. profile を分けるか → **分ける（per-report）。** 共通 profile は却下。
2. terms の class 置換 → **置換する**（#98 実施済）。
3. ドメインの意味の扱い → **レポート profile はドメイン非依存。** 同一性は不透明な `data-alps` ポインタ。`def` は参照リンクであり借用表示は可。
4. profile URL → **正準ホスト固定。**
5. `audit.json` → **対象外（将来）。**
6. ☑ の CSS 化・a11y → **CSS `::after` 化し、アクセシブルな代替を併設。**

## Non-Goals

- ドメイン語彙 profile の自動生成（別件）。
- `audit.json`（将来）。
- 装飾的 UI（方針: 構造 ＞ 装飾）。

## Risks

- per-report で profile が増える → ただし各 profile は小さく、宣言と束縛が1対1で閉じる利点が勝る。
- ☑ の CSS 化で被覆の意味が CSS 依存になる → a11y 節の代替で担保。
- terms / audit の出力変更で snapshot 差分 → ゴールデン更新で吸収。

## References

- #98（terms に専用 profile / 本 ADR の terms 部分の実装）
- #90（生成レポートの HTML 化）, #91（レポート profile）
- [ADR 0002](0002-lexical-is-not-semantic.md)
- [ALPS specification](http://alps.io/)
