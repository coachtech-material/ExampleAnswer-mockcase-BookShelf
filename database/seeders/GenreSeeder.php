<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            '文学・小説',
            'ビジネス・経済',
            '自己啓発',
            'コンピュータ・IT',
            '科学・テクノロジー',
            '歴史・地理',
            '芸術・エンターテインメント',
            '健康・医学',
            '料理・グルメ',
            '旅行・ガイド',
        ];

        foreach ($genres as $name) {
            Genre::create(['name' => $name]);
        }
    }
}
