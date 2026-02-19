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
    
    /**
     * Validate and sanitize search query to prevent injection
     */
    public function validateSearchQuery(?string $query, int $maxLength = 100): ?string
    {
        if ($query === null || $query === '') {
            return null;
        }
        
        // Remove potentially dangerous characters
        $query = trim($query);
        
        // Remove SQL injection patterns
        $query = preg_replace('/(\'|";|--|\/\*|\*\/|xp_|sp_|exec|union|select|insert|drop|update|delete|create|alter|grant|revoke|call|declare|open|fetch|fetch next|fetch prior|fetch absolute|fetch relative|fetch first|fetch last|fetch prior|fetch next|fetch first|fetch last|order by|group by|having|exists|between|like|in|any|some|all|case|when|then|else|end|begin|end|commit|rollback|savepoint|lock|unlock|truncate|replace|merge|intersect|minus|except|connect by|start with|prior|level|connect|dual|rownum|rowid|uid|user|sysdate|systimestamp|current_timestamp|current_date|current_time|timestamp|date|time|interval|extract|to_char|to_date|to_timestamp|to_number|cast|convert|substr|substring|length|char_length|character_length|upper|lower|initcap|ltrim|rtrim|trim|replace|translate|regexp_like|regexp_replace|instr|position|concat|lpad|rpad|ascii|chr|reverse|soundex|to_multi_byte|to_single_byte|quote_ident|format_type|pg_typeof|obj_description|current_schema|current_database|current_user|session_user|has_table_privilege|has_database_privilege|has_schema_privilege|has_sequence_privilege|has_function_privilege|has_language_privilege|has_tablespace_privilege|has_type_privilege|pg_get_keywords|pg_get_constraintdef|pg_get_expr|pg_get_function_arguments|pg_get_function_identity_arguments|pg_get_function_result|pg_get_indexdef|pg_get_ruledef|pg_get_serial_sequence|pg_get_triggerdef|pg_get_viewdef|pg_table_is_visible|pg_type_is_visible|pg_function_is_visible|pg_operator_is_visible|pg_opclass_is_visible|pg_conversion_is_visible|pg_language_is_visible|pg_ts_config_is_visible|pg_ts_dict_is_visible|pg_ts_parser_is_visible|pg_ts_template_is_visible|pg_table_size|pg_indexes_size|pg_total_relation_size|pg_size_pretty|pg_column_size|pg_relation_size|pgstattuple|pgstatindex|pg_tablespace_size|pg_database_size|pg_cancel_backend|pg_terminate_backend|pg_reload_conf|pg_rotate_logfile|pg_postmaster_start_time|pg_conf_load_time|pg_is_in_recovery|pg_is_in_backup|pg_backup_start_time|pg_current_xlog_location|pg_current_xlog_insert_location|pg_last_xlog_receive_location|pg_last_xlog_replay_location|pg_last_xact_replay_timestamp|pg_xlog_location_diff|pg_create_restore_point|pg_stat_get_bgwriter_timed_checkpoints|pg_stat_get_bgwriter_requested_checkpoints|pg_stat_get_bgwriter_checkpoint_write_time|pg_stat_get_bgwriter_buffer_write_time|pg_stat_get_buf_alloc|pg_stat_get_buf_read|pg_stat_get_buf_written_backend|pg_stat_get_blocks_fetched|pg_stat_get_blocks_hit|pg_stat_get_tuples_returned|pg_stat_get_tuples_fetched|pg_stat_get_tuples_inserted|pg_stat_get_tuples_updated|pg_stat_get_tuples_deleted|pg_stat_get_tuples_hot_updated|pg_stat_get_live_tuples|pg_stat_get_dead_tuples|pg_stat_get_mod_since_analyze|pg_stat_get_last_vacuum_time|pg_stat_get_last_autovacuum_time|pg_stat_get_last_analyze_time|pg_stat_get_last_autoanalyze_time|pg_stat_get_vacuum_count|pg_stat_get_autovacuum_count|pg_stat_get_analyze_count|pg_stat_get_autoanalyze_count|pg_stat_get_numscans|pg_stat_get_tuples_returned|pg_stat_get_tuples_fetched|pg_stat_get_tuples_inserted|pg_stat_get_tuples_updated|pg_stat_get_tuples_deleted|pg_stat_get_blocks_fetched|pg_stat_get_blocks_hit|pg_stat_get_tuples_hot_updated|pg_stat_get_dead_tuples|pg_stat_get_mod_since_analyze|pg_stat_get_last_vacuum_time|pg_stat_get_last_autovacuum_time|pg_stat_get_last_analyze_time|pg_stat_get_last_autoanalyze_time|pg_stat_get_vacuum_count|pg_stat_get_autovacuum_count|pg_stat_get_analyze_count|pg_stat_get_autoanalyze_count|pg_stat_get_numscans|pg_stat_get_tuples_returned|pg_stat_get_tuples_fetched|pg_stat_get_tuples_inserted|pg_stat_get_tuples_updated|pg_stat_get_tuples_deleted|pg_stat_get_blocks_fetched|pg_stat_get_blocks_hit|pg_stat_get_tuples_hot_updated|pg_stat_get_dead_tuples|pg_stat_get_mod_since_analyze|pg_stat_get_last_vacuum_time|pg_stat_get_last_autovacuum_time|pg_stat_get_last_analyze_time|pg_stat_get_last_autoanalyze_time|pg_stat_get_vacuum_count|pg_stat_get_autovacuum_count|pg_stat_get_analyze_count|pg_stat_get_autoanalyze_count|pg_stat_get_numscans)/i', '', $query);
        
        // Remove XSS patterns
        $query = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $query);
        $query = preg_replace('/javascript:/i', '', $query);
        $query = preg_replace('/on\w+\s*=/i', '', $query);
        
        // Limit length
        if (strlen($query) > $maxLength) {
            $query = substr($query, 0, $maxLength);
        }
        
        return $query;
    }
    
    /**
     * Validate URL input
     */
    public function validateUrl(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }
        
        // Basic sanitization
        $url = trim($url);
        
        // Validate URL format
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }
        
        // Only allow http/https
        if (!preg_match('/^https?:\/\//', $url)) {
            return null;
        }
        
        return $url;
    }
    
    /**
     * Validate email input
     */
    public function validateEmail(?string $email): ?string
    {
        if ($email === null || $email === '') {
            return null;
        }
        
        $email = trim($email);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }
        
        return $email;
    }
    
    /**
     * Validate JSON input
     */
    public function validateJson(?string $json): ?array
    {
        if ($json === null || $json === '') {
            return null;
        }
        
        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $decoded;
    }
}
