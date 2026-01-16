@php
    $bookGenreIds = isset($book) ? $book->genres->pluck('id')->toArray() : [];
@endphp

@csrf
<div class="space-y-6">
    <!-- タイトル -->
    <div>
        <label for="title" class="block font-medium text-sm text-gray-700 mb-1">
            タイトル <span class="text-red-500">*</span>
        </label>
        <input type="text" name="title" id="title" value="{{ old('title', $book->title ?? '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
            placeholder="書籍のタイトルを入力">
        @error('title')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- 著者 -->
    <div>
        <label for="author" class="block font-medium text-sm text-gray-700 mb-1">
            著者 <span class="text-red-500">*</span>
        </label>
        <input type="text" name="author" id="author" value="{{ old('author', $book->author ?? '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
            placeholder="著者名を入力">
        @error('author')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- ISBN -->
    <div>
        <label for="isbn" class="block font-medium text-sm text-gray-700 mb-1">
            ISBN-13 <span class="text-red-500">*</span>
        </label>
        <input type="text" name="isbn" id="isbn" value="{{ old('isbn', $book->isbn ?? '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
            placeholder="9784000000000" maxlength="13">
        <p class="text-xs text-gray-500 mt-1">13桁のISBNコードを入力してください</p>
        @error('isbn')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- 出版日 -->
    <div>
        <label for="published_date" class="block font-medium text-sm text-gray-700 mb-1">
            出版日 <span class="text-red-500">*</span>
        </label>
        <input type="date" name="published_date" id="published_date" value="{{ old('published_date', $book->published_date ?? '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full">
        @error('published_date')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- 概要 -->
    <div>
        <label for="description" class="block font-medium text-sm text-gray-700 mb-1">
            概要
        </label>
        <textarea name="description" id="description" rows="4"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
            placeholder="書籍の概要を入力（任意）">{{ old('description', $book->description ?? '') }}</textarea>
        @error('description')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- 画像URL -->
    <div>
        <label for="image_url" class="block font-medium text-sm text-gray-700 mb-1">
            画像URL
        </label>
        <input type="url" name="image_url" id="image_url" value="{{ old('image_url', $book->image_url ?? '') }}"
            class="border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm block w-full"
            placeholder="https://example.com/image.jpg">
        <p class="text-xs text-gray-500 mt-1">書籍の表紙画像のURLを入力してください（任意）</p>
        @error('image_url')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>

    <!-- ジャンル -->
    <div>
        <label class="block font-medium text-sm text-gray-700 mb-2">
            ジャンル <span class="text-red-500">*</span>
        </label>
        <div class="bg-gray-50 rounded-md p-4">
            @if($genres->isEmpty())
                <p class="text-sm text-gray-500">ジャンルが登録されていません。先にジャンルを登録してください。</p>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($genres as $genre)
                        <label class="inline-flex items-center cursor-pointer hover:bg-gray-100 p-2 rounded">
                            <input type="checkbox" name="genres[]" value="{{ $genre->id }}"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                @if(in_array($genre->id, old('genres', $bookGenreIds))) checked @endif>
                            <span class="ml-2 text-sm text-gray-700">{{ $genre->name }}</span>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
        @error('genres')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
        @error('genres.*')
            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
        @enderror
    </div>
</div>
