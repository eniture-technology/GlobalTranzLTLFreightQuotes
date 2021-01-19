<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Carrier;

use Magento\Store\Model\ScopeInterface;

/**
 * class that generated request data
 */
class GlobalTranzGenerateRequestData
{
    /**
     * @var Object
     */
    private $registry;
    /**
     * @var Object
     */
    private $moduleManager;
    /**
     * @var object
     */
    private $request;
    /**
     * @var object
     */
    private $scopeConfig;

    /**
     * @var object
     */
    private $timezone;

    private $appConfigData = [];

    /**
     * constructor of class that accepts request object
     * @param $scopeConfig
     * @param $registry
     * @param $moduleManager
     * @param $request
     * @param $timezone
     */
    public function _init(
        $scopeConfig,
        $registry,
        $moduleManager,
        $request,
        $timezone
    ) {
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->moduleManager = $moduleManager;
        $this->request = $request;
        $this->timezone = $timezone;
        $this->setAppConfigData();
    }

    public function setAppConfigData()
    {
        $scope     = ScopeInterface::SCOPE_STORE;
        $planNumber= (int) $this->scopeConfig->getValue("eniture/ENGlobalTranzLTL/plan", $scope);
        $quoteSett = $this->scopeConfig->getValue("gtQuoteSetting/fourth", $scope);
        $connSett  = $this->scopeConfig->getValue("gtConnSettings/first", $scope);
        $quoteSett = $quoteSett ?? [];
        $connSett  = $connSett ?? [];
        $this->appConfigData = array_merge($quoteSett, $connSett, ['plan' => $planNumber]);
    }

    /**
     * function that generates array
     * @return array
     */
    public function generateEnitureArray() //$origin, $request
    {
        $getDistance = 0;
        return [
            'licenseKey' => $this->getConfigData('licnsKey'),
            'serverName' => $this->request->getServer('SERVER_NAME'),
            'carrierMode' => 'pro', // use test / pro
            'quotestType' => 'ltl', // ltl / small
            'version' => '2.0',
            'returnQuotesOnExceedWeight' => $this->getConfigData('weightExeeds'),
            'liftGateAsAnOption' => $this->getConfigData('offerLiftGate'),
            'api' => $this->getApiInfoArr(),
            'getDistance' => $getDistance,
        ];
    }


    /**
     * function for generate request array
     * @param $request
     * @param $originArr
     * @param $itemsArr
     * @param $cart
     * @return array|bool
     */
    public function generateRequestArray($request, $originArr, $itemsArr, $cart)
    {
        if (count($originArr['originAddress']) > 1) {
            $whIDs = [];
            foreach ($originArr['originAddress'] as $wh) {
                $whIDs[] = $wh['locationId'];
            }
            if (count(array_unique($whIDs)) > 1) {
                foreach ($originArr['originAddress'] as $id => $wh) {
                    if (isset($wh['InstorPickupLocalDelivery'])) {
                        $originArr['originAddress'][$id]['InstorPickupLocalDelivery'] = [];
                    }
                }
            }
        }
        $carriers = $this->registry->registry('enitureCarriers');
        $index = 'globalTranz';
        if ($this->getConfigData('endPoint') == 1) {
            unset($carriers['globalTranz']);
            $index = 'cerasis';

        }
        $carriers[$index] = $originArr;
        $receiverAddress = $this->getReceiverData($request);

        $autoResidential = $liftGateWithAuto = '0';
        if ($this->autoResidentialDelivery()) {
            $autoResidential = '1';
            $liftGateWithAuto = $this->getConfigData('RADforLiftgate') ?? '0';

            if ($this->registry->registry('radForLiftgate') === null) {
                $this->registry->register('radForLiftgate', $liftGateWithAuto);
            }
        }
        $smartPost = $this->registry->registry('fedexSmartPost');

        return [
            'apiVersion' => '2.0',
            'platform' => 'magento2',
            'binPackagingMultiCarrier' => $this->binPackSuspend(),
            'autoResidentials' => $autoResidential,
            'liftGateWithAutoResidentials' => $liftGateWithAuto,
            'FedexOneRatePricing' => $smartPost,
            'FedexSmartPostPricing' => $smartPost,
            'requestKey' => $cart->getQuote()->getId(),
            'carriers' => $carriers,
            'receiverAddress' => $receiverAddress,
            'commdityDetails' => $itemsArr,
        ];
    }

    /**
     * @return string
     */
    public function binPackSuspend()
    {
        $return = "0";
        if ($this->moduleManager->isEnabled('Eniture_BoxSizes')) {
            $return = $this->scopeConfig->getValue("binPackaging/suspend/value", ScopeInterface::SCOPE_STORE) == "no" ? "1" : "0";
        }
        return $return;
    }

    /**
     * this function returns active eniture modules count
     * @return int
     */
    public function getActiveEnitureModulesCount()
    {
        $activeModules = array_keys($this->dataHelper->getActiveCarriersForENCount());
        $activeEnModulesArr = array_filter($activeModules, function ($moduleName) {
            if (substr($moduleName, 0, 2) == 'EN') {
                return true;
            }
            return false;
        });

        return count($activeEnModulesArr);
    }

    /**
     * this function returns active Eniture modules count
     * @return int
     */
    public function autoResidentialDelivery()
    {
        $autoDetectResidential = 0;
        if ($this->moduleManager->isEnabled('Eniture_ResidentialAddressDetection')) {
            $suspendPath = "resaddressdetection/suspend/value";
            $autoResidential = $this->scopeConfig->getValue($suspendPath, ScopeInterface::SCOPE_STORE);
            if ($autoResidential != null && $autoResidential == 'no') {
                $autoDetectResidential = 1;
            }
        }
        return $autoDetectResidential;
    }

