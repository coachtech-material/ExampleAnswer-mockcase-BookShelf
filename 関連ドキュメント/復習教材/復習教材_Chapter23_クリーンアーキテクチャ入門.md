# Chapter 23: 「クリーンアーキテクチャ入門」 - 模擬案件2（Certify LMS）への予習

> 📌 **このChapterは「予習」です。** 模擬案件1（BookShelf）お疲れさまでした。ここからは次に取り組む **模擬案件2（Certify LMS）** に向けた予習に入ります。模擬案件1の実装手順（Chapter 01〜22）とは独立して読めます。

## 🎯 このChapterの目標

模擬案件2は、模擬案件1や確認テストと違い、**すでに動いている実務PJ（提供プロジェクト、以下「提供PJ」）を渡され、その上にチケットを実装していく**形式です。そして提供PJのバックエンドは、模擬案件1では出てこなかった **「Action / Service 層（クリーンアーキテクチャ）」** で書かれています。

このChapterの目標は、提供PJを開いたときに **「どこに何が書いてあるか」を読み解けるようになる**ことです。自分でゼロから書けるようになる必要はありません。**「読めれば十分」** です。

| このChapterで学ぶこと | 解説 |
|:---|:---|
| 提供PJ形式の進め方 | ゼロから作る案件との違い。フロントは完成済み、あなたはバックエンドを読み解いて実装する |
| なぜ Controller を薄くするのか | 模擬案件1で書いた「全部入りController」が抱える課題 |
| Action（ユースケース）クラス | 「1つの操作 = 1つのクラス」に切り出す考え方と読み方 |
| Service クラス | 複数の場所から使い回す業務ロジック・集計の置き場所 |
| ドメイン例外 | `abort()` ではなく「業務的な例外クラス」を投げて分岐させる設計 |
| 提供PJの歩き方 | `Controller → Action → Service` をたどってコードを読む手順 |

---

## 📖 背景知識：模擬案件2は「提供PJ」形式

### 模擬案件1との違い

| | 模擬案件1（BookShelf） | 模擬案件2（Certify LMS） |
|:---|:---|:---|
| スタート地点 | ほぼ空のLaravel | **すでに動いている実務PJ（提供PJ）** |
| あなたの仕事 | 設計から実装まで全部 | **提供PJを読んで、チケット（機能追加・バグ修正・改善）を実装** |
| フロント | 自分で作る | **完成済みで提供される**（Blade / CSS / JS は触らないのが基本） |
| バックエンドの作り | Controller 中心のMVC | **Action / Service 層を使ったクリーンアーキテクチャ** |

実務では「新規にゼロから作る」よりも「**既存のコードベースに参加して、その流儀に合わせて手を入れる**」ことのほうが圧倒的に多いです。模擬案件2はその練習でもあります。

### なぜ「予習」が必要なのか

模擬案件の **基礎機能（Basic）** は「これまでの教材・確認テスト・模擬案件1で扱った技術の範囲内」というルールで作られています。ところが提供PJは、基礎機能のコードも含めて **最初から Action / Service 層で書かれています**。この層は模擬案件1では一度も登場していません。

そこで、提供PJを開いて戸惑わないように、**このChapterで「Action / Service とは何か」を先に知っておく** わけです。

> ### ⭐ 最初に伝えたい、いちばん大事なこと
>
> 1. **あなたが自分で新しく書くコードは、これまで通り「Controller の中に書く」やり方でも構いません。** Action / Service に必ず分けなければいけない、というルールではありません。
> 2. **提供PJの Action / Service は「読めれば十分」です。** 特にバグ修正・改善系のチケットは、**既存のコードを読んで、該当箇所を直す**のが仕事です。アーキテクチャをゼロから設計する力は求められていません。
>
> つまりこのChapterは「**書けるようになる**」ためではなく「**読めるようになる**」ための予習です。

---

## 📋 ステップ：模擬案件1のコードを Action / Service に分解してみる

いちばん分かりやすいのは、**模擬案件1（BookShelf）で自分が書いたコード**を題材に、「もしこれを提供PJ流に書き直すとどうなるか」を見てみることです。新しいドメインではなく、見慣れた書籍・ジャンルの例で考えます。

### Step 1: 模擬案件1の「全部入りController」を思い出す

書籍登録（`BookController@store`）を、模擬案件1ではこんな風に書いたはずです。

