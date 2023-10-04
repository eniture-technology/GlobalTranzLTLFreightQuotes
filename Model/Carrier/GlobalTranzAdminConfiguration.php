<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Carrier;

use Magento\Framework\Registry;
use Magento\Store\Model\ScopeInterface;

/**
 * class for admin configuration that runs first
 */
class GlobalTranzAdminConfiguration
{

    private $registry;
    private $scopeConfig;

    public function _init($scopeConfig, $registry)
    {
        $this->registry = $registry;
        $this->scopeConfig = $scopeConfig;
        $this->setCarriersAndHelpersCodesGlobaly();
        $this->myUniqueLineItemAttribute();
    }

    /**
     * This function set unique Line Item Attributes of carriers
     */
    public function myUniqueLineItemAttribute()
    {
        $lineItemAttArr = [];
        if ($this->registry->registry('UniqueLineItemAttributes') === null) {
            $this->registry->register('UniqueLineItemAttributes', $lineItemAttArr);
        }
    }

    /**
     * This function is for set carriers codes and helpers code globally
     */
    public function setCarriersAndHelpersCodesGlobaly()
    {
        $this->setCodesGlobally('enitureCarrierCodes', 'ENGlobalTranzLTL');
        $this->setCodesGlobally('enitureCarrierTitle', 'GlobalTranz LTL Freight Quotes');
        $this->setCodesGlobally('enitureHelpersCodes', '\Eniture\GlobalTranzLTLFreightQuotes');
        $this->setCodesGlobally('enitureActiveModules', $this->checkModuleIsEnabled());
        $this->setCodesGlobally('enatureModuleTypes', 'ltl');
    }

    /**
     * return if this module is enable or not
     * @return boolean
     */
    public function checkModuleIsEnabled()
    {
        return $this->scopeConfig->getValue("carriers/ENGlobalTranzLTL/active", ScopeInterface::SCOPE_STORE);
    }

    /**
     * This function sets Codes Globally e.g carrier code or helper code
     * @param $globArrayName
     * @param $arrValue
     */
    public function setCodesGlobally($globArrayName, $arrValue)
    {
        if ($this->registry->registry($globArrayName) === null) {
            $codesArray = [];
            $codesArray['globalTranz'] = $arrValue;
            $codesArray['cerasis'] = $arrValue;  //Custom change for this extension only
            $codesArray['globalTranzN'] = $arrValue;  //Custom change for this extension only
            $this->registry->register($globArrayName, $codesArray);
        } else {
            $codesArray = $this->registry->registry($globArrayName);
            $codesArray['globalTranz'] = $arrValue;
            $codesArray['cerasis'] = $arrValue;  //Custom change for this extension only
            $codesArray['globalTranzN'] = $arrValue;  //Custom change for this extension only
            $this->registry->unregister($globArrayName);
            $this->registry->register($globArrayName, $codesArray);
        }
    }
}
