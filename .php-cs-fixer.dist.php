<?php

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude('var')
    ->exclude('vendor')
    ->exclude('examples')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        'no_unused_imports' => true,
        'full_opening_tag' => true,
        'phpdoc_align' => ['align' => 'vertical'],
        'no_extra_blank_lines' => ['tokens' => ['curly_brace_block']],
        'blank_line_before_statement' => ['statements' => ['return']],
        'trailing_comma_in_multiline' => ['elements' => ['arrays']],
        'phpdoc_separation' => true,
        'ordered_class_elements' => [
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
                'phpunit',
                'method_public',
                'method_protected',
                'method_private',
            ],
        ],
    ])
    ->setFinder($finder)
    ->setCacheFile(__DIR__.'/var/.php-cs-fixer.cache')
;
