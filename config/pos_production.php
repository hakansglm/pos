<?php

/**
 * NOT! Bu dosya örnek amaçlıdır. Canlı ortamda kopyasını oluşturup, kopyasını kullanınız!
 */
return [
    'banks' => [
        'akbank-pos'            => [
            // AKBANK T.A.S. Akbank Pos
            'class'             => \Mews\Pos\Gateway\AkbankPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://api.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgateway.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgateway.akbank.com/payhosting',
            ],
        ],
        'akbank'                => [
            // AKBANK T.A.S. Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'tosla'                 => [
            // AkÖde A.Ş.
            'class'             => \Mews\Pos\Gateway\ToslaPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://entegrasyon.tosla.com/api/Payment',
                'gateway_3d'      => 'https://entegrasyon.tosla.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://entegrasyon.tosla.com/api/Payment/threeDSecure',
            ],
        ],
        'paytr'                 => [
            // PayTR
            'class'             => \Mews\Pos\Gateway\PayTrPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.paytr.com',
                'gateway_3d'      => 'https://www.paytr.com/odeme',
                'gateway_3d_host' => 'https://www.paytr.com/odeme/guvenli',
            ],
        ],
        'finansbank'            => [
            // QNB Finansbank Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://www.fbwebpos.com/fim/api',
                'gateway_3d'  => 'https://www.fbwebpos.com/fim/est3dgate',
            ],
        ],
        'halkbank'              => [
            // Halkbank Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.halkbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.halkbank.com.tr/fim/est3dgate',
            ],
        ],
        'teb'                   => [
            // TEB Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.teb.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.teb.com.tr/fim/est3Dgate',
            ],
        ],
        'isbank'                => [
            // İşbank T.A.S. Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.isbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
            ],
        ],
        'sekerbank'             => [
            // Şeker Bank Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.sekerbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate',
            ],
        ],
        'yapikredi'             => [
            // Yapıkredi
            'class'             => \Mews\Pos\Gateway\PosNetPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
                'gateway_3d'  => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'              => [
            // Albaraka
            'class'             => \Mews\Pos\Gateway\PosNetV1Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://epos.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'  => 'https://epos.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'               => [
            // Garanti Banka
            'class'             => \Mews\Pos\Gateway\GarantiPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'gateway_3d'  => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor'  => [
            // QNBFinansbank-PayFor
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://vpos.qnb.com.tr/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpos.qnb.com.tr/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpos.qnb.com.tr/Gateway/3DHost.aspx',
            ],
        ],
        'ziraat-katilim-payfor' => [
            // ZiraatKatilim-PayFor
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'gateway_configs'   => [
                'lang'                  => \Mews\Pos\PosInterface::LANG_TR,
                // Ziraat Katilim için hash kontrolü çalışmıyor. O yüzden devre dışı bırakıyoruz.
                'disable_3d_hash_check' => true,
            ],
            'gateway_endpoints' => [
                'payment_api'     => 'https://vpos.ziraatkatilim.com.tr/Mpi/XMLGate.aspx',
                'gateway_3d'      => 'https://vpos.ziraatkatilim.com.tr/Mpi/Default.aspx',
                'gateway_3d_host' => 'https://vpos.ziraatkatilim.com.tr/Mpi/3Dhost.aspx',
            ],
        ],
        'vakifbank'             => [
            // VakifBank-VPOS
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                'query_api'   => 'https://onlineodeme.vakifbank.com.tr:4443/UIService/Search.aspx',
            ],
        ],
        'ziraat-vpos'           => [
            // Ziraat Bankası PayFlex
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://mpi.ziraatbank.com.tr/Enrollment.aspx',
                'query_api'   => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'ziraat-asseco'         => [
            // Ziraat Bankası Asseco
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos2.ziraatbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate',
            ],
        ],
        'vakifbank-cp'          => [
            // VakifBank-PayFlex-Common-Payment
            'class'             => \Mews\Pos\Gateway\PayFlexCPV4Pos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://cpweb.vakifbank.com.tr/CommonPayment/api',
            ],
        ],
        'denizbank'             => [
            // DenizBank-InterPos
            'class'             => \Mews\Pos\Gateway\InterPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'             => [
            // kuveyt-pos
            'class'             => \Mews\Pos\Gateway\KuveytPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home',
                'query_api'   => 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
        ],
        'vakif-katilim'         => [
            // Vakıf Katılım
            'class'             => \Mews\Pos\Gateway\VakifKatilimPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d_host' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
            ],
        ],
        'param-pos'             => [
            // TURK Elektronik Para A.Ş
            'class'             => \Mews\Pos\Gateway\ParamPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx',
            ],
        ],
        'param-3d-host-pos'     => [
            // TURK Elektronik Para A.Ş
            'class'             => \Mews\Pos\Gateway\Param3DHostPos::class,
            'gateway_endpoints' => [
                'payment_api'     => 'https://pos.param.com.tr/Tahsilat/to.ws/Service_Odeme.asmx',
                'gateway_3d_host' => 'https://pos.param.com.tr/Tahsilat/Default.aspx',
            ],
        ],
        'iyzico'                => [
            'class'             => \Mews\Pos\Gateway\IyzicoPos::class,
            'gateway_endpoints' => [
                'payment_api' => 'https://api.iyzipay.com',
                'query_api'   => 'https://api.iyzipay.com/v2/reporting/payment',
            ],
        ],
    ],
];
