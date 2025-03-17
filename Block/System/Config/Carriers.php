<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Block\System\Config;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Eniture\GlobalTranzLTLFreightQuotes\Model\Source\GlobalTranzCarriers;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class Carriers extends Field
{
    const GT_TEMPLATE = 'system/config/globaltranzCarriers.phtml';

    public $dataHelper;
    public $carriersList = [];
    public $requestTime;
    public $autoEnable = 'no';
    /**
     * Reinitable Config Model.
     *
     * @var ReinitableConfigInterface
     */
    private $reinitableConfig;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var mixed
     */
    public $selectedCarriers;
    /**
     * @var mixed
     */
    private $configData;
    /**
     * @var array
     */
    private $imagesArray;

    /**
     * @param Context $context
     * @param Data $dataHelper
     * @param ScopeConfigInterface $scopeConfig
     * @param ReinitableConfigInterface $reinitableConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $dataHelper,
        ScopeConfigInterface $scopeConfig,
        ReinitableConfigInterface $reinitableConfig,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        $this->scopeConfig = $scopeConfig;
        parent::__construct($context, $data);
        $this->reinitableConfig = $reinitableConfig;
        $this->setConfigData();
        $this->setCarriersData();
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(self::GT_TEMPLATE);
        }
        return $this;
    }

    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    public function getCerasisCarriers()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Carriers/CerasisGetCarriers/';
    }

    public function autoEnableCarriers()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Carriers/AutoEnableCarriers/';
    }

    public function setCarriersData()
    {
        $this->imagesArray = GlobalTranzCarriers::getImagesArray();
        $this->clearMagentoCache();
        $this->getGlobalTranzCarriersList();
    }

    public function getGlobalTranzCarriersList()
    {
        $selectedCarriers = $this->getConfigData('selectedGtCarriers');
        if (!empty($selectedCarriers) && is_string($selectedCarriers)) {
            $this->selectedCarriers = json_decode($selectedCarriers);
        }else{
            $this->selectedCarriers = [];
        }
        $this->carriersList = GlobalTranzCarriers::getCarriersArray();
    }

    public function setConfigData()
    {
        $this->configData = $this->scopeConfig->getValue('gtLtlCarriers/second', ScopeInterface::SCOPE_STORE);
    }

    public function getConfigData($index)
    {
        return $this->configData[$index] ?? '';
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    public function clearMagentoCache()
    {
        $this->reinitableConfig->reinit();
        $this->dataHelper->clearCache();
    }

    /**
     * Show Get Plan Notice
     * @return string
     */
    public function getPlanNotice()
    {
        $planRefreshUrl = $this->getPlanRefreshUrl();
        return $this->dataHelper->LtlSetPlanNotice($planRefreshUrl);
    }

    public function getImgUrl($name)
    {
        $url = '';
        $imgName = strpos($name, '-') !== false ? strstr($name, '-', true) : $name;
        $imgName = strtolower($imgName);
        if (in_array($imgName, $this->imagesArray)) {
            $path = 'Eniture_GlobalTranzLTLFreightQuotes::images/'.$imgName.'.png';
            $url = $this->getViewFileUrl($path);
        }
        return $url;
    }

    /**
     * @return string
     */
    public function getPlanRefreshUrl()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Test/PlanRefresh/';
    }
}
