<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('評価ランキング TOP 10') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($rankedBooks->isEmpty())
                        <p class="text-gray-500 text-center py-8">まだレビューが投稿された書籍がありません。</p>
                    @else
                        <div class="space-y-4">
                            @foreach($rankedBooks as $index => $book)
                                <a href="{{ route('books.show', $book) }}" class="block hover:bg-gray-50 transition rounded-lg">
                                    <div class="flex items-center p-4 border rounded-lg {{ $index < 3 ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200' }}">
                                        <!-- 順位 -->
                                        <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center rounded-full {{ $index === 0 ? 'bg-yellow-400 text-white' : ($index === 1 ? 'bg-gray-300 text-white' : ($index === 2 ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-600')) }} font-bold text-xl mr-4">
                                            {{ $index + 1 }}
                                        </div>
                                        
                                        <!-- 書籍画像 -->
                                        <div class="flex-shrink-0 w-16 h-20 mr-4">
                                            @if($book->image_url)
                                                <img src="{{ $book->image_url }}" alt="{{ $book->title }}" class="w-full h-full object-cover rounded shadow">
                                            @else
                                                <div class="w-full h-full bg-gray-200 flex items-center justify-center rounded">
                                                    <span class="text-gray-400 text-xs">No Image</span>
                                                </div>
                                            @endif
                                        </div>
                                        
                                        <!-- 書籍情報 -->
                                        <div class="flex-grow min-w-0">
                                            <h3 class="text-lg font-semibold text-blue-600 hover:text-blue-800 truncate">
                                                {{ $book->title }}
                                            </h3>
                                            <p class="text-sm text-gray-600">{{ $book->author }}</p>
                                            <div class="flex items-center mt-1">
                                                @for($i = 1; $i <= 5; $i++)
                                                    @if($i <= round($book->average_rating))
                                                        <span class="text-yellow-400">★</span>
                                                    @else
                                                        <span class="text-gray-300">★</span>
                                                    @endif
                                                @endfor
                                                <span class="ml-2 text-sm text-gray-600">
                                                    {{ number_format($book->average_rating, 2) }}
                                                </span>
                                                <span class="ml-2 text-xs text-gray-500">
                                                    ({{ $book->review_count }}件のレビュー)
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- 評価バッジ -->
                                        <div class="flex-shrink-0 ml-4">
                                            <div class="text-center">
                                                <div class="text-2xl font-bold {{ $index < 3 ? 'text-yellow-500' : 'text-gray-600' }}">
                                                    {{ number_format($book->average_rating, 1) }}
                                                </div>
                                                <div class="text-xs text-gray-500">平均評価</div>
                                            </div>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
