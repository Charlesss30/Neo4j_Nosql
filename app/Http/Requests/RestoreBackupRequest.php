<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app()->environment('local');
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'backup' => ['required', 'file', 'extensions:json', 'max:10240'],
            'confirm_restore' => ['accepted'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'backup.required' => 'Vui lòng chọn tệp sao lưu gia phả.',
            'backup.file' => 'Tệp tải lên không hợp lệ.',
            'backup.extensions' => 'Hãy chọn tệp sao lưu có đuôi .json.',
            'backup.max' => 'Tệp sao lưu không được lớn hơn 10 MB.',
            'confirm_restore.accepted' => 'Vui lòng xác nhận thay thế dữ liệu hiện tại bằng bản sao lưu.',
        ];
    }
}
