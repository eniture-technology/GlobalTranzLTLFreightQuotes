<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Sales\Block\Adminhtml\Order;

use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Block\Adminhtml\Order\View as OrderView;

class BillOfLadding
{
    /** @var object Order Detail Information */
    protected $orderDetail;

    /** @var object BOL Data */
    protected $billOfLaddingData;

    /** @var string BOL Button Label */
    protected $bolLabel;

    /** @var string Order Shipment Method */
    protected $method;

    /** @var string Shipping Carrier */
    protected $carrier;

    /** @var string bolFunctionName */
    protected $bolFunctionName;

    /** @var \Magento\Framework\UrlInterface */
    protected $_urlBuilder;

    /** @var \Magento\Framework\AuthorizationInterface */
    protected $_authorization;

    /** @var \Magento\Framework\ScopeInterface */
    protected $_scopeConfig;

    protected $_connection;

    protected $_tableName;
    /**
     * Reinitable Config Model.
     *
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    /**
     *
     * @param UrlInterface $url
     * @param AuthorizationInterface $authorization
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\UrlInterface $urlInterface
     */
    public function __construct(
        UrlInterface $url,
        AuthorizationInterface $authorization,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\UrlInterface $urlInterface,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\App\Cache\Frontend\Pool $cacheFrontendPool,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->_urlBuilder = $url;
        $this->_authorization = $authorization;
        $this->_coreRegistry = $registry;
        $this->_scopeConfig = $scopeConfig;
        $this->_urlInterface = $urlInterface;
        $this->_connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->_tableName = $resource->getTableName('BillOfLadding');
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->reinitableConfig = $reinitableConfig;
    }

    /**
     *
     * @param OrderView $view
     */
    public function beforeSetLayout(OrderView $view)
    {
        $orderData = $this->_coreRegistry->registry('sales_order');
        $this->setBOLButtonLabel($orderData->getIncrementId());

        $shippingMethod = $orderData->getShippingMethod();

        $explode = explode('_', $shippingMethod);
        $this->method = (isset($explode[0])) ? $explode[0] : '';
        $carriersList = $this->_scopeConfig->getValue('gtLtlCarriers/second/selectedCarriers', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if(!empty($carriersList) && is_string($carriersList)){
            $carrier = json_decode($carriersList, true);
        }else{
            $carrier = [];
        }

        if ($this->method == 'ENGlobalTranzLTL') {
            if (in_array($explode[1], $carrier)) {
                $this->bolFeatureButtons($view, $orderData->getIncrementId());
            }
        }
    }

    public function bolFeatureButtons($view, $orderIncrementId)
    {
        $senderInfo = $this->_scopeConfig->getValue("gtLtlCarriers/bol/senderinfo", \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        if ($this->bolLabel == 'View Bill of Lading') {
            $view->addButton(
                'viewtracking',
                [
                    'label' => __('View Tracking'),
                    'onclick' => "javascript:checkTrackingStatus('" . $this->bolNumber . "', '" . $this->bolTrackingUrl . "', '" . $this->getTrackingAjaxUrl() . "')",
                    'class' => 'tracking-button'
                ]
            );
        }

        $view->addButton(
            'billofladding',
            [
                'label' => __($this->bolLabel),
                'onclick' => "javascript:$this->bolFunctionName('" . $orderIncrementId . "','" . $this->getBOLAjaxUrl() . "','" . $this->printBOLAjaxUrl() . "'," . $senderInfo . "); return false;",
                'class' => 'bol-button'
            ]
        );
    }

    /**
     * Button BOL Button Label
     */
    public function setBOLButtonLabel($orderID)
    {
        $getBolData = $this->_connection->fetchAll("SELECT `bol_print`, `bol_number`, `tracking_url` FROM $this->_tableName WHERE `order_id` = '" . $orderID . "'");
        $getBolData = reset($getBolData);
        $this->bolLabel = (isset($getBolData) && $getBolData['bol_print'] == 'yes') ? 'View Bill of Lading' : 'Print Bill of Lading';
        $this->bolFunctionName = ($this->bolLabel == 'View Bill of Lading') ? 'cerasisBillOfLadding' : 'cerasisBOLNameField';
        $this->bolTrackingUrl = (isset($getBolData['tracking_url'])) ? $getBolData['tracking_url'] : '';
        $this->bolNumber = (isset($getBolData['bol_number'])) ? $getBolData['bol_number'] : '';
    }

    /**
     * Retrieve order model object
     *
     * @return \Magento\Sales\Model\Order
     */
    public function getOrder()
    {
        return $this->_coreRegistry->registry('sales_order');
    }

    public function getBOLAjaxUrl()
    {
        return $this->_urlInterface->getbaseUrl() . '/GlobalTranzLTLFreight/Adminhtml/BillOfLadding/';
    }

    public function printBOLAjaxUrl()
    {
        return $this->_urlInterface->getbaseUrl() . '/GlobalTranzLTLFreight/Adminhtml/BillOfLadding/';
    }

    public function getTrackingAjaxUrl()
    {
        return $this->_urlInterface->getbaseUrl() . '/GlobalTranzLTLFreight/Adminhtml/GetTrackingStatus/';
    }

    public function clearMagentoCache()
    {
        $this->reinitableConfig->reinit();
        $types = ['config', 'layout', 'block_html', 'collections', 'reflection', 'db_ddl', 'eav', 'config_integration', 'config_integration_api', 'full_page', 'translate', 'config_webservice'];
        foreach ($types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
