<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            読書計画編集
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <p class="text-sm text-gray-700">対象書籍: <strong>{{ $readingPlan->book->title }}</strong></p>
                        <p class="text-sm text-gray-700">現在の状態:
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $readingPlan->status->badgeClass() }}">
                                {{ $readingPlan->status->label() }}
                            </span>
                        </p>
                    </div>

                    <form action="{{ route('reading-plans.update', $readingPlan) }}" method="POST" novalidate>
                        @csrf
                        @method('PUT')
                        <div class="mb-4">
                            <label for="target_date" class="block text-sm font-medium text-gray-700">期日 <span class="text-red-500">*</span></label>
                            <input type="date" name="target_date" id="target_date" value="{{ old('target_date', $readingPlan->target_date->format('Y-m-d')) }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('target_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('reading-plans.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">キャンセル</a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                更新
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
