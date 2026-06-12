<?php

use Mews\Pos\Entity\Card\CreditCardInterface;

require __DIR__.'/../_main_config.php';

$bankTestsUrl = $hostUrl.'/kuveytpos';
$posClass = \Mews\Pos\Gateways\KuveytPos::class;

$testCards = [
    'visa1' => [
        //Kart Doğrulama Şifresi: 123456
        'number' => '5188961939192544',
        'year' => '29',
        'month' => '06',
        'cvv' => '588',
        'name' => 'John Doe',
        'type' => CreditCardInterface::CARD_TYPE_MASTERCARD,
    ],
];

function createGatewaySpecificOrderFields(): array
{
    return [
        /**
         * payment_channel: İşlemin yapıldığı cihaz bilgisi (DeviceData.DeviceChannel).
         * 2 karakter olmalıdır. 01-Mobil, 02-Web Browser için kullanılmalıdır.
         */
        'payment_channel' => '02',
        'buyer'           => [
            /**
             * Email: Kullanılan kart ile ilişkili kart hamilinin iş yerinde oluşturduğu hesapta
             * kullandığı email adresi. Maksimum 254 karakter uzunluğunda olmalıdır.
             */
            'email'         => 'xxxxx@gmail.com',
            /**
             * gsm_number_cc: Kart hamilinin cep telefonuna ait ülke kodu.
             * 1-3 karakter uzunluğunda olmalıdır.
             */
            'gsm_number_cc' => '90',
            /**
             * gsm_number: Kart hamilinin cep telefonuna ait abone numarası.
             * Maksimum 15 karakter uzunluğunda olmalıdır.
             */
            'gsm_number'    => '1234567899',
        ],
        'billing_address' => [
            /**
             * BillAddrCity: Kullanılan kart ile ilişkili kart hamilinin fatura adres şehri.
             * Maksimum 50 karakter uzunluğunda olmalıdır.
             */
            'city'     => 'İstanbul',
            /**
             * BillAddrCountry: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki ülke kodu.
             * Maksimum 3 karakter uzunluğunda olmalıdır.
             * ISO 3166-1 sayısal üç haneli ülke kodu standardı kullanılmalıdır.
             */
            'country'  => '792',
            /**
             * BillAddrLine1: Kart hamilinin fatura adresinde yer alan sokak vb. bilgileri içeren açık adresi.
             * Maksimum 150 karakter uzunluğunda olmalıdır.
             */
            'address'  => 'XXX Mahallesi XXX Caddesi No 55 Daire 1',
            /**
             * BillAddrPostCode: Kullanılan kart ile ilişkili kart hamilinin fatura adresindeki posta kodu.
             */
            'zip_code' => '34000',
            /**
             * BillAddrState: Kart hamilinin fatura adresindeki il veya eyalet bilgisi kodu.
             * ISO 3166-2'de tanımlı olan il/eyalet kodu olmalıdır.
             */
            'state'    => '34',
        ],
    ];
}
