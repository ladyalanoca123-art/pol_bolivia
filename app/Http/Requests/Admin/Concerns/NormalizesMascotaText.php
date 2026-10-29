<?php

namespace App\Http\Requests\Admin\Concerns;

trait NormalizesMascotaText
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->normalizeLabel($this->input('nombre')),
            'raza' => $this->normalizeLabel($this->input('raza')),
            'color' => $this->normalizeLabel($this->input('color')),
            'descripcion' => $this->normalizeDescription($this->input('descripcion')),
        ]);
    }

    private function normalizeLabel(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/\s+/u', ' ', trim($value));

        if ($value === '') {
            return $value;
        }

        $value = mb_strtolower($value, 'UTF-8');

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($value, 1, null, 'UTF-8');
    }

    private function normalizeDescription(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        return mb_strtoupper(mb_substr($value, 0, 1, 'UTF-8'), 'UTF-8')
            .mb_substr($value, 1, null, 'UTF-8');
    }
}
