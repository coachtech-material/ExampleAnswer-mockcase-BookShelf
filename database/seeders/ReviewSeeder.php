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

        $reviews = [
            // 吾輩は猫である
            ['book_index' => 0, 'user_index' => 0, 'rating' => 5, 'comment' => '日本文学の傑作。猫の視点から人間社会を風刺する手法が秀逸です。'],
            ['book_index' => 0, 'user_index' => 1, 'rating' => 4, 'comment' => '古典的な作品ですが、今読んでも面白い。文体に慣れるまで少し時間がかかりました。'],
            ['book_index' => 0, 'user_index' => 2, 'rating' => 5, 'comment' => '何度読んでも新しい発見がある名作です。'],
            
            // 人を動かす
            ['book_index' => 1, 'user_index' => 0, 'rating' => 5, 'comment' => 'ビジネスパーソン必読の一冊。人間関係の基本が学べます。'],
            ['book_index' => 1, 'user_index' => 2, 'rating' => 4, 'comment' => '具体的な事例が豊富で実践しやすい内容です。'],
            ['book_index' => 1, 'user_index' => 3, 'rating' => 5, 'comment' => '何度も読み返しています。読むたびに新しい気づきがあります。'],
            ['book_index' => 1, 'user_index' => 4, 'rating' => 4, 'comment' => '古い本ですが、内容は今でも十分通用します。'],
            
            // リーダブルコード
            ['book_index' => 2, 'user_index' => 0, 'rating' => 5, 'comment' => 'エンジニア必読！コードの可読性について深く考えさせられました。'],
            ['book_index' => 2, 'user_index' => 1, 'rating' => 5, 'comment' => '新人エンジニアに最初に読ませたい本。'],
            ['book_index' => 2, 'user_index' => 4, 'rating' => 4, 'comment' => '実践的なテクニックが多く、すぐに業務に活かせます。'],
            
            // 7つの習慣
            ['book_index' => 3, 'user_index' => 1, 'rating' => 5, 'comment' => '人生のバイブルです。定期的に読み返しています。'],
            ['book_index' => 3, 'user_index' => 2, 'rating' => 4, 'comment' => '内容は素晴らしいですが、少し長いです。'],
            ['book_index' => 3, 'user_index' => 3, 'rating' => 5, 'comment' => '自己啓発書の中でも最高峰。'],
            
            // 坊っちゃん
            ['book_index' => 4, 'user_index' => 0, 'rating' => 4, 'comment' => '痛快な物語。主人公の真っ直ぐな性格が好きです。'],
            ['book_index' => 4, 'user_index' => 3, 'rating' => 5, 'comment' => '夏目漱石の作品の中で一番好きです。'],
            
            // サピエンス全史
            ['book_index' => 5, 'user_index' => 0, 'rating' => 5, 'comment' => '人類の歴史を俯瞰できる素晴らしい本。視野が広がりました。'],
            ['book_index' => 5, 'user_index' => 1, 'rating' => 5, 'comment' => '知的好奇心を刺激される一冊。'],
            ['book_index' => 5, 'user_index' => 2, 'rating' => 4, 'comment' => '内容は濃いですが、読み応えがあります。'],
            ['book_index' => 5, 'user_index' => 4, 'rating' => 5, 'comment' => '世界の見方が変わりました。'],
            
            // Clean Code
            ['book_index' => 6, 'user_index' => 0, 'rating' => 4, 'comment' => 'プロのプログラマーを目指すなら必読。'],
            ['book_index' => 6, 'user_index' => 4, 'rating' => 5, 'comment' => 'コードの品質について深く考えさせられます。'],
            
            // 嫌われる勇気
            ['book_index' => 7, 'user_index' => 1, 'rating' => 5, 'comment' => 'アドラー心理学を分かりやすく学べます。人生観が変わりました。'],
            ['book_index' => 7, 'user_index' => 2, 'rating' => 4, 'comment' => '対話形式で読みやすい。'],
            ['book_index' => 7, 'user_index' => 3, 'rating' => 5, 'comment' => '何度も読み返したい本です。'],
            ['book_index' => 7, 'user_index' => 4, 'rating' => 4, 'comment' => '考え方の転換に役立ちました。'],
            
            // 火花
            ['book_index' => 8, 'user_index' => 0, 'rating' => 4, 'comment' => '芸人の世界をリアルに描いた作品。'],
            ['book_index' => 8, 'user_index' => 2, 'rating' => 3, 'comment' => '文学作品として評価は分かれるかも。'],
            
            // FACTFULNESS
            ['book_index' => 9, 'user_index' => 0, 'rating' => 5, 'comment' => 'データに基づいた世界の見方を学べます。目から鱗でした。'],
            ['book_index' => 9, 'user_index' => 1, 'rating' => 5, 'comment' => '思い込みを打ち破る良書。'],
            ['book_index' => 9, 'user_index' => 3, 'rating' => 4, 'comment' => '統計データの見方が変わりました。'],
            
            // コンテナ物語
            ['book_index' => 10, 'user_index' => 1, 'rating' => 4, 'comment' => 'コンテナが世界を変えた歴史を学べます。'],
            ['book_index' => 10, 'user_index' => 4, 'rating' => 5, 'comment' => '物流の歴史に興味がある人におすすめ。'],
        ];

        foreach ($reviews as $reviewData) {
            $book = $books[$reviewData['book_index']];
            $user = $users[$reviewData['user_index']];
            
            Review::firstOrCreate(
                [
                    'book_id' => $book->id,
                    'user_id' => $user->id,
                ],
                [
                    'rating' => $reviewData['rating'],
                    'comment' => $reviewData['comment'],
                ]
            );
        }
    }
}
