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
        $user = User::first();
        
        $books = [
            [
                'title' => '吾輩は猫である',
                'author' => '夏目漱石',
                'isbn' => '9784101010014',
                'published_date' => '1905-01-01',
                'description' => '中学校の英語教師である珍野苦沙弥の家に飼われている猫である「吾輩」の視点から、珍野一家や、そこに出入りする人々の様子を風刺的に描いた作品。',
                'image_url' => 'https://cover.openbd.jp/9784101010014.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => '人を動かす',
                'author' => 'D・カーネギー',
                'isbn' => '9784422100524',
                'published_date' => '1936-10-01',
                'description' => '人間関係の古典として、あらゆる自己啓発本の原点となったデール・カーネギーの名著。',
                'image_url' => 'https://cover.openbd.jp/9784422100524.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell',
                'isbn' => '9784873115658',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニックを紹介。',
                'image_url' => 'https://cover.openbd.jp/9784873115658.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '7つの習慣',
                'author' => 'スティーブン・R・コヴィー',
                'isbn' => '9784863940246',
                'published_date' => '2013-08-30',
                'description' => '全世界3000万部、国内180万部を超えるベストセラー。人生を成功に導く7つの習慣を解説。',
                'image_url' => 'https://cover.openbd.jp/9784863940246.jpg',
                'genres' => ['ビジネス', '自己啓発'],
            ],
            [
                'title' => '坊っちゃん',
                'author' => '夏目漱石',
                'isbn' => '9784101010021',
                'published_date' => '1906-04-01',
                'description' => '東京の物理学校を卒業後、四国の中学校に数学教師として赴任した主人公「坊っちゃん」の物語。',
                'image_url' => 'https://cover.openbd.jp/9784101010021.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'サピエンス全史',
                'author' => 'ユヴァル・ノア・ハラリ',
                'isbn' => '9784309226712',
                'published_date' => '2016-09-08',
                'description' => 'なぜ人類だけが文明を築けたのか？ホモ・サピエンスの歴史を俯瞰する世界的ベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784309226712.jpg',
                'genres' => ['歴史', '科学'],
            ],
            [
                'title' => 'Clean Code',
                'author' => 'Robert C. Martin',
                'isbn' => '9784048930598',
                'published_date' => '2017-12-18',
                'description' => 'アジャイルソフトウェア開発の奥義として、クリーンなコードを書くための原則を解説。',
                'image_url' => 'https://cover.openbd.jp/9784048930598.jpg',
                'genres' => ['技術書'],
            ],
            [
                'title' => '嫌われる勇気',
                'author' => '岸見一郎・古賀史健',
                'isbn' => '9784478025819',
                'published_date' => '2013-12-13',
                'description' => 'アドラー心理学を対話形式でわかりやすく解説した自己啓発書のベストセラー。',
                'image_url' => 'https://cover.openbd.jp/9784478025819.jpg',
                'genres' => ['自己啓発'],
            ],
            [
                'title' => '火花',
                'author' => '又吉直樹',
                'isbn' => '9784163902302',
                'published_date' => '2015-03-11',
                'description' => '芥川賞受賞作。売れない芸人の青春を描いた純文学作品。',
                'image_url' => 'https://cover.openbd.jp/9784163902302.jpg',
                'genres' => ['小説'],
            ],
            [
                'title' => 'FACTFULNESS',
                'author' => 'ハンス・ロスリング',
                'isbn' => '9784822289607',
                'published_date' => '2019-01-11',
                'description' => 'データを基に世界を正しく見る習慣を身につける。思い込みを乗り越え、世界を正しく見る方法。',
                'image_url' => 'https://cover.openbd.jp/9784822289607.jpg',
                'genres' => ['ビジネス', '科学'],
            ],
            [
                'title' => 'コンテナ物語',
                'author' => 'マルク・レビンソン',
                'isbn' => '9784822251468',
                'published_date' => '2007-01-18',
                'description' => 'コンテナが世界を変えた。物流革命の歴史を描いたノンフィクション。',
                'image_url' => 'https://cover.openbd.jp/9784822251468.jpg',
                'genres' => ['ビジネス', '歴史'],
            ],
        ];

        foreach ($books as $bookData) {
            $genreNames = $bookData['genres'];
            unset($bookData['genres']);
            
            $book = Book::firstOrCreate(
                ['isbn' => $bookData['isbn']],
                array_merge($bookData, ['user_id' => $user->id])
            );

            // ジャンルを紐付け
            $genreIds = Genre::whereIn('name', $genreNames)->pluck('id')->toArray();
            $book->genres()->sync($genreIds);
        }
    }
}
