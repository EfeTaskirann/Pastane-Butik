<?php

/**
 * PHP-CS-Fixer konfigürasyonu — PSR-12 + proje-özel kurallar.
 *
 * Kullanım:
 *   composer require --dev friendsofphp/php-cs-fixer
 *   vendor/bin/php-cs-fixer fix                         # tüm dosyaları düzelt
 *   vendor/bin/php-cs-fixer fix --dry-run --diff        # sadece rapor
 *   vendor/bin/php-cs-fixer fix src/Services/UrunService.php   # tek dosya
 *
 * Hız için .php-cs-fixer.cache kullanıyoruz — CI'da cache path environment'a göre değişebilir.
 */

$finder = (new PhpCsFixer\Finder())
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/includes',
        __DIR__ . '/admin',
        __DIR__ . '/api',
        __DIR__ . '/tests',
        __DIR__ . '/database',
        __DIR__ . '/config',
        __DIR__ . '/bin',
    ])
    ->notPath([
        'vendor',
        'node_modules',
        'storage',
        'public/build',
        'dist',
        'coverage',
    ])
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

$config = new PhpCsFixer\Config();

return $config
    ->setRiskyAllowed(true)
    ->setLineEnding("\n")
    ->setIndent('    ')
    ->setCacheFile(__DIR__ . '/.php-cs-fixer.cache')
    ->setRules([
        // Temel PSR standartları
        '@PSR12'                       => true,
        '@PSR12:risky'                 => true,
        '@PhpCsFixer'                  => true,
        '@PhpCsFixer:risky'            => false, // agresif risky kuralları kapalı

        // ------- Import / use statement düzeni -------
        'ordered_imports'              => [
            'sort_algorithm' => 'alpha',
            'imports_order'  => ['class', 'function', 'const'],
        ],
        'no_unused_imports'            => true,
        'single_import_per_statement'  => true,
        'group_import'                 => false,
        'no_leading_import_slash'      => true,

        // ------- Array syntax -------
        'array_syntax'                 => ['syntax' => 'short'],
        'trim_array_spaces'            => true,
        'whitespace_after_comma_in_array' => true,
        'no_whitespace_before_comma_in_array' => true,

        // ------- String / quote tercihleri -------
        // TR projemizde çoğunluk tek tırnak — migration maliyeti düşük
        'single_quote'                 => true,
        'explicit_string_variable'     => true,

        // ------- Method / function imza -------
        'method_argument_space'        => [
            'on_multiline'                     => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => false,
        ],
        'return_type_declaration'      => ['space_before' => 'none'],
        'no_superfluous_phpdoc_tags'   => [
            'allow_mixed' => true,
        ],

        // ------- Binary operator / spacing -------
        'binary_operator_spaces'       => [
            'default'   => 'single_space',
            'operators' => [
                '=>' => null,
                '='  => null,
            ],
        ],
        'concat_space'                 => ['spacing' => 'one'],
        'unary_operator_spaces'        => true,
        'cast_spaces'                  => ['space' => 'single'],

        // ------- Control flow -------
        'yoda_style'                   => false, // $foo === 'x' tercih (Yoda değil)
        'no_useless_else'              => true,
        'no_useless_return'            => true,
        'simplified_null_return'       => true,
        'no_alternative_syntax'        => false, // template view'larda : endif; kullanıyoruz
        'no_empty_statement'           => true,
        'no_empty_phpdoc'              => true,
        'no_empty_comment'             => true,

        // ------- PHPDoc -------
        'phpdoc_align'                 => ['align' => 'left'],
        'phpdoc_indent'                => true,
        'phpdoc_separation'            => true,
        'phpdoc_scalar'                => true,
        'phpdoc_trim'                  => true,
        'phpdoc_types'                 => true,
        'phpdoc_var_without_name'      => true,
        'phpdoc_order'                 => true,
        'phpdoc_line_span'             => [
            'property' => 'single',
            'method'   => 'multi',
            'const'    => 'single',
        ],

        // ------- Strict type / modernization (opt-in) -------
        'declare_strict_types'         => false, // legacy kod çok, aşamalı geçiş
        'modernize_types_casting'      => true,
        'void_return'                  => false,
        'native_function_invocation'   => false,
        'nullable_type_declaration_for_default_null_value' => true,

        // ------- Comment / whitespace -------
        'line_ending'                  => true,
        'no_trailing_whitespace'       => true,
        'no_trailing_whitespace_in_comment' => true,
        'single_blank_line_at_eof'     => true,
        'no_extra_blank_lines'         => [
            'tokens' => [
                'extra',
                'throw',
                'use',
                'use_trait',
                'curly_brace_block',
                'parenthesis_brace_block',
                'return',
                'square_brace_block',
            ],
        ],
        'blank_line_after_namespace'   => true,
        'blank_line_after_opening_tag' => true,
        'blank_line_before_statement'  => [
            'statements' => ['return', 'throw', 'try'],
        ],

        // ------- Naming -------
        'class_attributes_separation'  => [
            'elements' => [
                'method'   => 'one',
                'property' => 'one',
                'const'    => 'only_if_meta',
            ],
        ],
        'ordered_class_elements'       => [
            'order' => [
                'use_trait',
                'case',
                'constant_public',
                'constant_protected',
                'constant_private',
                'property_public',
                'property_protected',
                'property_private',
                'construct',
                'destruct',
                'magic',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],

        // ------- TR proje özel: naming convention snake_case field'ları için -------
        // camel_case strict değil — DB kolonları snake_case (isim, fiyat, kategori_id)
        'php_unit_method_casing'       => ['case' => 'camel_case'],
    ])
    ->setFinder($finder);
