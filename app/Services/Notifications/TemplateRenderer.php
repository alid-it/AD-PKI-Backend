<?php

namespace App\Services\Notifications;

class TemplateRenderer
{
    public function render(string $template, array $data): string
    {
        foreach ($data as $key => $value) {

            // 🔥 Arrays sauber behandeln
            if (is_array($value)) {
                $value = implode(', ', $value);
            }

            // 🔥 null vermeiden
            if (is_null($value)) {
                $value = '';
            }

            $template = str_replace(
                '{{' . $key . '}}',
                (string) $value,
                $template
            );
        }

        return $template;
    }
}