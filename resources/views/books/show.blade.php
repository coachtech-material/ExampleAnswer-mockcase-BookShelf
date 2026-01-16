<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $book->title }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex flex-col md:flex-row gap-6">
                        <div class="md:w-1/3">
                            @if($book->image_url)
                                <img src="{{ $book->image_url }}" alt="{{ $book->title }}" class="w-full rounded shadow">
                            @else
                                <div class="w-full h-64 bg-gray-200 flex items-center justify-center rounded">
                                    <span class="text-gray-500">画像なし</span>
                                </div>
                            @endif
                        </div>
                        <div class="md:w-2/3">
                            <div class="flex items-start justify-between mb-4">
                                <h1 class="text-2xl font-bold">{{ $book->title }}</h1>
                                
                                <!-- お気に入りボタン -->
                                @auth
                                    @if(Auth::user()->favoriteBooks->contains($book->id))
                                        <form action="{{ route('favorites.toggle', $book) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-red-500 hover:text-red-700" title="お気に入りから削除">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="currentColor" viewBox="0 0 24 24">
                                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @else
                                        <form action="{{ route('favorites.toggle', $book) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="text-gray-400 hover:text-red-500" title="お気に入りに追加">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                                </svg>
                                            </button>
                                        </form>
                                    @endif
                                @endauth
                            </div>
                            
                            <p class="text-gray-600 mb-2"><strong>著者:</strong> {{ $book->author }}</p>
                            <p class="text-gray-600 mb-2"><strong>ISBN:</strong> {{ $book->isbn ?? '未登録' }}</p>
                            <p class="text-gray-600 mb-2"><strong>出版日:</strong> {{ $book->published_date ?? '未登録' }}</p>
                            <div class="mb-4">
                                <strong>ジャンル:</strong>
                                @foreach($book->genres as $genre)
                                    <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded">{{ $genre->name }}</span>
                                @endforeach
                            </div>
                            @if($book->description)
                                <div class="mb-4">
                                    <strong>概要:</strong>
                                    <p class="mt-2 text-gray-700">{{ $book->description }}</p>
                                </div>
                            @endif

                            @can('update', $book)
                                <div class="flex gap-2 mt-4">
                                    <a href="{{ route('books.edit', $book) }}" class="bg-yellow-500 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded">
                                        編集
                                    </a>
                                    <form action="{{ route('books.destroy', $book) }}" method="POST" onsubmit="return confirm('本当に削除しますか？')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded">
                                            削除
                                        </button>
                                    </form>
                                </div>
                            @endcan
                        </div>
                    </div>

                    <!-- レビューセクション -->
                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <h2 class="text-xl font-bold mb-4">レビュー</h2>
                        
                        @auth
                            <!-- レビュー投稿フォーム -->
                            <div class="mb-6 bg-gray-50 p-4 rounded-lg">
                                <h3 class="font-semibold mb-3">レビューを投稿</h3>
                                <form action="{{ route('reviews.store', $book) }}" method="POST">
                                    @csrf
                                    <div class="mb-4">
                                        <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">評価</label>
                                        <select name="rating" id="rating" class="border-gray-300 rounded-md shadow-sm">
                                            <option value="">選択してください</option>
                                            @for($i = 5; $i >= 1; $i--)
                                                <option value="{{ $i }}" {{ old('rating') == $i ? 'selected' : '' }}>
                                                    {{ str_repeat('★', $i) }}{{ str_repeat('☆', 5 - $i) }} ({{ $i }})
                                                </option>
                                            @endfor
                                        </select>
                                        @error('rating')
                                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <div class="mb-4">
                                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">コメント</label>
                                        <textarea name="comment" id="comment" rows="3" 
                                            class="border-gray-300 rounded-md shadow-sm w-full"
                                            placeholder="この書籍の感想を書いてください">{{ old('comment') }}</textarea>
                                        @error('comment')
                                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                        投稿する
                                    </button>
                                </form>
                            </div>
                        @else
                            <p class="mb-6 text-gray-600">
                                レビューを投稿するには<a href="{{ route('login') }}" class="text-blue-600 hover:underline">ログイン</a>してください。
                            </p>
                        @endauth

                        <!-- レビュー一覧 -->
                        @if($book->reviews->count() > 0)
                            <div class="space-y-4">
                                @foreach($book->reviews as $review)
                                    <div class="border rounded-lg p-4">
                                        <div class="flex items-center justify-between mb-2">
                                            <div>
                                                <span class="font-semibold">{{ $review->user->name }}</span>
                                                <span class="text-yellow-500 ml-2">
                                                    {{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}
                                                </span>
                                            </div>
                                            <span class="text-sm text-gray-500">{{ $review->created_at->format('Y/m/d') }}</span>
                                        </div>
                                        @if($review->comment)
                                            <p class="text-gray-700">{{ $review->comment }}</p>
                                        @endif
                                        
                                        <div class="mt-3 flex items-center justify-between">
                                            <!-- いいねボタン -->
                                            @auth
                                                @if(Auth::user()->likedReviews->contains($review->id))
                                                    <form action="{{ route('reviews.like', $review) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-blue-500 hover:text-blue-700 text-sm flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                                <path d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/>
                                                            </svg>
                                                            いいね済み ({{ $review->likedByUsers->count() }})
                                                        </button>
                                                    </form>
                                                @else
                                                    <form action="{{ route('reviews.like', $review) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-gray-500 hover:text-blue-500 text-sm flex items-center">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 20 20">
                                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/>
                                                            </svg>
                                                            いいね ({{ $review->likedByUsers->count() }})
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="text-gray-500 text-sm flex items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 20 20">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2 10.5a1.5 1.5 0 113 0v6a1.5 1.5 0 01-3 0v-6zM6 10.333v5.43a2 2 0 001.106 1.79l.05.025A4 4 0 008.943 18h5.416a2 2 0 001.962-1.608l1.2-6A2 2 0 0015.56 8H12V4a2 2 0 00-2-2 1 1 0 00-1 1v.667a4 4 0 01-.8 2.4L6.8 7.933a4 4 0 00-.8 2.4z"/>
                                                    </svg>
                                                    いいね ({{ $review->likedByUsers->count() }})
                                                </span>
                                            @endauth
                                            
                                            <!-- 編集・削除ボタン -->
                                            @can('update', $review)
                                                <div class="flex gap-2">
                                                    <a href="{{ route('reviews.edit', $review) }}" class="text-sm text-gray-600 hover:text-gray-800">編集</a>
                                                    <form action="{{ route('reviews.destroy', $review) }}" method="POST" class="inline" onsubmit="return confirm('このレビューを削除しますか？')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-sm text-red-600 hover:text-red-800">削除</button>
                                                    </form>
                                                </div>
                                            @endcan
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500">まだレビューはありません。</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="mt-4">
                <a href="{{ route('books.index') }}" class="text-blue-600 hover:underline">← 書籍一覧に戻る</a>
            </div>
        </div>
    </div>
</x-app-layout>
