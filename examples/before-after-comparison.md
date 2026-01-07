# Before/After 比較

実際の出力を比較して、改善内容を確認できます。

## 1. API Endpoint ドキュメント

### 📄 Before (以前の出力)

```markdown
<a href="../index.html" style="color: black; text-decoration: none;">API Doc Title</a>

# /user

**Request**

| Name  | Type  | Description | Default | Required | Constraints | Example |
|-------|-------|-------------|---------|----------|-------------|---------|
| id | string | User ID |  | Required | {} | user123 |
| options | string | User Options | guest | Optional | {} |  |


**Response**

(n/a)


#### Embedded

| rel | src |
|-----|-----|
| ticket | [/ticket/{id}](ticket.html) |


#### Links

| rel | href |
|-----|-----|
| person | [/person](person.html) |
| calendar | [/calendar](calendar.html) |


#### Example

<pre>
<code>{
  "id": "user123",
  "name": "John Doe",
  "email": "john@example.com"
}</code>
</pre>
```

### ✨ After (改善後の出力)

```markdown
<a href="../index.html" style="color: black; text-decoration: none;">API Doc Title</a>

# /user

## GET

### Request

| Name | Type | Description | Default | Required | Constraints | Example |
|------|------|-------------|---------|----------|-------------|---------|
| id | string | User ID |  | Required | {} | user123 |
| options | string | User Options | guest | Optional | {} |  |

### Response

[Object: User](../schema/user.json)

| Name | Type | Description | Required | Constraints | Example |
|------|------|-------------|----------|-------------|---------|
| id | string | Unique user identifier | Required | {} | user123 |
| name | string | User's full name | Required | {} | John Doe |
| email | string | User's email address | Optional | {"format":"email"} | john@example.com |

#### Embedded Resources

| Relation | Source |
|----------|--------|
| ticket | [/ticket/{id}](ticket.html) |

#### Links

| Relation | URL |
|----------|-----|
| person | [/person](person.html) |
| calendar | [/calendar](calendar.html) |

#### Example

```json
{
  "id": "user123",
  "name": "John Doe",
  "email": "john@example.com"
}
```
```

## 2. Index Page

### 📄 Before (以前の出力)

```markdown
# API Doc Title
This is description of API Doc

 * [doc](http://www.google.com/)
 * [foo](http://www.google.com/)
 * [foo](a)


## Paths
 * [/user](paths/user.html)
 * [/ticket](paths/ticket.html)
 * [/address](paths/address.html)


## Objects
 * [User](schema/user.json)
 * [Ticket](schema/ticket.json)
 * [Address](schema/address.json)
```

### ✨ After (改善後の出力)

```markdown
# API Doc Title

This is description of API Doc

- [doc](http://www.google.com/)
- [foo](http://www.google.com/)
- [foo](a)

## API Endpoints
- [/user](paths/user.html)
- [/ticket](paths/ticket.html)
- [/address](paths/address.html)

## Data Models
- [User](schema/user.json)
- [Ticket](schema/ticket.json)
- [Address](schema/address.json)
```

## 3. OpenAPI 3.0 Output (NEW!)

### 🚀 生成されるOpenAPI仕様

```json
{
    "openapi": "3.0.0",
    "info": {
        "title": "API Doc Title",
        "description": "This is description of API Doc",
        "version": "1.0.0"
    },
    "paths": {
        "/user": {
            "get": {
                "summary": "",
                "description": "",
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
                    },
                    {
                        "name": "options",
                        "in": "query",
                        "description": "User Options",
                        "required": false,
                        "schema": {
                            "type": "string"
                        }
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
        },
        "/ticket": {
            "get": {
                "summary": "Get ticket information",
                "description": "",
                "parameters": [],
                "responses": {
                    "200": {
                        "description": "Successful response",
                        "content": {
                            "application/json": {
                                "schema": {
                                    "$ref": "#/components/schemas/Ticket"
                                }
                            }
                        }
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
                        "description": "Unique user identifier",
                        "example": "user123"
                    },
                    "name": {
                        "type": "string",
                        "description": "User's full name",
                        "example": "John Doe"
                    },
                    "email": {
                        "type": "string",
                        "format": "email",
                        "description": "User's email address",
                        "example": "john@example.com"
                    }
                }
            },
            "Ticket": {
                "type": "object",
                "title": "Ticket",
                "required": ["ticketId", "status"],
                "properties": {
                    "ticketId": {
                        "type": "string",
                        "description": "Unique ticket identifier"
                    },
                    "status": {
                        "type": "string",
                        "enum": ["open", "closed", "pending"],
                        "description": "Ticket status"
                    },
                    "priority": {
                        "type": "integer",
                        "minimum": 1,
                        "maximum": 5,
                        "description": "Ticket priority level"
                    }
                }
            }
        }
    }
}
```

## 改善点の詳細

### ✅ Markdownの改善

| 要素 | Before | After | 改善内容 |
|------|--------|-------|----------|
| セクション見出し | `**Request**` | `### Request` | 適切なMarkdown階層 |
| テーブルヘッダー | スペース不統一 | 統一されたフォーマット | 可読性向上 |
| JSONコードブロック | `<pre><code>` | ` ```json ``` ` | シンタックスハイライト対応 |
| リストマーカー | ` * ` (スペース付き) | `- ` | 標準的なMarkdown |
| 列名 | "Constraint" | "Constraints" | 一貫性のある用語 |
| 列名 | "rel/href" | "Relation/URL" | より明確な用語 |
| セクション名 | "Paths" | "API Endpoints" | より分かりやすい |
| セクション名 | "Objects" | "Data Models" | より分かりやすい |
| 空の値 | "(n/a)" | "_Not available_" | Markdown標準 |

### ✅ OpenAPI 3.0の利点

| 機能 | 説明 |
|------|------|
| 標準形式 | 業界標準のAPI仕様形式 |
| ツール連携 | Swagger UI, Postman, OpenAPI Generatorなどと連携 |
| コード生成 | クライアントコードの自動生成が可能 |
| バリデーション | API仕様の自動検証 |
| ドキュメント | 対話的なAPIドキュメント生成 |
| テスト | 自動テストケース生成 |

### ✅ レンダリング比較

#### HTML出力時の違い

**Before:**
```html
<pre>
<code>{
  "id": "user123",
  "name": "John Doe"
}</code>
</pre>
```

**After:**
```html
<pre><code class="language-json">{
  "id": "user123",
  "name": "John Doe"
}</code></pre>
```

→ シンタックスハイライトライブラリ（Prism.js, highlight.jsなど）が適用可能

## 使用例

### Swagger UIでの表示

生成されたOpenAPI仕様をSwagger UIで表示：

```bash
docker run -p 8080:8080 \
  -e SWAGGER_JSON=/openapi.json \
  -v $(pwd)/docs/openapi.json:/openapi.json \
  swaggerapi/swagger-ui
```

ブラウザで `http://localhost:8080` を開くと、美しい対話的なAPIドキュメントが表示されます。

### Postmanへのインポート

1. Postmanを開く
2. Import → Upload Files
3. `openapi.json` を選択
4. すべてのエンドポイントが自動的にコレクションとして追加される
5. すぐにAPIテストを開始できる

### クライアントコード生成

```bash
# TypeScript/Axiosクライアント生成
openapi-generator-cli generate \
  -i docs/openapi.json \
  -g typescript-axios \
  -o generated/api-client

# 生成されたクライアントを使用
import { UserApi } from './generated/api-client';

const api = new UserApi();
const user = await api.getUser({ id: 'user123' });
```
