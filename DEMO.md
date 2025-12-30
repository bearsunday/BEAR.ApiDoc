# BEAR.ApiDoc 改善デモ

このPRで実装した2つの主要な改善のデモンストレーションです。

## 1. API表示の改善 ✨

### Before (以前)

```markdown
**Request**

| Name  | Type  | Description | Default | Required | Constraints | Example |
|-------|-------|-------------|---------|----------|-------------|---------|

**Response**

(n/a)
```

### After (改善後)

```markdown
### Request

| Name | Type | Description | Default | Required | Constraints | Example |
|------|------|-------------|---------|----------|-------------|---------|

### Response

_Not available_
```

### 主な改善点

✅ **セクション階層の改善**
- `**Request**` → `### Request` (h3見出し)
- `**Response**` → `### Response` (h3見出し)

✅ **JSON Exampleの修正**
- Before: `<pre><code>...</code></pre>`
- After: ` ```json ... ``` `（シンタックスハイライト対応）

✅ **テーブルヘッダーの統一**
- スペーシングを統一
- 列名を改善（"Constraint" → "Constraints"）

✅ **用語の統一**
- "rel/src" → "Relation/Source"
- "rel/href" → "Relation/URL"
- "Paths" → "API Endpoints"
- "Objects" → "Data Models"

## 2. OpenAPI 3.0 出力形式のサポート 🚀

### 使用方法

**設定ファイル (apidoc.xml):**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<apidoc>
    <appName>MyVendor\MyProject</appName>
    <scheme>app</scheme>
    <docDir>docs/api</docDir>
    <format>openapi</format>  <!-- ← OpenAPI形式を指定 -->
    <title>My API Documentation</title>
    <description>REST API for My Application</description>
</apidoc>
```

**生成コマンド:**

```bash
php bin/apidoc apidoc.xml
# Output: ApiDoc generated. docs/api/openapi.json
```

### 生成される OpenAPI 3.0 仕様 (サンプル)

```json
{
    "openapi": "3.0.0",
    "info": {
        "title": "My API Documentation",
        "description": "REST API for My Application",
        "version": "1.0.0"
    },
    "paths": {
        "/user": {
            "get": {
                "summary": "Get user information",
                "description": "Retrieves user details by ID",
                "parameters": [
                    {
                        "name": "id",
                        "in": "query",
                        "description": "User ID",
                        "required": true,
                        "schema": {
                            "type": "string"
                        },
                        "example": "user123"
                    }
                ],
                "responses": {
                    "200": {
                        "description": "Successful response",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "$ref": "#/components/schemas/User"
                                }
                            }
                        }
                    }
                }
            },
            "post": {
                "summary": "Create user",
                "description": "Create user with given name and age",
                "parameters": [
                    {
                        "name": "name",
                        "in": "query",
                        "description": "The name of the user",
                        "required": true,
                        "schema": {
                            "type": "string"
                        }
                    },
                    {
                        "name": "age",
                        "in": "query",
                        "description": "The age of the user",
                        "required": true,
                        "schema": {
                            "type": "integer"
                        }
                    }
                ],
                "responses": {
                    "200": {
                        "description": "Successful response"
                    }
                }
            }
        }
    },
    "components": {
        "schemas": {
            "User": {
                "type": "object",
                "title": "User",
                "required": ["id", "name"],
                "properties": {
                    "id": {
                        "type": "string",
                        "description": "Unique user identifier"
                    },
                    "name": {
                        "type": "string",
                        "description": "User's full name"
                    },
                    "email": {
                        "type": "string",
                        "format": "email",
                        "description": "User's email address"
                    },
                    "age": {
                        "type": "integer",
                        "minimum": 0,
                        "maximum": 150
                    }
                }
            }
        }
    }
}
```

### OpenAPI仕様の活用方法

生成されたOpenAPI仕様は以下のツールで利用できます：

#### 1. **Swagger UI** で対話的なドキュメント表示

```bash
# Docker でSwagger UIを起動
docker run -p 8080:8080 \
  -e SWAGGER_JSON=/openapi.json \
  -v $(pwd)/docs/api/openapi.json:/openapi.json \
  swaggerapi/swagger-ui

# ブラウザで http://localhost:8080 を開く
```

#### 2. **OpenAPI Generator** でクライアントコード生成

```bash
# PHPクライアント生成
openapi-generator-cli generate \
  -i docs/api/openapi.json \
  -g php \
  -o generated/php-client

# TypeScriptクライアント生成
openapi-generator-cli generate \
  -i docs/api/openapi.json \
  -g typescript-axios \
  -o generated/ts-client
