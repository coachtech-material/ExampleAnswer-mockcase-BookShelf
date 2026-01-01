<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('マイ読書レポート') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 基本統計サマリー -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📊 基本統計</h3>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                        <div class="bg-blue-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-blue-600">{{ $stats['summary']['total_reviews'] }}</div>
                            <div class="text-sm text-gray-600">総レビュー数</div>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-green-600">{{ $stats['summary']['total_books_reviewed'] }}</div>
                            <div class="text-sm text-gray-600">レビュー済み書籍</div>
                        </div>
                        <div class="bg-yellow-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-yellow-600">{{ number_format($stats['summary']['average_rating'], 1) }}</div>
                            <div class="text-sm text-gray-600">平均評価</div>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-purple-600">{{ $stats['reading_streak']['current_streak'] }}</div>
                            <div class="text-sm text-gray-600">現在の連続日数</div>
                        </div>
                        <div class="bg-pink-50 rounded-lg p-4 text-center">
                            <div class="text-3xl font-bold text-pink-600">{{ $stats['reading_streak']['longest_streak'] }}</div>
                            <div class="text-sm text-gray-600">最長連続日数</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- 評価分布 -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">⭐ 評価分布</h3>
                        <div class="space-y-3">
                            @foreach($stats['rating_distribution'] as $rating => $count)
                                @php
                                    $maxCount = max($stats['rating_distribution']->toArray()) ?: 1;
                                    $percentage = ($count / $maxCount) * 100;
                                @endphp
                                <div class="flex items-center">
                                    <div class="w-12 text-sm font-medium">{{ $rating }}星</div>
                                    <div class="flex-1 mx-2">
                                        <div class="bg-gray-200 rounded-full h-4">
                                            <div class="bg-yellow-400 h-4 rounded-full" style="width: {{ $percentage }}%"></div>
                                        </div>
                                    </div>
                                    <div class="w-8 text-sm text-gray-600 text-right">{{ $count }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- お気に入りジャンル -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">❤️ お気に入りジャンル</h3>
                        @if($stats['favorite_genres']->isNotEmpty())
                            <div class="space-y-3">
                                @foreach($stats['favorite_genres'] as $genre => $count)
                                    @php
                                        $maxCount = $stats['favorite_genres']->first() ?: 1;
                                        $percentage = ($count / $maxCount) * 100;
                                    @endphp
                                    <div class="flex items-center">
                                        <div class="w-24 text-sm font-medium truncate">{{ $genre }}</div>
                                        <div class="flex-1 mx-2">
                                            <div class="bg-gray-200 rounded-full h-4">
                                                <div class="bg-pink-400 h-4 rounded-full" style="width: {{ $percentage }}%"></div>
                                            </div>
                                        </div>
                                        <div class="w-8 text-sm text-gray-600 text-right">{{ $count }}</div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-gray-500">お気に入り書籍がありません。</p>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ジャンル別統計 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📚 ジャンル別レビュー統計</h3>
                    @if($stats['genre_stats']->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ジャンル</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">レビュー数</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">平均評価</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($stats['genre_stats'] as $genre)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $genre['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $genre['count'] }}件</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <span class="text-yellow-500">
                                                    @for($i = 1; $i <= 5; $i++)
                                                        @if($i <= round($genre['average_rating']))★@else☆@endif
                                                    @endfor
                                                </span>
                                                ({{ $genre['average_rating'] }})
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">レビューがありません。</p>
                    @endif
                </div>
            </div>

            <!-- 月別アクティビティ -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">📅 月別アクティビティ</h3>
                    @if($stats['monthly_activity']->isNotEmpty())
                        <div class="overflow-x-auto">
                            <div class="flex items-end space-x-2 h-40">
                                @php
                                    $maxCount = $stats['monthly_activity']->max('count') ?: 1;
                                @endphp
                                @foreach($stats['monthly_activity'] as $month)
                                    @php
                                        $height = ($month['count'] / $maxCount) * 100;
                                    @endphp
                                    <div class="flex flex-col items-center">
                                        <div class="bg-blue-400 rounded-t" style="height: {{ max($height, 5) }}%; width: 40px;" title="{{ $month['count'] }}件"></div>
                                        <div class="text-xs text-gray-500 mt-1 transform -rotate-45 origin-top-left">{{ \Carbon\Carbon::parse($month['month'])->format('Y/m') }}</div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <p class="text-gray-500">アクティビティがありません。</p>
                    @endif
                </div>
            </div>

            <!-- 高評価書籍 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mt-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">🏆 高評価書籍（4星以上）</h3>
                    @if($stats['top_rated_books']->isNotEmpty())
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($stats['top_rated_books'] as $book)
                                <div class="border rounded-lg p-4">
                                    <h4 class="font-semibold text-gray-900">{{ $book['title'] }}</h4>
                                    <p class="text-sm text-gray-600">{{ $book['author'] }}</p>
                                    <div class="flex items-center justify-between mt-2">
                                        <span class="text-yellow-500">
                                            @for($i = 1; $i <= 5; $i++)
                                                @if($i <= $book['rating'])★@else☆@endif
                                            @endfor
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $book['reviewed_at'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500">高評価書籍がありません。</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
