<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

namespace AktiveMerchant\Billing\Gateways;

use AktiveMerchant\Billing\Interfaces as Interfaces;
use AktiveMerchant\Billing\Gateway;
use AktiveMerchant\Billing\CreditCard;
use AktiveMerchant\Billing\Response;

/**
 * PiraeusPaycenter gateway
 *
 * @package Aktive-Merchant
 * @author  Andreas Kollaros
 * @license http://www.opensource.org/licenses/mit-license.php
 */
class PiraeusPaycenter extends Gateway implements
    Interfaces\Charge,
    Interfaces\Credit
{
    const TEST_URL = 'https://paycenter.piraeusbank.gr/services/paymentgateway.asmx';
    const LIVE_URL = 'https://paycenter.piraeusbank.gr/services/paymentgateway.asmx';

    /**
     * {@inheritdoc }
     */
    public static $supported_countries = array('GR');

    /**
     * {@inheritdoc }
     */
    public static $homepage_url = 'http://www.piraeusbank.gr';

    /**
     * {@inheritdoc }
     */
    public static $display_name = 'Piraeus Paycenter';

    /**
     * {@inheritdoc }
     */
    public static $default_currency = 'EUR';

    private $options;

    private $post;

    private $CURRENCY_MAPPINGS = array(
        'USD' => 840, 'GRD' => 300, 'EUR' => 978
    );

    private $ENROLLED_MAPPINGS = array(
        'Y' => 'Yes',
        'N' => 'No',
        'U' => 'Undefined'
    );

    private $PARES_MAPPINGS = array(
        'U' => 'Unknown',
        'A' => 'Attempted',
        'Y' => 'Succeded',
        'N' => 'Failed'
    );

    private $SIGNATURE_MAPPINGS = array(
        'Y' => 'Yes',
        'N' => 'No',
        'U' => 'Undefined'
    );

    private $CARD_MAPPINGS = array(
        'visa' => 'VISA',
        'master' => 'MasterCard'
    );

    /**
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
      $this->required_options('acquire_id, merchant_id, pos_id, user, password, channel_type', $options);

        if (isset($options['currency']))
            self::$default_currency = $options['currency'];

        $this->options = $options;
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Merchant_Billing_Response
     */
    public function authorize($money, CreditCard $creditcard, $options=array())
    {
        $this->post = null;
        $this->add_invoice($money, $options);
        $this->post .= '<ns:ExpirePreauth>30</ns:ExpirePreauth>';
        $this->add_creditcard($creditcard);
        $this->add_centinel_data($options);

        return $this->commit('AUTHORIZE', $money);
    }

    /**
     *
     * @param number     $money
     * @param CreditCard $creditcard
     * @param array      $options
     *
     * @return Response
     */
    public function purchase($money, CreditCard $creditcard, $options=array())
    {
        $this->post = null;
        $this->add_invoice($money, $options);
        $this->add_creditcard($creditcard);
        $this->add_centinel_data($options);

        return $this->commit('SALE', $money);
    }

    /**
     *
     * @param number $money
     * @param string $authorization
     * @param array  $options
     *
     * @return Response
     */
    public function capture($money, $authorization, $options = array())
    {
        $this->post = null;
        $amount = $this->amount($money);
        $this->post .= <<<XML
      <ns:TransactionReferenceID>{$authorization}</ns:TransactionReferenceID>
      <ns:CurrencyCode>{$this->currency_lookup(self::$default_currency)}</ns:CurrencyCode>
      <ns:Amount>$amount</ns:Amount>
XML;
        return $this->commit('SETTLE', $money);
    }

    /**
     *
     * @param string $authorization
     * @param array  $options
     *
     * @return Response
     */
    public function void($authorization, $options = array())
    {
        $this->post = null;
        $money = $options['money'];
        $amount = $this->amount($money);
        $this->post .= <<<XML
      <ns:TransactionReferenceID>{$authorization}</ns:TransactionReferenceID>
      <ns:CurrencyCode>{$this->currency_lookup(self::$default_currency)}</ns:CurrencyCode>
      <ns:Amount>$amount</ns:Amount>
XML;
        return $this->commit('REFUND', null);
    }

    /**
     *
     * @param number $money
     * @param string $identification
     * @param array  $options
     *
     * @return Response
     */
    public function credit($money, $identification, $options = array())
    {
        $this->post = null;
        $amount = $this->amount($money);
        $this->post .= <<<XML
      <ns:TransactionReferenceID>{$identification}</ns:TransactionReferenceID>
      <ns:CurrencyCode>{$this->currency_lookup(self::$default_currency)}</ns:CurrencyCode>
      <ns:Amount>$amount</ns:Amount>
XML;
        return $this->commit('VOIDREQUEST', $money);
    }

    /* Private */

    /**
     *
     * @param array $options
     */
    private function add_invoice($money, $options)
    {
        $amount = $this->amount($money);
        $this->post .= <<<XML
      <ns:MerchantReference>{$options['order_id']}</ns:MerchantReference>
      <ns:EntryType>KeyEntry</ns:EntryType>
      <ns:CurrencyCode>{$this->currency_lookup(self::$default_currency)}</ns:CurrencyCode>
      <ns:Amount>$amount</ns:Amount>
XML;
    }

    /**
     *
     * @param CreditCard $creditcard
     */
    private function add_creditcard(CreditCard $creditcard)
    {
        $month = $this->cc_format($creditcard->month, 'two_digits');

        $cardholdername = strtoupper($creditcard->name());
        $this->post .= <<<XML
      <ns:CardInfo>
        <ns:CardType>{$this->CARD_MAPPINGS[$creditcard->type]}</ns:CardType>
        <ns:CardNumber>$creditcard->number</ns:CardNumber>
        <ns:CardHolderName>$cardholdername</ns:CardHolderName>
        <ns:ExpirationMonth>$month</ns:ExpirationMonth>
        <ns:ExpirationYear>$creditcard->year</ns:ExpirationYear>
        <ns:Cvv2>$creditcard->verification_value</ns:Cvv2>
        <ns:Aid/>
        <ns:Emv/>
        <ns:PinBlock/>
      </ns:CardInfo>
XML;
    }

    /**
     * Add required data from 3D centinel verification
     *
     * @param array $options
     */
    private function add_centinel_data($options)
    {
        $this->required_options('cavv, eci_flag, xid, enrolled, pares_status, signature_verification', $options);
        $this->post .= <<<XML
      <ns:AuthInfo>
        <ns:Cavv>{$options['cavv']}</ns:Cavv>
        <ns:Eci>{$options['eci_flag']}</ns:Eci>
        <ns:Xid>{$options['xid']}</ns:Xid>
        <ns:Enrolled>{$this->ENROLLED_MAPPINGS[$options['enrolled']]}</ns:Enrolled>
        <ns:PAResStatus>{$this->PARES_MAPPINGS[$options['pares_status']]}</ns:PAResStatus>
        <ns:SignatureVerification>{$this->SIGNATURE_MAPPINGS[$options['signature_verification']]}</ns:SignatureVerification>
      </ns:AuthInfo>
XML;
    }

    /**
     *
     * @param string $body
     */
    private function parse($body)
    {
        $body = preg_replace('#(</?)soap:#', '$1', $body);
        $xml = simplexml_load_string($body);

        $header = $xml->Body->ProcessTransactionResponse->TransactionResponse->Header;
        $transaction = $xml->Body->ProcessTransactionResponse->TransactionResponse->Body->TransactionInfo;

        $response = array();

        $response['status'] = (string) $transaction->StatusFlag;
        $response['result_description'] = (string) $header->ResultDescription;
        $response['support_reference_id'] = (string) $header->SupportReferenceID;
        $response['response_description'] = (string) $transaction->ResponseDescription;
        $response['authorization_id'] = (string) $transaction->TransactionID;
        $response['result_code'] = (string) $header->ResultCode;
        $response['response_code'] = (string) $transaction->ResponseCode;
        $response['approval_code'] = (string) $transaction->ApprovalCode;
        $response['package_no'] = (string) $transaction->PackageNo;

        return $response;
    }

    /**
     *
     * @param string $action
     * @param number $money
     * @param array  $parameters
     *
     * @return Response
     */
    private function commit($action, $money, $parameters=array())
    {
        $url = $this->isTest() ? self::TEST_URL : self::LIVE_URL;

        $post_data = $this->post_data($action, $parameters);
        $headers = array(
            "POST /services/paymentgateway.asmx HTTP/1.1",
            "Host: paycenter.piraeusbank.gr",
            "Content-type: text/xml; charset=\"utf-8\"",
            "Content-length: " . strlen($post_data),
            "SOAPAction: \"http://piraeusbank.gr/paycenter/ProcessTransaction\""
        );

        $data = $this->ssl_post($url, $post_data, array('headers' => $headers));

        $response = $this->parse($data);

        $test_mode = $this->isTest();

        return new Response(
            $this->success_from($response),
            $this->message_from($response),
            $response,
            array(
                'test' => $test_mode,
                'authorization' => $response['authorization_id']
            )
        );
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function success_from($response)
    {
        return $response['status'] == 'Success';
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function message_from($response)
    {
        return $response['response_description'] == ''
            ? $response['result_description']
            : $response['response_description'];
    }

    /**
     *
     * @param array $response
     *
     * @return string
     */
    private function fraud_review_from($response)
    {

    }

    /**
     *
     * @param array $response
     *
     * @return array
     */
    private function avs_result_from($response)
    {
        return array('code' => $response['avs_result_code']);
    }

    /**
     *
     * @param string $action
     * @param array  $parameters
     */
    private function post_data($action, $parameters = array())
    {
        /**
         * Add final parameters to post data and
         * build $this->post to the format that your payment gateway understands
         */
        $password = md5($this->options['password']);
        $xml = <<<XML

      <soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:pay="http://piraeusbank.gr/paycenter" xmlns:ns="http://piraeusbank.gr/paycenter/1.0">
        <soap:Header/>
        <soap:Body>
          <pay:ProcessTransaction>
            <ns:TransactionRequest>
              <ns:Header>
                <ns:RequestType>$action</ns:RequestType>
                <ns:RequestMethod>SYNCHRONOUS</ns:RequestMethod>
                <ns:MerchantInfo>
                  <ns:AcquirerID>{$this->options['acquire_id']}</ns:AcquirerID>
                  <ns:MerchantID>{$this->options['merchant_id']}</ns:MerchantID>
                  <ns:PosID>{$this->options['pos_id']}</ns:PosID>
                  <ns:ChannelType>{$this->options['channel_type']}</ns:ChannelType>
                  <ns:User>{$this->options['user']}</ns:User>
                  <ns:Password>{$password}</ns:Password>
                </ns:MerchantInfo>
              </ns:Header>
              <ns:Body>
                <ns:TransactionInfo>
                {$this->post}
                </ns:TransactionInfo>
              </ns:Body>
            </ns:TransactionRequest>
          </pay:ProcessTransaction>
        </soap:Body>
      </soap:Envelope>
XML;

        return ($xml);
    }

}
