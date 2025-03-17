<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Helper;

use Eniture\GlobalTranzLTLFreightQuotes\Model\Interfaces\DataHelperInterface;
use Eniture\GlobalTranzLTLFreightQuotes\Model\WarehouseFactory;
use Magento\Framework\App\Cache\Manager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Framework\Registry;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Shipping\Model\Carrier\AbstractCarrierInterface;
use Magento\Shipping\Model\Config;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper implements DataHelperInterface
{
    /**
     * @var Modulemanager Object
     */
    private $moduleManager;
    /**
     * @var Conn Object
     */
    private $connection;
    /**
     * @var Warehouse Table
     */
    private $WHTableName;
    /**
     * @var ship Config Object
     */
    private $shippingConfig;
    /**
     * @var context
     */
    private $context;
    /**
     * @var bool
     */
    public $canAddWh = 1;
    /**
     * @var Country
     */
    private $warehouseFactory;
    /**
     * @var Curl
     */
    private $curl;

    /**
     * @var Registry
     */
    private $registry;

    /**
     * @var bool
     */
    private $isResi = false;

    private $residentialDelivery;
    /**
     * @var SessionManagerInterface
     */
    public $coreSession;
    /**
     * @var string
     */
    private $resiLabel;
    /**
     * @var string
     */
    private $lgLabel;
    /**
     * @var string
     */
    private $resiLgLabel;
    /**
     * @var Manager
     */
    private $cacheManager;

    public $isMultiShipment = false;
    private $configWriter;
    private $timezoneInterface;
    /*
     * @var configSettings
     * */
    public $configSettings;

    private $endPoint;
    public $scopeConfig;

    private $liftGateArray = [];
    private $quotes;
    private $totalCarriers;
    private $OfferLiftgateAsAnOption;
    private $RADforLiftgate;
    private $residentialDlvry;
    private $liftGate;
    private $hndlngFee;
    private $symbolicHndlngFee;
    private $ownArangement;
    private $ownArangementText;

    /**
     * @param Context $context
     * @param Manager $moduleManager
     * @param ResourceConnection $resource
     * @param Config $shippingConfig
     * @param WarehouseFactory $warehouseFactory
     * @param Curl $curl
     * @param Registry $registry
     * @param SessionManagerInterface $coreSession
     * @param Manager $cacheManager
     * @param TimezoneInterface $timezoneInterface
     * @param WriterInterface $configWriter
     */
    public function __construct(
        Context $context,
        Manager $moduleManager,
        ResourceConnection $resource,
        Config $shippingConfig,
        WarehouseFactory $warehouseFactory,
        Curl $curl,
        Registry $registry,
        SessionManagerInterface $coreSession,
        Manager $cacheManager,
        TimezoneInterface $timezoneInterface,
        WriterInterface $configWriter
    )
    {
        $this->moduleManager = $context->getModuleManager();
        $this->connection = $resource->getConnection(ResourceConnection::DEFAULT_CONNECTION);
        $this->WHTableName = $resource->getTableName('warehouse');
        $this->shippingConfig = $shippingConfig;
        $this->context = $context;
        $this->warehouseFactory = $warehouseFactory;
        $this->curl = $curl;
        $this->registry = $registry;
        $this->coreSession = $coreSession;
        $this->cacheManager = $cacheManager;
        $this->timezoneInterface = $timezoneInterface;
        $this->configWriter = $configWriter;
        parent::__construct($context);
    }

    /**
     * @param string $location
     * @return array
     */
    public function fetchWarehouseSecData($location)
    {
        $whCollection = $this->warehouseFactory->create()->getCollection()->addFilter('location', ['eq' => $location]);
        return $this->purifyCollectionData($whCollection);
    }

    /**
     * @param $whCollection
     * @return array
     */
    public function purifyCollectionData($whCollection)
    {
        $warehouseSecData = [];
        foreach ($whCollection as $wh) {
            $warehouseSecData[] = $wh->getData();
        }
        return $warehouseSecData;
    }

    /**
     * @param $location
     * @param $warehouseId
     * @return array
     */
    public function fetchWarehouseWithID($location, $warehouseId)
    {
        try {
            $whFactory = $this->warehouseFactory->create();
            $dsCollection = $whFactory->getCollection()
                ->addFilter('location', ['eq' => $location])
                ->addFilter('warehouse_id', ['eq' => $warehouseId]);
            return $this->purifyCollectionData($dsCollection);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * @param $data
     * @param $whereClause
     * @return int
     */
    public function updateWarehouseData($data, $whereClause)
    {
        return $this->connection->update("$this->WHTableName", $data, "$whereClause");
    }

    /**
     * @param $data
     * @param $id
     * @return array
     */
    public function insertWarehouseData($data, $id)
    {
        $insertQry = $this->connection->insert("$this->WHTableName", $data);
        if ($insertQry == 0) {
            $lastId = $id;
        } else {
            $lastId = $this->connection->lastInsertId();
        }
        return ['insertId' => $insertQry, 'lastId' => $lastId];
    }

    /**
     * @param $data
     * @return int
     */
    public function deleteWarehouseSecData($data)
    {
        try {
            $response = $this->connection->delete("$this->WHTableName", $data);
        } catch (\Throwable $e) {
            $response = 0;
        }
        return $response;
    }

    /**
     *
     * @param $arr
     * @param $set
     * @return array
     */
    public function recursiveChangeKey($arr, $set)
    {
        if (is_array($arr) && is_array($set)) {
            $newArr = [];
            foreach ($arr as $k => $v) {
                $key = array_key_exists($k, $set) ? $set[$k] : $k;
                $newArr[$key] = is_array($v) ? $this->recursiveChangeKey($v, $set) : $v;
            }
            return $newArr;
        }
        return $arr;
    }

    /**
     * Data Array
     * @param $inputData
     * @return array
     */

    public function originArray($inputData)
    {
        $dataArr = [
            'city' => $inputData['city'],
            'state' => $inputData['state'],
            'zip' => $inputData['zip'],
            'country' => $inputData['country'],
            'location' => $inputData['location'],
            'nickname' => (isset($inputData['nickname'])) ? $inputData['nickname'] : '',
            'in_store' => 'null',
            'local_delivery' => 'null',
        ];
        $plan = $this->planInfo();
        if ($plan['planNumber'] == 3) {
            $suppressOption = ($inputData['ld_sup_rates'] === 'on') ? 1 : 0;
            //if (isset($inputData['instore_enable'])) {
            $pickupDeliveryArr = [
                'enable_store_pickup' => ($inputData['instore_enable'] === 'on') ? 1 : 0,
                'miles_store_pickup' => $inputData['is_within_miles'],
                'match_postal_store_pickup' => $inputData['is_postcode_match'],
                'checkout_desc_store_pickup' => $inputData['is_checkout_descp'],
                'suppress_other' => $suppressOption,
            ];
            $dataArr['in_store'] = json_encode($pickupDeliveryArr);

            //if ($inputData['ld_enable'] === 'on') {
            $localDeliveryArr = [
                'enable_local_delivery' => ($inputData['ld_enable'] === 'on') ? 1 : 0,
                'miles_local_delivery' => $inputData['ld_within_miles'],
                'match_postal_local_delivery' => $inputData['ld_postcode_match'],
                'checkout_desc_local_delivery' => $inputData['ld_checkout_descp'],
                'fee_local_delivery' => $inputData['ld_fee'],
                'suppress_other' => $suppressOption,
            ];
            $dataArr['local_delivery'] = json_encode($localDeliveryArr);
        }
        return $dataArr;
    }

    /**
     * setting properties dynamically
     */
    public function quoteSettingsData()
    {
        $this->ownArangement = $this->configSettings['ownArangement'] ?? '';
        $this->ownArangementText = $this->configSettings['ownArangementText'] ?? '';
        $this->residentialDlvry = $this->configSettings['residentialDlvry'] ?? '';
        $this->liftGate = $this->configSettings['liftGate'] ?? '';
        $this->OfferLiftgateAsAnOption = $this->configSettings['offerLiftGate'] ?? '';
        $this->RADforLiftgate = $this->configSettings['RADforLiftgate'] ?? '';
        $this->hndlngFee = $this->configSettings['hndlngFee'] ?? '';
        $this->symbolicHndlngFee = $this->configSettings['symbolicHndlngFee'] ?? '';

        $this->resiLabel = ' with residential delivery';
        $this->lgLabel = ' with lift gate delivery';
        $this->resiLgLabel = ' with residential delivery and lift gate delivery';
    }

    /**
     * This function send request and return response
     * $isAssocArray Parameter When TRUE, then returned objects will
     * be converted into associative arrays, otherwise its an object
     * @param $url
     * @param $postData
     * @param bool $isAssocArray
     * @return array|mixed
     */
    public function cerasisSendCurlRequest($url, $postData, $isAssocArray = false)
    {
        $fieldString = http_build_query($postData);
        try {
            $this->curl->post($url, $fieldString);
            $output = $this->curl->getBody();
            if(!empty($output) && is_string($output)){
                $response = json_decode($output, $isAssocArray);
            }else{
                $response = ($isAssocArray) ? [] : '';
            }
            
        } catch (\Throwable $e) {
            $response = [];
        }
        
        return $response;
    }

    /**
     *
     * @param type $registry
     * @param type $quoteArr
     * @useless
     */
    public function setGoogleApiDistanceForInstorePickup($registry, $quoteArr)
    {
        //      Google distance for instore pickup
        if ($registry->registry('googleDistanceFromApi') === null) {
            (isset($quoteArr->googleDistance)) ? $registry->register('googleDistanceFromApi', $quoteArr->googleDistance->text) : '';
            (isset($quoteArr->googleDistance)) ? $_SESSION['googleDistanceFromApi'] = $quoteArr->googleDistance->text : $_SESSION['googleDistanceFromApi'] = '';
        }
    }

    /**
     *
     * @param $quote
     * @return array
     * @useless
     */
    public function getAllServicesArr($quote)
    {
        return [
            'id' => $quote['serviceType'],
            'carrier_scac' => $quote['serviceType'],
            'carrier_name' => $quote['serviceDesc'],
            'label' => $quote['serviceDesc'],
            'cost' => $quote['totalNetCharge'],
            'transit_days' => $quote['deliveryDayOfWeek'],
            'liftgatefee' => isset($quote['liftgatefee']) ? $quote['liftgatefee'] : 0,
        ];
    }

    /**
     * Remove array
     * @param $quote
     * @param $removeIndex
     * @return array
     * @useless
     */
    public function removeArray($quote, $removeIndex)
    {
        unset($quote[$removeIndex]);

        return $quote;
    }

    public function enArrayColumn($data, $key)
    {
        $phpVersion = PHP_VERSION;
        $oldVersion = $phpVersion <= 5.4;
        $columns = (!$oldVersion && function_exists("array_column")) ? array_column($data, $key) : [];
        $arrLength = count($data);
        if (empty($arrLength) || !$oldVersion) {
            return $columns;
        }
        $indexArr = array_fill(0, $arrLength, $key);
        $columns = array_map(function ($data, $index) {
            return is_object($data) ? $data->$index : $data[$index];
        }, $data, $indexArr);
        return $columns;
    }

    public function randString()
    {
        return md5(uniqid(mt_rand(), true));
    }

    /**
     * calculate average for quotes
     * @return array type
     */
    public function averageRate()
    {
        $this->quotes = (isset($this->quotes) && (is_array($this->quotes))) ? array_slice($this->quotes, 0, $this->totalCarriers) : [];
        $rateList = $this->enArrayColumn($this->quotes, 'cost');
        $count = count($this->quotes);
        $count = $count > 0 ? $count : 1;
        $rateSum = array_sum($rateList) / $count;
        $quotesReset = reset($this->quotes);

        $rate[] = [
            'id' => $this->randString(),
            'carrier_scac' => (isset($quotesReset['carrier_scac'])) ? $quotesReset['carrier_scac'] : "",
            'label' => (isset($quotesReset['label'])) ? $quotesReset['label'] : "",
            'cost' => $rateSum,
            'markup' => (isset($quotesReset['markup'])) ? $quotesReset['markup'] : "",
            'appendLabel' => (isset($quotesReset['appendLabel'])) ? $quotesReset['appendLabel'] : "",
        ];
        return $rate;
    }

    /**
     * calculate cheapest rate numbers
     * @return array type
     */
    public function cheapestOptions()
    {
        return (isset($this->quotes) && (is_array($this->quotes))) ? array_slice($this->quotes, 0, $this->totalCarriers) : [];
    }

    /**
     * @param array $quotesArray
     * @param array $inStoreLd
     * @return array
     */
    public function instoreLocalDeliveryQuotes($quotesArray, $inStoreLd)
    {
        $data = $this->registry->registry('shipmentOrigin');
        if (count($data) > 1) {
            return $quotesArray;
        }

        foreach ($data as $array) {
            $warehouseData = $this->getWarehouseData($array);
            /**
             * Quotes array only to be made empty if Suppress other rates is ON and In-store
             *  Pickup or Local Delivery also carries some quotes. Else if In-store Pickup or
             *  Local Delivery does not have any quotes i.e Postal code or within miles does
             *  not match then the Quotes Array should be returned as it is.
             * */
            if (isset($warehouseData['suppress_other']) && $warehouseData['suppress_other']) {
                if ((isset($inStoreLd->inStorePickup->status) && $inStoreLd->inStorePickup->status == 1) ||
                    (isset($inStoreLd->localDelivery->status) && $inStoreLd->localDelivery->status == 1)
                ) {
                    $quotesArray = [];
                }
            }
            if (isset($inStoreLd->inStorePickup->status) && $inStoreLd->inStorePickup->status == 1) {
                $quotesArray[] = [
                    'code' => 'INSP',
                    'rate' => 0,
                    'transitTime' => '',
                    'title' => $warehouseData['inStoreTitle'],
                ];
            }

            if (isset($inStoreLd->localDelivery->status) && $inStoreLd->localDelivery->status == 1) {
                $quotesArray[] = [
                    'code' => 'LOCDEL',
                    'rate' => $warehouseData['fee_local_delivery'] ?? 0,
                    'transitTime' => '',
                    'title' => $warehouseData['locDelTitle'],
                ];
            }
        }
        return $quotesArray;
    }

    /**
     * @param array $data
     * @return array
     */
    public function getWarehouseData($data)
    {
        $return = [];
        $whCollection = $this->fetchWarehouseWithID($data['location'], $data['locationId']);

        if(!empty($whCollection[0]['in_store']) && is_string($whCollection[0]['in_store'])){
            $inStore = json_decode($whCollection[0]['in_store'], true);
        }else{
            $inStore = [];
        }

        if(!empty($whCollection[0]['local_delivery']) && is_string($whCollection[0]['local_delivery'])){
            $locDel = json_decode($whCollection[0]['local_delivery'], true);
        }else{
            $locDel = [];
        }

        if ($inStore) {
            $inStoreTitle = $inStore['checkout_desc_store_pickup'];
            if (empty($inStoreTitle)) {
                $inStoreTitle = "In-store pick up";
            }
            $return['inStoreTitle'] = $inStoreTitle;
            $return['suppress_other'] = $inStore['suppress_other'] == '1' ? true : false;
        }
        if ($locDel) {
            $locDelTitle = $locDel['checkout_desc_local_delivery'];
            if (empty($locDelTitle)) {
                $locDelTitle = "Local delivery";
            }
            $return['locDelTitle'] = $locDelTitle;
            $return['fee_local_delivery'] = $locDel['fee_local_delivery'];
            $return['suppress_other'] = $locDel['suppress_other'] == '1' ? true : false;
        }

        return $return;
    }

    /**
     * @param object $quotes
     * @param bool $getMinimum
     * @param bool $isMultiShipmentQuantity (this param will be true when semi case will be executed)
     * @param object $scopeConfig
     * @return array
     *
     * @info: This function will compile all quotes according to the origin.
     * After getting from quotes almost all type of compilation happened in this function
     */
    public function getQuotesResults($quotes, $getMinimum, $isMultiShipmentQuantity, $scopeConfig)
    {
        
        $this->configSettings = $this->getConfigData('gtQuoteSetting/fourth');
        $this->endPoint = $this->getTestConnConfigData('endPoint');
        $allConfigServices = $this->getAllConfigServicesArray($scopeConfig);
        $this->quoteSettingsData();
        // Migration from Legacy to NEW API
        $quotes = $this->migrateApiIfNeeded($quotes);

        if ($isMultiShipmentQuantity) {
            return $this->getOriginsMinimumQuotes($quotes, $allConfigServices, $scopeConfig);
        }
        $allQuotes = $odwArr = $hazShipmentArr = [];
        $count = 0;
        $lgQuotes = false;
        $this->isMultiShipment = count($quotes) > 1;

        foreach ($quotes as $origin => $quote) {
            if (isset($quote->severity)) {
                return [];
            }

            if ($count == 0) { //To be checked only once
                $inStoreLdData = $quote->InstorPickupLocalDelivery ?? false;
                unset($quote->InstorPickupLocalDelivery);
                $isRad = $quote->autoResidentialsStatus ?? '';
                $this->getAutoResidentialTitle($isRad);
                $lgQuotes = $this->OfferLiftgateAsAnOption;
            }

            $originQuotes = $arraySorting = [];
            if (isset($quote->q)) {
                if (isset($quote->hazardousStatus)) {
                    $hazShipmentArr[$origin] = $quote->hazardousStatus == 'y' ? 'Y' : 'N';
                }
                if (isset($quote->quotesWithLiftGate)){
                    $this->getLiftGateArray($quote->quotesWithLiftGate);
                }

                foreach ($quote->q as $key => $data) {
                    $serviceType = '';
                    $serviceDesc = '';
                    $transitDays = '';
                    if ($this->endPoint == 2) {
                        $serviceDesc = $data->CarrierDetail->CarrierName ?? '';
                        $serviceType = $data->CarrierDetail->CarrierCode ?? '';
                        $transitDays = $data->totalTransitTimeInDays;
                    }

                    if($this->endPoint == 3){
                        $serviceType = $data->serviceType ?? '';
                        $serviceDesc = $data->serviceDesc ?? '';
                        $transitDays = $data->totalTransitTimeInDays;
                        if(isset($data->serviceLevel) && is_string($data->serviceLevel) && strtolower($data->serviceLevel) != 'standard'){
                            continue;
                        }
                    }

                    if (in_array($serviceType, $allConfigServices)) {
                        $access = $this->getAccessorialCode();
                        $price = $this->calculatePrice($data);
                        $title = $this->getTitle($serviceDesc, false, false, $transitDays);

                        $arraySorting['simple'][$key] = $price;
                        $originQuotes[$key]['simple']['code'] = $serviceType . $access;
                        $originQuotes[$key]['simple']['rate'] = $price;
                        $originQuotes[$key]['simple']['title'] = $title;
                        $originQuotes[$key]['simple']['delivery_estimate'] = $transitDays;
                        if ($lgQuotes) {
                            $lgAccess = $this->getAccessorialCode(true);
                            $lgPrice = $this->calculatePrice($data, true, true);
                            $lgTitle = $this->getTitle($serviceDesc, true, false, $transitDays);
                            $arraySorting['liftgate'][$key] = $lgPrice;
                            $originQuotes[$key]['liftgate']['code'] = $serviceType . $lgAccess;
                            $originQuotes[$key]['liftgate']['rate'] = $lgPrice;
                            $originQuotes[$key]['liftgate']['title'] = $lgTitle;
                            $originQuotes[$key]['liftgate']['delivery_estimate'] = $transitDays;
                        }
                    }
                }
            }

            $compiledQuotes = $this->getCompiledQuotes($originQuotes, $arraySorting, $lgQuotes);
            if ($compiledQuotes !== null && count($compiledQuotes)) {
                if (count($compiledQuotes) > 1) {

                    foreach ($compiledQuotes as $k => $service) {
                        $allQuotes['simple'][] = $service['simple'];
                        ($lgQuotes) ? $allQuotes['liftgate'][] = $service['liftgate'] : '';
                    }
                } else {
                    $service = reset($compiledQuotes);
                    $allQuotes['simple'][] = $service['simple'];
                    ($lgQuotes) ? $allQuotes['liftgate'][] = isset($service['liftgate']) ? $service['liftgate'] : '' : '';
                }
            }
            if ($this->isMultiShipment) {
                $odwArr[$origin]['quotes'] = $compiledQuotes;
            }
            $count++;
        }

        $this->setOrderDetailWidgetData($odwArr, $hazShipmentArr);
        if (!empty($allQuotes)) {
            $allQuotes = $this->getFinalQuotesArray($allQuotes);
        }
        if (!$this->isMultiShipment && isset($inStoreLdData) && !empty($inStoreLdData)) {
            $allQuotes = $this->instoreLocalDeliveryQuotes($allQuotes, $inStoreLdData);
        }

        return $this->arrangeOwnFreight($allQuotes);
    }

    public function getLiftGateArray($data)
    {
        $this->liftGateArray = [];
        foreach ($data as $service) {
            $this->liftGateArray[$service->CarrierScac] = $service;
        }
    }

    /**
     * @param $quotes
     * @return array
     *
     * @info: This function will arrange array of quotes according to the accessorials.
     * This function will handle single shipment and multi shipment both for return final array.
     */
    public function getFinalQuotesArray($quotes)
    {
        if ($this->isMultiShipment == false) {
            if (isset($quotes['liftgate']) && $this->OfferLiftgateAsAnOption == 1 && ($this->RADforLiftgate == 0 || $this->isResi == 0)) {
                /**
                 * Condition for lift gate as an option
                 * */
                return array_merge($quotes['simple'], $quotes['liftgate']);
            }
            else {
                return $quotes['simple'];
            }
        }
        return $this->organizeQuotesArray($quotes);
    }

    public function organizeQuotesArray($quotes)
    {
        $quotesArr = [];
        foreach ($quotes as $key => $value) {
            if ($this->isMultiShipment) {
                $rate = 0;
                $code = '';
                $isLiftGate = $key == 'liftgate';
                foreach ($value as $key2 => $data) {
                    $rate += $data['rate'];
                    $code = $data['code'];
                }
                $quotesArr[] = [
                    'code' => $code,
                    'rate' => $rate,
                    'title' => $this->getTitle('Freight', $isLiftGate, true)
                ];
            } else {
                $quotesArr[] = reset($value);
            }
        }

        return $quotesArr;
    }

    /**
     * @param array $servicesArr
     * @param $hazShipmentArr
     */
    public function setOrderDetailWidgetData(array $servicesArr, $hazShipmentArr)
    {
        $setPkgForOrderDetailReg = $this->registry->registry('setPackageDataForOrderDetail') ?? [];
        $planNumber = $this->planInfo()['planNumber'];

        if ($planNumber > 1 && $setPkgForOrderDetailReg && $hazShipmentArr) {
            foreach ($hazShipmentArr as $origin => $value) {
                foreach ($setPkgForOrderDetailReg[$origin]['item'] as $key => $data) {
                    $setPkgForOrderDetailReg[$origin]['item'][$key]['isHazmatLineItem'] = $value;
                    break;
                }
            }
        }
        $orderDetail['shipmentData'] = array_replace_recursive($setPkgForOrderDetailReg, $servicesArr);

        // set order detail widget data
        $this->coreSession->start();
        $this->coreSession->setOrderDetailSession($orderDetail);
    }

    /**
     * @param bool $lgOption
     * @return string
     *
     * @info: This will return specific code according to the accessorials for appending with the service code.
     */
    public function getAccessorialCode($lgOption = false)
    {
        $access = '';
        if ($this->residentialDlvry == '1' || $this->isResi) {
            $access .= '+R';
        }
        if (($lgOption || $this->liftGate == '1') || (!$this->OfferLiftgateAsAnOption && $this->RADforLiftgate && $this->isResi)) {
            $access .= '+LG';
        }
        return $access;
    }

    /**
     * @param object $data
     * @param bool $lgOption
     * @param bool $getCost
     * @return float
     *
     * @info: This function will calculate all prices and return price against a specific service
     */
    public function calculatePrice($data, $lgOption = false, $getCost = false)
    {
        if ($this->endPoint == 3){

            $basePrice = $data->totalNetCharge->Amount;
            $lgCost = !$lgOption ? $this->getLiftgateCost($data, $getCost) : 0;
            $basePrice = (float)$basePrice - (float)$lgCost;

        } else {
            $basePrice = $data->LtlAmount;
            $lgCost = !$lgOption ? $this->getLiftgateCost($data, $getCost) : 0;
            $basePrice = (float)$basePrice - (float)$lgCost;
        }
        $basePrice = $this->calculateHandlingFee($basePrice);
        return $basePrice;
    }

    /**
     * @param $quotes
     * @param bool $getCost
     * @return float
     */
    public function getLiftgateCost($quotes, $getCost = false)
    {
        $lgCost = 0;
        if (!(($this->isResi && $this->RADforLiftgate) || $this->liftGate == '1') || $getCost) {
            if($this->endPoint == 3){
                $lgCost = (isset($quotes->surcharges->liftgateFee) && is_numeric($quotes->surcharges->liftgateFee)) ? $quotes->surcharges->liftgateFee : 0;
            } else {
                $charges = $quotes->Charges ?? [];
                foreach ($charges as $charge) {
                    if ($charge->AccessorialID == 12) {
                        $lgCost = $charge->Charge;
                    }
                }
            }
        }
        return $lgCost;
    }

    /**
     * Calculate Handling Fee
     * @param $cost
     * @return float
     */
    public function calculateHandlingFee($cost)
    {
        $handlingFeeMarkup = $this->hndlngFee;
        $symbolicHandlingFee = $this->symbolicHndlngFee;

        if (!empty($handlingFeeMarkup) && strlen($handlingFeeMarkup) > 0) {
            if ($symbolicHandlingFee == '%') {
                $percentVal = $handlingFeeMarkup / 100 * $cost;
                $grandTotal = $percentVal + $cost;
            } else {
                $grandTotal = $handlingFeeMarkup + $cost;
            }
        } else {
            $grandTotal = $cost;
        }
        return $grandTotal;
    }

    /**
     * @param $serviceName
     * @param bool $lgOption
     * @param bool $from
     * @param string $deliveryEstimate
     * @return string
     *
     * @info: This function will compile name of a service and return service name according to the settings enabled.
     */
    public function getTitle($serviceName, $lgOption = false, $from = false, $deliveryEstimate = '')
    {
        $serviceTitle = $this->customLabel($serviceName);
        if ($this->isMultiShipment && $from == false) {
            return $serviceTitle;
        }
        $deliveryEstimateLabel = (!empty($deliveryEstimate) && isset($this->configSettings['dlrvyEstimates']) && $this->configSettings['dlrvyEstimates']) ? ' (Estimated transit time of ' . $deliveryEstimate . ' calender days)' : '';
        $accessTitle = '';
        if ($lgOption === true) {
            $accessTitle = $this->isResi ? $this->resiLgLabel : $this->lgLabel;
        } elseif ((!$this->OfferLiftgateAsAnOption && $this->RADforLiftgate) || $this->isResi) {
            if ($this->RADforLiftgate && $this->isResi && !$this->OfferLiftgateAsAnOption) {
                $accessTitle = $this->resiLgLabel;
            }elseif ($this->isResi){
                $accessTitle = $this->resiLabel;
            }
        }

        return $serviceTitle . $accessTitle . $deliveryEstimateLabel;
    }

    /**
     * @param string $resi
     * @return string
     */
    public function getAutoResidentialTitle($resi)
    {
        if ($this->moduleManager->isEnabled('Eniture_ResidentialAddressDetection')) {
            $isRadSuspend = $this->getConfigData("resaddressdetection/suspend/value");
            if ($this->residentialDlvry == "1") {
                $this->residentialDlvry = $isRadSuspend == "no" ? '0' : '1';
            } else {
                $this->residentialDlvry = $isRadSuspend == "no" ? '0' : $this->residentialDlvry;
            }
            if ($this->residentialDlvry == null || $this->residentialDlvry == '0') {
                if ($resi == 'r') {
                    $this->isResi = true;
                }
            }
        }
    }

    /**
     * @param array $quotes
     * @param array $allConfigServices
     * @param $scopeConfig
     * @return object | array
     */
    public function getOriginsMinimumQuotes($quotes, $allConfigServices, $scopeConfig)
    {
        $minIndexArr = [];
        foreach ($quotes as $origin => $quote) {
            $minInQ = $counter = 0;
            if (isset($quote->q)) {
                foreach ($quote->q as $key => $data) {
                    $serviceType = '';
                    if ($this->endPoint == 2) {
                        $serviceType = $data->CarrierDetail->CarrierCode ?? '';
                        $currentAmount = $data->LtlAmount ?? 0;
                    }

                    if($this->endPoint == 3){
                        $serviceType = $data->serviceType ?? '';
                        $currentAmount = $data->totalNetCharge->Amount ?? 0;
                    }

                    if (in_array($serviceType, $allConfigServices)) {
                        if ($counter == 0) {
                            $minInQ = $currentAmount;
                        } else {
                            $minInQ = ($currentAmount < $minInQ ? $currentAmount : $minInQ);
                        }

                        $counter++;
                    }
                }
                if ($minInQ > 0) {
                    $minInQ = $this->calculateHandlingFee($minInQ, $scopeConfig);
                    $minIndexArr[$origin] = $minInQ;
                }
            }
        }
        return $minIndexArr;
    }

    /**
     * This function returns minimum array index from array
     * @param $servicesArr
     * @return array
     */
    public function findArrayMininum($servicesArr)
    {
        $counter = 1;
        $minIndex = [];
        foreach ($servicesArr as $value) {
            if ($counter == 1) {
                $minimum = $value['rate'];
                $minIndex = $value;
                $counter = 0;
            } else {
                if ($value['rate'] < $minimum) {
                    $minimum = $value['rate'];
                    $minIndex = $value;
                }
            }
        }
        return $minIndex;
    }

    /*
    * Average Rate ( LTL Freight Services )
    * @return Avg Rate
    */

    public function cerasisLtlGetAvgRate($allServices, $numberOption, $activeCarriers)
    {
        $price = 0;
        $totalPrice = 0;
        if (count($allServices) > 0) {
            foreach ($allServices as $services) {
                $totalPrice += $services['rate'];
            }

            if ($numberOption < count($activeCarriers) && $numberOption < count($allServices)) {
                $slicedArray = array_slice($allServices, 0, $numberOption);
                foreach ($slicedArray as $services) {
                    $price += $services['rate'];
                }
                $totalPrice = $price / $numberOption;
            } elseif (count($activeCarriers) < $numberOption && count($activeCarriers) < count($allServices)) {
                $totalPrice = $totalPrice / count($activeCarriers);
            } else {
                $totalPrice = $totalPrice / count($allServices);
            }

            return $totalPrice;
        }
    }

    /**
     * This Function returns all active services array from configurations
     * @param $scopeConfig
     * @return array
     */
    public function getAllConfigServicesArray($scopeConfig)
    {
        $servicesOptions = [];
        $selectedCarriers = $scopeConfig->getValue('gtLtlCarriers/second/selectedGtCarriers', ScopeInterface::SCOPE_STORE);
        if(!empty($selectedCarriers) && is_string($selectedCarriers)){
            $servicesOptions = json_decode($selectedCarriers);
        }
        return (array)$servicesOptions;
    }

    /**
     * Final quotes array
     * @param $grandTotal
     * @param $code
     * @param $title
     * @param $appendLabel
     * @return array
     */
    public function getFinalQuoteArray($grandTotal, $code, $title, $appendLabel)
    {
        $allowed = [];

        if ($grandTotal > 0) {
            $allowed = [
                'code' => $code,// or carrier name
                'title' => $title . $appendLabel,
                'rate' => $grandTotal
            ];
        }

        return $allowed;
    }

    public function checkOwnArrangement($finalArr)
    {
        if (isset($this->ownArangement) && $this->ownArangement == 1) {
            $title = (isset($this->ownArangementText) && trim($this->ownArangementText) != '') ? $this->ownArangementText :
                "I'll Arrange My Own Freight";
            $finalArr[] = ['code' => 'OWAR',// or carrier name
                'title' => $title,
                'rate' => 0
            ];
        }

        return $finalArr;
    }

    public function adminConfigData($fieldId, $scopeConfig)
    {
        return $scopeConfig->getValue("gtQuoteSetting/fourth/$fieldId", ScopeInterface::SCOPE_STORE);
    }

    /**
     *
     * @return AbstractCarrierInterface[]
     */
    public function getActiveCarriersForENCount()
    {
        return $this->shippingConfig->getActiveCarriers();
    }

    /**
     * execute cron job for get cerasis carriers
     */
    public function executeCronForGetCarriers()
    {
        $requestTime = $this->scopeConfig->getValue('gtLtlCarriers/second/requestTime', ScopeInterface::SCOPE_STORE);
        if(!empty($requestTime)){
            $requestTime = json_decode($requestTime);
        }
        $currentDate = date('m/d/Y h:i:s', time());

        if ($requestTime) {
            $interval = date_diff(date_create($requestTime), date_create($currentDate));
            $numberOfDays = $interval->format('%a');
            if ($numberOfDays > '7') {
                $this->getCerasisCarriers();
            }
        } else {
            $this->getCerasisCarriers();
        }
    }

    public function getCerasisCarriers()
    {
        $req = $this->getCerasisCarriersReq('getcarriers');
        $carriersRes = $this->cerasisSendCurlRequest(EnConstants::INDEX, $req);
        $this->carrierResult($carriersRes);
    }

    public function getCerasisCarriersReq($action)
    {
        return [
            'licence_key' => $this->getTestConnConfigData('licnsKey'),
            'server_name' => $_SERVER['SERVER_NAME'],
            'platform' => 'magento2',
            'carrierName' => 'cerasis',
            'carrier_mode' => $action,
            'requestKey' => '11461611123446',
            'shipperID' => $this->getTestConnConfigData('cerasisltlshipperID'),
            'username' => $this->getTestConnConfigData('cerasisltlusername'),
            'password' => $this->getTestConnConfigData('cerasisltlPassword'),
            'accessKey' => $this->getTestConnConfigData('cerasisltlAccessKey'),
        ];
    }

    /**
     * function return service data
     * @param $fieldId
     * @return string
     */
    public function getTestConnConfigData($fieldId)
    {
        $sectionId = 'gtConnSettings';
        $groupId = 'first';

        return $this->scopeConfig->getValue("$sectionId/$groupId/$fieldId", ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param $cerriersRes
     * @return array
     */
    public function carrierResult($cerriersRes)
    {
        $status = [];
        if (isset($cerriersRes) && !empty($cerriersRes->carriers)) {
            $date = $this->timezoneInterface->date()->format('m/d/y H:i:s');
            $this->configWriter->save('gtLtlCarriers/second/requestTime', json_encode($date), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
            $this->configWriter->save('gtLtlCarriers/second/carriers', json_encode($cerriersRes), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
            $status['SUCCESS'] = true;
        } else {
            $status['ERROR'] = true;
        }

        return $status;
    }

    /**
     * validate Input Post
     * @param $sPostData
     * @return mixed
     */
    public function LTLValidatedPostData($sPostData)
    {
        $dataArray = ['city', 'state', 'zip', 'country'];
        $data = [];
        foreach ($sPostData as $key => $tag) {
            $preg = '/[#$%@^&_*!()+=\-\[\]\';,.\/{}|":<>?~\\\\]/';
            $check_characters = (in_array($key, $dataArray)) ? preg_match($preg, $tag) : '';

            if ($check_characters != 1) {
                if ($key === 'city' || $key === 'nickname' || $key === 'in_store' || $key === 'local_delivery') {
                    $data[$key] = $tag;
                } else {
                    if(!empty($tag)){
                        $data[$key] = preg_replace('/\s+/', '', $tag);
                    }else{
                        $data[$key] = '';
                    }
                }
            } else {
                $data[$key] = 'Error';
            }
        }

        return $data;
    }

    /**
     * @return int
     */
    public function whPlanRestriction()
    {
        $planNumber = $this->planInfo()['planNumber'];
        $warehouses = $this->fetchWarehouseSecData('warehouse');
        if ($planNumber < '2' && count($warehouses)) {
            $this->canAddWh = 0;
        }
        return $this->canAddWh;
    }

    /**
     * Get Plan detail
     * @return array
     */
    public function planInfo()
    {
        $planData = $this->coreSession->getPlanDetail();
        if ($planData == null) {
            $appData = $this->getConfigData("eniture/ENGlobalTranzLTL");
            $plan = $appData["plan"] ?? '-1';
            $storeType = $appData["storetype"] ?? '';
            $expireDays = $appData["expireday"] ?? '';
            $expiryDate = $appData["expiredate"] ?? '';
            $planName = "";
            switch ($plan) {
                case 3:
                    $planName = "Advanced Plan";
                    break;
                case 2:
                    $planName = "Standard Plan";
                    break;
                case 1:
                    $planName = "Basic Plan";
                    break;
                case 0:
                    $planName = "Trial Plan";
                    break;
            }
            $planData = [
                'planNumber' => $plan,
                'planName' => $planName,
                'expireDays' => $expireDays,
                'expiryDate' => $expiryDate,
                'storeType' => $storeType
            ];
            $this->coreSession->setPlanDetail($planData);
        }
        return $planData;
    }

    /**
     * @param $confPath
     * @return mixed
     */
    public function getConfigData($confPath)
    {
        return $this->scopeConfig->getValue($confPath, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string
     */
    public function ltlSetPlanNotice($planRefreshUrl = '')
    {
        $planPackage = $this->planInfo();
        if ($planPackage['storeType'] == '') {
            $planPackage = [];
        }
        return $this->displayPlanMessages($planPackage, $planRefreshUrl);
    }

    /**
     * @param $planPackage
     * @return string
     */
    public function displayPlanMessages($planPackage, $planRefreshUrl = '')
    {
        $planRefreshLink = '';
        if(!empty($planRefreshUrl)){
            $planRefreshLink = ' <a href="javascript:void(0)" id="plan-refresh-link" planRefAjaxUrl = '.$planRefreshUrl.' onclick="gtzLtlPlanRefresh(this)" >Click here</a> to refresh the plan (please sign in again after this action).';
        }
        $planMsg = __('Eniture - GlobalTranz LTL Freight Quotes plan subscription is inactive.'.$planRefreshLink.'  If the subscription status remains inactive, log into eniture.com and update your license.');
        if (isset($planPackage) && !empty($planPackage)) {
            if ($planPackage['planNumber'] != null && $planPackage['planNumber'] != -1) {
                $planMsg = __('Eniture - GlobalTranz LTL Freight Quotes is currently on the ' . $planPackage['planName'] . '. Your plan will expire within ' . $planPackage['expireDays'] . ' days and plan renews on ' . $planPackage['expiryDate'] . '.'.$planRefreshLink);
            }
        }
        return $planMsg;
    }

    /**
     * @return array
     */
    public function quoteSettingFieldsToRestrict()
    {
        $restriction = [];
        $currentPlan = $this->planInfo()['planNumber'];
        $standard = [
            'enableCuttOff'
        ];
        $advance = [];
        switch ($currentPlan) {
            case 2:
            case 3:
                break;

            default:
                $restriction = [
//                    'advance' => $advance,
                    'standard' => $standard
                ];
                break;
        }
        return $restriction;
    }

    /**
     *
     */
    public function clearCache()
    {
        $types = $this->cacheManager->getAvailableTypes();
        $this->cacheManager->flush($types);
        $this->cacheManager->clean($types);
    }

    /**
     * @param null $msg
     * @param bool $type
     * @return array
     */
    public function generateResponse($msg = null, $type = false)
    {
        $defaultError = 'Something went wrong. Please try again!';
        return [
            'error' => ($type == true) ? 1 : 0,
            'msg' => ($msg != null) ? $msg : $defaultError
        ];
    }

    /**
     * @param $services
     * @param $arraySorting
     * @param $lgQuotes
     * @param $quickestArray
     * @return array
     */
    public function getCompiledQuotes($services, $arraySorting, $lgQuotes)
    {
        if (empty($arraySorting) || empty($services)) {
            return [];
        }
        asort($arraySorting['simple']);
        
        $options = (!empty($this->configSettings['ratingMethod']) && $this->configSettings['ratingMethod'] > 1 && $this->isMultiShipment == false) ? (int)$this->configSettings['options'] : 1;
        if (!empty($this->configSettings['ratingMethod']) && $this->configSettings['ratingMethod'] == 3) {
            return $this->averageRattingMethod($arraySorting, $options, $lgQuotes);
        }
        $sliced = array_slice($arraySorting['simple'], 0, $options, true);

        return array_intersect_key($services, $sliced);
    }

    /**
     * @param $ratesArray
     * @param $options
     * @param $lgQuotes
     * @return array
     */
    public function averageRattingMethod($ratesArray, $options, $lgQuotes)
    {
        $sliced = array_slice($ratesArray['simple'], 0, $options, true);
        $simplePrice = $this->getAveragePrice($sliced, $options);
        $serviceName = $this->customLabel('Freight');
        $averageRateService[0]['simple'] = [
            'title' => $serviceName,
            'code' => 'AVG' . $this->getAccessorialCode(),
            'rate' => $simplePrice,
        ];
        if ($lgQuotes) {
            asort($ratesArray['liftgate']);
            $sliced = array_slice($ratesArray['liftgate'], 0, $options, true);
            $lfgPrice = $this->getAveragePrice($sliced, $options);
            $averageRateService[0]['liftgate'] = [
                'title' => $this->getTitle($serviceName, true),
                'code' => 'AVG' . $this->getAccessorialCode($lgQuotes),
                'rate' => $lfgPrice,
            ];
        }

        return $averageRateService;
    }

    public function getAveragePrice($arraySorting, $options)
    {
        $numOfIndexes = count($arraySorting);
        $divider = ($numOfIndexes == $options) ? $options : $numOfIndexes;
        return array_sum($arraySorting) / $divider;
    }

    public function customLabel($serviceName)
    {
        $return = (!$this->isMultiShipment && !empty($this->configSettings['ratingMethod']) && ($this->configSettings['ratingMethod'] == 1 ||
                        $this->configSettings['ratingMethod'] == 3) &&
                    !empty($this->configSettings['labelAs'])) ? $this->configSettings['labelAs'] : $serviceName;
        return $return;
    }

    /**
     *
     * @param array $getWarehouse
     * @param array $validateData
     * @return string
     */
    public function checkUpdateInstorePickupDelivery($getWarehouse, $validateData)
    {
        $update = 'no';
        if (empty($getWarehouse)) {
            return $update;
        }

        $newData = [];
        $oldData = [];

        $getWarehouse = reset($getWarehouse);
        unset($getWarehouse['warehouse_id']);
        unset($getWarehouse['nickname']);
        unset($validateData['nickname']);

        foreach ($getWarehouse as $key => $value) {
            if (empty($value) || $value == null) {
                $newData[$key] = 'empty';
            } else {
                $oldData[$key] = trim($value);
            }
        }
        $whData = array_merge($newData, $oldData);
        $diff1 = array_diff($whData, $validateData);
        $diff2 = array_diff($validateData, $whData);

        if ((is_array($diff1) && !empty($diff1)) || (is_array($diff2) && !empty($diff2))) {
            $update = 'yes';
        }
        return $update;
    }

    /**
     * @param $finalQuotes
     * @return array
     */
    public function arrangeOwnFreight($finalQuotes)
    {
        if ($this->ownArangement == 0) {
            return $finalQuotes;
        }
        $ownArrangement[] = [
            'code' => 'ownArrangement',
            'title' => (!empty($this->ownArangementText)) ? $this->ownArangementText : "I'll Arrange My Own Freight",
            'rate' => 0
        ];
        return array_merge($finalQuotes, $ownArrangement);
    }

    /**
     * Function to migrate API
     */
    protected function migrateApiIfNeeded($quotes)
    {
        foreach ($quotes as $key => $quote) {
            if(isset($quote->newAPICredentials) && !empty($quote->newAPICredentials->client_id) && !empty($quote->newAPICredentials->client_secret)){
                $this->configWriter->save('gtConnSettings/first/clientId', $quote->newAPICredentials->client_id);
                $this->configWriter->save('gtConnSettings/first/clientSecret', $quote->newAPICredentials->client_secret);
                $this->configWriter->save('gtConnSettings/first/endPoint', '3');
                $username = $this->getConfigData('gtConnSettings/first/gtLtlUsername');
                $password = $this->getConfigData('gtConnSettings/first/gtLtlPassword');
                $this->configWriter->save('gtConnSettings/first/usernameNewAPI', $username);
                $this->configWriter->save('gtConnSettings/first/passwordNewAPI', $password);
                unset($quotes[$key]->newAPICredentials);
                $this->clearCache();
            }

            if(isset($quote->oldAPICredentials)){
                $this->configWriter->save('gtConnSettings/first/endPoint', '2');
                unset($quotes[$key]->oldAPICredentials);
                $this->clearCache();
            }
        }

        return $quotes;
    }

}
