<?php /** @var array<string, mixed> $response */ ?>
<?php /** @var \Mews\Pos\PosQuery\PosQueryInterface $posQuery */ ?>

<div class="mb-4">
    <?php dump($response); ?>
    <?php if (!$posQuery->isSuccess()): ?>
        <div class="alert alert-danger">
            <strong>Hata:</strong> <?= $response['error_message'] ?? 'Bilinmeyen hata'; ?>
        </div>
    <?php elseif (empty($response['installment_prices'])): ?>
        <div class="alert alert-info">Taksit fiyatı bilgisi bulunamadı.</div>
    <?php else: ?>
        <?php foreach ($response['installment_prices'] as $detail): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Banka Kodu</dt>
                        <dd class="col-sm-9"><?= $package['bank_code'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">Banka Adı</dt>
                        <dd class="col-sm-9"><?= $package['bank_name'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">BIN</dt>
                        <dd class="col-sm-9"><?= $package['card_prefix'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">Kart Adı</dt>
                        <dd class="col-sm-9"><?= $package['card_name'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">Kart Tipi</dt>
                        <dd class="col-sm-9"><?= $package['card_type'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">Kart Sınıfı</dt>
                        <dd class="col-sm-9"><?= $package['card_type'] ?? '-'; ?></dd>
                        <dt class="col-sm-3">Kart Ailesi</dt>
                        <dd class="col-sm-9"><?= $package['card_family'] ?? '-'; ?></dd>
                    </dl>
                </div>
                <div class="card-body p-0">
                    <table class="table table-striped table-bordered mb-0">
                        <thead class="table-dark">
                        <tr>
                            <th>Taksit</th>
                            <th>Aylık Ödeme</th>
                            <th>Toplam Tutar</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($detail['prices'] as $price): ?>
                            <tr>
                                <td><?= (int) $price['installment'] === 1 ? 'Peşin' : (int) $price['installment'].' Taksit'; ?></td>
                                <td><?= number_format((float) $price['installment_price'], 2); ?> TL</td>
                                <td><?= $price['total_price'] !== null ? number_format((float) $price['total_price'], 2).' TL' : '-'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
