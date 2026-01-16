@csrf
<div class="space-y-6">
    <div>
        <label for="title" class="block text-sm font-medium text-gray-700">タイトル <span class="text-red-500">*</span></label>
        <input type="text" name="title" id="title" value="{{ old('title', $book->title ?? '') }}" 
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               placeholder="例: 吾輩は猫である">
        @error('title')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="author" class="block text-sm font-medium text-gray-700">著者 <span class="text-red-500">*</span></label>
        <input type="text" name="author" id="author" value="{{ old('author', $book->author ?? '') }}" 
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               placeholder="例: 夏目漱石">
        @error('author')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="isbn" class="block text-sm font-medium text-gray-700">ISBN</label>
        <input type="text" name="isbn" id="isbn" value="{{ old('isbn', $book->isbn ?? '') }}" 
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               placeholder="例: 9784101010014" maxlength="13">
        <p class="mt-1 text-xs text-gray-500">13桁のISBNを入力してください（ハイフンなし）</p>
        @error('isbn')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="published_date" class="block text-sm font-medium text-gray-700">出版日</label>
        <input type="date" name="published_date" id="published_date" 
               value="{{ old('published_date', isset($book->published_date) ? $book->published_date->format('Y-m-d') : '') }}" 
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        @error('published_date')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium text-gray-700">説明</label>
        <textarea name="description" id="description" rows="4" 
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  placeholder="書籍の説明を入力してください">{{ old('description', $book->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="image_url" class="block text-sm font-medium text-gray-700">画像URL</label>
        <input type="url" name="image_url" id="image_url" value="{{ old('image_url', $book->image_url ?? '') }}" 
               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
               placeholder="例: https://example.com/image.jpg">
        <p class="mt-1 text-xs text-gray-500">書籍の表紙画像のURLを入力してください</p>
        @error('image_url' )
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">ジャンル <span class="text-red-500">*</span></label>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($genres as $genre)
                <label class="flex items-center p-2 bg-gray-50 rounded hover:bg-gray-100 cursor-pointer">
                    <input type="checkbox" name="genres[]" value="{{ $genre->id }}" 
                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                           {{ in_array($genre->id, old('genres', isset($book) ? $book->genres->pluck('id')->toArray() : [])) ? 'checked' : '' }}>
                    <span class="ml-2 text-sm text-gray-700">{{ $genre->name }}</span>
                </label>
            @endforeach
        </div>
        @error('genres')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('genres.*')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
</div>
