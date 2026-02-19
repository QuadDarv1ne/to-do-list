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
        // Базовые стандарты
        '@PSR12' => true,
        '@PSR12:risky' => true,

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

        // Классы
        'class_attributes_separation' => [
            'elements' => [
                'const' => 'one',
                'property' => 'one',
                'method' => 'one',
            ],
        ],

        // Контроль потока
        'yoda_style' => [
            'equal' => false,
            'identical' => false,
            'less_and_greater' => false,
        ],
        'simplified_if_return' => true,

        // Операторы
        'binary_operator_spaces' => [
            'default' => 'single_space',
        ],
        'unary_operator_spaces' => true,
        'ternary_operator_spaces' => true,

        // Пробелы и отступы
        'no_whitespace_in_blank_line' => true,
        'no_trailing_whitespace' => true,
        'no_trailing_whitespace_in_comment' => true,
        'blank_line_before_statement' => [
            'statements' => ['return', 'throw', 'try', 'continue', 'break'],
        ],
        'single_blank_line_at_eof' => true,
        'no_multiple_statements_per_line' => true,

        // Импорты
        'ordered_imports' => [
            'sort_algorithm' => 'alpha',
            'imports_order' => ['class', 'function', 'const'],
        ],
        'no_unused_imports' => true,

        // Комментарии
        'no_empty_comment' => true,

        // Типы
        'no_superfluous_phpdoc_tags' => [
            'allow_mixed' => true,
            'allow_unused_params' => false,
        ],
        'phpdoc_types_order' => [
            'null_adjustment' => 'always_last',
            'sort_algorithm' => 'alpha',
        ],
        'phpdoc_separation' => ['groups' => [['param', 'return']]],
    ])
    ->setFinder($finder)
    ->setCacheFile('.php-cs-fixer.cache')
    ->setUsingCache(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
;
