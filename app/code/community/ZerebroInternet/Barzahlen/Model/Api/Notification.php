<?php
/**
 * Barzahlen Payment Module SDK for Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to info@barzahlen.de so we can send you a copy immediately.
 *
 * @category    ZerebroInternet
 * @package     ZerebroInternet_Barzahlen
 * @copyright   Copyright (c) 2013 Zerebro Internet GmbH (http://www.barzahlen.de)
 * @author      Alexander Diebler
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL-3.0)
 */

class ZerebroInternet_Barzahlen_Model_Api_Notification extends ZerebroInternet_Barzahlen_Model_Api_Abstract
{
    protected $_isValid = false; //!< state of validity
    protected $_shopId; //!< merchants shop id
    protected $_notificationKey; //!< merchants notification key
    protected $_receivedData; //!< data which were send by Barzahlen
    protected $_notificationType = 'payment'; //!< type of notification (payment or refund)
    protected $_notficationData = array('state', 'transaction_id', 'shop_id', 'customer_email', 'amount',
        'currency', 'hash'); //!< all necessary attributes for a valid notification
    protected $_originData = array('transaction_id', 'order_id'); //!< origin values for refund notifications

    /**
     * Constructor. Sets basic settings. Adjusted for Magento
     *
     * @param array $arguements array with settings
     */
    public function __construct(array $arguments)
    {
        $this->_shopId = $arguments['shopId'];
        $this->_notificationKey = $arguments['notificationKey'];
        $this->_receivedData = $arguments['receivedData'];
    }

    /**
     * Validates the received data. Throws exception when an error occurrs.
     */
    public function validate()
    {
        $this->_checkExistence();
        $this->_checkValues();
        $this->_checkHash();
        $this->_isValid = true;
    }

    /**
     * Gets state of validity.
     *
     * @return boolean if notification is valid
     */
    public function isValid()
    {
        return $this->_isValid;
    }

    /**
     * Checks that all attributes are available.
     */
    protected function _checkExistence()
    {
        if (array_key_exists('refund_transaction_id', $this->_receivedData)) {
            $this->_notificationType = 'refund';
            foreach ($this->_originData as $attribute) {
                $this->_notficationData = str_replace($attribute, 'origin_' . $attribute, $this->_notficationData);
                $this->_notficationData[] = 'refund_transaction_id';
            }
        }

        foreach ($this->_notficationData as $attribute) {
            if (!array_key_exists($attribute, $this->_receivedData)) {
                Mage::throwException('Notification array not complete, at least ' . $attribute . ' is missing.');
            }
        }
    }

    /**
     * Checks that attribute values are as expected.
     */
    protected function _checkValues()
    {
        if ($this->_notificationType == 'refund') {
            if (!is_numeric($this->_receivedData['refund_transaction_id'])) {
                Mage::throwException('Refund transaction id is not numeric.');
            }
            if (!is_numeric($this->_receivedData['origin_transaction_id'])) {
                Mage::throwException('Origin transaction id is not numeric.');
            }
        } else {
            if (!is_numeric($this->_receivedData['transaction_id'])) {
                Mage::throwException('Transaction id is not numeric.');
            }
        }

        if ($this->_shopId != $this->_receivedData['shop_id']) {
            Mage::throwException('Shop id doesn\'t match the given value.');
        }

        if (!preg_match('/^\d{1,3}(\.\d\d?)?$/', $this->_receivedData['amount'])) {
            Mage::throwException('Amount is no valid value.');
        }
    }

    /**
     * Checks that received hash is valid.
     */
    protected function _checkHash()
    {
        $receivedHash = $this->_receivedData['hash'];
        $hashArray = $this->_sortAttributes();
        $generatedHash = $this->_createHash($hashArray, $this->_notificationKey);

        if ($receivedHash != $generatedHash) {
            Mage::throwException('Notification hash is not valid.');
        }
    }

