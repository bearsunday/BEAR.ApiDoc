# ADR 001: HTML API Documentation Design

## Status

Accepted

## Context

BEAR.ApiDocのHTML出力デザインが簡素すぎた。OpenAPIにはないBEAR.Sundayの価値（ハイパーメディア：embed/link関係）を視覚的に表現する必要がある。

## Decision

### 全体構造

1ページ構成：
- **Endpoints** - APIエンドポイント一覧
- **Objects** - レスポンススキーマ定義

### Endpoints テーブル

| 列 | 内容 |
|----|------|
| Path | エンドポイントパス（rowspanでグループ化） |
| Method | HTTPメソッド + 型インジケータ |
| Name | パラメータ名（*で必須表示） |
| Description | パラメータ説明 |
| Meta | 型 + 制約（バッジ形式） |
| Example | 値の例 |
| Response | スキーマ名（Objectsへリンク） |

### Objects セクション

各オブジェクトは以下を含む：
- プロパティ一覧（Name, Description, Meta, Example）
- **embed関係** - 黄色スティッキー行
- **link関係** - 青スティッキー行

### バッジスタイル（アウトライン）

```css
/* 型バッジ */
.badge.type-string  /* 青 */
.badge.type-int     /* 紫 */
.badge.type-bool    /* 緑 */
.badge.type-array   /* オレンジ */
.badge.type-object  /* ピンク */

/* 制約バッジ */
.badge.constraint   /* グレー: maxLength: 30, min: 0, enum: a, b */
.badge.format       /* 緑: format: email, format: date-time */

/* 関係バッジ */
.badge.embed        /* 紫 */
.badge.link         /* 青 */
```

### スティッキー行（embed/link）

```css
.embed-row {
  background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
  box-shadow: 3px 3px 6px rgba(0,0,0,0.15);
  border-left: 3px solid #F59E0B;
}

.link-row {
  background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
  box-shadow: 3px 3px 6px rgba(0,0,0,0.15);
  border-left: 3px solid #3B82F6;
}
```

### 型インジケータ（HTTPメソッド）

ALPSスタイルの色分け：
- **safe (GET)** - 緑チェッカー
- **idempotent (PUT)** - 黄ドット
- **unsafe (POST/DELETE)** - 赤ストライプ

### スキーマID

- `$id` あり → `$id` を使用
- `$id` なし → Warning出力 + ファイル名フォールバック

### タイトル

`apidoc.xml` の `<title>` から取得

## Consequences

### Positive

- ハイパーメディア関係（embed/link）が視覚的に明確
- OS9スティッカー風デザインで親しみやすい
- バッジ形式で情報密度が高い
- OpenAPIにない価値を提供

### Negative

- HTMLのみの表現力（Markdownは簡素化される）
- CSS依存（印刷時の考慮必要）

## Template

See: `prototype12.html`
