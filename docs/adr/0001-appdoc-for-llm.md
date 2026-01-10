# ADR 0001: AppDoc - Application Documentation for LLM

## Status

Proposed

## Context

BEAR.ApiDocは現在、外部API利用者向けのドキュメント（HTML/Markdown/OpenAPI）を生成している。
しかし、AI/LLM支援開発において、アプリケーション内部構造全体を理解させる必要がある。

現状の課題：
- APIエンドポイントのみでは、データフロー全体が見えない
- SQL、Entity、MediaQueryインターフェイスの情報が欠落
- AIがコード生成する際、既存構造との整合性を保てない

## Decision

新しいドキュメント形式「AppDoc」を導入する。

### 出力内容

1. **Routes** - HTTPルートとResourceのマッピング
2. **ResourceObjects** - 全ResourceObjectとonメソッド、パラメータ、Links
3. **Query Interfaces** - Query/Commandインターフェイス
4. **SQL** - 全SQLファイルとその内容
5. **Entities** - エンティティクラスとプロパティ（ドメインの語彙）

### 出力形式

CLAUDE.mdに埋め込み可能なMarkdown形式。詳細は [appdoc-sample.md](appdoc-sample.md) を参照。

### 表記規則

| 表記 | 意味 |
|------|------|
| `id*` | 必須パラメータ |
| `href(goUser)` | outbound link (遷移先) |
| `src(ticket)` | inbound embed (埋め込み) |
| `(3)` | セクション内のアイテム数 |

### 使用目的

1. CLAUDE.mdに含めてAI開発支援
2. このドキュメントからALPSプロファイルを自動生成

## ALPS生成

AppDocにはALPS生成に必要な情報が全て含まれている。

### オントロジー（語彙）の抽出

パラメーター名とエンティティプロパティから、ドメインの語彙を抽出：

```
Parameters: id, name, email, age, title, description, status, assignee
Entities: User(id, name, email, age), Ticket(id, title, status, assignee)
```

→ ALPS `descriptor` (semantic)

### タクソノミー（関係性）の抽出

Links（href/src）とEntity構造から、リソース間の関係を抽出：

```
User --src(ticket)--> Ticket    (埋め込み関係)
Ticket --href(goUser)--> User   (遷移関係)
```

→ ALPS `descriptor` (transition)

### 生成されるALPS

```json
{
  "alps": {
    "descriptor": [
      {"id": "id", "def": "identifier"},
      {"id": "name", "def": "name of entity"},
      {"id": "User", "type": "semantic", "descriptor": [
        {"href": "#id"}, {"href": "#name"}, {"href": "#email"}, {"href": "#age"}
      ]},
      {"id": "Ticket", "type": "semantic", "descriptor": [
        {"href": "#id"}, {"href": "#title"}, {"href": "#status"}
      ]},
      {"id": "goUser", "type": "safe", "rt": "#User"},
      {"id": "ticket", "type": "safe", "rt": "#Ticket"}
    ]
  }
}
```

## Consequences

### Positive

- AIがアプリ全体構造を理解できる
- コード生成の精度向上
- ALPSプロファイル生成の自動化が可能
- 新規開発者のオンボーディング改善
- SQLの中身が見える（他のAPIドキュメントにはない特徴）

### Negative

- BEAR.ApiDocの責務拡大
- Ray.MediaQuery依存の情報を扱う必要がある

### Risks

- 大規模アプリでは出力が大きくなりすぎる可能性
  - → カウント表示 `(150)` で規模は把握可能

## Implementation Notes

### 設定ファイル

既存の `apidoc.xml` を使用し、formatに`llms`を指定：

```xml
<apidoc>
    <appName>MyVendor\MyProject</appName>
    <scheme>app</scheme>
    <docDir>docs</docDir>
    <format>llms</format>
    <description>A ticket management system</description>
</apidoc>
```

SQLディレクトリはRay.MediaQueryの`SqlDir`バインディングから自動検出される。

### 検出対象

| 種類 | 検出方法 |
|------|----------|
| Routes | Aura.Router設定 |
| ResourceObjects | `src/Resource/{App,Page}/**/*.php` |
| Query Interface | `src/Query/**/*Interface.php` |
| SQL | DIの`SqlDir`または`var/sql/**/*.sql` |
| Entities | `src/Entity/**/*.php` |

### オプション

```
--links    ファイルへのリンクを追加
```

## Related Documents

llms.txtは全体像に特化し、詳細は別ドキュメントに分離する。

| ファイル | 内容 |
|----------|------|
| **llms.txt** | 全体像（Routes, ResourceObjects, Query Interfaces, SQL, Entities） |
| APP_DB.md | テーブル構造、FK、インデックス |
| APP_CACHE.md | キャッシュ設定、TTL |
| APP_AUTH.md | 認証・認可設定 |

CLAUDE.mdでの参照：
```markdown
## App Documentation
- [llms.txt](llms.txt) - 全体像
- [APP_DB.md](APP_DB.md) - データベース
- [APP_CACHE.md](APP_CACHE.md) - キャッシュ
- [APP_AUTH.md](APP_AUTH.md) - 認証
```

AIが必要に応じて読みに行く。

## References

- [llms.txt specification](https://llmstxt.org/)
- [ALPS specification](http://alps.io/)
- [Ray.MediaQuery](https://github.com/ray-di/Ray.MediaQuery)
