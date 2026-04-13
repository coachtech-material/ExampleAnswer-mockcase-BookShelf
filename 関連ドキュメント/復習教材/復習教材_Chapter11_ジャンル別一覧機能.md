# Chapter 11: 分類の窓口 - ジャンル別一覧機能を実装する

## 🎯 このChapterの目標

このチャプターでは、特定のジャンルに属する書籍を一覧表示する機能を実装します。

- **リレーションを活用した絞り込み**: ジャンルから関連する書籍を取得
- **Eager Loading**: N+1問題を防ぐ `with()` メソッドの実践
- **ページネーション**: 大量データの分割表示

---

## 📋 要件の確認

| 操作 | HTTPメソッド | URI | コントローラー@メソッド | 認証 |
|:---|:---|:---|:---|:---|
| ジャンル別書籍一覧 | GET | `/genres/{genre}` | GenreController@show | 不要 |

---

## 💭 なぜこう作るのか？

### Genre起点のデータ取得

`$genre->books()` でジャンルから書籍へのリレーションを起点に、簡潔にデータを取得します。

---

## 🚀 コードの実装

### `GenreController@show`

```php
public function show(Genre $genre)
{
    $books = $genre->books()->with('genres')->paginate(10);

    return view('genres.show', compact('genre', 'books'));
}
```

---

## 🔍 コードリーディング

| コード | 解説 |
|:---|:---|
| `$genre->books()` | 指定ジャンルに属する書籍のクエリビルダを生成。 |
| `->with('genres')` | 各書籍に紐づくジャンル情報をEager Loading。N+1問題を防止。 |
| `->paginate(10)` | 10件ずつページネーション。 |
| `compact('genre', 'books')` | ジャンル情報と書籍一覧をビューに渡す。 |

---

## ✨ このChapterのまとめ

- **Genre起点のデータ取得**: `$genre->books()` で簡潔に取得
- **Eager Loadingの活用**: `with('genres')` でN+1問題を防止
- **ページネーション**: `paginate(10)` で大量データに対応

次の Chapter 12 では、**ジャンル管理機能（CRUD）**を実装します。