```php
// app/Http/Controllers/BookController.php （模擬案件1でよくある書き方）
public function store(StoreBookRequest $request)
{
    // ① 書籍を作成
    $book = Book::create([
        'user_id'        => auth()->id(),
        'title'          => $request->title,
        'author'         => $request->author,
        'isbn'           => $request->isbn,
        'published_date' => $request->published_date,
        'description'    => $request->description,
        'image_url'      => $request->image_url,
    ]);

    // ② ジャンル（多対多）を紐付け
    $book->genres()->sync($request->input('genres', []));

    // ③ 画面へ
    return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
}
```

今はまだスッキリしています。でも実務では、ここに「在庫チェック」「登録通知の送信」「操作ログの記録」「複数テーブルの同時更新」……と**業務ロジックがどんどん積み重なって**いきます。すると Controller のメソッドが 50行・100行と膨らみ、次の問題が起きます。

- **長くて読みづらい**（1メソッドに何種類もの仕事が混在する）
- **使い回せない**（同じ「書籍登録」処理をバッチや別画面から呼びたくても、Controller の中にあると呼べない）
- **テストしづらい**（HTTPリクエストを通さないとロジックを試せない）

### Step 2: 業務ロジックを「Action」に追い出す

そこで、**「書籍を登録する」という1つの操作（ユースケース）を、1つのクラスに切り出します**。これが **Action（ユースケース）クラス** です。

```php
// app/UseCases/Book/StoreBookAction.php （提供PJ流の書き方）
final class StoreBookAction
{
    public function __invoke(User $user, array $validated): Book
    {
        return DB::transaction(function () use ($user, $validated) {
            $book = Book::create([
                'user_id'        => $user->id,
                'title'          => $validated['title'],
                'author'         => $validated['author'],
                'isbn'           => $validated['isbn'],
                'published_date' => $validated['published_date'],
                'description'    => $validated['description'] ?? null,
                'image_url'      => $validated['image_url'] ?? null,
            ]);

            $book->genres()->sync($validated['genres'] ?? []);

            return $book;
        });
    }
}
```

すると Controller は **「受け取って、Action に渡して、画面を返すだけ」** の薄い存在になります。

```php
// app/Http/Controllers/BookController.php （Action を呼ぶだけの薄いController）
public function store(StoreBookRequest $request, StoreBookAction $storeBook)
{
    $book = $storeBook(auth()->user(), $request->validated());

    return redirect()->route('books.show', $book)->with('success', '書籍を登録しました。');
}
```

#### ここで覚えておきたい「Action の読み方」3点

| 着目点 | 意味 |
|:---|:---|
| **`__invoke()` メソッドが1つだけ** | このクラスは「1つの操作」専用。`$storeBook(...)` のように、**クラスを関数のように呼べる**（PHPの「invokableクラス」） |
| **Controller の引数に Action が書いてある** | Laravel が自動で Action を用意して渡してくれる（**依存性注入＝DI**）。`new StoreBookAction()` と書く必要はない |
| **`DB::transaction()` で囲む** | 複数テーブルの更新（books と book_genre）を「全部成功 or 全部取り消し」にまとめる。`DB::transaction` 自体は模擬案件1の Chapter 20 で学んだものと同じ |

> 💡 提供PJでは、ファイルの先頭に `declare(strict_types=1);` や、引数・戻り値の **型宣言**（`User $user`, `: Book`）、メソッドの上に **PHPDoc コメント** が付いています。これは提供PJが「応用（Advance）の流儀」で書かれているからで、**読むときの手がかり**になります。あなたが自分で基礎機能を書くときは、ここまで厳密に書かなくても構いません。

### Step 3: 使い回す業務ロジックは「Service」に

Action が「1つの操作」だとすると、**Service** は「**いろいろな場所から呼ばれる、まとまった業務ロジックや集計**」の置き場所です。

たとえば模擬案件1の「ランキング機能」で書いた *「レビュー平均点で書籍を並べる集計ロジック」* を考えます。これがトップページ・ランキング画面・APIなど **複数の場所** から必要になったとします。同じクエリをあちこちにコピペすると、修正のたびに全部直す羽目になります。

そこで、集計ロジックを1つの Service にまとめます。

```php
// app/Services/BookRankingService.php
class BookRankingService
{
    /** レビュー平均点が高い順に書籍を取得する（複数画面から再利用） */
    public function topRatedBooks(int $limit = 10)
    {
        return Book::query()
            ->withAvg('reviews', 'rating')   // N+1 を避けてまとめて集計
            ->orderByDesc('reviews_avg_rating')
            ->limit($limit)
            ->get();
    }
}
```

この Service は、Controller からも Action からも呼べます。**呼ぶ側はコンストラクタで受け取ります**（これも DI）。

```php
final class ShowRankingAction
{
    // コンストラクタで Service を受け取る（Laravel が自動で渡す）
    public function __construct(private BookRankingService $ranking) {}

    public function __invoke()
    {
        return $this->ranking->topRatedBooks(10);
    }
}
```

