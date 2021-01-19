<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Block\System\Config;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class WarehouseTable extends Field
{
    const WAREHOUSE_TEMPLATE = 'system/config/warehouse.phtml';

    public $dataHelper;

    /**
     * @param Context $context
     * @param Data $dataHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $dataHelper,
        $data = []
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    /**
     * @return $this
     */
    public function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::WAREHOUSE_TEMPLATE);
        }
        return $this;
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    public function render(AbstractElement $element)
    {
        // Remove scope label
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * @return string
     */
    public function getAjaxUrl()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Warehouse/';
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * this function return the current plan active
     * @return string
     */
    public function getCurrentPlan()
    {
        return $this->dataHelper->planInfo()['planNumber'];
    }

    /**
     * Show LTL Plan Notice
     * @return string
     */
    public function ltlPlanNotice()
    {
        return $this->dataHelper->ltlSetPlanNotice();
    }

    /**
     * @return string
     */
    public function addWhRestriction()
    {
        return $this->dataHelper->whPlanRestriction();
    }

    public function enUrl()
    {
        return EnConstants::EN_URL;
    }
}
