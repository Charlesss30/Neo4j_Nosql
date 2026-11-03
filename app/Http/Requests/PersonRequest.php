<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $currentYear = date('Y');

        return [
            'name' => ['required', 'string', 'max:100'],
            'gender' => ['required', 'in:M,F'],
            'birthYear' => ['required', 'integer', 'between:1000,'.$currentYear],
            'deathYear' => ['nullable', 'integer', 'gte:birthYear', 'max:'.$currentYear],
            'birthPlace' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập họ và tên.',
            'name.max' => 'Họ tên không quá 100 ký tự.',
            'gender.*' => 'Giới tính phải là Nam hoặc Nữ.',
            'birthYear.*' => 'Năm sinh phải là số nguyên từ 1000 đến năm hiện tại.',
            'deathYear.*' => 'Năm mất phải là số nguyên, không trước năm sinh và không sau năm hiện tại.',
            'birthPlace.*' => 'Nơi sinh không quá 100 ký tự.',
            'note.*' => 'Ghi chú không quá 500 ký tự.',
        ];
    }

    /** @return array{name: string, gender: string, birthYear: int, deathYear: ?int, birthPlace: ?string, note: ?string} */
    public function personData(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'gender' => $data['gender'],
            'birthYear' => (int) $data['birthYear'],
            'deathYear' => isset($data['deathYear']) ? (int) $data['deathYear'] : null,
            'birthPlace' => $data['birthPlace'] ?? null,
            'note' => $data['note'] ?? null,
        ];
    }
}
