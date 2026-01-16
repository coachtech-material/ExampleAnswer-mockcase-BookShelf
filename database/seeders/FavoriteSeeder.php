<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        // 各ユーザーにお気に入りを設定
        $favorites = [
            // 山田太郎のお気に入り
            ['user_index' => 0, 'book_indices' => [0, 2, 5, 9]],
            // 鈴木花子のお気に入り
            ['user_index' => 1, 'book_indices' => [1, 3, 5, 7]],
            // 田中一郎のお気に入り
            ['user_index' => 2, 'book_indices' => [0, 1, 7]],
            // 佐藤美咲のお気に入り
            ['user_index' => 3, 'book_indices' => [3, 4, 7, 8]],
            // 高橋健太のお気に入り
            ['user_index' => 4, 'book_indices' => [2, 5, 6, 9, 10]],
        ];

        foreach ($favorites as $favoriteData) {
            $user = $users[$favoriteData['user_index']];
            $bookIds = collect($favoriteData['book_indices'])->map(fn($i) => $books[$i]->id)->toArray();
            $user->favoriteBooks()->syncWithoutDetaching($bookIds);
        }
    }
}
