<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Carriers;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Store\Model\ScopeInterface;

class CerasisGetCarriers extends Action
{
    /**
     * class property that have google api url
     * @var string
     */
    private $dataHelper;
    private $scopeConfig;
    private $configWriter;
    /**
     * Reinitable Config Model.
     *
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;

    /**
     *
     * @param Context $context
     * @param Data $dataHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     * @param ReinitableConfigInterface $reinitableConfig
     */

    public function __construct(
        Context $context,
        Data $dataHelper,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->dataHelper = $dataHelper;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->reinitableConfig = $reinitableConfig;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = [];
        $autoEnable = '';
        foreach ($this->getRequest()->getParams() as $key => $post) {
            $data[$key] = htmlspecialchars($post, ENT_QUOTES);
        }

        $autoEnableEnco = $this->scopeConfig->getValue('gtLtlCarriers/second/autoEnable', ScopeInterface::SCOPE_STORE);
        if(!empty($autoEnableEnco) && is_string($autoEnableEnco)){
            $autoEnable = json_decode($autoEnableEnco);
        }

        $req = $this->dataHelper->getCerasisCarriersReq($data['action']);
        $carriersRes = $this->dataHelper->cerasisSendCurlRequest(EnConstants::INDEX, $req);
        if ($autoEnable == 'yes') {
            $this->updateNewCarriers($carriersRes);
        }
        $status = $this->dataHelper->carrierResult($carriersRes);
        $this->reinitableConfig->reinit();
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode($status));
    }

    /**
     * @param $carriersRes
     */
    public function updateNewCarriers($carriersRes)
    {

        $selectedCarriersEnco = $this->scopeConfig->getValue('gtLtlCarriers/second/selectedCarriers', ScopeInterface::SCOPE_STORE);
        if(!empty($selectedCarriersEnco) && is_string($selectedCarriersEnco)){
            $selectedCarriers = (array)json_decode($selectedCarriersEnco);
        }else{
            $selectedCarriers = [];
        }

        $previousCarriersEnco = $this->scopeConfig->getValue('gtLtlCarriers/second/carriers', ScopeInterface::SCOPE_STORE);
        if(!empty($previousCarriersEnco) && is_string($previousCarriersEnco)){
            $previousCarriers = (array)json_decode($previousCarriersEnco);
        }else{
            $previousCarriers = [];
        }
        
        $newSelected = $oldSavedCarriers = [];

        foreach ($previousCarriers as $key => $data) {
            foreach ($data as $d) {
                $oldSavedCarriers[] = $d->CarrierSCAC;
            }
        }

        if (!isset($carriersRes->error)) {
            foreach ($carriersRes->carriers as $key => $carriers) {
                if (!in_array($carriers->CarrierSCAC, $oldSavedCarriers)) {
                    $newSelected[$carriers->CarrierSCAC] = $carriers->CarrierSCAC;
                }
            }

            $selectAllChkBx = (count($newSelected) == count($carriersRes->carriers)) ? ['cerasisAllCarriers' => 'on'] : [];
            $this->configWriter->save('gtLtlCarriers/second/selectedCarriers', json_encode(array_merge($selectAllChkBx, $selectedCarriers, $newSelected)), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
        }
    }
}
