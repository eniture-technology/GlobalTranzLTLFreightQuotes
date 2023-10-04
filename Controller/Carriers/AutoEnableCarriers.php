<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Carriers;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;

class AutoEnableCarriers extends Action
{
    /**
     * class property that have google api url
     * @var string
     */
    private $curlUrl;
    private $dataHelper;
    private $scopeConfig;
    private $configWriter;

    /**
     *
     * @param Context $context
     * @param Data $dataHelper
     * @param ScopeConfigInterface $scopeConfig
     */

    public function __construct(
        Context $context,
        Data $dataHelper,
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->dataHelper = $dataHelper;
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->curlUrl = EnConstants::INDEX;
        parent::__construct($context);
    }

    public function execute()
    {
        $data = [];
        foreach ($this->getRequest()->getParams() as $key => $post) {
            $data[$key] = htmlspecialchars($post, ENT_QUOTES);
        }

        $this->configWriter->save('gtLtlCarriers/second/autoEnable', json_encode($data['autoEnable']), $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody(json_encode(['SUCCESS' => 1]));
    }
}
