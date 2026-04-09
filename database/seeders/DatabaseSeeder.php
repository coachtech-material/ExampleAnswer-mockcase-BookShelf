<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 依存関係を考慮して実行順序を変更
        $this->call([
            UserSeeder::class,       // 先にユーザーを作成
            GenreSeeder::class,      // ジャンルも先に作成
            BookSeeder::class,       // ユーザーとジャンルを使って書籍を作成
            ReviewSeeder::class,     // ユーザーと書籍を使ってレビューを作成
            FavoriteSeeder::class,   // ユーザーと書籍を使ってお気に入りを作成
            ReviewLikeSeeder::class, // ユーザーとレビューを使っていいねを作成
        ]);
    }
}
