<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RelationshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        $personId = ['required', 'string', 'max:64'];

        return $this->routeIs('relationships.parent.*')
            ? ['parentId' => $personId, 'childId' => $personId]
            : ['a' => $personId, 'b' => $personId, 'marriageYear' => ['nullable', 'integer', 'between:1000,'.date('Y')]];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            '*.required' => 'Vui lòng chọn đủ hai thành viên.',
            '*.string' => 'Mã thành viên phải là văn bản.',
            '*.max' => 'Mã thành viên không quá 64 ký tự.',
            'marriageYear.*' => 'Năm kết hôn phải là số nguyên từ 1000 đến năm hiện tại.',
        ];
    }
}
