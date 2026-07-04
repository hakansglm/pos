
### Örnek PayTR 3DPay, 3DHost ödeme örneği

PayTR diğer alt yapılardan biraz farklı çalışır.
Kullanıcı **başarılı** 3D otorizasyonu sonrasında websiteye yönlendirildiğinde
(success_url, fail_url) boş veri ile yönlendirilir ve hala ödeme işlemi tamamlan**ma**mış oluyor.
Bu nedenle müşteriye ödemenin başarılı olup olmadığını direk iletemiyoruz.

Sonrasında ödeme işlemi tamamlandığı bildirimi PayTR panelde belirlediğiniz Bildirim URL'a (callback URL)
istek göndererek haber verir. Bu iletişim sizin website sunucunuz ve Paytr sunucusu arasında gerçekleşir.
Callback URL başarılı sonuç geldiğinde, ödemeyi başarılı sayabilirsiniz.

Desteklenen ödeme modelleri:
* MODEL_3D_PAY (Direkt API)
* MODEL_3D_HOST (iFrame API)

## Örnek Kullanım

1. Kütühanede yer alan config dosyasını projenize kopyalayınız.
    ```sh
    $ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
    $ cp ./vendor/mews/pos/config/pos.php ./pos_ayarlar.php
    ```
2. **config.php (Ayar dosyası)**
    ```php
    <?php
    require './vendor/autoload.php';

    session_set_cookie_params([
        'samesite' => 'None',
        'secure'   => true,
        'httponly' => true,
    ]);
    session_start();

    $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
    $transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

    $account = \Mews\Pos\Factory\AccountFactory::createPayTrPosAccount(
        'paytr', //pos config'deki ayarın index name'i
        'merchant_id',
        'merchant_ıd',
        'merchant_key',
        'merchant_salt',
    );

    $eventDispatcher = new Symfony\Component\EventDispatcher\EventDispatcher();

    try {
        $config = require __DIR__.'/pos_test_ayarlar.php';

        $pos = \Mews\Pos\Factory\PosFactory::create($account, $config, $eventDispatcher);
    } catch (\Mews\Pos\Exception\BankNotFoundException | \Mews\Pos\Exception\BankClassNullException $e) {
        var_dump($e);
        exit;
    }
    ```
3. Kullanıcıdan kredi kart bilgileri alma işlemi size bırakılmıştır.
   Örnegi `/examples/_templates/_credit_card_form.php` dosyasında bulabilirsiniz.
   `MODEL_3D_HOST` ödeme için bu aşamaya gerek yok.

