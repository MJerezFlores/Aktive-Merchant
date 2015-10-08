<?php

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Exception;
use AktiveMerchant\Billing\Response;
use AktiveMerchant\Common\Options;

class Moneris extends Gateway
{
    const TEST_URL = 'https://clickcanarias.net/system/import/pay';
    const LIVE_URL = 'http://clickcanarias-sandbox.net/system/import/pay';

    #Actions
    protected $authorize;
    protected $cavv_authorize;
    protected $purchase;
    protected $cavv_purchase;
    protected $capture;
    protected $void;
    protected $credit;

    public static $supported_countries = array('ES');

    public static $supported_cardtypes = array( 'visa',  'master','american_express', 'discover');

    public static $homepage_url = 'https://clickcanarias.net';

    public static $display_name = 'ClickCanarias Payment Gateway';

    public static $money_format = 'cents';
    public static $default_currency = 'EUR';

    private $options;
    private $post;
    private $xml;
    private $timestamp;

    public function __construct(array $options = array())
    {
        Options::required('identificador, clave, test', $options);

        $this->options = new Options($options);

        if (isset( $options['currency'])) {
            self::$default_currency = $options['currency'];
        }

        $this->authorize      = 'preauth';
        $this->cavv_authorize = 'cavv_preauth';
        $this->purchase       = 'purchase';
        $this->cavv_purchase  = 'cavv_purchase';
        $this->capture        = 'completion';
        $this->void           = 'purchasecorrection';
        $this->credit         = 'refund';

    }
}
