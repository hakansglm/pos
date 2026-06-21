<?php

/**
 * NOT! Bu dosya örnek amaçlıdır. Canlı ortamda kopyasını oluşturup, kopyasını kullanınız!
 */
return [
    'banks' => [
        'akbank-pos'            => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => \Mews\Pos\Gateway\AkbankPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://api.akbank.com/api/v1/payment/virtualpos',
                'gateway_3d'      => 'https://virtualpospaymentgateway.akbank.com/securepay',
                'gateway_3d_host' => 'https://virtualpospaymentgateway.akbank.com/payhosting',
            ],
        ],
        'akbankv3'              => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'akbank'                => [
            'name'              => 'AKBANK T.A.S.',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://www.sanalakpos.com/fim/api',
                'gateway_3d'      => 'https://www.sanalakpos.com/fim/est3Dgate',
                'gateway_3d_host' => 'https://sanalpos.sanalakpos.com.tr/fim/est3Dgate',
            ],
        ],
        'tosla'                 => [
            'name'              => 'AkÖde A.Ş.',
            'class'             => \Mews\Pos\Gateway\ToslaPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://entegrasyon.tosla.com/api/Payment',
                'gateway_3d'      => 'https://entegrasyon.tosla.com/api/Payment/ProcessCardForm',
                'gateway_3d_host' => 'https://entegrasyon.tosla.com/api/Payment/threeDSecure',
            ],
        ],
        'finansbank'            => [
            'name'              => 'QNB Finansbank',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://www.fbwebpos.com/fim/api',
                'gateway_3d'  => 'https://www.fbwebpos.com/fim/est3dgate',
            ],
        ],
        'halkbank'              => [
            'name'              => 'Halkbank',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.halkbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.halkbank.com.tr/fim/est3dgate',
            ],
        ],
        'teb'                   => [
            'name'              => 'TEB',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.teb.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.teb.com.tr/fim/est3Dgate',
            ],
        ],
        'isbank'                => [
            'name'              => 'İşbank T.A.S.',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.isbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.isbank.com.tr/fim/est3Dgate',
            ],
        ],
        'sekerbank'             => [
            'name'              => 'Şeker Bank',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.sekerbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos.sekerbank.com.tr/fim/est3Dgate',
            ],
        ],
        'yapikredi'             => [
            'name'              => 'Yapıkredi',
            'class'             => \Mews\Pos\Gateway\PosNetPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://posnet.yapikredi.com.tr/PosnetWebService/XML',
                'gateway_3d'  => 'https://posnet.yapikredi.com.tr/3DSWebService/YKBPaymentService',
            ],
        ],
        'albaraka'              => [
            'name'              => 'Albaraka',
            'class'             => \Mews\Pos\Gateway\PosNetV1Pos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://epos.albarakaturk.com.tr/ALBMerchantService/MerchantJSONAPI.svc',
                'gateway_3d'  => 'https://epos.albarakaturk.com.tr/ALBSecurePaymentUI/SecureProcess/SecureVerification.aspx',
            ],
        ],
        'garanti'               => [
            'name'              => 'Garanti',
            'class'             => \Mews\Pos\Gateway\GarantiPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalposprov.garanti.com.tr/VPServlet',
                'gateway_3d'  => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
            ],
        ],
        'qnbfinansbank-payfor'  => [
            'name'              => 'QNBFinansbank-PayFor',
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://vpos.qnb.com.tr/Gateway/XMLGate.aspx',
                'gateway_3d'      => 'https://vpos.qnb.com.tr/Gateway/Default.aspx',
                'gateway_3d_host' => 'https://vpos.qnb.com.tr/Gateway/3DHost.aspx',
            ],
        ],
        'ziraat-katilim-payfor' => [
            'name'              => 'ZiraatKatilim-PayFor',
            'class'             => \Mews\Pos\Gateway\PayForPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_configs'   => [
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
            'name'              => 'VakifBank-VPOS',
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://onlineodeme.vakifbank.com.tr:4443/VposService/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://3dsecure.vakifbank.com.tr:4443/MPIAPI/MPI_Enrollment.aspx',
                'query_api'   => 'https://onlineodeme.vakifbank.com.tr:4443/UIService/Search.aspx',
            ],
        ],
        'ziraat-vpos'           => [
            'name'              => 'Ziraat Bankası',
            'class'             => \Mews\Pos\Gateway\PayFlexV4Pos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.ziraatbank.com.tr/v4/v3/Vposreq.aspx',
                'gateway_3d'  => 'https://mpi.ziraatbank.com.tr/Enrollment.aspx',
                'query_api'   => 'https://sanalpos.ziraatbank.com.tr/v4/UIWebService/Search.aspx',
            ],
        ],
        'ziraat-estpos'         => [
            'name'              => 'Ziraat Bankası Payten',
            'class'             => \Mews\Pos\Gateway\AssecoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos2.ziraatbank.com.tr/fim/api',
                'gateway_3d'  => 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate',
            ],
        ],
        'vakifbank-cp'          => [
            'name'              => 'VakifBank-PayFlex-Common-Payment',
            'class'             => \Mews\Pos\Gateway\PayFlexCPV4Pos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://cpweb.vakifbank.com.tr/CommonPayment/api',
            ],
        ],
        'denizbank'             => [
            'name'              => 'DenizBank-InterPos',
            'class'             => \Mews\Pos\Gateway\InterPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d'      => 'https://inter-vpos.com.tr/mpi/Default.aspx',
                'gateway_3d_host' => 'https://inter-vpos.com.tr/mpi/3DHost.aspx',
            ],
        ],
        'kuveytpos'             => [
            'name'              => 'kuveyt-pos',
            'class'             => \Mews\Pos\Gateway\KuveytPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://sanalpos.kuveytturk.com.tr/ServiceGateWay/Home',
                'query_api'   => 'https://boa.kuveytturk.com.tr/BOA.Integration.WCFService/BOA.Integration.VirtualPos/VirtualPosService.svc/Basic',
            ],
        ],
        'vakif-katilim'         => [
            'name'              => 'Vakıf Katılım',
            'class'             => \Mews\Pos\Gateway\VakifKatilimPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/Home',
                'gateway_3d_host' => 'https://boa.vakifkatilim.com.tr/VirtualPOS.Gateway/CommonPaymentPage/CommonPaymentPage',
            ],
        ],
        'param-pos'             => [
            'name'              => 'TURK Elektronik Para A.Ş',
            'class'             => \Mews\Pos\Gateway\ParamPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://posws.param.com.tr/turkpos.ws/service_turkpos_prod.asmx',
            ],
        ],
        'param-3d-host-pos'     => [
            'name'              => 'TURK Elektronik Para A.Ş',
            'class'             => \Mews\Pos\Gateway\Param3DHostPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api'     => 'https://pos.param.com.tr/Tahsilat/to.ws/Service_Odeme.asmx',
                'gateway_3d_host' => 'https://pos.param.com.tr/Tahsilat/Default.aspx',
            ],
        ],
        'iyzico'                => [
            'name'              => 'Iyzico',
            'class'             => \Mews\Pos\Gateway\IyzicoPos::class,
            'lang'              => \Mews\Pos\PosInterface::LANG_TR,
            'gateway_endpoints' => [
                'payment_api' => 'https://api.iyzipay.com',
                'query_api'   => 'https://api.iyzipay.com/v2/reporting/payment',
            ],
        ],
    ],
];
