<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('書籍の登録') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <!-- ISBN検索 -->
            <div class="bg-blue-50 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="font-semibold text-lg mb-4">ISBNから書籍情報を自動入力</h3>
                    <div class="flex gap-2">
                        <input type="text" id="isbn-search" placeholder="13桁のISBNを入力" 
                               class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               maxlength="13">
                        <button type="button" id="fetch-btn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            検索
                        </button>
                    </div>
                    <p id="fetch-error" class="mt-2 text-sm text-red-600 hidden"></p>
                    <p id="fetch-success" class="mt-2 text-sm text-green-600 hidden"></p>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('books.store') }}" method="POST">
                        @include('books._form')
                        
                        <div class="flex items-center justify-end mt-6 pt-6 border-t border-gray-200">
                            <a href="{{ route('books.index') }}" class="text-gray-600 hover:text-gray-900 mr-4">
                                キャンセル
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded">
                                登録する
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.getElementById('fetch-btn').addEventListener('click', async function() {
            const isbn = document.getElementById('isbn-search').value.trim();
            const errorEl = document.getElementById('fetch-error');
            const successEl = document.getElementById('fetch-success');

            errorEl.classList.add('hidden');
            successEl.classList.add('hidden');

            if (isbn.length !== 13) {
                errorEl.textContent = 'ISBNは13桁で入力してください。';
                errorEl.classList.remove('hidden');
                return;
            }

            this.disabled = true;
            this.textContent = '検索中...';

            try {
                const response = await fetch(`/books/isbn/${isbn}`, {
                    headers: {
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();

                if (data.error) {
                    errorEl.textContent = data.error;
                    errorEl.classList.remove('hidden');
                } else {
                    document.getElementById('title').value = data.title || '';
                    document.getElementById('author').value = data.author || '';
                    document.getElementById('isbn').value = isbn;
                    document.getElementById('description').value = data.description || '';
                    document.getElementById('image_url').value = data.image_url || '';

                    if (data.published_date) {
                        const date = new Date(data.published_date);
                        if (!isNaN(date)) {
                            document.getElementById('published_date').value = date.toISOString().split('T')[0];
                        }
                    }

                    successEl.textContent = '書籍情報を取得しました。';
                    successEl.classList.remove('hidden');
                }
            } catch (e) {
                errorEl.textContent = '通信エラーが発生しました。';
                errorEl.classList.remove('hidden');
            } finally {
                this.disabled = false;
                this.textContent = '検索';
            }
        });
    </script>
    @endpush
</x-app-layout>
