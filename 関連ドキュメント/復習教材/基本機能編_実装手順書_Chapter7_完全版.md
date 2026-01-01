'''# Chapter 7: Bladeテンプレートによるフロントエンド実装

このChapterでは、これまでに実装してきたバックエンドの機能と連携するフロントエンドの画面を、Laravelのテンプレートエンジン「Blade」を使って構築します。コンポーネントベースの設計や、パフォーマンスを意識したデータの表示方法が重要なポイントです。

> **思考プロセス:**
> なぜHTMLを直接書かずにBladeを使うのでしょうか？ Bladeは、PHPのコードをHTML内に簡潔かつ安全に埋め込むための強力な機能を提供します。
> 
> - **可読性**: `<?php echo htmlspecialchars($book->title); ?>` のような冗長な記述は、`{{ $book->title }}` と書くだけで済みます。Bladeが自動的にXSS（クロスサイトスクリプティング）対策のエスケープ処理を行ってくれるため、安全です。
> - **制御構文**: `@if`, `@foreach`, `@auth` のような直感的なディレクティブを使って、条件分岐やループ、認証状態のチェックをHTML構造を崩さずに記述できます。
> - **コンポーネントとレイアウト**: アプリケーション全体で共通のヘッダーやフッターを「レイアウト」として定義し、各ページはその中のコンテンツ部分だけを記述すればよくなります。これにより、コードの重複を劇的に削減し、一貫性のあるUIを効率的に構築できます。

## 7-1. レイアウトの作成と適用

アプリケーション全体の骨格となるレイアウトファイルを作成します。Laravel Breezeをインストールすると、`resources/views/layouts/app.blade.php` に基本的なレイアウトが生成されているので、これを活用します。

**`resources/views/books/index.blade.php`**
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            書籍一覧
        </h2>
    </x-slot>

    <div class="py-12">
        {{-- この内側が {{ $slot }} に挿入される --}}
    </div>
</x-app-layout>
```

> **コード解説:**
> - `<x-app-layout>`: `resources/views/components/app-layout.blade.php` をコンポーネントとして呼び出しています。これがレイアウトの本体です。
> - `<x-slot name="header">`: レイアウトファイル内の `{{ $header }}` の部分に、このスロット内のコンテンツを挿入します。
> - `<x-app-layout>` タグに囲まれた部分が、レイアウトファイル内の `{{ $slot }}` の部分に挿入されます。

## 7-2. 書籍一覧・詳細ページの作成

`BookController`の`index`と`show`アクションに対応するビューを作成します。

### 書籍一覧 (`books.index`)

**`resources/views/books/index.blade.php`**
```blade
<x-app-layout>
    {{-- ... headerスロット ... --}}

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @foreach ($books as $book)
                        <div class="mb-4 p-4 border-b">
                            <h3 class="text-lg font-bold">
                                <a href="{{ route('books.show', $book) }}">{{ $book->title }}</a>
                            </h3>
                            <p class="text-gray-600">著者: {{ $book->author }}</p>
                            <div>
                                @foreach ($book->genres as $genre)
                                    <span class="text-sm text-white bg-gray-500 px-2 py-1 rounded">{{ $genre->name }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                    {{ $books->links() }} {{-- ページネーションリンク --}}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

> **思考プロセス (N+1問題の回避):**
> このループの中で `$book->genres` にアクセスしています。もし`BookController@index`で `Book::latest()->paginate(10)` のようにデータを取得していた場合、書籍の数だけジャンルを取得するクエリが追加で発行され、パフォーマンスが著しく悪化します（これがN+1問題です）。
> 
> `Book::with('genres')->latest()->paginate(10)` のように **Eager Loading (事前読み込み)** を行うことで、書籍を取得するクエリと、それら全ての書籍に関連するジャンルを取得するクエリの、合計2つのクエリで済むようになります。

### 書籍詳細 (`books.show`)

書籍の詳細情報に加え、レビューの一覧と投稿フォームを表示します。

**`resources/views/books/show.blade.php`**
```blade
<x-app-layout>
    {{-- ... 書籍詳細情報 ... --}}

    {{-- レビュー投稿フォーム --}}
    @auth
    <div class="mt-8">
        <h2 class="text-xl font-bold">レビューを投稿する</h2>
        <form action="{{ route('reviews.store', $book) }}" method="POST">
            @csrf
            {{-- ... フォーム項目 (rating, comment) ... --}}
            <button type="submit">投稿</button>
        </form>
    </div>
    @endauth

    {{-- レビュー一覧 --}}
    <div class="mt-8">
        <h2 class="text-xl font-bold">レビュー一覧</h2>
        @forelse ($book->reviews as $review)
            <div class="border-t py-4">
                <p><strong>{{ $review->user->name }}</strong> (評価: {{ $review->rating }})</p>
                <p>{{ $review->comment }}</p>
                @can('delete', $review)
                    <form action="{{ route('reviews.destroy', $review) }}" method="POST" onsubmit="return confirm('本当に削除しますか？');">
                        @csrf
                        @method('DELETE')
                        <button type="submit">削除</button>
                    </form>
                @endcan
            </div>
        @empty
            <p>まだレビューはありません。</p>
        @endforelse
    </div>
</x-app-layout>
```

> **コード解説:**
> - `@forelse ... @empty ... @endforelse`: `@foreach` と似ていますが、コレクションが空の場合に `@empty` の部分が表示される便利なディレクティブです。
> - `@can('delete', $review)`: Chapter 5で実装した`ReviewPolicy`を呼び出しています。認可されたユーザー（この場合はレビューの投稿者本人）にのみ、削除ボタンが表示されます。

## 7-3. 書籍登録・編集フォームの作成

登録と編集のフォームは非常によく似ているため、共通の部分を部分テンプレートとして切り出します。

### フォーム部分テンプレート (`_form.blade.php`)

**`resources/views/books/_form.blade.php`**
```blade
@csrf
<div class="mb-4">
    <label for="title">タイトル</label>
    <input type="text" name="title" id="title" value="{{ old('title', $book->title ?? '') }}">
</div>
{{-- ... author, isbnなどの他のフォーム項目 ... --}}
<div class="mb-4">
    <label>ジャンル</label>
    @foreach ($genres as $genre)
        <input type="checkbox" name="genres[]" value="{{ $genre->id }}" 
            @if(is_array(old('genres', $book->genres->pluck('id')->toArray() ?? [])) && in_array($genre->id, old('genres', $book->genres->pluck('id')->toArray() ?? []))) checked @endif>
        {{ $genre->name }}
    @endforeach
</div>
```

> **コード解説:**
> - `old('title', $book->title ?? '')`: `old()`ヘルパーは、バリデーションエラーでリダイレクトされた際に、直前の入力値を復元します。第二引数はデフォルト値で、編集画面の場合は既存の書籍データ (`$book->title`) が表示されます。
> - `@if(is_array(...) && in_array(...)) checked @endif`: チェックボックスのチェック状態を復元・表示するためのロジックです。`old('genres')`（バリデーション失敗時の入力値）または `$book->genres->pluck('id')->toArray()`（編集画面の既存データ）の中に現在のジャンルIDが含まれていれば`checked`属性を付与します。模範解答では`is_array`のチェックが追加されています。

### 登録・編集ページの作成

作成した部分テンプレートを`@include`で読み込みます。

**`resources/views/books/create.blade.php`**
```blade
<x-app-layout>
    {{-- ... header ... --}}
    <form action="{{ route('books.store') }}" method="POST">
        @include('books._form')
        <button type="submit">登録</button>
    </form>
</x-app-layout>
```

**`resources/views/books/edit.blade.php`**
```blade
<x-app-layout>
    {{-- ... header ... --}}
    <form action="{{ route('books.update', $book) }}" method="POST">
        @method('PUT')
        @include('books._form')
        <button type="submit">更新</button>
    </form>
</x-app-layout>
```

> **思考プロセス (DRY原則):**
> 登録と編集のフォームはほぼ同じコードの繰り返しになります。このような場合、共通部分を`@include`で読み込める部分テンプレートに切り出すのが定石です。これにより、**DRY (Don't Repeat Yourself)** の原則を守り、修正が必要になった場合も1箇所の変更で済むため、保守性が大幅に向上します。

---

これで、ユーザーがブラウザを通して書籍やレビューを操作するための基本的な画面が整いました。次のChapterでは、ユーザーエンゲージメントを高める「お気に入り」と「いいね」機能を実装します。'''
