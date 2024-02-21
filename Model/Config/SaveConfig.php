<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Config;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ReinitableConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Store\Model\ScopeInterface;

class SaveConfig
{
    /**
     * @var WriterInterface
     */
    private $configWriter;
    private $scopeConfig;
    private $request;
    private $_cacheTypeList;
    private $_cacheFrontendPool;
    private $reinitableConfig;

    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter,
        RequestInterface $request,
        TypeListInterface $cacheTypeList,
        Pool $cacheFrontendPool,
        ReinitableConfigInterface $reinitableConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
        $this->_cacheTypeList = $cacheTypeList;
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->reinitableConfig = $reinitableConfig;
        $this->request = $request;
    }

    public function aroundSave(
        \Magento\Config\Model\Config $subject,
        \Closure $proceed
    ) {
        $post = $this->request->getPostValue();
        $path = 'gtLtlCarriers/second/selectedGtCarriers';
        $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT;

        if (isset($post['config_state']['gtLtlCarriers_second'])) {
            unset($post['config_state']);
            unset($post['form_key']);
            $this->configWriter->save($path, json_encode($post), $scope, $scopeId = 0);
        }

        $this->clearMagentoCache();
        return $proceed();
    }

    public function clearMagentoCache()
    {
        $this->reinitableConfig->reinit();
        $types = ['config', 'layout', 'block_html', 'collections', 'reflection', 'db_ddl', 'eav', 'config_integration', 'config_integration_api', 'full_page', 'translate', 'config_webservice'];
        foreach ($types as $type) {
            $this->_cacheTypeList->cleanType($type);
        }
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
