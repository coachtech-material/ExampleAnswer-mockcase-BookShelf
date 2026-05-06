<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            通知一覧
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                @if($notifications->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="mx-auto h-12 w-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        <p class="mt-3 text-sm text-gray-500">通知はありません。</p>
                    </div>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach($notifications as $notification)
                            @php
                                $timing = $notification->data['timing'] ?? null;
                                $isUnread = $notification->read_at === null;

                                // タイミング別の色・アイコン定義（Tailwind 静的解析のため class 全体をリテラルで書く）
                                $style = match ($timing) {
                                    'three_days_before' => [
                                        'border' => 'bg-blue-500',
                                        'iconBg' => $isUnread ? 'bg-blue-100' : 'bg-gray-100',
                                        'iconColor' => $isUnread ? 'text-blue-600' : 'text-gray-400',
                                        'icon' => 'calendar',
                                    ],
                                    'on_due_date' => [
                                        'border' => 'bg-yellow-500',
                                        'iconBg' => $isUnread ? 'bg-yellow-100' : 'bg-gray-100',
                                        'iconColor' => $isUnread ? 'text-yellow-700' : 'text-gray-400',
                                        'icon' => 'clock',
                                    ],
                                    'three_days_after' => [
                                        'border' => 'bg-red-500',
                                        'iconBg' => $isUnread ? 'bg-red-100' : 'bg-gray-100',
                                        'iconColor' => $isUnread ? 'text-red-600' : 'text-gray-400',
                                        'icon' => 'warning',
                                    ],
                                    default => [
                                        'border' => 'bg-gray-300',
                                        'iconBg' => 'bg-gray-100',
                                        'iconColor' => 'text-gray-400',
                                        'icon' => 'bell',
                                    ],
                                };
                            @endphp
                            <li class="relative {{ $isUnread ? 'bg-blue-50/40' : 'bg-white' }} hover:bg-gray-50 transition-colors">
                                {{-- Left border for unread --}}
                                @if($isUnread)
                                    <span class="absolute inset-y-0 left-0 w-1 {{ $style['border'] }}" aria-hidden="true"></span>
                                @endif

                                <div class="px-6 py-4 flex items-start">
                                    {{-- Icon --}}
                                    <div class="flex-shrink-0">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-full {{ $style['iconBg'] }}">
                                            @if($style['icon'] === 'calendar')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $style['iconColor'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            @elseif($style['icon'] === 'clock')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $style['iconColor'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            @elseif($style['icon'] === 'warning')
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $style['iconColor'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            @else
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 {{ $style['iconColor'] }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                                                </svg>
                                            @endif
                                        </span>
                                    </div>

                                    {{-- Content --}}
                                    <div class="ml-4 flex-1 min-w-0">
                                        <div class="flex items-center space-x-2">
                                            @if($isUnread)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">未読</span>
                                            @endif
                                            <p class="text-sm font-semibold text-gray-900 truncate">{{ $notification->data['title'] ?? '通知' }}</p>
                                        </div>
                                        <p class="mt-1 text-sm text-gray-600">{{ $notification->data['body'] ?? '' }}</p>
                                        <p class="mt-2 text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</p>
                                    </div>

                                    {{-- Action --}}
                                    @if($isUnread)
                                        <div class="ml-4 flex-shrink-0">
                                            <form action="{{ route('notifications.read', $notification->id) }}" method="POST" novalidate>
                                                @csrf
                                                <button type="submit" class="text-sm font-medium text-blue-600 hover:text-blue-800 hover:underline">
                                                    既読にする
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
