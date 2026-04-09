<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('マイ読書レポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 基本サマリー -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">基本統計</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-blue-600 mb-2">{{ $stats['summary']['total_reviews'] }}</div>
                            <div class="text-sm text-gray-600">総レビュー数</div>
                        </div>
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-green-600 mb-2">{{ $stats['summary']['books_read'] }}</div>
                            <div class="text-sm text-gray-600">読了冊数</div>
                        </div>
                        <div class="border rounded-lg p-6 text-center">
                            <div class="text-4xl font-bold text-yellow-500 mb-2">
                                @if ($stats['summary']['average_rating'] > 0)
                                    {{ number_format($stats['summary']['average_rating'], 1) }}
                                @else
                                    -
                                @endif
                            </div>
                            <div class="text-sm text-gray-600">平均評価</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <!-- 評価分布 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">評価分布</h3>
                        <div class="space-y-3">
                            @foreach ($stats['rating_distribution'] as $index => $count)
                                @php
                                    $rating = $index + 1;
                                    $maxCount = $stats['rating_distribution']->max() ?: 1;
                                    $percentage = ($count / $maxCount) * 100;
                                @endphp
                                <div class="flex items-center">
                                    <div class="w-16 text-sm text-gray-700">
                                        <span class="text-yellow-500">{{ str_repeat('★', $rating) }}</span>
                                    </div>
                                    <div class="flex-1 mx-3">
                                        <div class="bg-gray-200 rounded-full h-5 overflow-hidden">
                                            <div class="bg-yellow-400 h-5 rounded-full transition-all duration-300" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="w-12 text-sm text-gray-600 text-right font-medium">{{ $count }}件</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- 高評価書籍TOP5 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">高評価書籍 TOP5</h3>
                        @if (count($stats['top_rated_books']) > 0)
                            <div class="space-y-3">
                                @foreach ($stats['top_rated_books'] as $index => $book)
                                    @php
                                        $rankColors = [
                                            0 => 'bg-yellow-400 text-white',
                                            1 => 'bg-gray-400 text-white',
                                            2 => 'bg-amber-600 text-white',
                                        ];
                                        $rankColor = $rankColors[$index] ?? 'bg-gray-200 text-gray-600';
                                    @endphp
                                    <a href="{{ route('books.show', $book['id']) }}" class="flex items-center p-3 border rounded-lg hover:shadow-md transition">
                                        <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full {{ $rankColor }} font-bold text-sm">
                                            {{ $index + 1 }}
                                        </div>
                                        <div class="flex-grow min-w-0 ml-3">
                                            <div class="font-medium text-gray-900 truncate">{{ $book['title'] }}</div>
                                            <div class="text-sm text-gray-500">{{ $book['author'] }}</div>
                                        </div>
                                        <div class="flex-shrink-0 ml-3 text-yellow-500 text-sm">
                                            {{ str_repeat('★', $book['rating']) }}{{ str_repeat('☆', 5 - $book['rating']) }}
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500 text-center py-8">4星以上の書籍がありません</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ジャンル別評価傾向 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-1">ジャンル別評価傾向 TOP5</h3>
                    <p class="text-sm text-gray-500 mb-4">どのジャンルを高く評価する傾向があるかを表示</p>
                    @if (count($stats['genre_ratings']) > 0)
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach ($stats['genre_ratings'] as $index => $genre)
                                @php
                                    $rankColors = [
                                        0 => 'bg-yellow-400 text-white',
                                        1 => 'bg-gray-400 text-white',
                                        2 => 'bg-amber-600 text-white',
                                    ];
                                    $rankColor = $rankColors[$index] ?? 'bg-gray-200 text-gray-600';
                                @endphp
                                <a href="{{ route('genres.show', $genre['id']) }}" class="flex items-center p-4 border rounded-lg hover:shadow-md transition">
                                    <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full {{ $rankColor }} font-bold text-sm">
                                        {{ $index + 1 }}
                                    </div>
                                    <div class="flex-grow ml-3">
                                        <div class="font-medium text-gray-900">{{ $genre['name'] }}</div>
                                        <div class="text-sm text-gray-500">{{ $genre['count'] }}件のレビュー</div>
                                    </div>
                                    <div class="flex-shrink-0 ml-3 text-right">
                                        <div class="text-lg font-bold text-yellow-500">{{ number_format($genre['average_rating'], 1) }}</div>
                                        <div class="text-xs text-gray-400">平均評価</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">ジャンルが設定された書籍のレビューがありません</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
