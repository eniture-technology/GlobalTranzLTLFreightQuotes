<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Block\System\Config;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Mtf\Client\BrowserInterface;

class UserGuide extends Field
{
    const GUIDE_TEMPLATE = 'system/config/userguide.phtml';

    private $dataHelper;

    public $docUrl = EnConstants::EN_URL.'/#documentation';

    /**
     * @param Context $context
     * @param Data $dataHelper
     * @param array $data
     */
    public function __construct(
        Context $context,
        Data $dataHelper,
        array $data = []
    ) {
        $this->dataHelper = $dataHelper;
        parent::__construct($context, $data);
    }

    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::GUIDE_TEMPLATE);
        }
        return $this;
    }

    protected function _getElementHtml(AbstractElement $element)
    {
        return $this->_toHtml();
    }

    /**
     * Show Plan Notice
     * @return string
     */
    public function ltlPlanNotice()
    {
        return $this->dataHelper->LtlSetPlanNotice();
    }
}
