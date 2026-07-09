<?php
require '_config.php';

require '../../_common-codes/queries/custom_query.php';

/**
 * @return array{array<string, mixed>, non-empty-string|null}
 */
function getCustomRequestData(): array
{
    return [
        [
            'Type'     => 'Query',
            'Number'   => '4242424242424242',
            'Expires'  => '10.2028',
            'Extra'    => [
                'IMECECARDQUERY' => null,
            ],
        ],
        null,
    ];
}
