<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Class OrderPlacebeforeSaveData
 *
 * @package Eniture\GlobalTranzLTLFreightQuotes\Observer
 */
class OrderPlacebeforeSaveData implements ObserverInterface
{
    /**
     * @var SessionManagerInterface
     */
    private $coreSession;

    /**
     * OrderPlacebeforeSaveData constructor.
     *
     * @param SessionManagerInterface $coreSession
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        SessionManagerInterface $coreSession,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->coreSession = $coreSession;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        try {
            $isMulti = '0';
            $multiShip = false;
            $order = $observer->getEvent()->getOrder();
            $quote = $order->getQuote();

            if (isset($quote)) {
                $isMulti = $quote->getIsMultiShipping();
            }

            $method = $order->getShippingMethod();
            if (strpos($method, 'ENGlobalTranzLTL') !== false) {
                $semiOrderDetailData = $this->coreSession->getSemiOrderDetailSession();
                $orderDetailData = $this->coreSession->getOrderDetailSession();
                if ($orderDetailData != null && $semiOrderDetailData == null) {
                    if (count($orderDetailData['shipmentData']) > 1) {
                        $multiShip = true;
                    }
                    $orderDetailData = $this->getData($order, $method, $orderDetailData, $multiShip);
                } elseif ($semiOrderDetailData) {
                    $orderDetailData = $semiOrderDetailData;
                    $this->coreSession->unsSemiOrderDetailSession();
                }
                $order->setData('order_detail_data', json_encode($orderDetailData));
                $order->save();
                if (!$isMulti) {
                    $this->coreSession->unsOrderDetailSession();
                }
            }
        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
    }

    public function getData($order, $method, $orderDetailData, $multiShip)
    {
        $liftGate = $resi = false;
        $shippingMethod = explode('_', $method);
        $ownArr = $shippingMethod[1] == 'ownArrangement';
        /*These Lines are added for compatibility only*/
        $lgArray = ['always' => 1, 'asOption' => '', 'residentialLiftgate' => ''];
        $orderDetailData['residentialDelivery'] = 0;
        /*These Lines are added for compatibility only*/

        $arr = (explode('+', $method));
        if (in_array('LG', $arr)) {
            $orderDetailData['liftGateDelivery'] = $lgArray;
            $liftGate = true;
        }
        if (in_array('R', $arr)) {
            $orderDetailData['residentialDelivery'] = 1;
            $resi = true;
        }
        foreach ($orderDetailData['shipmentData'] as $key => $value) {
            if ($multiShip && !$ownArr) {
                $quotes = reset($value['quotes']);
                if ($liftGate && isset($quotes['liftgate'])) {
                    $orderDetailData['shipmentData'][$key]['quotes'] = $quotes['liftgate'];
                } else {
                    $orderDetailData['shipmentData'][$key]['quotes'] = $quotes['simple'];
                }
            } else {
                $orderDetailData['shipmentData'][$key]['quotes'] = $this->getFinalArray($shippingMethod[1], $order);
            }
        }
        return $orderDetailData;
    }

    public function getFinalArray($method, $order)
    {
        return [
            'code' => $method[1],
            'title' => str_replace("GlobalTranz LTL Freight Quotes - ", "", $order->getShippingDescription()),
            'rate' => number_format((float)$order->getShippingAmount(), 2, '.', '')
        ];
    }
}
