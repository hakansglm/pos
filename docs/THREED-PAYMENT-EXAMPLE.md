
### Örnek 3DSecure, 3DPay, 3DHost ödeme kodu

1. 3DSecure, 3DPay ödemeniz gereken kodlar arasında tek fark `$paymentModel` değeridir.
3DHost'da icin kullanıcıdan kart bilgileri alma aşması yok, kart bilgileri banka tarafından alınır.
Kütüphane içersinde ödeme modele göre farklı kodlar çalışacak.
    ```php
    $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
    // veya
    // $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_PAY;
    // $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_HOST;
    ```

2. Kütühanede yer alan config dosyasını projenize kopyalayınız.
    ```sh
    $ cp ./vendor/mews/pos/config/pos_test.php ./pos_test_ayarlar.php
    $ cp ./vendor/mews/pos/config/pos.php ./pos_ayarlar.php
    ```
3. **config.php (Ayar dosyası)**
    ```php
    <?php
    require './vendor/autoload.php';

    // Session için projenizde başka bir tool kullanıyorsanız, aşağıdaki kod yerine onu kullanmaya devam edebiliriniz.
    // Yalnız session cookie'si için samesite, secure ve httponly flagleri aynı şekilde ayarlamanız gerekiyor.
    session_set_cookie_params([
        'samesite' => 'None',
        'secure'   => true,
        'httponly' => true, // Javascriptin session'a erişimini engelliyoruz.
    ]);
    session_start();

    $paymentModel = \Mews\Pos\PosInterface::MODEL_3D_SECURE;
    $transactionType = \Mews\Pos\PosInterface::TX_TYPE_PAY_AUTH;

    // API kullanıcı bilgileri
    // AccountFactory'de kullanılacak method Gateway'e göre değişir!!!
    // /examples klasörde farklı gatewayler için örnek kullanımı ve kodları bulabilirsiniz.
    //  Config ayar örnekleri _config.php dosyasında yer alır (örn: /examples/akbankpos/3d/_config.php).
    $account = \Mews\Pos\Factory\AccountFactory::createAssecoPosAccount(
        'akbank', //pos config'deki ayarın index name'i
        'yourClientID',
        'yourKullaniciAdi',
        'yourSifre',
        'yourStoreKey'
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
4. Kullanıcıdan kredi kart bilgileri alma işlemi size bırakılmıştır.
   Örnegi `/examples/_templates/_credit_card_form.php` dosyasında bulanilirsiniz.
   3DHost ödeme için bu aşamaya gerek yok.
5. **form.php (3DSecure ve 3DPay odemede kullanıcıdan kredi kart bilgileri alındıktan sonra çalışacak kod)**.

    ```php
    <?php

    require 'config.php';

   /**
     * Sipariş bilgileri.
     *
     * NOT!!! IyzicoPos, KuveytPos ve PayTrPos sipariş verileri için ekstra alanlar istemektedir.
     * Ekstra alanlarla ilgili detaylı bilgiyi /examples klasörde bulabilirsiniz.
     */
    $order = [
        'id'          => 'BENZERSIZ-SIPARIS-ID',
        'amount'      => 1.01,
        'currency'    => \Mews\Pos\PosInterface::CURRENCY_TRY, //optional. default: TRY
        'installment' => 0, //0 ya da 1'den büyük değer, optional. default: 0

        // Success ve Fail URL'ler farklı olabilir ama kütüphane success ve fail için aynı kod çalıştırır.
        // success_url ve fail_url'lerin aynı olmasın fayda var çünkü bazı gateyway'ler tek bir URL kabul eder.
        'success_url' => 'https://example.com/response.php',
        'fail_url'    => 'https://example.com/response.php',

        // lang degeri verilmezse config'de tanimlanan dil veya default olarak LANG_TR kullanılacak.
        'lang' => \Mews\Pos\Gateway\PosInterface::LANG_TR, // Kullanıcının yönlendirileceği banka gateway sayfasının ve gateway'den dönen mesajların dili.
    ];

    // ============================================================================================
    // Tekrarlanan/recurring ödemeler için ekstra gereken veriler:
    // ============================================================================================
    // Tekrarlanan ödemeyi destekleyen gatewayler: GarantiPos, AssecoPos, PayFlexV4, AkbankPos
    $order['installment'] = 0; // Tekrarlayan ödemeler taksitli olamaz.

    $recurringFrequency     = 3;
    $recurringFrequencyType = 'MONTH'; // DAY|WEEK|MONTH|YEAR
    $endPeriod              = $installment * $recurringFrequency;

    $order['recurring'] = [
        'frequency'     => $recurringFrequency,
        'frequencyType' => $recurringFrequencyType,
        'installment'   => $installment,

        // GarantiPos optional
        'startDate'     => new \DateTimeImmutable(),

        // Sadece PayFlexV4'te zorunlu
        'endDate'       => (new \DateTime())->modify(\sprintf('+%d %s', $endPeriod, $recurringFrequencyType)),
    ];

    $_SESSION['order'] = $order;

    // Kredi kartı bilgileri
    $card = null;
    if (\Mews\Pos\PosInterface::MODEL_3D_HOST !== $paymentModel) {
        try {
            $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
                $pos,
                $_POST['card_number'],
                $_POST['card_year'],
                $_POST['card_month'],
                $_POST['card_cvv'],
                $_POST['card_name'],

                // kart tipi Gateway'e göre zorunlu, alabileceği örnek değer: "visa"
                // alabileceği alternatif değerler için \Mews\Pos\Model\Card\CreditCardInterface'a bakınız.
                $_POST['card_type'] ?? null
          );
        } catch (\Mews\Pos\Exception\CardTypeRequiredException $e) {
            // bu gateway için kart tipi zorunlu
        } catch (\Mews\Pos\Exception\CardTypeNotSupportedException $e) {
            // sağlanan kart tipi bu gateway tarafından desteklenmiyor
        }

        if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
            // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım olacak.
            $_SESSION['card'] = $_POST;
        }
    }

    // ============================================================================================
    // OZEL DURUMLAR ICIN KODLAR START
    // ============================================================================================
    try {
        /**
         * NOT!!! event listenerin çalışması için $eventDispatcher objesi $pos objesi oluştururken
         * kullandığınız $eventDıspatcher ile aynısı olması gerekiyor!
         * $pos = \Mews\Pos\Factory\PosFactory::create($account, $config, $eventDispatcher);
         * $eventDispatcher'i tekrardan oluşturursanız, listener çalışmaz!
         */
        /** @var \Symfony\Component\EventDispatcher\EventDispatcher $eventDispatcher */
        $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
            /**
             * Bazı Gatewayler 3D Form verisini oluşturabilmek için bankaya API istek gönderir.
             * 3D form verisini oluşturmak için API isteği Gönderen Gateway'ler: ToslaPos, PosNet, PayFlexCPV4Pos, PayFlexV4Pos, KuveytPos
             * Burda istek banka API'na gönderilmeden önce gonderilecek veriyi değistirebilirsiniz.
             * Örnek:
             * if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
             *     $data = $event->getRequestData();
             *     $data['abcd'] = '1234';
             *     $event->setRequestData($data);
             * }
             */
        });

        /**
         * Bu Event'i dinleyerek 3D formun hash verisi hesaplanmadan önce formun input array içireğini güncelleyebilirsiniz.
         * Eğer ekleyeceğiniz veri hash hesaplamada kullanılmıyorsa Form verisi oluştuktan sonra da güncelleyebilirsiniz.
         */
        $eventDispatcher->addListener(Before3DFormHashCalculatedEvent::class, function (Before3DFormHashCalculatedEvent $event): void {
            if ($event->getGatewayClass() === \Mews\Pos\Gateway\AssecoPos::class) {
                //    if ($event->getGatewayClass() !== \Mews\Pos\Gateway\AssecoPos::class) {
                //        return;
                //    }
                //    // Örneğin İşbank İmece Kart ile ödeme yaparken aşağıdaki verilerin eklenmesi gerekiyor:
                //    $supportedPaymentModels = [
                //        \Mews\Pos\PosInterface::MODEL_3D_PAY,
                //        \Mews\Pos\PosInterface::MODEL_3D_PAY_HOSTING,
                //        \Mews\Pos\PosInterface::MODEL_3D_HOST,
                //    ];
                //    if ($event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH && in_array($event->getPaymentModel(), $supportedPaymentModels, true)) {
                //        $formInputs           = $event->getFormInputs();
                //        $formInputs['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
                //        $formInputs['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı.
                //        $event->setFormInputs($formInputs);
                //    }
            }
            if ($event->getGatewayClass() === \Mews\Pos\Gateway\AssecoPos::class) {
    //           Örnek 2: callbackUrl eklenmesi
    //           $formInputs                = $event->getFormInputs();
    //           $formInputs['callbackUrl'] = $formInputs['failUrl'];
    //           $formInputs['refreshTime'] = '10'; // birim: saniye; callbackUrl sisteminin doğru çalışması için eklenmesi gereken parametre
    //           $event->setFormInputs($formInputs);
            }
        });

    // ============================================================================================
    // OZEL DURUMLAR ICIN KODLAR END
    // ============================================================================================
        $formData = $pos->get3DFormData(
            $order,
            $paymentModel,
            $transactionType,
            $card,
            /**
             * MODEL_3D_SECURE veya MODEL_3D_PAY ödemelerde kredi kart verileri olmadan
             * form verisini oluşturmak için true yapabilirsiniz.
             * Yine de bazı gatewaylerde kartsız form verisi oluşturulamıyor.
             */
            false, // optional, default: false
            /**
             * İsteğe bağlı: 3D form verisinin dönüş formatını belirtir.
             * PosInterface::FORM_FORMAT_ARRAY: gateway URL, HTTP metodu ve form alanlarını içeren dizi döner.
             * PosInterface::FORM_FORMAT_HTML: hazır HTML form string'i döner.
             * Belirtilmezse (null) gateway'in varsayılan formatı kullanılır.
             * Desteklenmeyen format talep edilirse UnsupportedFormFormatException fırlatılır.
             *
             * PayForPos'da IP M047 IP kısıtlaması sorunu yaşarsanız PosInterface::FORM_FORMAT_HTML değeri kullanarak sorunu
             * çözebilirsiniz.
             */
            // null // $formFormat, default: null
        );
    } catch (\Mews\Pos\Exception\UnsupportedFormFormatException $e) {
        var_dump($e);
        exit;
    } catch (\InvalidArgumentException $e) {
        // örneğin kart bilgisi sağlanmadığında bu exception'i alırsınız.
        var_dump($e);
    } catch (\LogicException $e) {
        // ödeme modeli veya işlem tipi desteklenmiyorsa bu exception'i alırsınız.
        var_dump($e);
    } catch (\Exception|\Error $e) {
        var_dump($e);
        exit;
    }
    ```
    ```php
    <?php if (is_string($formData)) : ?>
        <?= $formData; ?>
    <?php elseif ($formData['method'] === 'GET' && $formData['inputs'] === []):
        header('Location: '.$formData['gateway']);
    else: ?>
    // Kullanıcı ödeme gateway'ne HTML form ile yölendirilir.
    // $formData içeriği HTML forma render ediyoruz ve kullanıcıyı banka gateway'ine yönlendiriyoruz.
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
    **response.php (gateway'den döndükten sonra çalışacak kod)**

    ```php
    <?php

    require 'config.php';

    $order = $_SESSION['order'];
    $card  = null;
    if (\Mews\Pos\PosInterface::MODEL_3D_HOST !== $paymentModel) {
        if (get_class($pos) === \Mews\Pos\Gateway\PayFlexV4Pos::class) {
            // bu gateway için ödemeyi tamamlarken tekrar kart bilgisi lazım.
            $cardData = $_SESSION['card'];
            unset($_SESSION['card']);
            $card = \Mews\Pos\Factory\CreditCardFactory::createForGateway(
                $pos,
                $cardData['card_number'],
                $cardData['card_year'],
                $cardData['card_month'],
                $cardData['card_cvv'],
                $cardData['card_name'],
                $cardData['card_type']
          );
        }
    }

    // ============================================================================================
    // OZEL DURUMLAR ICIN KODLAR START
    // ============================================================================================

    //    // Isbank İMECE için ekstra alanların eklenme örneği
    //    $eventDispatcher->addListener(RequestDataPreparedEvent::class, function (RequestDataPreparedEvent $event) {
    //        if ($event->getPaymentModel() === PosInterface::MODEL_3D_SECURE && $event->getTxType() === PosInterface::TX_TYPE_PAY_AUTH) {
    //            $data                    = $event->getRequestData();
    //            $data['Extra']['IMCKOD'] = '9999'; // IMCKOD bilgisi bankadan alınmaktadır.
    //            $data['Extra']['FDONEM'] = '5'; // Ödemenin faizsiz ertelenmesini istediğiniz dönem sayısı
    //            $event->setRequestData($data);
    //        }
    //    });

    // ============================================================================================
    // OZEL DURUMLAR ICIN KODLAR END
    // ============================================================================================


    // Ödeme tamamlanıyor
    $gatewayResponseData = $_POST;
    if (get_class($pos) === \Mews\Pos\Gateway\PayFlexCPV4Pos::class) {
        $gatewayResponseData = $_GET;
    }

    try  {
        $response = $pos->payment(
            $paymentModel,
            $order,
            $transactionType,
            $card,
            $gatewayResponseData
        );

        var_dump($response);
        // response içeriği için /examples/template/_payment_response.php dosyaya bakınız.

        // Ödeme başarılı mı?
        if ($pos->isSuccess()) {
            // NOT: Ödeme durum sorgulama, iptal ve iade işlemleri yapacaksanız $response değerini saklayınız.
        } else {
            // Hata durumunda kullanıcıya hata mesajını göstermek isterseniz:
            $errorMessage = $response['md_error_message'] ?? $response['error_message'];
        }
    } catch (\Mews\Pos\Exception\HashMismatchException $e) {
        /**
         * Bankadan gelen verilerin bankaya ait olmadığında bu exception oluşur.
         * Veya Banka API bilgileriniz hatalı ise de oluşur.
         * Eğer kütühaneden dolayı hash doğrulama hatası alıyorsanız, issue oluşturunuz.
         * Issue çözülene kadar geçici olarak disable_3d_hash_check: true ayarla hash doğrulamasını devre dışı bırakabilirsiniz.
         * Güvenlik açısından disable_3d_hash_check: false olarak kullanılması tavsiye edilmez.
         */
    } catch (\Error $e) {
        var_dump($e);
        exit;
    }
    ```