```

#### 3. **Postman** でAPIテスト

1. Postmanを開く
2. Import → Upload Files
3. `openapi.json` を選択
4. すべてのエンドポイントが自動的にコレクションに追加される

#### 4. **API仕様のバリデーション**

```bash
# Spectral でバリデーション
spectral lint docs/api/openapi.json

# または swagger-cli
swagger-cli validate docs/api/openapi.json
```

## 3. 包括的なテスト 🧪

### テストカバレッジ

#### OpenAPI仕様バリデーションテスト

```php
// OpenAPI 3.0 仕様への準拠を検証
public function testValidOpenApiSpec(): void
{
    // ✅ 必須フィールドの存在確認
    // ✅ info, paths, components の検証
    // ✅ operation構造の検証
}

// パラメータの妥当性検証
public function testParametersAreValid(): void
{
    // ✅ name, in フィールドの存在
    // ✅ 'in' の値が正しいか (query, header, path, cookie)
    // ✅ required フィールドの型チェック
}

// レスポンスの妥当性検証
public function testResponsesAreValid(): void
{
    // ✅ すべてのoperationがresponsesを持つ
    // ✅ レスポンスにdescriptionがある
    // ✅ status codeの形式チェック
}
```

### テスト実行

```bash
# すべてのテストを実行
./vendor/bin/phpunit

# OpenAPIテストのみ
./vendor/bin/phpunit --filter OpenApiGeneratorTest

# 静的解析
composer sa  # PHPStan level max: ✅ No errors
```

## 4. 実装の詳細

### アーキテクチャ

```
src/
├── ApiDoc.php               # メインエントリーポイント
├── OpenApiGenerator.php     # OpenAPI生成ロジック (新規)
├── DocMethod.php            # 改善されたMarkdown生成
├── DocParam.php             # パラメータ表示 (改善)
├── Index.php                # インデックスページ (改善)
└── Schema.php               # スキーマ処理 (改善)
```

### 主要クラス: OpenApiGenerator

```php
final class OpenApiGenerator
{
    public function generate(): string
    {
        // 1. リソースファイルを走査
        foreach ($this->config->resourceFiles as $meta) {
            $this->processResource($path, $class);
        }

        // 2. スキーマをコンポーネントに追加
        $this->openApiSpec['components']['schemas'] = $this->schemas;

        // 3. JSON形式で出力
        return json_encode($this->openApiSpec, JSON_PRETTY_PRINT);
    }

    private function processMethod(ReflectionMethod $method): array
    {
        // PHPDocからsummary/description抽出
        // JSON Schemaアノテーションからパラメータ抽出
        // レスポンススキーマ参照を生成
    }
}
```

## 5. 移行ガイド

### 既存プロジェクトでの使用

**ステップ1: 設定を更新**

```xml
<!-- apidoc.xml -->
<format>openapi</format>  <!-- html/md/openapi から選択 -->
```

**ステップ2: ドキュメント生成**

```bash
php bin/apidoc apidoc.xml
```

**ステップ3: 生成されたファイルを確認**

- HTML/MD形式: `docs/index.html`
- OpenAPI形式: `docs/openapi.json`

### 互換性

✅ **後方互換性あり**
- 既存のHTML/MD形式は変更なしで動作
- 新しいOpenAPI形式はオプション

✅ **段階的な移行が可能**
- 既存のドキュメントをそのまま使用
- 必要に応じてOpenAPI形式を追加

## 6. まとめ

### 改善された点

| 項目 | Before | After |
|------|--------|-------|
| 出力形式 | HTML, Markdown | HTML, Markdown, **OpenAPI 3.0** |
| Exampleレンダリング | `<pre><code>` | ` ```json ``` ` |
| セクション見出し | `**Bold**` | `### h3` |
| テーブル | 不統一 | 統一されたフォーマット |
| バリデーション | なし | **OpenAPI仕様準拠テスト** |
| 静的解析 | 14エラー | **0エラー** |

### 新機能

🎯 **OpenAPI 3.0完全サポート**
- 業界標準形式での出力
- Swagger UI, Postman等と互換
- クライアントコード自動生成対応

🎯 **改善されたドキュメント表示**
- より読みやすいMarkdown
- 適切なシンタックスハイライト
- 統一されたフォーマット

🎯 **包括的なテストスイート**
- OpenAPI仕様バリデーション
- パラメータ/レスポンス検証
- PHPStan level max準拠

---

**PR**: https://github.com/bearsunday/BEAR.ApiDoc/pull/52
**Commits**: 5 commits (80514cf...3b8fe84)
**Files Changed**: +448, -29 lines
