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
            'TransactionType' => 'CampaignSearch',
            'TransactionId'   => date('Ymd').strtoupper(substr(uniqid(sha1((string)time()), true), 0, 4)),
        ],
        null,
    ];
}
