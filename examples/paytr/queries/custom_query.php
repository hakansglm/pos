<?php

require '../../_common-codes/queries/custom_query.php';

function getCustomRequestData(): array
{
    // Örnek: işlem sorgulama isteği.
    // Taksit oranları sorgusu için installment_rates.php sayfasını kullanın.
    return [
        [
            'request_id' => date('Ymd').strtoupper(substr(uniqid(sha1(time())), 0, 10)),
        ],
        null,
    ];
}
