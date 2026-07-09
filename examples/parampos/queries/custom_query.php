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
            // API hesap bilgileri kütüphane tarafından otomatik eklenir.
            'TP_Ozel_Oran_Liste' => [
                '@xmlns' => 'https://turkpos.com.tr/',
            ],
        ],
        null,
    ];
}
