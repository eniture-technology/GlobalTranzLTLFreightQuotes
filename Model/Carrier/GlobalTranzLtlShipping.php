<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Carrier;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Catalog\Model\ProductFactory;
use Magento\Checkout\Model\Cart;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Module\Manager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Carrier\AbstractCarrier;
use Magento\Shipping\Model\Carrier\CarrierInterface;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;

/**
 * @category   Shipping
 * @package    Eniture_GlobalTranzLTLFreight
 * @author     john@eniture-dev.com
 * @website    http://ess.eniture.com
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class GlobalTranzLtlShipping extends AbstractCarrier implements
    CarrierInterface
{
    /**
     * @var string
     */
    public $_code = EnConstants::APP_CODE;

    /**
     * @var bool
     */
    private $isFixed = true;

    /**
     * @var ResultFactory
     */
    private $rateResultFactory;

    /**
     * @var MethodFactory
     */
    private $rateMethodFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var Manager
     */
    private $moduleManager;

    /**
     * @var SessionManagerInterface
     */
    private $session;

    /**
     * @var ProductFactory
     */
    private $productLoader;

    /**
     * @var
     */
    private $mageVersion;

    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var UrlInterface
     */
    private $urlInterface;
    /**
     * @var Cart
     */
    private $cart;
    /**
     * @var GlobalTranzLTLGenerateRequestData
     */
    private $generateReqData;
    /**
     * @var GlobalTranzManageAllQuotes
     */
    private $manageAllQuotes;
    /**
     * @var GlobalTranzShipmentPackage
     */
    private $shipmentPkg;
    /**
     * @var GlobalTranzAdminConfiguration
     */
    private $adminConfig;
    /**
     * @var GlobalTranzSetCarriersGlobaly
     */
    private $setGlobalCarrier;
    /**
     * @var
     */
    private $weightUnit;
    /**
     * @var TimezoneInterface
     */
    private $timezone;
    /**
     * @var bool
     */
    private $freeShipping;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param ErrorFactory $rateErrorFactory
     * @param LoggerInterface $logger
     * @param ResultFactory $rateResultFactory
     * @param MethodFactory $rateMethodFactory
     * @param Cart $cart
     * @param Data $dataHelper
     * @param Registry $registry
     * @param Manager $moduleManager
     * @param UrlInterface $urlInterface
     * @param SessionManagerInterface $session
     * @param ProductFactory $productLoader
     * @param ProductMetadataInterface $productMetadata
     * @param ObjectManagerInterface $objectManager
     * @param GlobalTranzGenerateRequestData $generateReqData
     * @param GlobalTranzManageAllQuotes $manAllQuotes
     * @param GlobalTranzShipmentPackage $shipmentPkg
     * @param GlobalTranzAdminConfiguration $adminConfig
     * @param GlobalTranzSetCarriersGlobaly $setGlobalCarrier
     * @param RequestInterface $httpRequest
     * @param TimezoneInterface $timezone
     * @param array $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        Cart $cart,
        Data $dataHelper,
        Registry $registry,
        Manager $moduleManager,
        UrlInterface $urlInterface,
        SessionManagerInterface $session,
        ProductFactory $productLoader,
        ProductMetadataInterface $productMetadata,
        ObjectManagerInterface $objectManager,
        GlobalTranzGenerateRequestData $generateReqData,
        GlobalTranzManageAllQuotes $manAllQuotes,
        GlobalTranzShipmentPackage $shipmentPkg,
        GlobalTranzAdminConfiguration $adminConfig,
        GlobalTranzSetCarriersGlobaly $setGlobalCarrier,
        RequestInterface $httpRequest,
        TimezoneInterface $timezone,
        array $data = []
    ) {
        $this->rateResultFactory = $rateResultFactory;
        $this->rateMethodFactory = $rateMethodFactory;
        $this->scopeConfig = $scopeConfig;
        $this->cart = $cart;
        $this->dataHelper = $dataHelper;
        $this->registry = $registry;
        $this->moduleManager = $moduleManager;
        $this->urlInterface = $urlInterface;
        $this->session = $session;
        $this->productLoader = $productLoader;
        $this->mageVersion = $productMetadata->getVersion();
        $this->objectManager = $objectManager;
        $this->generateReqData = $generateReqData;
        $this->manageAllQuotes = $manAllQuotes;
        $this->shipmentPkg = $shipmentPkg;
        $this->adminConfig = $adminConfig;
        $this->setGlobalCarrier = $setGlobalCarrier;
        $this->request = $httpRequest;
        $this->timezone = $timezone;
        $this->weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit', ScopeInterface::SCOPE_STORE);
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->initClasses();
    }

    /**
     *
     */
    public function initClasses()
    {
        $this->adminConfig->_init($this->scopeConfig, $this->registry);

        $this->generateReqData->_init($this->scopeConfig, $this->registry, $this->moduleManager, $this->request, $this->timezone);

        $this->manageAllQuotes->_init($this->scopeConfig, $this->registry, $this->session, $this->objectManager);

        $this->shipmentPkg->_init($this->scopeConfig, $this->dataHelper, $this->productLoader, $this->request);

        $this->setGlobalCarrier->_init($this->dataHelper);
    }

    /**
     * @param RateRequest $request
     * @return boolean|object
     */
    public function collectRates(RateRequest $request)
    {
        $this->freeShipping = $request->getFreeShipping();

        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /*if (empty($request->getDestCity()) || empty($request->getDestRegionCode()) || empty($request->getDestPostcode()) || empty($request->getDestCountryId())) {
            return false;
        }*/
        $getQuotesFromSession = $this->quotesFromSession();
        if (null !== $getQuotesFromSession) {
            return $getQuotesFromSession;
        }
        $ItemsList = $request->getAllItems();
        $receiverZipCode = $request->getDestPostcode();
        $package = $this->getShipmentPackageRequest($ItemsList, $receiverZipCode, $request);
        $gtLtlArr = $this->generateReqData->generateEnitureArray();
        $gtLtlArr['originAddress'] = $package['origin'];
        $resp = $this->setGlobalCarrier->manageCarriersGlobaly($gtLtlArr, $this->registry);
        if (!$resp) {
            return false;
        }

        $requestArr = $this->generateReqData->generateRequestArray($request, $gtLtlArr, $package['items'], $this->cart);
        if (empty($requestArr)) {
            return false;
        }
        $url = EnConstants::QUOTES_URL;
        $quotes = $this->dataHelper->cerasisSendCurlRequest($url, $requestArr);
        // Debug point will print data if en_print_query=1
        if ($this->printQuery()) {
            $printData = [
                'url' => $url,
                'buildQuery' => http_build_query($requestArr),
                'request' => $requestArr,
                'quotes' => $quotes];
            print_r('<pre>');
            print_r($printData);
            print_r('</pre>');
            return;
        }

        $quotesResult = $this->manageAllQuotes->getQuotesResultArr($quotes);
        $this->session->setEnShippingQuotes($quotesResult);

        return (!empty($quotesResult)) ? $this->setCarrierRates($quotesResult) : '';
    }

    /**
     * @return object | null
     */
    public function quotesFromSession()
    {
        $currentAction = $this->urlInterface->getCurrentUrl();
        $currentAction = strtolower($currentAction);

        if (strpos($currentAction, 'totals-information') !== false
            || strpos($currentAction, 'shipping-information') !== false
            || strpos($currentAction, 'payment-information') !== false) {
            $sessionQuotes = $this->session->getEnShippingQuotes(); // FROM SESSSION
            $availableQuotes = (!empty($sessionQuotes)) ? $this->setCarrierRates($sessionQuotes) : null;
        } else {
            $availableQuotes = null;
        }
        return $availableQuotes;
    }

    /**
     * This function returns package array
     * @param $items
     * @param $receiverZipCode
     * @param $request
     * @return array
     * getShipmentPackageRequest
     */
    public function getShipmentPackageRequest($items, $receiverZipCode, $request)
    {
        $tempQty = 0;
        $package = [];
        foreach ($items as $item) {
            $_product = $this->productLoader->create()->load($item->getProductId());
            $productType = $item->getRealProductType() ?? $_product->getTypeId();

            if ($productType == 'configurable') {
                $tempQty = $item->getQty();
            }
            if ($productType == 'simple') {
                $productQty = ($tempQty > 0) ? $tempQty : $item->getQty();
                $tempQty = 0;
                $originAddress = $this->shipmentPkg->cerasisOriginAddress($request, $_product, $receiverZipCode);
                $package['origin'][$_product->getId()] = $originAddress;

                $orderWidget[$originAddress['senderZip']]['origin'] = $originAddress;

                $weight = $this->getFtLbsUnit($_product->getWeight(), 'w');
                $length = $this->getFtLbsUnit($this->getDims($_product, 'length'), 'd');
                $width  = $this->getFtLbsUnit($this->getDims($_product, 'width'), 'd');
                $height = $this->getFtLbsUnit($this->getDims($_product, 'height'), 'd');

                $setHzAndIns = $this->setHzAndIns($_product);
                $lineItemClass = $this->getLineItemClass($_product);
                $lineItem = [
                    'lineItemClass' => $lineItemClass,
                    'freightClass' => $this->isLTL($_product),
                    'lineItemId' => $_product->getId(),
                    'lineItemName' => $_product->getName(),
                    'piecesOfLineItem' => $productQty,
                    'lineItemPrice' => $_product->getPrice(),
                    'lineItemWeight' => number_format($weight, 2, '.', ''),
                    'lineItemLength' => number_format($length, 2, '.', ''),
                    'lineItemWidth' => number_format($width, 2, '.', ''),
                    'lineItemHeight' => number_format($height, 2, '.', ''),
                    'isHazmatLineItem' => $setHzAndIns['hazmat'],
                    'product_insurance_active' => $setHzAndIns['insurance'],
                    'shipBinAlone' => $_product->getData('en_own_package'),
                    'vertical_rotation' => $_product->getData('en_vertical_rotation'),
                ];

                $package['items'][$_product->getId()] = $lineItem;
                $orderWidget[$originAddress['senderZip']]['item'][] = $package['items'][$_product->getId()];
            }
        }

        if (isset($orderWidget) && !empty($orderWidget)) {
            $uniqueOrigins = [];
            foreach ($orderWidget as $data) {
                $uniqueOrigins [] = $data['origin'];
            }
            $this->setDataInRegistry($uniqueOrigins, $orderWidget);
        }

        return $package;
    }

    /**
     * @param $value float
     * @param $entity string
     * @return float
     * This function standardises the units to FPS
     */
    public function getFtLbsUnit($value, $entity)
    {
        if ($this->weightUnit === 'kgs') {

            switch ($entity) {
                case 'w':
                    $value *= 2.20462262185;
                    break;

                case 'd':
                    $value /= 2.54;
                    break;
            }
        }

        return $value;
    }

    /**
     * @param $origin
     * @param $orderWidget
     */
    public function setDataInRegistry($origin, $orderWidget)
    {
        // set order detail widget data
        if ($this->registry->registry('setPackageDataForOrderDetail') === null) {
            $this->registry->register('setPackageDataForOrderDetail', $orderWidget);
        }

        // set shipment origin globally for instore pickup and local delivery
        if ($this->registry->registry('shipmentOrigin') === null) {
            $this->registry->register('shipmentOrigin', $origin);
        }
    }

    /**
     * @param object $_product
     * @return array
     */
    private function setHzAndIns($_product)
    {
        $hazmat = ($_product->getData('en_hazmat')) ? 'Y' : 'N';
        $insurance = $_product->getData('en_insurance');
        if ($insurance && $this->registry->registry('en_insurance') === null) {
            $this->registry->register('en_insurance', $insurance);
        }
        return ['hazmat' => $hazmat,
            'insurance' => $insurance
        ];
    }

    /**
     * @param $_product
     * @return float|int|string
     */
    private function getLineItemClass($_product)
    {
        $lineItemClass = $_product->getData('en_freight_class');
        switch ($lineItemClass) {
            case 77:
                $lineItemClass = 77.5;
                break;
            case 92:
                $lineItemClass = 92.5;
                break;
            case 1:
                $lineItemClass = 'DensityBased';
                break;
            default:
                break;
        }
        return $lineItemClass;
    }

    /**
     * @param $_product
     * @return string
     */
    private function isLTL($_product)
    {
        $path = 'gtQuoteSetting/fourth/weightExeeds';
        $weightExceedOpt = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);

        if ($this->registry->registry('weightConfigExceedOpt') === null) {
            $this->registry->register('weightConfigExceedOpt', $weightExceedOpt);
        }

        $isEnableLtl = $_product->getData('en_ltl_check');
        if (($isEnableLtl) || ($_product->getWeight() > 150 && $weightExceedOpt)) {
            $freightClass = 'ltl';
        } else {
            $freightClass = '';
        }

        return $freightClass;
    }

    /**
     * @param $_product
     * @param $dimOf
     * @return float
     */
    private function getDims($_product, $dimOf)
    {
        $prefix = ($this->mageVersion < '2.2.5' || $this->mageVersion > '2.3.2') ? 'en_' : 'ts_dimensions_';
        return $_product->getData($prefix.$dimOf);
    }

    public function cerasisGetConfigVal()
    {
        $this->scopeConfig->getValue('dev/debug/template_hints', ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $allowed = explode(',', $this->getConfigData('allowed_methods'));
        $arr = [];
        foreach ($allowed as $k) {
            $arr[$k] = $this->getCode('method', $k);
        }
        return $arr;
    }

    /**
     * Get configuration data of carrier
     *
     * @param string $type
     * @param string $code
     * @return array|false
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function getCode($type, $code = '')
    {
        $codes = [
            'method' => [
                'PYLE' => __('A. Duie Pyle, Inc.'),
                'AACT' => __('AAA Cooper Transportation'),
                'CHRW-DEMO' => __('C.H. Robinson Worldwide - TRUCKLOAD'),
                'CENF' => __('Central Freight Lines, Inc'),
                'CMMS-DEMO' => __('Command Transportation LLC - TRUCKLOAD'),
                'DAFG' => __('Dayton Freight'),
                'DHRN' => __('Dohrn Transfer Company'),
                'EXLA' => __('Estes Express Lines'),
                'EXLA-ENVOY' => __('Estes Envoy (E)'),
                'FXNL' => __('FedExFreightEconomy'),
                'HJBT-DEMO' => __('J.B. Hunt - TRUCKLOAD'),
                'KNIT-DEMO' => __('Knight Transportation - TRUCKLOAD'),
                'LKVL' => __('Lakeville Motor Express Inc'),
                'MIDW' => __('Midwest Motor Express'),
                'NMTF' => __('N&M Transfer Co., Inc.'),
                'NEMF' => __('New England Motor Freight'),
                'NEMF-CN' => __('New England Motor Freight-Canada'),
                'NPME' => __('New Penn Motor Express'),
                'PITD' => __('Pitt Ohio Express, LLC'),
                'RLCA' => __('R & L Carriers'),
                'RLCA-ENVOY' => __('R & L Carriers (E)'),
                'RETL' => __('Reddaway'),
                'RDFS' => __('Roadrunner Transportation Services'),
                'SAIA' => __('Saia, Inc.'),
                'SEFL' => __('Southeastern Freight Lines'),
                'UPGF' => __('UPS Freight'),
                'UPGF-CAUS' => __('UPS Freight-Canada to US'),
                'UPGF-CN' => __('UPS Freight-Canada'),
                'HMES' => __('USF Holland LLC'),
                'WTVA' => __('Wilson Trucking'),
                'RDWY' => __('YRC'),
                'RDWY-CN' => __('YRC Freight - Canada'),
                'YRCA' => __('YRC Freight Accelerated'),
                'CMS' => __('Freight'),
                'LCR' => __('Lowest Cost Rate'),
                'QTR' => __('Quickest Transit Rate')
            ],
        ];

        if (!isset($codes[$type])) {
            return false;
        } elseif ('' === $code) {
            return $codes[$type];
        }

        if (!isset($codes[$type][$code])) {
            return false;
        } else {
            return $codes[$type][$code];
        }
    }

    public function setCarrierRates($quotes)
    {
        if (empty($quotes)) {
            //To show error
            $error = $this->_rateErrorFactory->create();
            $error->setCarrier($this->_code);
            $error->setCarrierTitle($this->getConfigData('title'));
            $error->setErrorMessage($this->getConfigData('specificerrmsg'));
            return $error;
        } else {
            $carriersArray = $this->registry->registry('enitureCarrierCodes');
            $carriersTitle = $this->registry->registry('enitureCarrierTitle');
            $result = $this->rateResultFactory->create();
            foreach ($quotes as $carrierKey => $quote) {
                foreach ($quote as $key => $carrier) {
                    if (isset($carrier['code'])) {
                        $carrierCode = $carriersArray[$carrierKey] ?? $this->_code;
                        $carrierTitle = $carriersTitle[$carrierKey] ?? $this->getConfigData('title');
                        $method = $this->rateMethodFactory->create();
                        $price = $this->freeShipping ? 0 : $carrier['rate'];
                        $method->setCarrier($carrierCode);
                        $method->setCarrierTitle($carrierTitle);
                        $method->setMethod($carrier['code']);
                        $method->setMethodTitle($carrier['title']);
                        $method->setPrice($price);
                        $method->setCost($price);
                        $result->append($method);
                    }
                }
            }
            $this->registry->unregister('enitureCarriers');
            return $result;
        }
    }

    public function printQuery()
    {
        $printQuery = 0;
        parse_str(parse_url($this->request->getServer('HTTP_REFERER'), PHP_URL_QUERY), $query);

        if (!empty($query)) {
            $printQuery = ($query['en_print_query']) ?? 0;
        }
        return $printQuery;
    }
}
