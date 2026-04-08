<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['required', 'string', 'size:13', Rule::unique('books')->ignore($this->route('book'))],
            'published_date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'image_url' => ['nullable', 'url', 'max:255'],
            'genres' => ['required', 'array', 'min:1'],
            'genres.*' => ['integer', 'exists:genres,id'],
        ];
    }

    public function messages()
    {
        return [
            'user_id.required' => 'ユーザーIDは必須です。',
            'user_id.exists' => '指定されたユーザーが存在しません。',
            'title.required' => 'タイトルは必須です。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'author.required' => '著者は必須です。',
            'author.max' => '著者は255文字以内で入力してください。',
            'isbn.required' => 'ISBNは必須です。',
            'isbn.size' => 'ISBNは13桁で入力してください。',
            'isbn.unique' => 'このISBNは既に登録されています。',
            'published_date.required' => '出版日は必須です。',
            'published_date.date' => '出版日は日付形式で入力してください。',
            'image_url.url' => '画像URLはURL形式で入力してください。',
            'genres.required' => 'ジャンルを1つ以上選択してください。',
            'genres.min' => 'ジャンルを1つ以上選択してください。',
            'genres.*.exists' => '指定されたジャンルが存在しません。',
        ];
    }
}
