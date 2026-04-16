@if ($errors->any())
    <div {{ $attributes->merge(['class' => 'mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded']) }}>
        <ul class="list-disc list-inside text-sm space-y-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
