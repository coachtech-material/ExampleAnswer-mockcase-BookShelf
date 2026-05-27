# Chapter 15: 既存コードへの品質強化（型宣言・PHPDoc・Collection）

> このChapterの本文は Phase 1A で記述します（現状はスケルトン）。

---

## 🎯 このセクションで学ぶこと

このChapterから「応用機能編」に入ります。応用機能編の最初の Chapter として、Chapter 14 までで実装した Basic コードに対して品質強化を一括で施します。

- クロージャ・メソッドへの戻り値型宣言（`: void` 等）の追加
- 各クラス・メソッドへの PHPDoc コメントの整備
- ランキング機能等での Collection メソッドへのリファクタリング

これらを Chapter 16 以降の応用機能実装に入る前に済ませることで、追加機能を一貫した品質基準のもと実装できます。

---

## 前提

- Chapter 14 までを完了している（Basic 模範解答コードと同一の状態）

## 応用機能編開始の準備: 提供 Blade ファイル（Advanced 用）の再取得

Chapter 04 で配置した **Basic ブランチ** の Blade を、応用機能用の **Advanced ブランチ** の Blade に上書きします。これにより応用機能用の Blade（マイレポート画面 / 読書計画画面 / 通知画面 / 高度な検索フォーム / ISBN 検索ボタン等）が利用可能になります。

```bash
# 提供 Blade リポジトリの Advanced ブランチを取得
git clone -b Advanced https://github.com/coachtech-prepared-file/Preparedblade-mockcase-BookShelf.git /tmp/prepared-blade-advanced

# resources/views/ 配下をすべて上書き
cp -r /tmp/prepared-blade-advanced/resources/views/. resources/views/
```

> **注:** Basic ブランチで先に動かしていた認証・書籍・ジャンル等の Blade も Advanced 版に置き換わりますが、Advanced 用 Blade は Basic 機能と互換のため動作が壊れることはありません。

## 概要（記述予定）

- 15.1. マイグレーションファイルへの型宣言追加
- 15.2. モデルへの戻り値型宣言・PHPDoc 追加
- 15.3. コントローラ・FormRequest への戻り値型宣言・PHPDoc 追加
- 15.4. Service / Repository 系（該当があれば）への型宣言・PHPDoc 追加
- 15.5. Collection メソッドへのリファクタリング（ランキング機能等）

> 詳細は後続セッションで記述します。
