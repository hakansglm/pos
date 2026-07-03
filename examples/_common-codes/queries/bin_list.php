<?php

use Mews\Pos\PosQuery\PosQueryInterface;

$templateTitle = 'BIN Sorgusu';

require '_config.php';
$transaction = PosQueryInterface::QUERY_TYPE_BIN_LIST;

require '../../_templates/_header.php';

// BIN numarası GET parametresi ile geçilebilir: ?bin=415956 (opsiyonel)
$bin = (string) ($_GET['bin'] ?? $defaultBin ?? null);

?>

<form method="get" class="mb-4">
    <div class="input-group">
        <input type="text" name="bin" class="form-control" value="<?= $bin; ?>"
               placeholder="BIN numarası (opsiyonel, ilk 6-8 rakam)" maxlength="8">
        <button type="submit" class="btn btn-primary">Sorgula</button>
    </div>
</form>

<?php

$params = ['ip' => $ip];
if (null !== $bin) {
    $params['bin'] = $bin;
}
if (get_class($posQuery) === \Mews\Pos\PosQuery\GarantiPosQuery::class) {
    // ekstra optional filtre
    $params['card_class'] = \Mews\Pos\Model\Card\CreditCardInterface::CARD_CLASS_CREDIT;
}
try {
    $response = $posQuery->getBinList($params);
} catch (\Mews\Pos\Exception\UnsupportedTransactionTypeException $e) {
    echo '<div class="alert alert-warning">Bu gateway BIN sorgusu desteklemiyor.</div>';
    require '../../_templates/_footer.php';
    exit;
} catch (Exception $e) {
    dd($e);
}

?>

<?php if (!$posQuery->isSuccess()): ?>
    <div class="alert alert-danger">
        <strong>Hata:</strong> <?= $response['error_message'] ?? 'Bilinmeyen hata'; ?>
    </div>
<?php elseif ([] === $response['bin_list']): ?>
    <div class="alert alert-warning">
        Kayıt bulunamadı.
    </div>
<?php else: ?>
    <div class="alert alert-success">
        Toplam <strong><?= count($response['bin_list']); ?></strong> BIN kaydı döndü.
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>BIN</th>
                    <th>Banka Kodu</th>
                    <th>Banka Adı</th>
                    <th>Kart Tipi</th>
                    <th>Kart Sınıfı</th>
                    <th>Kart Ailesi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($response['bin_list'] as $entry): ?>
                    <tr>
                        <td><code><?= $entry['bin'] ?? '-'; ?></code></td>
                        <td><?= $entry['bank_code'] ?? '-'; ?></td>
                        <td><?= $entry['bank_name'] ?? '-'; ?></td>
                        <td><?= $entry['card_type'] ?? '-'; ?></td>
                        <td><?= $entry['card_class'] ?? '-'; ?></td>
                        <td><?= $entry['card_family'] ?? '-'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require '../../_templates/_footer.php'; ?>
