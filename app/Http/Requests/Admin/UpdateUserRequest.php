<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('update', $target) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombres' => $this->normalizeName($this->input('nombres')),
            'apellidos' => $this->normalizeName($this->input('apellidos')),
            'email' => is_string($this->input('email')) ? trim($this->input('email')) : $this->input('email'),
            'telefono' => is_string($this->input('telefono')) ? trim($this->input('telefono')) : $this->input('telefono'),
        ]);
    }

    public function rules(): array
    {
        $user = $this->route('user');

        return [
            'nombres' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?: [\pL]+)*$/u'],
            'apellidos' => ['required', 'string', 'max:100', 'regex:/^[\pL]+(?: [\pL]+)*$/u'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('usuarios', 'email')->ignore($user)],
            'telefono' => ['nullable', 'regex:/^7\d{7}$/'],
        ];
    }

    private function normalizeName(mixed $value): mixed
    {
        return is_string($value) ? preg_replace('/\s+/', ' ', trim($value)) : $value;
    }
}
