<?php

/** @var \Mews\Pos\PosQuery\PosQueryInterface $posQuery */

use Mews\Pos\PosQuery\PosQueryInterface;

$templateTitle = 'Taksit Oranları';

$transaction = PosQueryInterface::QUERY_TYPE_INSTALLMENT_RATES;

require '../../_templates/_header.php';

// BIN numarası GET parametresi ile de geçilebilir: ?bin=415956
$bin = (int) ($_GET['bin'] ?? $defaultBin ?? null);

?>

<form method="get" class="mb-4">
    <div class="input-group">
        <input type="text" name="bin" class="form-control" value="<?= $bin; ?>"
               placeholder="BIN numarası (ilk 6-8 rakam)" maxlength="8">
        <button type="submit" class="btn btn-primary">Sorgula</button>
    </div>
</form>

<?php

$requestData = [];
if (get_class($posQuery) === \Mews\Pos\PosQuery\ToslaPosQuery::class) {
    $requestData['bin'] = $bin;
}

try {
    $response = $posQuery->getInstallmentRates($requestData);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    echo '<div class="alert alert-warning">Bu gateway taksit oranı sorgusunu desteklemiyor.</div>';
    require '../../_templates/_footer.php';
    exit;
} catch (Exception $e) {
    dd($e);
}

require '../../_templates/_installment_rates_table.php';
require '../../_templates/_footer.php';