    /**
     * Puts $_GET attributes in the right order.
     *
     * @return array for hash generation
     */
    protected function _sortAttributes()
    {
        $hashArray = array();
        $hashArray[] = $this->_receivedData['state'];
        if ($this->_notificationType == 'refund') {
            $hashArray[] = $this->_receivedData['refund_transaction_id'];
            $hashArray[] = $this->_receivedData['origin_transaction_id'];
        } else {
            $hashArray[] = $this->_receivedData['transaction_id'];
        }
        $hashArray[] = $this->_receivedData['shop_id'];
        $hashArray[] = $this->_receivedData['customer_email'];
        $hashArray[] = $this->_receivedData['amount'];
        $hashArray[] = $this->_receivedData['currency'];
        if ($this->_notificationType == 'refund') {
            $hashArray[] = isset($this->_receivedData['origin_order_id']) ? $this->_receivedData['origin_order_id'] : '';
        } else {
            $hashArray[] = isset($this->_receivedData['order_id']) ? $this->_receivedData['order_id'] : '';
        }
        $hashArray[] = isset($this->_receivedData['custom_var_0']) ? $this->_receivedData['custom_var_0'] : '';
        $hashArray[] = isset($this->_receivedData['custom_var_1']) ? $this->_receivedData['custom_var_1'] : '';
        $hashArray[] = isset($this->_receivedData['custom_var_2']) ? $this->_receivedData['custom_var_2'] : '';

        return $hashArray;
    }

    /**
     * Returns a single value from the notification array or the whole array.
     *
     * @param string $attribute single attribute, that shall be returned
     * @return single value if exists (else: null) or whole array
     */
    public function getNotificationArray($attribute = '')
    {
        if (!$this->_isValid) {
            return null;
        }

        if ($attribute != '') {
            return array_key_exists($attribute, $this->_receivedData) ? $this->_receivedData[$attribute] : null;
        }

        return $this->_receivedData;
    }

    /**
     * Returns notification type.
     *
     * @return string with notification type
     */
    public function getNotificationType()
    {
        return $this->_isValid ? $this->_notificationType : null;
    }

    /**
     * Returns notification state.
     *
     * @return string with state
     */
    public function getState()
    {
        return $this->getNotificationArray('state');
    }

    /**
     * Returns refund transaction id.
     *
     * @return string with refund transaction id
     */
    public function getRefundTransactionId()
    {
        return $this->getNotificationArray('refund_transaction_id');
    }

    /**
     * Returns transaction id.
     *
     * @return string with transaction id
     */
    public function getTransactionId()
    {
        return $this->getNotificationArray('transaction_id');
    }

    /**
     * Returns origin transaction id.
     *
     * @return string with origin transaction id
     */
    public function getOriginTransactionId()
    {
        return $this->getNotificationArray('origin_transaction_id');
    }

    /**
     * Returns shop id.
     *
     * @return string with shop id
     */
    public function getShopId()
    {
        return $this->getNotificationArray('shop_id');
    }

    /**
     * Returns customer e-mail.
     *
     * @return string with customer e-mail
     */
    public function getCustomerEmail()
    {
        return $this->getNotificationArray('customer_email');
    }

    /**
     * Returns amount.
     *
     * @return string with amount
     */
    public function getAmount()
    {
        return $this->getNotificationArray('amount');
    }

    /**
     * Returns currency.
     *
     * @return string with currency
     */
    public function getCurrency()
    {
        return $this->getNotificationArray('currency');
    }

    /**
     * Returns order id.
     *
     * @return string with order id
     */
    public function getOrderId()
    {
        return $this->getNotificationArray('order_id');
    }

    /**
     * Returns origin order id.
     *
     * @return string with origin order id
     */
    public function getOriginOrderId()
    {
        return $this->getNotificationArray('origin_order_id');
    }

    /**
     * Returns customer var 0.
     *
     * @return string with custom var
     */
    public function getCustomVar0()
    {
        return $this->getNotificationArray('custom_var_0');
    }

    /**
     * Returns customer var 1.
     *
     * @return string with custom var
     */
    public function getCustomVar1()
    {
        return $this->getNotificationArray('custom_var_1');
    }

    /**
     * Returns customer var 2.
     *
     * @return string with custom var
     */
    public function getCustomVar2()
    {
        return $this->getNotificationArray('custom_var_2');
    }

    /**
     * Returns customer var as array.
     *
     * @return array with custom variables
     */
    public function getCustomVar()
    {
        return array($this->getCustomVar0(), $this->getCustomVar1(), $this->getCustomVar2());
    }
}
