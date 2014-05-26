<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

use AktiveMerchant\Billing\Gateways\BraintreeBlue;
use AktiveMerchant\Billing\Base;
use AktiveMerchant\Billing\CreditCard;

require_once 'config.php';

class BraintreeTest extends AktiveMerchant\TestCase
{
    public $gateway;
    public $amount;
    public $options;
    public $creditcard;

    public function setUp()
    {
        Base::mode('test');

        $login_info = $this->getFixtures()->offsetGet('braintree');

        $this->gateway = new BraintreeBlue($login_info);
        $this->amount = mt_rand(0, 10);
        $this->creditcard = new CreditCard(
            array(
                "first_name" => "John",
                "last_name" => "Doe",
                "number" => "4111111111111111",
                "month" => "01",
                "year" => "2015",
                "verification_value" => "000"
            )
        );
        $this->options = array(
            'order_id' => 'REF' . $this->gateway->generateUniqueId(),
            'description' => 'Braintree Test Transaction',
            'address' => array(
                'address1' => '1234 Street',
                'zip' => '98004',
                'state' => 'WA'
            )
        );
    }

    public function testSuccessfulAuthorization()
    {
        //$this->mock_request($this->successful_authorize_response());

        $response = $this->gateway->authorize(
            $this->amount,
            $this->creditcard,
            $this->options
        );

        $this->assert_success($response);
        $this->assertEquals(
            'authorized',
            $response->message()
        );

        $this->assertTrue(!is_null($response->authorization()));

    }

    protected function mock_request()
    {
        $class= $this->getMock(
            'Braintree_Transaction',
            array('sale')
        );

        $class::staticExpects($this->any())
            ->method('sale')
            ->will($this->returnValue($answer));
    }

    private function successful_authorize_response()
    {

        $attributes = array(
            'id' => '2rc4br',
            'status' => 'authorized',
            'type' => 'sale',
            'currencyIsoCode' => 'USD',
            'amount' => '10.00',
            'merchantAccountId' => 'rsrwqj9v3vqfwjng',
            'orderId' => NULL,
            'avsErrorResponseCode' => NULL,
            'avsPostalCodeResponseCode' => 'I',
            'avsStreetAddressResponseCode' => 'I',
            'cvvResponseCode' => 'M',
            'gatewayRejectionReason' => NULL,
            'processorAuthorizationCode' => '9RD5MX',
            'processorResponseCode' => '1000',
            'processorResponseText' => 'Approved',
            'voiceReferralNumber' => NULL,
            'purchaseOrderNumber' => NULL,
            'taxAmount' => NULL,
            'taxExempt' => false,
        );

        $transaction = Braintree_Transaction::factory($attributes);

        $result = new Braintree_Result_Successful($transaction);

        return $result;
    }
}
