<?php

/** @var \Mews\Pos\PosQuery\PosQueryInterface $posQuery */

use Mews\Pos\PosQuery\PosQueryInterface;

$templateTitle = 'Taksit Fiyatları';

$transaction = PosQueryInterface::QUERY_TYPE_INSTALLMENT_PRICES;

require '../../_templates/_header.php';

// BIN ve tutar GET parametresi ile de geçilebilir: ?bin=54308100&amount=100
$requestData = [
    'amount' => (float) ($_GET['amount'] ?? 100.0),
];
if (get_class($posQuery) === \Mews\Pos\PosQuery\IyzicoPosQuery::class) {
    $requestData['bin'] = (string) ($_GET['bin'] ?? $defaultBin ?? null);
}
?>

    <form method="get" class="mb-4">
        <div class="input-group">
            <input type="text" name="bin" class="form-control" value="<?= $requestData['bin'] ?? ''; ?>"
                   placeholder="BIN numarası (ilk 6-8 rakam)" maxlength="8">
            <button type="submit" class="btn btn-primary">Sorgula</button>
        </div>
    </form>

<?php
try {
    $response = $posQuery->getInstallmentPrices($requestData);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    echo '<div class="alert alert-warning">Bu gateway taksit fiyatı sorgusunu desteklemiyor.</div>';
    require '../../_templates/_footer.php';
    exit;
} catch (Exception $e) {
    dd($e);
}

require '../../_templates/_installment_prices_table.php';
require '../../_templates/_footer.php';