#### Action と Service の使い分け（ざっくり）

| | Action（ユースケース） | Service |
|:---|:---|:---|
| 役割 | 「**この1つの操作**を実行する」（書籍を登録する、面談を予約する…） | 「**何度も使う業務ロジック・集計**」（未読件数を数える、合否を判定する…） |
| 数 | 操作ごとに1つ | ドメインの関心ごとに1つ |
| 呼ばれ方 | 主に Controller から | Action や Controller、別の Service から |

> 提供PJでも、`MeetingAvailabilityService`（面談の空き枠を計算）や `ChatUnreadCountService`（チャット未読件数を集計）のように、**「複数箇所で使う・少し複雑な集計」が Service にまとまっています**。「画面に出ているこの数字、どこで計算してるんだろう？」と思ったら Service を探す、と覚えておくと早いです。

### Step 4: エラーは「ドメイン例外」で投げる

模擬案件1では、認可エラーを `abort(403)` で返したり、Controller の中で `if` を書いてエラーメッセージを出したりしました。提供PJでは、**業務ルール違反を「専用の例外クラス」で投げる**スタイルがよく使われます。

模擬案件1の Chapter 00 で出てきた **「紐付く書籍があるジャンルは削除できない」** という制約を例にします。

```php
// app/Exceptions/Genre/GenreInUseException.php
class GenreInUseException extends \Exception {}
```

```php
// app/UseCases/Genre/DeleteGenreAction.php
final class DeleteGenreAction
{
    public function __invoke(Genre $genre): void
    {
        // 業務ルール: 使用中のジャンルは消せない
        if ($genre->books()->exists()) {
            throw new GenreInUseException('紐付く書籍があるため削除できません。');
        }

        $genre->delete();
    }
}
```

こうしておくと、**「業務ルール（消せない条件）」が Action 側に、「画面の出し方（どんなメッセージを出すか）」が Controller 側に**きれいに分かれます。

#### ドメイン例外の読み方

- `app/Exceptions/` 配下に、機能ごと（`Auth/` `Enrollment/` …）の例外クラスが並んでいます。
- 名前そのものが仕様書になっています。たとえば提供PJの `InvalidInvitationTokenException`（招待トークンが無効）や `EmailAlreadyRegisteredException`（メール重複）は、**名前を見れば「どんな業務ルールを守っているか」が分かる**ようになっています。
- バグ修正チケットでは、「**この例外が投げられるべきなのに投げられていない／逆に投げられすぎている**」が原因になっていることがよくあります。例外名を手がかりに該当の Action / Service を探しましょう。

---

## 💭 なぜこう作るのか？：3つのメリット

模擬案件1の「全部入りController」を、Action / Service / 例外に分けると、現場では次の3つが効いてきます。

### 1. 責務の分離（誰が何の担当か、がはっきりする）

- **Controller** … HTTPの入口（リクエストを受けて、結果を画面に返すだけ）
- **Action** … 1つの操作の手順
- **Service** … 使い回す業務ロジック・集計
- **例外クラス** … 業務ルール違反の表明

「面談予約の不具合」を調べるとき、**まず `Meeting` 関連の Action / Service を見ればよい**と分かるので、原因にたどり着くのが速くなります。

### 2. 再利用できる

同じ「書籍登録」処理を、画面・バッチ（定期実行）・APIのどこからでも `StoreBookAction` を呼ぶだけで使えます。Controller の中に閉じ込めると、これができません。

### 3. テストしやすい

Action / Service は「ただのPHPクラス」なので、HTTPを通さずに直接呼んでテストできます。提供PJに大量のテストが付いているのはこのためです。

> 💡 **「薄いController」の合言葉**：提供PJの設計ルールは **「1つのControllerメソッド = 1つのAction呼び出し」「Controllerメソッドの中に業務ロジックは原則0行」** です。Controller を読んで業務ロジックが見当たらなくても慌てないでください。**ロジックは Action / Service に移動しているだけ**です。

---

## 🔍 コードリーディング：Certify LMS のディレクトリを歩く

提供PJ（Certify LMS）を開いたら、`app/` の下に模擬案件1には無かったフォルダがあります。模擬案件1で書いた要素との対応はこうです。

