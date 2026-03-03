# 評価シート - BookShelf（書籍レビューアプリ 模擬案件）

## 総合評価

| 総合 | 配点 | 正答数 | 正答率 | 評価 | コメント |
|------|------|--------|--------|------|----------|
| 総合 | 207 | 0 | 0.0% | D | |

| パーツ別 | 配点 | 正答数 | 正答率 | 評価 | コメント |
|----------|------|--------|--------|------|----------|
| Laravel単体 | 74 | 0 | 0.0% | D | |
| マイグレーション | 31 | 0 | 0.0% | D | |
| API | 8 | 0 | 0.0% | D | |
| バリデーション | 10 | 0 | 0.0% | D | |
| シーディング | 14 | 0 | 0.0% | D | |
| テスト | 44 | 0 | 0.0% | D | |
| コード品質 | 22 | 0 | 0.0% | D | |
| ドキュメント | 4 | 0 | 0.0% | D | |

---

## 詳細項目

| 大項目 | 評価基準 | 評価点 | 可否 | 点数 |
|--------|----------|--------|------|------|
| Laravel単体 | ユーザー登録（Fortify）機能において、/register にアクセスした際に登録画面が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ユーザー登録（Fortify）機能において、有効な情報で登録送信した際にユーザーが作成されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ユーザー登録（Fortify）機能において、登録成功後に / へ遷移するか。 | 1 | FALSE | 0 |
| Laravel単体 | ユーザー登録（Fortify）機能において、登録送信後にDB（users）へレコードが追加されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ユーザー登録（Fortify）機能において、必須入力漏れで登録送信した際にエラーメッセージが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ログイン機能において、正しい資格情報でログインした際に / へ遷移するか。 | 1 | FALSE | 0 |
| Laravel単体 | ログイン機能において、誤った資格情報でログインした際にエラーメッセージが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ログアウト機能において、POST /logout でセッションが破棄されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ログアウト機能において、ログアウト後に保護されたページへアクセスできないか。 | 1 | FALSE | 0 |
| Laravel単体 | 認証（Fortify）機能において、FortifyServiceProviderが登録され認証機能が正しく動作するか。 | 1 | FALSE | 0 |
| Laravel単体 | ログイン必須制御機能において、未ログインで /books/create にアクセスした際に /login へリダイレクトされるか。 | 1 | FALSE | 0 |
| Laravel単体 | ログイン必須制御機能において、未ログインでお気に入り・ジャンル管理ページにアクセスした際にリダイレクトされるか。 | 1 | FALSE | 0 |
| Laravel単体 | ログイン必須制御機能において、ログイン済みユーザーが /login や /register にアクセスした際に書籍一覧へリダイレクトされるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍一覧表示機能において、/books にアクセスした際に書籍一覧が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍一覧表示機能において、各書籍にジャンル情報がEager Loadingで表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍一覧表示機能において、ページネーション（10件/ページ）で表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍詳細表示機能において、/books/{book} にアクセスした際に書籍詳細が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍詳細表示機能において、書籍に紐づくレビュー一覧が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍詳細表示機能において、書籍に紐づくジャンル情報が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍登録機能において、/books/create にアクセスした際に登録フォームが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍登録機能において、ジャンル選択（チェックボックス複数選択）がフォームに存在するか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍登録機能において、正しい入力で送信した際に書籍がDBに登録されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍登録機能において、登録時にジャンルがattach()でbook_genreテーブルに紐付けされるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍編集機能において、/books/{book}/edit にアクセスした際に編集フォームが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍編集機能において、既存データが編集フォームの初期値として表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍編集機能において、正しい入力で送信した際に書籍情報が更新されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍編集機能において、ジャンルがsync()で同期されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍編集機能において、Policyによる認可が実装され作成者のみ編集できるか。 | 2 | FALSE | 0 |
| Laravel単体 | 書籍削除機能において、DELETE /books/{book} で書籍が削除されるか。 | 1 | FALSE | 0 |
| Laravel単体 | 書籍削除機能において、Policyによる認可が実装され作成者のみ削除できるか。 | 2 | FALSE | 0 |
| Laravel単体 | 書籍削除機能において、削除時に関連データ（レビュー・お気に入り等）がカスケード削除されるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー投稿機能において、POST /books/{book}/reviews で認証ユーザーがレビューを投稿できるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー投稿機能において、rating（1〜5）とcommentがDBに保存されるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー編集機能において、/reviews/{review}/edit にアクセスした際に編集フォームが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー編集機能において、正しい入力で送信した際にレビューが更新されるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー編集機能において、Policyによる認可が実装され投稿者のみ編集できるか。 | 2 | FALSE | 0 |
| Laravel単体 | レビュー削除機能において、DELETE /reviews/{review} でレビューが削除されるか。 | 1 | FALSE | 0 |
| Laravel単体 | レビュー削除機能において、Policyによる認可が実装され投稿者のみ削除できるか。 | 2 | FALSE | 0 |
| Laravel単体 | レビュー削除機能において、削除時に関連いいねがカスケード削除されるか。 | 1 | FALSE | 0 |
| Laravel単体 | お気に入り登録/解除機能において、POST /books/{book}/favorites で追加/解除（トグル）が正常に動作するか。 | 1 | FALSE | 0 |
| Laravel単体 | お気に入り登録/解除機能において、ゲストが操作した際にログイン画面にリダイレクトされるか。 | 1 | FALSE | 0 |
| Laravel単体 | お気に入り一覧表示機能において、/favorites にアクセスした際にお気に入り書籍一覧が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | お気に入り一覧表示機能において、ページネーション（10件/ページ）で表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | いいね機能において、POST /reviews/{review}/like で追加/解除（トグル）が正常に動作するか。 | 1 | FALSE | 0 |
| Laravel単体 | いいね機能において、いいね数が書籍詳細ページに表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル一覧表示機能において、/genres にアクセスした際にジャンル一覧が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル一覧表示機能において、各ジャンルに紐づく書籍数がwithCount()で表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル登録機能において、/genres/create にアクセスした際に登録フォームが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル登録機能において、正しい入力で送信した際にジャンルがDBに登録されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル編集機能において、/genres/{genre}/edit にアクセスした際に編集フォームが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル編集機能において、正しい入力で送信した際にジャンル名が更新されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル削除機能において、書籍未紐付けのジャンルが正常に削除されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル削除機能において、書籍紐付きのジャンル削除時にエラーメッセージが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル詳細表示機能において、/genres/{genre} にアクセスした際にジャンル内書籍一覧が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ジャンル詳細表示機能において、ページネーション（10件/ページ）で表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ランキング表示機能において、/ranking にアクセスした際にランキングページが表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | ランキング表示機能において、レビュー平均評価の高い順にTOP10書籍が表示されるか。 | 1 | FALSE | 0 |
| Laravel単体 | キーワード検索機能において、タイトル・著者の部分一致検索が正常に動作するか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | ジャンルフィルタ機能において、ジャンルを指定して絞り込み検索ができるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | ソート機能において、並び順（登録日新しい順/古い順/タイトル順/評価順）を変更できるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | 検索条件維持機能において、検索条件を維持したままページネーションが機能するか（withQueryString）。(応用) | 1 | FALSE | 0 |
| Laravel単体 | CSVエクスポート機能において、GET /books/csv で書籍一覧がCSVファイルでダウンロードされるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | CSVエクスポート機能において、ダウンロードされたCSVのヘッダーと内容が仕様通り（BOM付UTF-8、7列）に出力されるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | CSVエクスポート機能において、StreamedResponseでストリーミング出力されているか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | ISBN検索機能において、書籍登録・編集画面にISBN入力欄と検索ボタンが設置されているか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | ISBN検索機能において、GET /books/isbn/{isbn} でGoogle Books APIから書籍情報を取得できるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | ISBN検索機能において、エラーハンドリング（バリデーション400・書籍なし404・通信エラー500）が実装されているか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | マイ読書レポート機能において、/reports にアクセスした際に基本サマリー（総レビュー数、読了冊数、平均評価）が正しく表示されるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | マイ読書レポート機能において、評価分布と高評価書籍TOP5が正しく表示されるか。(応用) | 1 | FALSE | 0 |
| Laravel単体 | マイ読書レポート機能において、ジャンル別評価傾向TOP5が正しく表示されるか。(応用) | 1 | FALSE | 0 |
| マイグレーション | usersテーブルにおいて、id がBIGINT UNSIGNED AUTO_INCREMENT + PRIMARY KEY になっているか。 | 1 | FALSE | 0 |
| マイグレーション | usersテーブルにおいて、name / email / password が VARCHAR(255) で NOT NULL、email に UNIQUE 制約があるか。 | 1 | FALSE | 0 |
| マイグレーション | usersテーブルにおいて、email_verified_at が NULL許容の timestamp、remember_token や timestamps が存在するか。 | 1 | FALSE | 0 |
| マイグレーション | usersテーブルにおいて、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | genresテーブルにおいて、id の定義と PRIMARY KEY が正しいか。 | 1 | FALSE | 0 |
| マイグレーション | genresテーブルにおいて、name が VARCHAR(255) で NOT NULL、UNIQUE 制約があり、timestamps が存在するか。 | 1 | FALSE | 0 |
| マイグレーション | genresテーブルにおいて、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、title / author が VARCHAR(255) で NOT NULL になっているか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、isbn が VARCHAR(13) で UNIQUE、description が text で NULL許容になっているか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、user_id が外部キー（users.id）で ON DELETE CASCADE になっているか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、published_date が date 型、image_url が VARCHAR(255) で NULL許容に定義され、timestamps が存在するか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | reviewsテーブルにおいて、rating が tinyInteger で NOT NULL、comment が text で定義されているか。 | 1 | FALSE | 0 |
| マイグレーション | reviewsテーブルにおいて、user_id / book_id がそれぞれ users / books を参照する外部キーで ON DELETE CASCADE になっているか。 | 1 | FALSE | 0 |
| マイグレーション | reviewsテーブルにおいて、timestamps が存在し、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | book_genreテーブル（中間テーブル）において、book_id と genre_id がそれぞれ books / genres を参照し ON DELETE CASCADE になっているか。 | 1 | FALSE | 0 |
| マイグレーション | book_genreテーブル（中間テーブル）において、(book_id, genre_id) の複合主キーまたは複合UNIQUE制約が設定されているか。 | 1 | FALSE | 0 |
| マイグレーション | book_genreテーブルにおいて、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | favoritesテーブル（中間テーブル）において、user_id と book_id がそれぞれ users / books を参照し ON DELETE CASCADE になっているか。 | 1 | FALSE | 0 |
| マイグレーション | favoritesテーブル（中間テーブル）において、(user_id, book_id) の複合主キーまたは複合UNIQUE制約が設定されているか。 | 1 | FALSE | 0 |
| マイグレーション | favoritesテーブルにおいて、マイグレーションと実DB構造が一致しているか。 | 1 | FALSE | 0 |
| マイグレーション | review_likesテーブル（中間テーブル）において、user_id と review_id がそれぞれ users / reviews を参照し ON DELETE CASCADE になっているか。 | 1 | FALSE | 0 |
| マイグレーション | review_likesテーブル（中間テーブル）において、(user_id, review_id) の複合主キーまたは複合UNIQUE制約が設定されているか。 | 1 | FALSE | 0 |
| マイグレーション | すべての外部キー（books.user_id, reviews.user_id, reviews.book_id 等）が想定どおり設定されているか。 | 1 | FALSE | 0 |
| マイグレーション | テーブル仕様書内の型・制約が実DBと合致しているか。 | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、isbn カラムが NOT NULL から nullable に変更されているか。(応用) | 1 | FALSE | 0 |
| マイグレーション | booksテーブルにおいて、published_date カラムが NOT NULL から nullable に変更されているか。(応用) | 1 | FALSE | 0 |
| マイグレーション | reviewsテーブルにおいて、comment カラムが NOT NULL から nullable に変更されているか。(応用) | 1 | FALSE | 0 |
| マイグレーション | reviewsテーブルにおいて、(user_id, book_id) の複合UNIQUE制約が追加されているか。(応用) | 1 | FALSE | 0 |
| マイグレーション | 中間テーブル（book_genre, favorites, review_likes）に id 主キーと timestamps が追加されているか。(応用) | 1 | FALSE | 0 |
| マイグレーション | 中間テーブルの制約が複合主キーから id + 複合UNIQUE制約に変更されているか。(応用) | 1 | FALSE | 0 |
| API | routes/api.php に API v1 のルートが定義されているか。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/booksにて、正常系でリクエストを送信すると HTTP 200 が返り、JSON の data に書籍一覧が含まれるか確認。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/booksにて、ページネーション（20件/ページ）が機能し、meta に current_page / last_page / total が含まれるか確認。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/booksにて、keyword パラメータでタイトル・著者の部分一致検索が動作するか確認。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/booksにて、genre_id パラメータでジャンル絞り込みが動作するか確認。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/books/{book}にて、存在する ID を指定して 200 が返り、ジャンル・レビュー含む書籍詳細が返されるか確認。(応用) | 1 | FALSE | 0 |
| API | GET /api/v1/books/{book}にて、存在しない ID を指定すると 404 とエラーメッセージが返るか確認。(応用) | 1 | FALSE | 0 |
| API | BookResource / GenreResource / ReviewResource 等の API Resource クラスが使用されているか。(応用) | 1 | FALSE | 0 |
| バリデーション | "書籍登録画面にて、以下のルールでStoreBookRequestを用いたバリデーションができていること: title(required/string/max:255), author(required/string/max:255), isbn(required/string/size:13/unique:books), published_date(required/date), description(nullable/string), image_url(nullable/url), genres(required/array), genres.*(exists:genres,id)" | 1 | FALSE | 0 |
| バリデーション | "書籍編集画面にて、以下のルールでUpdateBookRequestを用いたバリデーションができていること: title(required/string/max:255), author(required/string/max:255), isbn(required/string/size:13/Rule::unique('books')->ignore()), published_date(required/date), description(nullable/string), image_url(nullable/url), genres(required/array), genres.*(exists:genres,id)" | 1 | FALSE | 0 |
| バリデーション | "レビュー投稿画面にて、以下のルールでStoreReviewRequestを用いたバリデーションができていること: rating(required/integer/min:1/max:5), comment(nullable/string/max:1000)" | 1 | FALSE | 0 |
| バリデーション | "レビュー編集画面にて、以下のルールでUpdateReviewRequestを用いたバリデーションができていること: rating(required/integer/min:1/max:5), comment(nullable/string/max:1000)" | 1 | FALSE | 0 |
| バリデーション | "ジャンル登録画面にて、以下のルールでStoreGenreRequestを用いたバリデーションができていること: name(required/string/max:255/unique:genres,name)" | 1 | FALSE | 0 |
| バリデーション | "ジャンル編集画面にて、以下のルールでUpdateGenreRequestを用いたバリデーションができていること: name(required/string/max:255/Rule::unique('genres')->ignore())" | 1 | FALSE | 0 |
| バリデーション | "ユーザー登録画面にて、以下のルールでバリデーションができていること: name(required/string/max:255), email(required/email/max:255/unique:users), password(Fortify標準 8文字以上・確認用一致)" | 1 | FALSE | 0 |
| バリデーション | "ログイン画面にて、以下のルールでバリデーションができていること: email(required/email), password(required)" | 1 | FALSE | 0 |
| バリデーション | 書籍登録・編集画面（StoreBookRequest/UpdateBookRequest）にて、isbn・published_dateがnullableに変更され、genresにmin:1が追加されているか。(応用) | 1 | FALSE | 0 |
| バリデーション | 全FormRequest（StoreBookRequest/UpdateBookRequest/StoreReviewRequest/UpdateReviewRequest/StoreGenreRequest/UpdateGenreRequest）にmessages()メソッドでカスタムエラーメッセージが定義されているか。(応用) | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、usersテーブルのダミーデータとして指定された5件のユーザー（yamada/suzuki/tanaka/sato/takahashi）を作成できているか。 | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、genresテーブルのダミーデータとして指定された10件のジャンルを作成できているか。 | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、booksテーブルのダミーデータとして指定された11件の書籍を作成できているか。 | 1 | FALSE | 0 |
| シーディング | BookSeederにおいて、各書籍にISBN・ジャンル紐付け（sync）が仕様通りに設定されているか。 | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、reviewsテーブルのダミーデータとしてrating 3〜5のレビューを作成できているか。 | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、favoritesテーブルのダミーデータとして各ユーザー3〜5冊のお気に入りを作成できているか。 | 1 | FALSE | 0 |
| シーディング | 要件の指示に従って、review_likesテーブルのダミーデータとしていいねデータを作成できているか。 | 1 | FALSE | 0 |
| シーディング | DatabaseSeederで全Seederが依存関係を考慮した順序（User→Genre→Book→Review→Favorite→ReviewLike）で呼び出されるか。 | 1 | FALSE | 0 |
| シーディング | 各Seederでfirstまたはcreateメソッドが適切に使用され、重複データが防止されているか。 | 1 | FALSE | 0 |
| シーディング | php artisan db:seed を実行し、全テーブルにデータが正常に投入されるか。 | 1 | FALSE | 0 |
| シーディング | GenreSeederにおいて、ジャンル名が応用版の名称に変更され、create()メソッドに変更されているか。(応用) | 1 | FALSE | 0 |
| シーディング | BookSeederにおいて、ランダムユーザー割当・ISBN変更・attach()メソッドに変更されているか。(応用) | 1 | FALSE | 0 |
| シーディング | ReviewSeederにおいて、rating 1〜5の全範囲・コメントテンプレート・create()メソッドに変更されているか。(応用) | 1 | FALSE | 0 |
| シーディング | DatabaseSeederの実行順序がGenre→User→Book→Review→Favorite→ReviewLikeに変更されているか。(応用) | 1 | FALSE | 0 |
| テスト | Userモデルのリレーション（User関連）において、books / reviews / favoriteBooks / likedReviews が正しく定義されていることを検証する Unit Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | Bookモデルのリレーション（Book関連）において、user / reviews / genres / favoritedByUsers が正しく定義されていることを検証する Unit Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | Reviewモデルのリレーション（Review関連）において、user / book / likedByUsers が正しく定義されていることを検証する Unit Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍一覧表示（画面アクセス）において、/books が正常に表示され200レスポンスが返ることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍登録フォーム（アクセス制御）において、認証ユーザーのみが /books/create を表示でき、ゲストはリダイレクトされることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍詳細表示（書籍CRUD）において、/books/{book} が正常に表示され書籍タイトルが含まれることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍登録（書籍CRUD）において、認証ユーザーが書籍を登録でき、ジャンルがbook_genreテーブルに紐付けられることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍編集（書籍CRUD）において、所有者のみが編集でき、他ユーザーは403 Forbiddenとなることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍削除（書籍CRUD）において、所有者のみが削除でき、DBからレコードが削除されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 書籍検索（書籍CRUD）において、検索クエリに一致する書籍が表示されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | レビュー投稿（レビュー）において、認証ユーザーがレビューを投稿でき、ゲストはリダイレクトされることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | レビュー編集（レビュー）において、投稿者のみが編集でき、他ユーザーは403 Forbiddenとなることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | レビュー削除（レビュー）において、投稿者のみが削除でき、他ユーザーは403 Forbiddenとなることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル一覧表示（ジャンル）において、ジャンル一覧ページが正常に表示されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル登録フォーム（ジャンル）において、認証ユーザーが登録フォームを表示できることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル登録（ジャンル）において、認証ユーザーがジャンルを作成でき、genresテーブルにレコードが作成されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル詳細（ジャンル）において、/genres/{genre} でジャンルに紐づく書籍が表示されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル編集（ジャンル）において、認証ユーザーがジャンル名を更新できることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ジャンル削除制約（ジャンル）において、書籍紐付きのジャンルは削除できず、紐付きなしは削除できることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | お気に入り追加・解除（お気に入り）において、認証ユーザーがお気に入りの追加・解除ができfavoritesテーブルが更新されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | お気に入りトグル（お気に入り）において、追加→解除→追加のトグル動作が正しく動作することを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | お気に入り一覧（お気に入り）において、お気に入り一覧ページが正常に表示されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | お気に入りゲスト制限（お気に入り）において、ゲストが操作するとログインにリダイレクトされることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | いいね追加・解除（いいね）において、認証ユーザーがいいねの追加・解除ができreview_likesテーブルが更新されることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | いいねトグル（いいね）において、追加→解除→追加のトグル動作が正しく動作することを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | いいねゲスト制限（いいね）において、ゲストが操作するとログインにリダイレクトされることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ランキング表示（ランキング）において、/ranking が正常に表示されレビューのある書籍タイトルが含まれることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | ランキング順序（ランキング）において、書籍が平均評価の降順で正しく並ぶことを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | 認証済みリダイレクト（認証）において、認証済みユーザーがログインページにアクセスするとホームにリダイレクトされることを検証する Feature Tests が実装されパスしているか。 | 1 | FALSE | 0 |
| テスト | php artisan test で全テストがパスするか。 | 2 | FALSE | 0 |
| テスト | テストカバレッジが60%以上であるか。 | 2 | FALSE | 0 |
| テスト | キーワード検索テスト（検索・フィルタ）において、キーワードで該当書籍のみが表示されることを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | ジャンルフィルタテスト（検索・フィルタ）において、ジャンルフィルタで該当書籍のみが表示されることを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | ソートテスト（ソート）において、新着順・古い順・タイトル順・評価順の各ソートが正しく動作することを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | CSVエクスポートテスト（CSVエクスポート）において、認証ユーザーがCSVをDLでき Content-Type・ファイル名・内容が正しいことを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | CSVソートテスト（CSVエクスポート）において、CSV出力が各ソート条件で正しく並んでいることを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | ISBN検索テスト（ISBN検索）において、Http::fake()で外部APIをモック化し正常・バリデーションエラー・404・500の各ケースを検証する Feature Tests が実装されパスしているか。(応用) | 2 | FALSE | 0 |
| テスト | マイ読書レポートテスト（マイ読書レポート）において、認証ユーザーの統計情報が正しく計算されることを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | 公開APIテスト（公開API）において、GET /api/v1/books および /api/v1/books/{book} が正しいJSONを返すことを検証する Feature Tests が実装されパスしているか。(応用) | 1 | FALSE | 0 |
| テスト | テストカバレッジが80%以上であるか。(応用) | 2 | FALSE | 0 |
| コード品質 | 変数名などに、a, x などの意味のない命名をしていないか。 | 1 | FALSE | 0 |
| コード品質 | 変数名などに、ローマ字など英単語ではないもので命名をしていないか。 | 1 | FALSE | 0 |
| コード品質 | モデル名に「アッパーキャメル（PascalCase）」を使用できているか。 | 1 | FALSE | 0 |
| コード品質 | コントローラ名に「アッパーキャメル（PascalCase）」を使用できているか。 | 1 | FALSE | 0 |
| コード品質 | フォームリクエスト名に「アッパーキャメル（PascalCase）」を使用できているか。 | 1 | FALSE | 0 |
| コード品質 | マイグレーションファイル名に「スネークケース」を使用できているか。 | 1 | FALSE | 0 |
| コード品質 | シーディングファイル名に「アッパーキャメル」を使用できているか。 | 1 | FALSE | 0 |
| コード品質 | テーブル名はスネークケース・複数形、カラム名はスネークケースで命名できているか。 | 1 | FALSE | 0 |
| コード品質 | バリデーションは、全てフォームリクエストを使用して実装できているか（認証を除く）。 | 1 | FALSE | 0 |
| コード品質 | DB操作に関するプログラムは、Eloquent ORMでの記述で統一されているか（生SQLを使用していない）。 | 1 | FALSE | 0 |
| コード品質 | N+1問題への対策（Eager Loading: with / load）が実施されているか。 | 1 | FALSE | 0 |
| コード品質 | 使用していないクラスやファイルをuseで読み込んでいないか。 | 1 | FALSE | 0 |
| コード品質 | 必要のないコメントアウトが残っていないか。 | 1 | FALSE | 0 |
| コード品質 | ER図のカーディナリティの記述に不適切な箇所がないか。 | 1 | FALSE | 0 |
| コード品質 | インデントや改行が整理できているか。 | 1 | FALSE | 0 |
| コード品質 | Laravel Pintを用いた整形がされているか。 | 1 | FALSE | 0 |
| コード品質 | 全メソッドに引数と戻り値の型宣言がされているか。(応用) | 2 | FALSE | 0 |
| コード品質 | 主要なメソッドにPHPDocコメントが記述されているか。(応用) | 1 | FALSE | 0 |
| コード品質 | Collectionメソッド（map, filter, groupBy, flatMap等）が効果的に使用されているか。(応用) | 1 | FALSE | 0 |
| コード品質 | 外部API連携にLaravel HttpクライアントのHttp::get()が使用されているか。(応用) | 1 | FALSE | 0 |
| コード品質 | APIキー等の機密情報が.envファイルで管理されているか（config/services.php経由）。(応用) | 1 | FALSE | 0 |
| ドキュメント | README.mdにプロジェクト概要・ER図・使用技術等の必要な情報が記載されているか。 | 2 | FALSE | 0 |
| ドキュメント | README.mdの手順通りに環境構築が正常に完了するか。 | 2 | FALSE | 0 |
