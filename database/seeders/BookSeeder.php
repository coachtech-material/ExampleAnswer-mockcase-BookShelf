<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Database\Seeder;

class BookSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();
        $genres = Genre::all();

        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥先生の家に飼われている猫の視点から、人間社会を風刺的に描いた作品。',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の古典として、あらゆる自己啓発本の原点となったベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784422100524.jpg',
                'genres' => ['ビジネス・経済', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニックを紹介。',
                'image_url' => 'https://cover.openbd.jp/9784873115658.jpg',
                'genres' => ['コンピュータ・IT'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '人格主義の回復を訴え、真の成功を得るための7つの習慣を説く。',
                'image_url' => 'https://cover.openbd.jp/9784863940246.jpg',
                'genres' => ['ビジネス・経済', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '四国の中学校に赴任した江戸っ子の数学教師「坊っちゃん」の物語。',
                'image_url' => 'https://cover.openbd.jp/9784101010021.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => 'なぜ人類だけが文明を築けたのか？その謎を解き明かす世界的ベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784309226712.jpg',
                'genres' => ['歴史・地理', '科学・テクノロジー'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => 'アジャイルソフトウェア達人の技。クリーンなコードを書くための実践的ガイド。',
                'image_url' => 'https://cover.openbd.jp/9784048930598.jpg',
                'genres' => ['コンピュータ・IT'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりやすく解説した自己啓発書。',
                'image_url' => 'https://cover.openbd.jp/9784478025819.jpg',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '芥川賞受賞作。売れない芸人の青春と友情を描いた純文学。',
                'image_url' => 'https://cover.openbd.jp/9784163902302.jpg',
                'genres' => ['文学・小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => '10の思い込みを乗り越え、データを基に世界を正しく見る習慣。',
                'image_url' => 'https://cover.openbd.jp/9784822289607.jpg',
                'genres' => ['ビジネス・経済', '科学・テクノロジー'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822245566',
                'published_date' => '2007-01-18',
                'description' => '世界を変えたのは「箱」の発明だった。物流革命の歴史。',
                'image_url' => 'https://cover.openbd.jp/9784822245566.jpg',
                'genres' => ['ビジネス・経済', '歴史・地理'],
            ],
        ];

        foreach ($books as $bookData) {
            $book = Book::create([
                'user_id' => $users->random()->id,
                'title' => $bookData['title'],
                'author' => $bookData['author'],
                'isbn' => $bookData['isbn'],
                'published_date' => $bookData['published_date'],
                'description' => $bookData['description'],
                'image_url' => $bookData['image_url'],
            ]);

            $genreIds = $genres->whereIn('name', $bookData['genres'])->pluck('id');
            $book->genres()->attach($genreIds);
        }
    }
}
