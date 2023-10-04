<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Adminhtml;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use \Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;

class GetTrackingStatus extends Action
{
    /**
     * @var Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var Eniture\GlobalTranzLTLFreightQuotes\Helper\Data
     */
    protected $cerasisHelper;

    /**
     * @var URL API Call URL
     */
    protected $curlUrl;

    /**
     * @var Object Database Read Object
     */
    protected $connection;

    /**
     *
     * @var string BOL Database Table Name
     */
    protected $tableName;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\App\ResourceConnection $resource,
        \Eniture\GlobalTranzLTLFreightQuotes\Helper\Data $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->cerasisHelper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->tableName = $resource->getTableName('BillOfLadding');
        $this->curlUrl = EnConstants::INDEX;
        parent::__construct($context);
    }

    /**
     * BOL API call
     * Ajax Response
     */
    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $bolNumber = isset($postData['bolNumber']) ? htmlspecialchars($postData['bolNumber'], ENT_QUOTES) : '';
        $reqData = [
            'dont_auth' => '1',
            'licence_key' => $this->scopeConfig->getValue('gtConnSettings/first/licnsKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'server_name' => $_SERVER['SERVER_NAME'],
            'carrierName' => 'cerasis',
            'carrier_mode' => isset($postData['action']) ? htmlspecialchars($postData['action'], ENT_QUOTES) : '',
            'shipperID' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlshipperID', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'username' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlusername', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'password' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlPassword', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'accessKey' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlAccessKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'shipmentBillNumber' => $bolNumber
        ];

        $trackingResponse = $this->cerasisHelper->cerasisSendCurlRequest($this->curlUrl, $reqData);

        if (isset($trackingResponse->status) && $trackingResponse->status->TrackingCode) {
            $this->connection->update($this->tableName, ['tracking_code' => $trackingResponse->status->TrackingCode, 'tracking_url' => $trackingResponse->status->TrackingURL], "bol_number = '$bolNumber'");
        }

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode(['SUCCESS' => 'SUCCESS', 'trackingUrl' => $trackingResponse->status->TrackingURL]));
    }
}
