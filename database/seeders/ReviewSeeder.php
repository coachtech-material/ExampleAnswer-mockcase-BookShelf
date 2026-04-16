<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $books = Book::all();

        $comments = [
            5 => ['素晴らしい本でした！', '人生が変わりました。', '何度も読み返しています。'],
            4 => ['とても参考になりました。', '読みやすくておすすめです。', '期待通りの内容でした。'],
            3 => ['普通でした。', '可もなく不可もなく。', '期待したほどではなかった。'],
            2 => ['少し期待外れでした。', '内容が薄い印象。', 'もう少し深掘りしてほしかった。'],
            1 => ['残念ながら合いませんでした。', '期待と違いました。'],
        ];

        foreach ($books as $book) {
            $reviewCount = rand(2, 4);
            $reviewers = $users->random($reviewCount);

            foreach ($reviewers as $user) {
                $rating = rand(1, 5);
                Review::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'book_id' => $book->id,
                    ],
                    [
                        'rating' => $rating,
                        'comment' => $comments[$rating][array_rand($comments[$rating])],
                    ]
                );
            }
        }
    }
}