| 模擬案件1（BookShelf）での置き場所 | 模擬案件2（Certify LMS）での置き場所 | 中身 |
|:---|:---|:---|
| Controller の中に直接書いた業務ロジック | **`app/UseCases/{機能}/XxxAction.php`** | 1操作 = 1クラス（例: `Auth\OnboardAction` = 招待を受けて受講開始） |
| Controller やModelに散らばった集計・共通処理 | **`app/Services/XxxService.php`** | 使い回す業務ロジック・集計（例: `ChatUnreadCountService`） |
| `abort()` や Controller内の `if` | **`app/Exceptions/{機能}/XxxException.php`** | 業務ルール違反の例外（例: `Auth\InvalidInvitationTokenException`） |
| `app/Http/Controllers/` | `app/Http/Controllers/`（**薄い**） | 入口だけ。中身は Action を呼ぶ |
| Model / Migration / FormRequest / Policy | 同じ（`app/Models/` 等） | **ここは模擬案件1と同じ**。安心してOK |

### 提供PJを読むときの手順

機能の挙動を追いたいとき、次の順でたどると迷いません。

1. **ルート（`routes/web.php`）** … そのURLがどの Controller のどのメソッドに行くか
2. **Controller のメソッド** … どの Action / Service を呼んでいるか（だいたい1〜数行）
3. **Action（`app/UseCases/`）** … 操作の本体。`__invoke()` を読む
4. **Service（`app/Services/`）** … Action が呼んでいる集計・共通ロジック
5. **例外（`app/Exceptions/`）** … どんな業務ルールで弾いているか

> 🧭 **フロントは完成済み**。「この画面はどんなデータを必要としているか」は、提供済みの Blade（`resources/views/`）を読めば分かります。提供PJの `ONBOARDING.md` に「どこに何があるか」の地図があるので、最初に目を通しておきましょう。

### バグ修正・改善チケットでの実践

模擬案件2の基礎機能のチケットには、Action / Service の中に原因があるものがあります。でも、やることはシンプルです。

- **やること**：上の手順でたどって原因の1行を見つけ、**その場で直す**
- **やらないこと**：アーキテクチャを設計し直す／全部を Controller に書き戻す

**「既存の構造はそのまま使い、該当箇所だけ修正する」** ——これが提供PJでのバグ修正の基本姿勢です。

---

## 🧐 調べ方のヒント

分からないことがあったら、AIにこう聞いてみましょう。

| 疑問 | プロンプト例 |
|:---|:---|
| Action（invokableクラス）の仕組み | 「PHPの `__invoke()` マジックメソッドとは何ですか？ Laravelで `$action($arg)` のようにクラスを関数として呼べる理由を、簡単な例で教えてください。」 |
| 依存性注入（DI）が分からない | 「Laravelのコントローラーのメソッド引数に書いたクラスが、自動でインスタンス化されて渡されるのはなぜですか？ サービスコンテナの役割を初心者向けに教えてください。」 |
| Action と Service の違い | 「Laravelで『ユースケース(Action)クラス』と『Serviceクラス』を分ける場合、それぞれどんな処理を置くべきですか？ 具体例で違いを教えてください。」 |
| なぜControllerを薄くするのか | 「『Fat Controller（太ったコントローラー）』の問題点と、ビジネスロジックをActionやServiceに分離するメリットを教えてください。」 |
| ドメイン例外の使いどころ | 「Laravelで業務ルール違反のときに、独自の例外クラスを作って投げる設計のメリットを、`abort(403)` と比較して教えてください。」 |

---

## ✨ このChapterのまとめ

模擬案件2の提供PJで使われている「クリーンアーキテクチャ」を、模擬案件1のコードと対応づけて学びました。

| 要素 | ひとことで言うと | 提供PJでの場所 |
|:---|:---|:---|
| 薄い Controller | HTTPの入口。中身は Action を呼ぶだけ | `app/Http/Controllers/` |
| Action（ユースケース） | 「1つの操作」を1クラスに（`__invoke()`） | `app/UseCases/{機能}/` |
| Service | 使い回す業務ロジック・集計 | `app/Services/` |
| ドメイン例外 | 業務ルール違反を表す例外クラス | `app/Exceptions/{機能}/` |
| DI（依存性注入） | 必要なクラスは引数で受け取る（`new` しない） | Controller / Action の引数・コンストラクタ |

**いちばん大事な2点（再掲）:**

1. **提供PJの Action / Service は「読めれば十分」。** `Controller → Action → Service` の順にたどれば挙動を追えます。
2. **自分で基礎機能を書くときは Controller 内に書いてもOK。** バグ修正・改善は「既存構造のまま該当箇所を直す」。

これで提供PJを開いても、「どこに何が書いてあるか」が見える状態になりました。次は実際に模擬案件2の提供PJを開き、`ONBOARDING.md` と `routes/web.php` から、気になる機能を1つ `Controller → Action → Service` とたどってみましょう。
