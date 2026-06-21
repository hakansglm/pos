<form method="post" action="<?= $url; ?>" role="form">
    <div class="row">
        <div class="row">
            <div class="mb-3 col-md-4">
                <select name="installment" id="installment" class="form-select input-lg">
                <?php foreach ($installments as $installment => $label) : ?>
                    <option value="<?= $installment; ?>"><?= $label; ?></option>
                <?php endforeach; ?>
                </select>
            </div>
            <?php if ([] !== $pos->getCurrencies()): ?>
                <div class="mb-3 col-md-4">
                    <select name="currency" id="currency" class="form-select input-lg">
                        <?php foreach ($pos->getCurrencies() as $currency) : ?>
                            <option value="<?= $currency; ?>" <?= $currency === \Mews\Pos\PosInterface::CURRENCY_TRY ? 'selected': null ?>><?= $currency; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="mb-3 col-md-4">
                <select name="tx" id="currency" class="form-select input-lg">
                    <option value="<?= \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH; ?>" selected>Ödeme</option>
                    <?php if ($pos::isSupportedTransaction(\Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH, $paymentModel)): ?>
                        <option value="<?= \Mews\Pos\PosInterface::TX_TYPE_PAY_PRE_AUTH; ?>">Ön Provizyon</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mb-3 col-md-4">
                <?php if ([] !== $pos->getLanguages()): ?>
                <select name="lang" id="lang" class="form-select input-lg">
                    <?php foreach ($pos->getLanguages() as $lang) : ?>
                        <option value="<?= $lang; ?>" <?= $lang === \Mews\Pos\PosInterface::LANG_TR ? 'selected': null ?>><?= strtoupper($lang); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
            </div>
            <div class="mb-3 col-md-4">
                <div class="form-group">
                    <label class="form-check-label" for="isRecurringPayment">
                    <input type="checkbox" class="form-check-input" id="isRecurringPayment" name="is_recurring" value="1">
                        Tekrarlanan Ödeme
                    <small class="form-text text-muted">henuz butun gatewayler'e bu ozellik destegi eklenmedi.</small>
                    </label>
                </div>
            </div>
            <div class="mb-3 col-xs-12">
                <div class="form-check form-check-inline">
                    <input type="radio" class="form-check-input" name="payment_flow_type" value="by_redirection"
                           checked>
                    <label class="form-check-label">Redirektli ödeme</label>
                </div>
                <div class="form-check form-check-inline">
                    <input type="radio" class="form-check-input" name="payment_flow_type" value="by_iframe">
                    <label class="form-check-label">Modal box'da ödeme</label>
                </div>
                <div class="form-check form-check-inline">
                    <input type="radio" class="form-check-input" name="payment_flow_type" value="by_popup_window">
                    <label class="form-check-label">Popup Windowda ödeme</label>
                </div>
            </div>
        </div>
        <hr>
        <div class="mb-3 text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Payment</button>
        </div>
</form>
