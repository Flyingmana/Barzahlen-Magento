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

abstract class ZerebroInternet_Barzahlen_Model_Api_Request_Abstract extends ZerebroInternet_Barzahlen_Model_Api_Abstract
{
    protected $_isValid = false; //!< requests validity
    protected $_type; //!< request type
    protected $_xmlObj; //!< SimpleXMLElement
    protected $_xmlAttributes = array(); //!< expected xml nodes
    protected $_xmlData = array(); //!< array with parsed xml data

    abstract public function buildRequestArray($shopId, $paymentKey, $language);
    /**
     * Returns request type.
     *
     * @return type of request
     */
    public function getRequestType()
    {
        return $this->_type;
    }

    /**
     * Gets state of validity.
     *
     * @return boolean if request response is valid
     */
    public function isValid()
    {
        return $this->_isValid;
    }

    /**
     * Returns a single value from the xml array or the whole array.
     *
     * @param string $attribute single attribute, that shall be returned
     * @return single value if exists (else: null) or whole array
     */
    public function getXmlArray($attribute = '')
    {
        if ($attribute != '') {
            return array_key_exists($attribute, $this->_xmlData) ? $this->_xmlData[$attribute] : null;
        }

        return $this->_xmlData;
    }

    /**
     * Function to get response data out of received xml string.
     *
     * @param array $xmlResponse array with the response from the server
     * @param string $paymentKey merchants payment key
     * @return array with parsed xml data
     */
    public function parseXml($xmlResponse, $paymentKey)
    {
        if (!is_string($xmlResponse) || $xmlResponse == '') {
            Mage::throwException('No valid xml response received.');
        }

        try {
            $this->_xmlObj = new SimpleXMLElement($xmlResponse);
        } catch (Exception $e) {
            Mage::throwException($e->getMessage());
        }

        $this->_getXmlError();
        $this->_getXmlAttributes();
        $this->_checkXmlHash($paymentKey);
        $this->_isValid = true;
    }

    /**
     * Checks if an error occurred.
     */
    protected function _getXmlError()
    {
        if ($this->_xmlObj->{'result'} != 0) {
            Mage::throwException('XML response contains an error: ' . $this->_xmlObj->{'error-message'});
        }
    }

    /**
     * Gets attributes from xml object depending on its type.
     *
     * @param string $responseType type for xml response
     */
    protected function _getXmlAttributes()
    {
        $this->_xmlData = array();

        foreach ($this->_xmlAttributes as $attribute) {
            $this->_xmlData[$attribute] = (string) $this->_xmlObj->{$attribute};
        }
    }

    /**
     * Checks if hash is valid.
     *
     * @param string $paymentKey merchants payment key
     */
    protected function _checkXmlHash($paymentKey)
    {
        $receivedHash = $this->_xmlData['hash'];
        unset($this->_xmlData['hash']);
        $generatedHash = $this->_createHash($this->_xmlData, $paymentKey);

        if ($receivedHash != $generatedHash) {
            Mage::throwException('response - xml hash not valid');
        }

        unset($this->_xmlData['result']);
    }
}
