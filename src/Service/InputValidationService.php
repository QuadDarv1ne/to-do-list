<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class InputValidationService
{
    /**
     * Validate and sanitize string input
     */
    public function validateString(?string $value, int $maxLength = 255, bool $allowEmpty = true): ?string
    {
        if ($value === null || $value === '') {
            return $allowEmpty ? null : '';
        }

        $value = trim($value);
        $value = strip_tags($value);
        
        if (strlen($value) > $maxLength) {
            $value = substr($value, 0, $maxLength);
        }

        return $value;
    }

    /**
     * Validate integer input
     */
    public function validateInt($value, int $min = null, int $max = null): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($intValue === false) {
            return null;
        }

        if ($min !== null && $intValue < $min) {
            return $min;
        }

        if ($max !== null && $intValue > $max) {
            return $max;
        }

        return $intValue;
    }

    /**
     * Validate boolean input
     */
    public function validateBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Validate date input
     */
    public function validateDate(?string $value, string $format = 'Y-m-d'): ?\DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        $date = \DateTime::createFromFormat($format, $value);
        
        if ($date === false) {
            return null;
        }

        return $date;
    }

    /**
     * Validate enum value
     */
    public function validateEnum(?string $value, array $allowedValues): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return in_array($value, $allowedValues, true) ? $value : null;
    }

    /**
     * Validate array of integers
     */
    public function validateIntArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_filter(array_map(function($item) {
            return $this->validateInt($item);
        }, $value), function($item) {
            return $item !== null;
        });
    }

    /**
     * Sanitize SQL table name (alphanumeric and underscore only)
     */
    public function sanitizeTableName(string $tableName): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $tableName);
    }

    /**
     * Validate pagination parameters
     */
    public function validatePagination(Request $request, int $defaultLimit = 10, int $maxLimit = 100): array
    {
        $page = $this->validateInt($request->query->get('page', 1), 1);
        $limit = $this->validateInt($request->query->get('limit', $defaultLimit), 1, $maxLimit);

        return [
            'page' => $page ?? 1,
            'limit' => $limit ?? $defaultLimit,
            'offset' => (($page ?? 1) - 1) * ($limit ?? $defaultLimit),
        ];
    }

    /**
     * Validate sort parameters
     */
    public function validateSort(Request $request, array $allowedFields, string $defaultField = 'id'): array
    {
        $sort = $request->query->get('sort', $defaultField);
        $direction = strtoupper($request->query->get('direction', 'DESC'));

        $validSort = in_array($sort, $allowedFields, true) ? $sort : $defaultField;
        $validDirection = in_array($direction, ['ASC', 'DESC'], true) ? $direction : 'DESC';

        return [
            'sort' => $validSort,
            'direction' => $validDirection,
        ];
    }
}
