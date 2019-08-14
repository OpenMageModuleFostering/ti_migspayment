<?php
/**
 * Ti Migspayment Payment Module
 *
 * @category    Ti
 * @package     Ti_Migspayment
 * @copyright   Copyright (c) 2012 Ti Technologies (http://www.titechnologies.in)
 * @link        http://www.titechnologies.in
 */

class Ti_Migspayment_Model_Virtualcredit extends Mage_Payment_Model_Method_Cc
{
    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    */
    protected $_code = 'migspayment';

    /**
     * Here are examples of flags that will determine functionality availability
     * of this module to be used by frontend and backend.
     *
     * @see all flags and their defaults in Mage_Payment_Model_Method_Abstract
     *
     * It is possible to have a custom dynamic logic by overloading
     * public function can* for each flag respectively
     */

    /**
     * Is this payment method a gateway (online auth/charge) ?
     */
    protected $_isGateway               = true;

    /**
     * Can authorize online?
     */
    protected $_canAuthorize            = true;

    /**
     * Can capture funds online?
     */
    protected $_canCapture              = true;

    /**
     * Can capture partial amounts online?
     */
    protected $_canCapturePartial       = false;

    /**
     * Can refund online?
     */
    protected $_canRefund               = false;

    /**
     * Can void transactions online?
     */
    protected $_canVoid                 = true;

    /**
     * Can use this payment method in administration panel?
     */
    protected $_canUseInternal          = true;

    /**
     * Can show this payment method as an option on checkout payment page?
     */
    protected $_canUseCheckout          = true;

    /**
     * Is this payment method suitable for multi-shipping checkout?
     */
    protected $_canUseForMultishipping  = true;

    /**
     * Can save credit card information for future processing?
     */
    protected $_canSaveCc = false;

    /**
     * Here you will need to implement authorize, capture and void public methods
     *
     * @see examples of transaction specific public methods such as
     * authorize, capture and void in Mage_Paygate_Model_Authorizenet
     */
    public function authorize(Varien_Object $payment, $amount)
    {
            $orderId = $payment->getOrder()->getIncrementId();
        try {
            $expyear=substr($payment->getCcExpYear(),2,2);
            $m=$payment->getCcExpMonth();
            $expmonth= strlen($m) < 2 ? "0".$m : $m;
            $expval=$expyear.$expmonth;
            $amount=$amount*1000;
            $paymentValues = array("vpc_CardExp" => $expval,
                                   "vpc_CardNum" => $payment->getCcNumber(),
                                   "vpc_Amount" => $amount,
                                   "vpc_MerchTxnRef" => $orderId,
                                   "vpc_OrderInfo" => "Order ID:".$orderId." Card Holder:".$payment->getCcOwner(),
                                   "vpc_CardSecurityCode" => $payment->getCcCid(),
                                  );
                $paymentValues['vpc_Version'] = "1";
                $paymentValues['vpc_Command'] = "pay";
            //Define the url where I'm making the request...
                $urlToPost = Mage::getStoreConfig('payment/migspayment/gatewayurl');
            if(Mage::getStoreConfig('payment/migspayment/testmode')=='1'){
                $paymentValues['vpc_AccessCode'] = Mage::getStoreConfig('payment/migspayment/testaccesscode');
                $paymentValues['vpc_Merchant'] = Mage::getStoreConfig('payment/migspayment/testmerchantid');
            } else {
                $paymentValues['vpc_AccessCode'] = Mage::getStoreConfig('payment/migspayment/accesscode');
                $paymentValues['vpc_Merchant'] = Mage::getStoreConfig('payment/migspayment/merchantid');
            }
            //Now Create the request which I will send via Post Method...
            $postData = "";
            foreach ($paymentValues as $key => $val) {
                $postData .= "{$key}=" . urlencode($val) . "&";
            }

            //Let's create a curl request and send the values above to the bank...
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $urlToPost);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData); //Put the created string here in use...
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $data = curl_exec($ch); //This value is the string returned from the bank...
            if (!$data) {
                Mage::throwException(curl_error($ch));
            }
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpcode && substr($httpcode, 0, 2) != "20") { //Unsuccessful post request...
                Mage::throwException("Returned HTTP CODE: " . $httpcode . " for this URL: " . $urlToPost);
            }
            curl_close($ch);
        } catch (Exception $e) {
            $payment->setStatus(self::STATUS_ERROR);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            $this->setStore($payment->getOrder()->getStoreId());
            Mage::throwException($e->getMessage());
        }
        $map = array();
        $message='';
        // process response if no errors
            $pairArray = split("&", $data);
            foreach ($pairArray as $pair) {
                $param = split("=", $pair);
                $map[urldecode($param[0])] = urldecode($param[1]);
            }
        $message         = Mage::helper('migspayment')->null2unknown($map, "vpc_Message");

        // Standard Receipt Data
        # merchTxnRef not always returned in response if no receipt so get input
        //TK//$merchTxnRef     = $vpc_MerchTxnRef;
        $merchTxnRef     = $_POST["vpc_MerchTxnRef"];


        $amount          = Mage::helper('migspayment')->null2unknown($map, "vpc_Amount");
        $locale          = Mage::helper('migspayment')->null2unknown($map, "vpc_Locale");
        $batchNo         = Mage::helper('migspayment')->null2unknown($map, "vpc_BatchNo");
        $command         = Mage::helper('migspayment')->null2unknown($map, "vpc_Command");
        $version         = Mage::helper('migspayment')->null2unknown($map, "vpc_Version");
        $cardType        = Mage::helper('migspayment')->null2unknown($map, "vpc_Card");
        $orderInfo       = Mage::helper('migspayment')->null2unknown($map, "vpc_OrderInfo");
        $receiptNo       = Mage::helper('migspayment')->null2unknown($map, "vpc_ReceiptNo");
        $merchantID      = Mage::helper('migspayment')->null2unknown($map, "vpc_Merchant");
        $authorizeID     = Mage::helper('migspayment')->null2unknown($map, "vpc_AuthorizeId");
        $transactionNo   = Mage::helper('migspayment')->null2unknown($map, "vpc_TransactionNo");
        $acqResponseCode = Mage::helper('migspayment')->null2unknown($map, "vpc_AcqResponseCode");
        $txnResponseCode = Mage::helper('migspayment')->null2unknown($map, "vpc_TxnResponseCode");

        if ($txnResponseCode=='0') {

            $this->setStore($payment->getOrder()->getStoreId());
            $payment->setStatus(self::STATUS_APPROVED);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);

            $transaction = Mage::getModel('sales/order_payment_transaction');
            $transaction->setTxnId($transactionNo);
            $transaction->setOrderPaymentObject($payment)
            ->setTxnType(Mage_Sales_Model_Order_Payment_Transaction::TYPE_CAPTURE);
            $transaction->save();


        } else {

            $this->setStore($payment->getOrder()->getStoreId());
            $payment->setStatus(self::STATUS_DECLINED);
            $payment->setAmount($amount);
            $payment->setLastTransId($orderId);
            Mage::throwException(Mage::helper('migspayment')->getResponseDescription($txnResponseCode).' - '.$message);

        }
        return $this;
    }

}
?>
