<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Block\System\Config;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

class TestConnection extends Field
{
    const BUTTON_TEMPLATE = 'system/config/testconnection.phtml';

    private $dataHelper;

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
            $this->setTemplate(static::BUTTON_TEMPLATE);
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
    public function getAjaxCheckUrl()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Test/TestConnection/';
    }

    /**
     * @param AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element)
    {
        $this->addData(
            [
                'id' => 'gtLtlTestConnBtn',
                'button_label' => 'Test Connection',
            ]
        );
        return $this->_toHtml();
    }

    /**
     * Show LTL Plan Notice
     * @return string
     */
    public function getPlanNotice()
    {
        $planRefreshUrl = $this->getPlanRefreshUrl();
        return $this->dataHelper->ltlSetPlanNotice($planRefreshUrl);
    }

    public function gtLtlConnMsgs()
    {
        $msgGt = 'You must have a GlobalTranz account to use this application. If you do not have one contact GlobalTranz at 866-275-1407 or <a target="_blank" href="https://www.globaltranz.com/">register online</a>';
        $div = '<div class="message message-notice notice gtLt-conn-setting-note"><div data-ui-id="messages-message-notice">'.$msgGt.'</div></div>';

        return ['msgGt' => $msgGt,
                'div' => $div
        ];
    }

    /**
     * @return string
     */
    public function getPlanRefreshUrl()
    {
        return $this->getbaseUrl() . 'gtltlfreight/Test/PlanRefresh/';
    }
}
