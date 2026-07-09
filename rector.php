<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/config',
        __DIR__.'/src',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_80,
    ])
    ->withSkip([
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector::class,
        \Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector::class,
        \Rector\Php74\Rector\Property\RestoreDefaultNullToNullableTypePropertyRector::class,

        \Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector::class,
        \Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector::class,
        \Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector::class,
        \Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector::class,
        \Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector::class,

        \Rector\TypeDeclaration\Rector\ClassMethod\NumericReturnTypeFromStrictScalarReturnsRector::class,
        \Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector::class,
        \Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictParamRector::class,
        \Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector::class,

        \Rector\CodeQuality\Rector\CallLike\AddNameToBooleanArgumentRector::class,
        \Rector\CodeQuality\Rector\CallLike\AddNameToNullArgumentRector::class,
        \Rector\DeadCode\Rector\MethodCall\RemoveNullNamedArgOnNullDefaultParamRector::class,
    ])
    ->withPreparedSets(
        true,
        true,
        true,
        true,
        false,
        true,
        true,
        true,
        true,
    );
