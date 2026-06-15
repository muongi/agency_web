<?php

namespace App\Services\Cbs;

use InvalidArgumentException;

class JsonPayloadMapper
{
    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @param  array<string, mixed>  $sourceData
     * @return array<string, mixed>
     */
    public function mapRequest(array $mappings, array $sourceData): array
    {
        $payload = [];

        foreach ($mappings as $mapping) {
            $source = $mapping['source'] ?? null;
            $target = $mapping['target'] ?? null;
            $required = (bool) ($mapping['required'] ?? false);
            $default = $mapping['default'] ?? null;
            $transform = $mapping['transform'] ?? null;

            if (! $target) {
                continue;
            }

            $value = null;
            if ($source && str_starts_with($source, 'literal.')) {
                $value = $default;
            } elseif ($source && array_key_exists($source, $sourceData)) {
                $value = $sourceData[$source];
            } elseif ($default !== null && $default !== '') {
                $value = $default;
            }

            if (($value === null || $value === '') && $required) {
                throw new InvalidArgumentException("Required mapping source '{$source}' is missing for target '{$target}'.");
            }

            if ($value === null || $value === '') {
                continue;
            }

            $value = $this->applyTransform($value, $transform);
            $this->setNestedValue($payload, $target, $value);
        }

        return $payload;
    }

    /**
     * @param  array<int, array<string, mixed>>  $mappings
     * @param  array<string, mixed>  $responseData
     * @return array<string, mixed>
     */
    public function mapResponse(array $mappings, array $responseData): array
    {
        $result = [];

        foreach ($mappings as $mapping) {
            $source = $mapping['source'] ?? $mapping['cbs_path'] ?? null;
            $target = $mapping['target'] ?? $mapping['internal_key'] ?? null;

            if (! $source || ! $target) {
                continue;
            }

            $value = $this->getNestedValue($responseData, $source);
            if ($value !== null) {
                $result[$target] = $value;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function getNestedValue(array $data, string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private function applyTransform(mixed $value, ?string $transform): mixed
    {
        if (! $transform || $transform === 'none') {
            return $value;
        }

        if (str_starts_with($transform, 'date:')) {
            $format = substr($transform, 5);

            return date($format, is_numeric($value) ? (int) $value : strtotime((string) $value));
        }

        return match ($transform) {
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'float' => (float) $value,
            'integer' => (int) $value,
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function setNestedValue(array &$payload, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $current = &$payload;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $current[$segment] = $value;
            } else {
                if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }
}
