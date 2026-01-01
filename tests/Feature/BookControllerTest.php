<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_書籍一覧ページが表示される(): void
    {
        $response = $this->get(route('books.index'));
        $response->assertStatus(200);
    }

    public function test_書籍詳細ページが表示される(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id]);
        $book->genres()->attach($genre->id);

        $response = $this->get(route('books.show', $book));
        $response->assertStatus(200);
        $response->assertSee($book->title);
    }

    public function test_未認証ユーザーは書籍登録ページにアクセスできない(): void
    {
        $response = $this->get(route('books.create'));
        $response->assertRedirect(route('login'));
    }

    public function test_認証ユーザーは書籍を登録できる(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();

        $response = $this->actingAs($user)->post(route('books.store'), [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'genres' => [$genre->id],
        ]);

        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseHas('books', [
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
        ]);
    }

    public function test_書籍登録者のみが編集できる(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->genres()->attach($genre->id);

        // 所有者は編集ページにアクセスできる
        $response = $this->actingAs($owner)->get(route('books.edit', $book));
        $response->assertStatus(200);

        // 他のユーザーは編集ページにアクセスできない
        $response = $this->actingAs($other)->get(route('books.edit', $book));
        $response->assertStatus(403);
    }

    public function test_書籍登録者のみが削除できる(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $genre = Genre::factory()->create();
        $book = Book::factory()->create(['user_id' => $owner->id]);
        $book->genres()->attach($genre->id);

        // 他のユーザーは削除できない
        $response = $this->actingAs($other)->delete(route('books.destroy', $book));
        $response->assertStatus(403);

        // 所有者は削除できる
        $response = $this->actingAs($owner)->delete(route('books.destroy', $book));
        $response->assertRedirect(route('books.index'));
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
    }

    public function test_書籍検索が機能する(): void
    {
        $user = User::factory()->create();
        $genre = Genre::factory()->create();
        
        $book1 = Book::factory()->create(['user_id' => $user->id, 'title' => 'Laravel入門']);
        $book2 = Book::factory()->create(['user_id' => $user->id, 'title' => 'PHP基礎']);
        $book1->genres()->attach($genre->id);
        $book2->genres()->attach($genre->id);

        $response = $this->get(route('books.search', ['query' => 'Laravel']));
        $response->assertStatus(200);
        $response->assertSee('Laravel入門');
        $response->assertDontSee('PHP基礎');
    }
}