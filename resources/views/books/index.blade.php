<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('書籍一覧') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <!-- 検索フォーム -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('books.index') }}" method="GET" class="space-y-4" novalidate>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="keyword" class="block text-sm font-medium text-gray-700 mb-1">キーワード</label>
                                <input type="text" name="keyword" id="keyword" value="{{ request('keyword') }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="タイトル・著者で検索">
                            </div>
                            <div>
                                <label for="genre" class="block text-sm font-medium text-gray-700 mb-1">ジャンル</label>
                                <select name="genre" id="genre" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">すべて</option>
                                    @foreach($genres ?? [] as $genre)
                                        <option value="{{ $genre->id }}" {{ request('genre') == $genre->id ? 'selected' : '' }}>
                                            {{ $genre->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">並び順</label>
                                <select name="sort" id="sort" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>新しい順</option>
                                    <option value="oldest" {{ request('sort') == 'oldest' ? 'selected' : '' }}>古い順</option>
                                    <option value="rating" {{ request('sort') == 'rating' ? 'selected' : '' }}>評価順</option>
                                    <option value="title" {{ request('sort') == 'title' ? 'selected' : '' }}>タイトル順</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                    検索
                                </button>
                                <a href="{{ route('books.index') }}" class="text-gray-600 hover:text-gray-900">
                                    リセット
                                </a>
                            </div>
                            <a href="{{ route('books.create') }}" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                書籍を登録
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            @if(request('keyword') || request('genre'))
                <div class="mb-4 text-gray-600">
                    検索結果: {{ $books->total() }}件
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($books->isEmpty())
                        <p class="text-gray-500">書籍が見つかりませんでした。</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($books as $book)
                                <a href="{{ route('books.show', $book) }}" class="block border rounded-lg p-4 shadow hover:shadow-lg transition cursor-pointer">
                                    @if($book->image_url)
                                        <img src="{{ $book->image_url }}" alt="{{ $book->title }}" class="w-full h-48 object-cover mb-4 rounded">
                                    @else
                                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center mb-4 rounded">
                                            <span class="text-gray-500">画像なし</span>
                                        </div>
                                    @endif
                                    <h3 class="font-bold text-lg mb-2 text-blue-600 hover:text-blue-800">
                                        {{ $book->title }}
                                    </h3>
                                    <p class="text-gray-600 text-sm mb-2">{{ $book->author }}</p>
                                    <div class="flex flex-wrap gap-1 mb-2">
                                        @foreach($book->genres as $genre)
                                            <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded">{{ $genre->name }}</span>
                                        @endforeach
                                    </div>
                                    @if($book->reviews_avg_rating)
                                        <div class="flex items-center">
                                            <span class="text-yellow-500">
                                                @for($i = 1; $i <= 5; $i++)
                                                    @if($i <= round($book->reviews_avg_rating))
                                                        ★
                                                    @else
                                                        ☆
                                                    @endif
                                                @endfor
                                            </span>
                                            <span class="text-sm text-gray-500 ml-2">
                                                ({{ number_format($book->reviews_avg_rating, 1) }})
                                            </span>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $books->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
