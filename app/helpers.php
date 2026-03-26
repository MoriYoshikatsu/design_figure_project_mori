<?php

use App\Support\RoleHelper;

if (!function_exists('current_role')) {
    function current_role(): ?string
    {
        return RoleHelper::currentRole();
    }
}

if (!function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        return RoleHelper::currentHasRole($roles);
    }
}

if (!function_exists('format_amount')) {
    function format_amount(mixed $value, string $empty = '-', int $maxDecimals = 6): string
    {
        if ($value === null) {
            return $empty;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || $trimmed === '-') {
                return $empty;
            }
            $normalized = str_replace(',', '', $trimmed);
            if (!is_numeric($normalized)) {
                return $trimmed;
            }
            $value = $normalized;
        }

        if (!is_numeric($value)) {
            return $empty;
        }

        $number = (float)$value;
        $decimals = 0;
        if (is_string($value) && preg_match('/^-?\d+(?:\.(\d+))?$/', $value, $matches) === 1) {
            $fraction = isset($matches[1]) ? rtrim($matches[1], '0') : '';
            $decimals = min($maxDecimals, strlen($fraction));
        } else {
            $fixed = rtrim(rtrim(sprintf('%.' . $maxDecimals . 'F', $number), '0'), '.');
            $dotPos = strpos($fixed, '.');
            $decimals = $dotPos === false ? 0 : strlen(substr($fixed, $dotPos + 1));
        }

        return number_format($number, $decimals, '.', ',');
    }
}