    /**
     * This function returns carriers array if have not empty origin address
     * @return array
     */
    public function getCarriersArray()
    {
        $carriersArr = $this->registry->registry('enitureCarriers');
        $newCarriersArr = [];
        foreach ($carriersArr as $carrKey => $carrArr) {
            $notHaveEmptyOrigin = true;
            foreach ($carrArr['originAddress'] as $value) {
                if (empty($value['senderZip'])) {
                    $notHaveEmptyOrigin = false;
                }
            }
            if ($notHaveEmptyOrigin) {
                $newCarriersArr[$carrKey] = $carrArr;
            }
        }
        return $newCarriersArr;
    }

    /**
     * function that returns API array
     * @return array
     */
    public function getApiInfoArr()
    {
        $endPoint = $this->getConfigData('endPoint');
        $defaults = [
            'direction' => 'Dropship',    //Not used now
            'billingType' => 'Prepaid',
        ];
        $defaults = $endPoint == 1 ? $defaults : [];
        $api = $this->getApiCreds($endPoint);
        $accessorials['accessorial'] = $this->getAccessorials($endPoint);

        return array_merge($api, $defaults, $accessorials);
    }

    public function getApiCreds($endPoint)
    {
        $apiArray = [];
        if ($endPoint == 1) {
            $isFinalMile = $this->getConfigData('shippingService') == 2;
            $finalMileService = $isFinalMile ? $this->getConfigData('finalMileServices') : 0;
            $finalMileServices = [  0 => '',
                                    1 => 'THRSHLD_FM',
                                    2 => 'ROOMCHC_FM',
                                    3 => 'PREMIUM_FM',
            ];
            $apiArray =  [
                'shipperID'         => $this->getConfigData('cerasisltlshipperID'),
                'username'          => $this->getConfigData('cerasisltlusername'),
                'password'          => $this->getConfigData('cerasisltlPassword'),
                'accessKey'         => $this->getConfigData('cerasisltlAccessKey'),
                'isFinalMile'       => $isFinalMile,
                'finalMileService'  => $finalMileServices[$finalMileService],
                'cerasisApiVersion' => '2.0'
            ];
        } else {
            $apiArray =  [
                'username'   => $this->getConfigData('gtLtlUsername'),
                'password'   => $this->getConfigData('gtLtlPassword'),
                'accessKey'  => $this->getConfigData('gtLtlAuthKey'),
                'customerId' => $this->getConfigData('gtLtlCustomerId'),
                'accessLevel' => 'pro', // or test (in parallet to API credentials in GT only)
                'version' => '2.0'
            ];
        }
        //$apiArray['cerasisApiVersion'] = '2.0';
        $cutOffData = $this->getCutoffData();
        return array_merge($apiArray, $cutOffData);
    }

    public function getAccessorials($endPoint)
    {
        $residential = !$this->autoResidentialDelivery() ? $this->getConfigData('residentialDlvry') : 0;
        $liftGate = ($this->getConfigData('liftGate') || $this->getConfigData('offerLiftGate')) ? 1 : 0;
        $isFmService = $this->getConfigData('shippingService') == 2;

        $accessorials = [];
        if ($endPoint == 1 && !$isFmService) {
            $residential ? array_push($accessorials, 'RESDEL') : '';
            $liftGate ? array_push($accessorials, 'LFTGATDEST') : '';
        } else if ($endPoint == 2) {
            $residential ? $accessorials['RESD'] = '14' : '';
            $liftGate ? $accessorials['LGD'] = '12' : '';
        }
        return $accessorials;
    }

    public function getCutoffData()
    {
        $return = [];
        $isEligible = $this->getConfigData('plan') > 1 && $this->getConfigData('enableCuttOff');
        if ($isEligible) {
            $cutOffTime = str_replace(',' , ':', $this->getConfigData('cutOffTime'));
            $shipDays   = explode(',' ,$this->getConfigData('shipDays'));

            $return = [ 'modifyShipmentDateTime' => '1',
                        'OrderCutoffTime'        => $cutOffTime,
                        'shipmentOffsetDays'     => $this->getConfigData('offsetDays'),
                        'storeDateTime'          => $this->timezone->date()->format('Y-m-d H:i:s'),
                        'shipmentWeekDays'       => $shipDays
                ];
        }
        return $return;
    }

    /**
     * function return service data
     * @param $index
     * @return string
     */
    public function getConfigData($index)
    {
        return $this->appConfigData[$index] ?? '';
    }

    /**
     * This function returns Receiver Data Array
     * @param $request
     * @return array
     */
    public function getReceiverData($request)
    {
        $country = $request->getDestCountryId();
        $addressTypePath = "resaddressdetection/addressType/value";
        $addressType = $this->scopeConfig->getValue($addressTypePath, ScopeInterface::SCOPE_STORE);
        $endPoint = $this->scopeConfig->getValue('gtConnSettings/first/endPoint', ScopeInterface::SCOPE_STORE);
        if ($endPoint == 2) {
            $country = $country == 'CA' ? 'CAN' : 'USA';
        }
        return [
            'addressLine' => $request->getDestStreet(),
            'receiverCity' => $request->getDestCity(),
            'receiverState' => $request->getDestRegionCode(),
            'receiverZip' => preg_replace('/\s+/', '', $request->getDestPostcode()),
            'receiverCountryCode' => $country,
            'defaultRADAddressType' => $addressType ?? 'residential', //get value from RAD
        ];
    }
}
