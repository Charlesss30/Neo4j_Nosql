<?php

namespace App\Http\Requests;

class CreatePersonRequest extends PersonRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [...parent::rules(),
            'relation_type' => ['nullable', 'required_with:relative_id', 'in:child,parent,spouse'],
            'relative_id' => ['nullable', 'required_with:relation_type', 'string', 'max:64'],
            'second_parent_id' => ['nullable', 'string', 'max:64', 'different:relative_id', 'prohibited_unless:relation_type,child'],
            'marriageYear' => ['nullable', 'integer', 'between:1000,'.date('Y'), 'prohibited_unless:relation_type,spouse'],
            'return_to' => ['nullable', 'in:person,tree'],
            'tree_root' => ['nullable', 'string', 'max:64'],
            'tree_all' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [...parent::messages(),
            'relation_type.*' => 'Hãy chọn quan hệ của người mới với thành viên đã có.',
            'relative_id.*' => 'Hãy chọn mã thành viên đã có để gắn quan hệ.',
            'second_parent_id.*' => 'Cha/mẹ thứ hai chỉ dùng khi thêm con và phải khác người thứ nhất.',
            'marriageYear.*' => 'Năm kết hôn chỉ dùng cho vợ/chồng, phải là số nguyên từ 1000 đến năm hiện tại.',
            'return_to.*' => 'Trang quay lại không hợp lệ.',
            'tree_root.*' => 'Mã gốc của cây không hợp lệ.',
            'tree_all.*' => 'Chế độ cây không hợp lệ.',
        ];
    }

    /** @return array<string, mixed>|null */
    public function relationshipData(): ?array
    {
        $data = $this->validated();
        if (empty($data['relation_type'])) {
            return null;
        }

        return [
            'relation_type' => $data['relation_type'], 'relative_id' => $data['relative_id'],
            'second_parent_id' => $data['second_parent_id'] ?? null,
            'marriageYear' => isset($data['marriageYear']) ? (int) $data['marriageYear'] : null,
        ];
    }
}
