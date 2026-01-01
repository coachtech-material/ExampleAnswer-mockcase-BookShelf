# Chapter 3: 書籍管理機能(CRUD) - アプリケーションの中核を築く

認証機能が完成し、いよいよこのアプリケーションのメイン機能である「書籍の管理機能」を実装します。CRUD（Create, Read, Update, Delete）は、あらゆるWebアプリケーションの基本です。ここでは、現場でよく使われる「M-C-R-V」の順序で、効率的かつ堅牢に実装を進める方法を学びます。

## 3-1. PMへのヒアリングと設計

詳細度50%の要件定義書には「書籍のCRUD機能」としか書かれていません。ここから、PM（コーチ）にヒアリングして仕様を固めます。

**ヒアリングシート（例）**
- **Create（登録）**: 必要な項目は？（タイトル、著者、ISBN...）バリデーションルールは？（必須？文字数制限は？）登録後の遷移先は？
- **Read（表示）**: 一覧画面には何を表示する？ページネーションは必要？詳細画面は？
- **Update（更新）**: 誰が更新できる？（登録者本人のみ？）
- **Delete（削除）**: 誰が削除できる？

これらのヒアリングの結果、詳細度100%の要件定義書にあるような仕様（バリデーションルール、認可ポリシーなど）が固まります。

## 3-2. M-C-R-Vモデルによる実装

CRUDを実装する際、行き当たりばったりで実装すると手戻りが多くなります。ここでは、データ構造から順番に実装していく「M-C-R-V」モデルを採用します。

- **M (Model & Migration)**: データベースの設計と準備
- **C (Controller)**: ビジネスロジックの記述
- **R (Route)**: URLとコントローラのアクションを紐付け
- **V (View)**: ユーザーが見る画面の作成

### Step 1: M (Model & Migration) - データベースの準備

まず、アプリケーションの心臓部であるデータベースを設計します。添付手順書の「Step 2」と「Step 3」に従い、マイグレーションファイルとモデルファイルを作成・編集します。

1.  **マイグレーションファイルの確認**: `database/migrations` にあるファイルを確認します。`create_books_table` には、`title`, `author`, `isbn` などのカラムが定義されています。`user_id` カラムがあることに注目してください。これは書籍がどのユーザーによって登録されたかを記録するためのものです（`users` テーブルとの関連付け）。
2.  **モデルファイルの編集**: `app/Models/Book.php` を編集します。

    ```php
    // app/Models/Book.php
    protected $fillable = [
        // ここに書かれたカラムは、create()メソッドなどで一括代入が許可される
        "user_id", "title", "author", "isbn", ...
    ];

    public function user()
    {
        // Bookは一人のUserに属する (belongsTo)
        return $this->belongsTo(User::class);
    }

    public function genres()
    {
        // Bookは複数のGenreを持つ (belongsToMany)
        return $this->belongsToMany(Genre::class);
    }
    ```

    **思考プロセス**: `$fillable` はなぜ必要？
    これは「マスアサインメント脆弱性」を防ぐための仕組みです。もしこれが無いと、悪意のあるユーザーがフォームに `is_admin=1` のような隠しフィールドを仕込み、意図せず管理者権限を付与してしまう、といった攻撃が可能になる場合があります。`$fillable` で許可された項目だけが `create()` や `update()` で一括更新されるようになります。

### Step 2: C (Controller) & R (Route) - ロジックとURLの設計

次に、リクエストを処理するコントローラと、URLを定義するルートを作成します。添付手順書の「Step 5」に従います。

1.  **各種ファイルの生成**: `--resource` オプション付きでコントローラを作成すると、CRUDの基本的なメソッド（`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`）が自動で生成されます。同時に、バリデーションを担当する `StoreBookRequest`, `UpdateBookRequest` と、認可（権限管理）を担当する `BookPolicy` も作成します。

    ```bash
    sail artisan make:controller BookController --resource
    sail artisan make:request StoreBookRequest
    sail artisan make:request UpdateBookRequest
    sail artisan make:policy BookPolicy --model=Book
    ```

2.  **ルートの定義 (`routes/web.php`)**: `Route::resource("books", BookController::class)` のように書くと、7つのCRUDアクションに対応するルートが自動で定義されます。今回はより細かく制御するため、一つずつ定義しています。

3.  **コントローラの実装 (`BookController.php`)**: ここがビジネスロジックの中心です。

    ```php
    // app/Http/Controllers/BookController.php

    // 書籍登録処理
    public function store(StoreBookRequest $request): RedirectResponse
    {
        // バリデーション済みのデータを取得
        $validated = $request->validated();
        // ジャンル情報を除いた書籍データを準備
        $bookData = collect($validated)->except("genres")->toArray();
        // ログイン中のユーザーの書籍として登録
        $book = $request->user()->books()->create($bookData);
        // 中間テーブルにジャンル情報を登録
        $book->genres()->attach($validated["genres"]);

        return redirect()->route("books.show", $book)->with("success", "書籍を登録しました。");
    }

    // 書籍編集処理
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        // ポリシーを使って、このユーザーがこの書籍を更新できるかチェック
        $this->authorize("update", $book);
        // ... 更新処理 ...
    }
    ```

    **思考プロセス**: なぜ `StoreBookRequest` を使うのか？
    コントローラメソッドの引数で `StoreBookRequest` を型指定するだけで、Laravelは自動的にこのリクエストクラスに定義されたバリデーションルールを実行してくれます。ルールに違反すれば、コントローラの処理が実行される前に自動でエラーメッセージ付きで前のページにリダイレクトされます。これにより、コントローラはバリデーションのことを気にせず、本来のビジネスロジックに集中できます。

### Step 3: V (View) - 画面の作成

最後に、ユーザーが見る画面を作成します。PMから提供されたBladeテンプレートを元に、コントローラから渡されたデータを表示します。

**Bladeの読み解き方**: `books/_form.blade.php` を見てみましょう。
- `value="{{ old("title", $book->title ?? "") }}"`: この書き方は非常に重要です。
    - `old("title", ...)`: バリデーションエラーで戻ってきた場合に、入力していた値を復元します。
    - `$book->title ?? ""`: 新規登録（`$book` が存在しない）の場合は空文字、編集（`$book` が存在する）の場合はその書籍のタイトルを表示します。
    - この一行で、新規登録フォームと編集フォームの両方でこの部品を使えるようにしつつ、バリデーションエラー時の入力保持も実現しています。
- `@error("title")<p>{{ $message }}</p>@enderror`: `title` フィールドにバリデーションエラーがあれば、エラーメッセージを表示します。

添付手順書の「Step 5.6」に従い、`_form.blade.php`, `create.blade.php`, `edit.blade.php` などを実装してください。

これで書籍管理機能のCRUDが完成しました。次のChapterでは、この書籍に紐づくレビュー機能を実装していきます。
