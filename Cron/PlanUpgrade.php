<?php
namespace Eniture\GlobalTranzLTLFreightQuotes\Cron;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\HTTP\Client\Curl;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class PlanUpgrade
{
    /**
     * @var String URL
     */
    private $curlUrl = EnConstants::PLAN_URL;
    /**
     * @var Logger Object
     */
    protected $logger;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var Curl
     */
    private $curl;
    /**
     * @var ConfigInterface
     */
    private $resourceConfig;
    /**
     * @var ConfigInterface
     */
    public $scopeConfig;

    /**
     * PlanUpgrade constructor.
     * @param StoreManagerInterface $storeManager
     * @param Curl $curl
     * @param ConfigInterface $resourceConfig
     * @param ScopeConfigInterface scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        Curl $curl,
        ConfigInterface $resourceConfig,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface $logger
    ) {
        $this->storeManager = $storeManager;
        $this->curl = $curl;
        $this->resourceConfig = $resourceConfig;
        $this->scopeConfig   = $scopeConfig;
        $this->logger = $logger;
    }

    /**
     * upgrade plan information
     */
    public function execute()
    {
        $domain = $this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
        $webhookUrl = $domain . 'gtltlfreight';
        $licenseKey = $this->scopeConfig->getValue('gtConnSettings/first/licnsKey',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $postData = http_build_query([
            'platform' => 'magento2',
            'carrier' => '53',
            'store_url' => $domain,
            'webhook_url' => $webhookUrl,
            'license_key'   => ($licenseKey) ?? '',
        ]);

        $this->curl->post($this->curlUrl, $postData);
        $output = $this->curl->getBody();
        if(!empty($output) && is_string($output)){
            $result = json_decode($output, true);
        }else{
            $result = [];
        }

        $plan = $result['pakg_group'] ?? '';
        $expireDay = $result['pakg_duration'] ?? '';
        $expiryDate = $result['expiry_date'] ?? '';
        $planType = $result['plan_type'] ?? '';
        $pakgPrice = $result['pakg_price'] ?? 0;
        if ($pakgPrice == 0) {
            $plan = 0;
        }

        $today = date('F d, Y');
        if (!empty($expiryDate) && strtotime($today) > strtotime($expiryDate)) {
            $plan = '-1';
        }

        $this->saveConfigurations('eniture/ENGlobalTranzLTL/plan', "$plan");
        $this->saveConfigurations('eniture/ENGlobalTranzLTL/expireday', "$expireDay");
        $this->saveConfigurations('eniture/ENGlobalTranzLTL/expiredate', "$expiryDate");
        $this->saveConfigurations('eniture/ENGlobalTranzLTL/storetype', "$planType");
        $this->saveConfigurations('eniture/ENGlobalTranzLTL/pakgprice', "$pakgPrice");
        $this->saveConfigurations('eniture/ENGlobalTranzLTL/label', "Eniture - GlobalTranz LTL Freight Quotes");

        $this->logger->info($output);
    }

    /**
     * @param type $path
     * @param type $value
     */
    public function saveConfigurations($path, $value)
    {
        $this->resourceConfig->saveConfig(
            $path,
            $value,
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            Store::DEFAULT_STORE_ID
        );
    }
}