4. **form.php** (`MODEL_3D_PAY` ödemede kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod).
    ```php
    <?php

    require 'config.php';

   /**
     * Sipariş bilgileri.
     */
    $order = [
        'id'          => 'BENZERSIZ-SIPARIS-ID',
        'amount'      => 1.01,
        'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY,
        'installment' => 0,
        'success_url' => 'https://example.com/response.php',
        'fail_url'    => 'https://example.com/response.php',
        'lang' => \Mews\Pos\Gateway\PosInterface::LANG_TR,
        'buyer'           => [
            'email'      => 'test@example.com',
            'name'       => 'John Doe',
            'gsm_number' => '05001234567',
        ],
        'billing_address' => [
            'address' => 'Test Sokak No:1 Istanbul',
        ],
        'basket_items' => [ // optional
            [
                'name'       => 'Binocular',
                'item_count' => 1,
                'price'      => 0.3,
            ],
            [
                'name'       => 'Game code',
                'item_count' => 1,
                'price'      => 9.71,
            ],
        ],
    ];

    $_SESSION['order'] = $order;

    // Kredi kartı bilgileri
    $card = null;
    if (\Mews\Pos\PosInterface::MODEL_3D_HOST !== $paymentModel) {
         $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
            $pos,
            $_POST['card_number'],
            $_POST['card_year'],
            $_POST['card_month'],
            $_POST['card_cvv'],
            $_POST['card_name'],
        );
    }

    try {
        $formData = $pos->get3DFormData(
            $order,
            $paymentModel,
            $transactionType,
            $card,
        );
    } catch (\Exception|\Error $e) {
        var_dump($e);
        exit;
    }

   if ($paymentModel === \Mews\Pos\PosInterface::MODEL_3D_HOST) {
        header('Location: '.$formData['gateway']);
        // yada iframe olarak render edebilirsiniz:
        // <script src="https://www.paytr.com/js/iframeResizer.min.js"></script>
        // <iframe src="<?php echo $formData['gateway'];?\>" id="paytriframe" frameborder="0" scrolling="no" style="width: 100%;"></iframe>
        // <script>iFrameResize({},'#paytriframe');</script>
        return;
    }
    if ($paymentModel === \Mews\Pos\PosInterface::MODEL_3D_PAY): ?>
    <!-- Kullanıcı ödeme gateway'ne HTML form ile yölendirilir. -->
    <!-- $formData içeriği HTML forma render ediyoruz ve kullanıcıyı banka gateway'ine yönlendiriyoruz. -->
    <form method="<?= $formData['method']; ?>" action="<?= $formData['gateway']; ?>"  class="redirect-form" role="form">
        <?php foreach ($formData['inputs'] as $key => $value) : ?>
            <input type="hidden" name="<?= $key; ?>" value="<?= $value; ?>">
        <?php endforeach; ?>
        <div class="text-center">Redirecting...</div>
        <hr>
        <div class="form-group text-center">
            <button type="submit" class="btn btn-lg btn-block btn-success">Submit</button>
        </div>
    </form>
    <script>
        // Formu JS ile otomatik submit ederek kullaniciyi banka gatewayine yonlendiriyoruz.
        let redirectForm = document.querySelector('form.redirect-form');
        if (redirectForm) {
            redirectForm.submit();
        }
    </script>
    <?php endif; ?>
    ```
5. **response.php (success_url, fail_url) 3D otorizasyon sonrası çalışacak kod.

    ```php
    <?php
    require 'config.php';
    if ('GET' === $_SERVER['REQUEST_METHOD']) {
        // PayTr başarılı durumda hiç bir veri göndermiyor.
        // Yine de ödeme tamamlanmamış oluyor. Bu yüzden alttaki kodlar çalışmamaı gerekiyor.
        // Burda müşteriye ödemeniz işleme alınmıştır diye haber veriyoruz.
        exit();
        // Bildirim URL'a gelecek sonucu beklememiz gerekiyor.
        // Başarısız durumda ise $_POST verisi gönderir.
    }

    // 3D otorizasyon başarısız olduğunda POST cevabı alıyoruz.
    // Ödeme tamamlanıyor
    $gatewayResponseData = $_POST;
    $order = $_SESSION['order'];
    $card  = null;

    try  {
        $response = $pos->payment(
            $paymentModel,
            $order,
            $transactionType,
            $card,
            $gatewayResponseData
        );

        // Kullanıcıya hata mesajını göstermek isterseniz:
        $errorMessage = $response['md_error_message'];
    } catch (\Error $e) {
        var_dump($e);
        exit;
    }
    ```

6. PayTR panelinde Bildirim URL'i olarak bir sayfa URL'i tanımlayınız.
   O sayfada bu alttaki kod çalışacak:

   **callback.php**
    ```php

    require 'config.php';
    $gatewayResponseData = $_POST;

    try  {
        $response = $pos->payment(
            $paymentModel,
            [],
            $transactionType,
            null,
            $gatewayResponseData
        );

        // Ödeme başarılı mı?
        if ($pos->isSuccess()) {
            $orderId = $response['order_id'];
        } else {
            $errorMessage = $response['md_error_message'] ?? $response['error_message'];
        }
    } catch (\Mews\Pos\Exception\HashMismatchException $e) {
    } catch (\Error $e) {
        var_dump($e);
        exit;
    }

     /**
     * Cevap olarak PayTR'ye "OK" göndermemiz gerekiyor.
     * NOT: PayTR "OK" cevabı alıncaya kadar aynı ödeme işlemi için Bildirim URL birden fazla kez call eder.
     * "OK" baska cevap veri gonderilmeyecek.
     */
    echo 'OK';
    exit;
    ```
