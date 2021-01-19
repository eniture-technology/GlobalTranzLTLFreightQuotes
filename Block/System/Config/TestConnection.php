<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Block\System\Config;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Store\Model\ScopeInterface;

class TestConnection extends Field
{
    const BUTTON_TEMPLATE = 'system/config/testconnection.phtml';

    private $dataHelper;
    private $scopeConfig;

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
        $this->scopeConfig = $context->getScopeConfig();
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
        return $this->dataHelper->ltlSetPlanNotice();
    }

    public function gtLtlConnMsgs()
    {
        $eP = $this->scopeConfig->getValue('gtConnSettings/first/endPoint', ScopeInterface::SCOPE_STORE) ?? '1';
        $msgCe = 'You must have a Cerasis account to use this application. If you do not have one contact Cerasis at 800-734-5351 or <a target="_blank" href="http://cerasis.com/contact/transportation-management-consultation/">register online</a>';

        $msgGt = 'You must have a GlobalTranz account to use this application. If you do not have one contact GlobalTranz at 866-275-1407 or <a target="_blank" href="https://www.globaltranz.com/">register online</a>';

        $msg = $eP == 2 ? $msgGt : $msgCe;
        $div = '<div class="message message-notice notice gtLt-conn-setting-note"><div data-ui-id="messages-message-notice">'.$msg.'</div></div>';



        return ['msgCe' => $msgCe,
                'msgGt' => $msgGt,
                'div' => $div
        ];
    }
}
