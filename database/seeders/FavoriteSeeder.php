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

        foreach ($users as $user) {
            $favoriteCount = rand(3, 5);
            $favoriteBooks = $books->random($favoriteCount);
            $user->favoriteBooks()->attach($favoriteBooks->pluck('id'));
        }
    }
}
