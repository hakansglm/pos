<?php /** @var array<string, mixed> $response */ ?>
<?php /** @var \Mews\Pos\PosQuery\PosQueryInterface $posQuery */ ?>

<div class="mb-4">
    <?php dump($response); ?>
    <?php if (!$posQuery->isSuccess()): ?>
        <div class="alert alert-danger">
            <strong>Hata:</strong> <?= $response['error_message'] ?? 'Bilinmeyen hata'; ?>
        </div>
    <?php else: ?>
        <?php if (empty($response['installment_rates'])): ?>
            <div class="alert alert-info">Bu kart için taksit oranı bulunamadı.</div>
        <?php else: ?>
            <?php foreach ($response['installment_rates'] as $i => $package): ?>
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
                                <th>Oran (%)</th>
                                <th>Sabit Tutar</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($package['rates'] as $rate): ?>
                                <tr>
                                    <td><?= (int) $rate['installment']; ?></td>
                                    <td><?= number_format((float) $rate['rate'], 4); ?></td>
                                    <td><?= number_format((float) $rate['constant'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
