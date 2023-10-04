<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Adminhtml;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use \Magento\Framework\App\Action\Action;
use Magento\Framework\App\Config\ScopeConfigInterface;

class BillOfLadding extends Action
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
     * @var object Order Detail Information
     */
    protected $orderDetail;

    /**
     * @var object BOL Data
     */
    protected $billOfLaddingData;

    /**
     * @var URL API Call URL
     */
    protected $curlUrl;

    /**
     * @var Object Database Read Object
     */
    protected $connection;

    /**
     * @var Object Database Read Object
     */
    protected $write;

    /**
     *
     * @var string BOL Database Table Name
     */
    protected $tableNames;

    /**
     *
     * @var string Carrier Code
     */
    protected $carrierSCAC;

    /**
     *
     * @var string senderName
     */
    protected $senderName;

    protected $orderRepository;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\App\ResourceConnection $resource,
        \Eniture\GlobalTranzLTLFreightQuotes\Helper\Data $helper,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\App\ProductMetadataInterface $productMetadata,
        \Magento\Framework\App\Config\Storage\WriterInterface $configWriter
    ) {
        $this->orderRepository = $orderRepository;
        $this->connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->cerasisHelper = $helper;
        $this->scopeConfig = $scopeConfig;
        $this->_mageVersion = $productMetadata->getVersion();
        $this->_configWriter = $configWriter;
        $this->tableNames = [
            'BOL' => $resource->getTableName('BillOfLadding'),
            'WH' => $resource->getTableName('warehouse')
        ];
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
        $orderId = $postData['orderId'];
        $printBolAjaxUrl = filter_var($postData['printBolAjaxUrl'], FILTER_SANITIZE_URL);
        $this->senderName = isset($postData['senderName']) ? htmlspecialchars($postData['senderName'], ENT_QUOTES) : '';
        $this->senderAddress = isset($postData['senderAddress']) ? htmlspecialchars($postData['senderAddress'], ENT_QUOTES) : '';
        $bolBase64 = [];

        $BOLTable = $this->tableNames['BOL'];
        $getBolData = $this->connection->fetchAll("SELECT `id`, `order_id`, `bol_base64`, `bol_print`, `bol_number` FROM $BOLTable WHERE `order_id` = '" . $orderId . "'");

        if (empty($getBolData)) {
            $this->orderDetail = $order = $this->orderRepository->get($orderId);

            $this->getCarrierScac();

            $this->orderBOLRequest();

            $shipResponse = $this->cerasisHelper->cerasisSendCurlRequest($this->curlUrl, $this->billOfLaddingData);
            $responseSetting = $this->shipmentResonseSetting($shipResponse);

            if (isset($responseSetting['SUCCESS'])) {
                $this->_configWriter->save('gtLtlCarriers/bol/senderinfo', json_encode(['senderName' => $this->senderName, 'senderAddress' => $this->senderAddress]), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
                $bolBase64 = $responseSetting['SUCCESS']['BOL'];
                $bolNumber = $responseSetting['SUCCESS']['BOLNUMBER'];
                $this->connection->insert($this->tableNames['BOL'], ['order_id' => $orderId, 'bol_base64' => "$bolBase64", 'bol_print' => 'yes', 'bol_number' => $bolNumber]);
            } else {
                $this->getResponse()->setHeader('Content-type', 'application/json');
                $this->getResponse()->setBody(json_encode($responseSetting));
            }
        } else {
            $getBolData = reset($getBolData);
            $bolBase64 = $getBolData['bol_base64'];
        }

        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode(['orderId' => $orderId, 'bolJson' => $bolBase64, 'printBolAjaxUrl' => $printBolAjaxUrl]));
    }

    /**
     * Get Carrier SCAC
     */
    public function getCarrierScac()
    {
        $shippingMethod = $this->orderDetail->getShippingMethod();
        $explode = explode('_', $shippingMethod);
        $this->carrierSCAC = (isset($explode[1])) ? $explode[1] : '';
    }

    /**
     * Order Request
     * @return array
     */
    public function orderBOLRequest()
    {
        $this->prepareOrderDataForRequest();
        $shippingInfo = $this->getOrderShippingInfo();
        $package = $this->getOrderedPckageData($shippingInfo['zip']);

        $data = [
            'licence_key' => $this->scopeConfig->getValue('gtConnSettings/first/licnsKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'server_name' => $_SERVER['SERVER_NAME'],
            'carrierName' => 'cerasis',
            'carrier_mode' => 'ship',

            'shipperID' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlshipperID', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'username' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlusername', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'password' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlPassword', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'accessKey' => $this->scopeConfig->getValue('gtConnSettings/first/cerasisltlAccessKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'carrier' => $this->carrierSCAC,

            'senderName' => $this->senderName,
            'senderAddress1' => $this->senderAddress,
            'senderAddress2' => '',
            'senderAddress3' => '',
            'senderCity' => $package['origin']['senderCity'],
            'senderState' => $package['origin']['senderState'],
            'senderZip' => $package['origin']['senderZip'],
            'senderCountryCode' => $package['origin']['senderCountryCode'],
            'senderContact' => '',
            'senderEmailAddress' => '',
            'senderFax' => '',
            'senderPhone' => '',
            'senderReference' => '',

            'receiverName' => $shippingInfo['name'],
            'receiverAddress1' => $shippingInfo['street1'],
            'receiverAddress2' => $shippingInfo['street2'],
            'receiverAddress3' => '',
            'receiverCity' => $shippingInfo['city'],
            'receiverState' => $shippingInfo['state'],
            'receiverZip' => $shippingInfo['zip'],
            'receiverCountryCode' => $shippingInfo['country'],
            'receiverContact' => $shippingInfo['telephone'],
            'receiverEmailAddress' => $shippingInfo['email'],
            'receiverFax' => '',
            'receiverPhone' => '',
            'receiverReference' => '',

            'direction' => 'Dropship',

            'billingType' => 'Prepaid',

            'accessorial' => $this->getAccessorials(),

            'commdityDetails' => $package['items'],
        ];

        $this->billOfLaddingData = $data;
    }

    public function prepareOrderDataForRequest()
    {
    }

    /**
     * Get Order Package Data
     * @param $destZip
     * @return array
     */
    public function getOrderedPckageData($destZip)
    {
        $package = [];
        foreach ($this->orderDetail->getAllItems() as $item) {
            $product = $this->getProductAttrData($item);
            $package['items'][] = [
                'piecesOfLineItem' => (int)$item->getQtyOrdered(),
                'lineItemClass' => $product['freightClass'],
                'lineItemWeight' => number_format((float) $item->getWeight(), 2, '.', ''),
                'lineItemLength' => number_format((float) $product['length'], 2, '.', ''),
                'lineItemWidth' => number_format((float) $product['width'], 2, '.', ''),
                'lineItemHeight' => number_format((float) $product['height'], 2, '.', ''),
                'lineItemPackageCode' => 'PLT',
                'hazmat' => 0,
                'description' => 'TEST', // optional
                'NMFCCode' => '4620-5',
                'isHazmatLineItem' => '0',
                'hazmatDescription' => '', // optional
                'hazmatClass' => '', // optional
                'hazmatSubClass' => '', // optional
                'hazMatPackagingClass' => '', //  optional
                'UNIdentificationNumber' => 'UN1133', // required with hazmat
                'hazmatTechnicalName' => '', // optional
                'hazmatZone' => '', // optional
                'hazmatDetailDescription' => '', // optional
                'hazmatSpecialProvision' => '', // optional
                'hazmatSpecialProvExpDate' => '', // optional
                'eRGGuidePage' => '', // optional
                'contactName' => '', // optional
                'contactNumber' => '', // optional
                'bolDescription1' => 'Order # ' . $this->orderDetail->getIncrementId(), // optional
            ];

            $package['origin'] = $this->cerasisLtlOrderOriginAddress($item, $destZip);
        }

        return $package;
    }

    /**
     *
     * @param $item
     * @return array
     */
    public function getProductAttrData($item)
    {
        $product = $item->getProduct();
        $product->load($item->getProduct()->getId());

        $lineItemClass = $product->getData('en_freight_class');
        switch ($lineItemClass) {
            case 77:
                $lineItemClass = 77.5;
                break;
            case 92:
                $lineItemClass = 92.5;
                break;
            default:
                break;
        }

        return [
            'length' => ($this->_mageVersion < '2.2.5') ? $product->getData('en_length') : $product->getData('ts_dimensions_length'),
            'width' => ($this->_mageVersion < '2.2.5') ? $product->getData('en_width') : $product->getData('ts_dimensions_width'),
            'height' => ($this->_mageVersion < '2.2.5') ? $product->getData('en_height') : $product->getData('ts_dimensions_height'),
            'freightClass' => $lineItemClass
        ];
    }

    /**
     *
     * @param type $freightClass
     * @return real
     */
    public function setFreightClass($freightClass)
    {
        switch ($freightClass) {
            case 77:
                $freightClass = 77.5;
                break;
            case 92:
                $freightClass = 92.5;
                break;
            default:
                break;
        }

        return $freightClass;
    }

    /**
     * Get Shipping Information
     * @return array
     */

    public function getOrderShippingInfo()
    {
        $order = $this->orderDetail;
        $shippingData = $order->getShippingAddress()->getData();
        $middlName = (isset($shippingData['middlename'])) ? ' ' . $shippingData['middlename'] : '';
        $name = $shippingData['firstname'] . $middlName . ' ' . $shippingData['lastname'];

        $shippingInfo = [
            'name' => $name,
            'email' => $order->getCustomerEmail(),
            'telephone' => $order->getShippingAddress()->getTelephone(),
            'street1' => $order->getShippingAddress()->getStreet()[0],
            'street2' => isset($order->getShippingAddress()->getStreet()[1]) ? $order->getShippingAddress()->getStreet()[1] : '',
            'zip' => $order->getShippingAddress()->getPostcode(),
            'city' => $order->getShippingAddress()->getCity(),
            'state' => $order->getShippingAddress()->getRegionCode(),
            'country' => $order->getShippingAddress()->getCountryId(),
        ];

        return $shippingInfo;
    }

    /**
     * Get Activated Accessorials
     * @return array
     */
    function getAccessorials()
    {
        $accessorials = [];
        ($this->scopeConfig->getValue('gtQuoteSetting/third/residentialDlvry', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) ? array_push($accessorials, 'RESDEL') : '';
        ($this->scopeConfig->getValue('gtQuoteSetting/third/liftGate', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) ? array_push($accessorials, 'LFTGATDEST') : '';

        return $accessorials;
    }

    /*
     * Get Origin
      @ param : Cart, Destination Zipcode
     *
      @ return Origin Array
     */

    public function cerasisLtlOrderOriginAddress($item, $receiverZipCode)
    {
        $product = $item->getProduct();
        $product->load($item->getProduct()->getId());

        $enable_dropship = $product->getData('en_dropship');
        $whTableName = $this->tableNames['WH'];
        if ($enable_dropship) {
            $dropshipID = $product->getData('en_dropship_location');
            $readresult = $this->connection->query("SELECT warehouse_id, city, state, zip, country, location, nickname FROM " . $whTableName . " WHERE warehouse_id IN ('" . $dropshipID . "')");
            $originList = $readresult->fetch();
            $origin = [$originList];
        } else {
            $origin = $this->connection->fetchAll("SELECT warehouse_id, city, state, zip, country, location FROM " . $whTableName . " WHERE location = 'warehouse'");
        }

        return $this->orderItemMultiWarehouse($origin, $receiverZipCode);
    }

    /*
     * Get Nearest Warehouse
      @ param : Warehouses List, Destination Zipcode
     *
      @ return Origin Array
     */

    public function orderItemMultiWarehouse($warehous_list, $receiverZipCode)
    {
        if (count($warehous_list) == 1) {
            $warehous_list = reset($warehous_list);
            return $this->cerasisLtlOrderOriginArray($warehous_list);
        }

        $response = $this->cerasisLtlOrderOrigin($warehous_list, "MultiDistance", $this->getOrderShippingInfo());
        return $this->cerasisLtlOrderOriginArray($response->origin_with_min_dist);
    }

    /*
     * Create Data To Get Nearest Warehouse
      @ param : Zipcodes, Access Level
     *
      @ return Call Curl Operation
     */

    public function cerasisLtlOrderOrigin($map_address, $accessLevel, $destAddress = [])
    {
        $originAddress = $this->cerasisHelper->recursiveChangeKey($map_address, ['warehouse_id' => 'id']);
        $postData = [
            'acessLevel' => $accessLevel,
            'address' => $originAddress,
            'originAddresses' => (isset($originAddress)) ? $originAddress : "",
            'destinationAddress' => (isset($destAddress)) ? $destAddress : "",
            'eniureLicenceKey' => $this->scopeConfig->getValue('gtConnSettings/first/licnsKey', \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            'ServerName' => $_SERVER['SERVER_NAME'],
        ];

        $json = $this->cerasisHelper->cerasisSendCurlRequest(EnConstants::GOOGLE_URL, $postData);
        return $json;
    }

    /*
     * Create Origin Array
      @ param : Warehouse
     *
      @ return Warehouse Address Array
     */

    public function cerasisLtlOrderOriginArray($shortOrigin)
    {
        $shortOrigin = (array)$shortOrigin;
        $origin = isset($shortOrigin['origin']) ? $shortOrigin['origin'] : $shortOrigin;
        return [
            'locationId' => isset($shortOrigin['id']) ? $shortOrigin['id'] : $shortOrigin['warehouse_id'],
            'senderZip' => isset($origin['zipcode']) ? $origin['zipcode'] : $origin['zip'],
            'senderCity' => $origin['city'],
            'senderState' => $origin['state'],
            'location' => isset($origin['location']) ? $origin['location'] : 'warehouse',
            'senderCountryCode' => ($origin['country'] == "CN") ? "CA" : $origin['country']
        ];
    }

    /**
     * Response Setting
     * @param $shipResponse
     * @return array
     */
    public function shipmentResonseSetting($shipemnt)
    {
        $shipResponseIndex = isset($shipemnt->soapBody) ? $shipemnt->soapBody->ProcessShipmentResponse->ShippingResponse : '';
        $response = [];

        if (isset($shipemnt->error)) {
            $response['ERROR'] = $shipemnt->error;
        } elseif (isset($shipemnt->soapBody) && $shipResponseIndex->Error->Code !== '0' && !empty($shipResponseIndex->Error->Message)) {
            $response['ERROR'] = $shipResponseIndex->Error->Message;
        } elseif ($shipResponseIndex->Error->Code == '0' && !empty($shipResponseIndex->BOLDocument)) {
            $response['SUCCESS'] = ['BOL' => $shipResponseIndex->BOLDocument, 'BOLNUMBER' => $shipResponseIndex->ShipmentBillNumber];
        } else {
            $response['ERROR'] = 'No response return against this request.';
        }
        return $response;
    }
}
