<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            ジャンル: {{ $genre->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('books.index') }}" class="text-blue-600 hover:text-blue-800">← 書籍一覧に戻る</a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($books->isEmpty())
                        <p>このジャンルの書籍はまだ登録されていません。</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach($books as $book)
                                <a href="{{ route('books.show', $book) }}" class="block border rounded-lg p-4 shadow hover:shadow-lg transition">
                                    @if($book->image_url)
                                        <img src="{{ $book->image_url }}" alt="{{ $book->title }}" class="w-full h-48 object-cover mb-4 rounded">
                                    @else
                                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center mb-4 rounded">
                                            <span class="text-gray-500">画像なし</span>
                                        </div>
                                    @endif
                                    <h3 class="font-bold text-lg mb-2 text-blue-600">{{ $book->title }}</h3>
                                    <p class="text-gray-600 text-sm mb-2">{{ $book->author }}</p>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach($book->genres as $g)
                                            <span class="bg-gray-200 text-gray-700 text-xs px-2 py-1 rounded {{ $g->id === $genre->id ? 'bg-blue-200 text-blue-700' : '' }}">
                                                {{ $g->name }}
                                            </span>
                                        @endforeach
                                    </div>
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