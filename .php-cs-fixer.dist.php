<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/config')
    ->exclude(['var', 'vendor', 'node_modules', 'public'])
    ->name('*.php')
;

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
        // Код стиль
        '@PSR12' => true,
        '@PSR12:risky' => true,
        '@Symfony' => true,
        '@Symfony:risky' => true,

        // PHP 8.4+ особенности
        'declare_strict_types' => true,
        'strict_param' => true,

        // Массивы
        'array_syntax' => ['syntax' => 'short'],
        'array_indentation' => true,
        'trailing_comma_in_multiline' => [
            'elements' => ['arrays', 'arguments', 'parameters'],
        ],

        // Строки
        'single_quote' => true,
        'string_implicit_backslashes' => [
            'single_quoted' => 'escape',
            'double_quoted' => 'escape',
        ],

        // Функции
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope' => 'namespaced',
            'strict' => true,
        ],
        'lambda_not_used_import' => true,

        // Классы
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
            ],
        ],
        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
            'single_item_single_line' => true,
            'single_line' => false,
        ],
        'ordered_class_elements' => [
            'order' => [
                'use_trait',
                'case',
                'constant',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public_static',
                'property_protected_static',
                'property_private_static',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'phpunit',
                'method_public_static',
                'method_protected_static',
                'method_private_static',
                'method_public',
                'method_protected',
                'method_private',
            ],
            'sort_algorithm' => 'none',
        ],

        // Контроль потока
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'simplified_if_return' => true,
        'simplified_null_return' => true,

        // Операторы
        'binary_operator_spaces' => [
            'default' => 'single_space',
            'operators' => ['=>' => 'align_single_space_by_scope'],
        ],
        'unary_operator_spaces' => true,
        'ternary_operator_spaces' => true,
        'standardize_not_equals' => true,

        // Пробелы и отступы
        'no_whitespace_in_blank_line' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'continue', 'break'],
        ],
        'blank_line_after_opening_tag' => true,
        'blank_line_after_namespace' => true,
        'single_blank_line_at_eof' => true,
        'no_multiple_statements_per_line' => true,

        // Импорты
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,
        'global_namespace_import' => [
            'import_classes' => true,
            'import_constants' => true,
            'import_functions' => true,
        ],

        // Комментарии
        'header_comment' => [
            'header' => '',
            'location' => 'after_open',
        ],
        'no_empty_comment' => true,
        'single_line_comment_style' => true,

        // Атрибуты PHP 8
        'attribute_empty_parentheses' => false,

        // Типы
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'allow_unused_params' => false,
            'remove_inheritdoc' => false,
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'phpdoc_align' => [
            'align' => 'vertical',
            'tags' => [
                'param',
                'return',
                'throws',
                'type',
                'var',
            ],
        ],
        'phpdoc_line_span' => [
            'property' => 'single',
            'method' => null,
            'const' => 'single',
        ],

        // Контроль версий
        'no_blank_lines_after_phpdoc' => true,
        'phpdoc_separation' => [
            'groups' => [
                ['author', 'copyright', 'license'],
                ['category', 'package', 'subpackage'],
                ['property', 'property-read', 'property-write'],
                ['param', 'return'],
                ['throws', 'deprecated', 'see', 'since', 'todo'],
            ],
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache')
    ->setUsingCache(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
;
