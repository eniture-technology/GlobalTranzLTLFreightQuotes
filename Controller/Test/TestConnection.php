<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Controller\Test;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\EnConstants;
use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;

class TestConnection extends Action
{
    /**
     * @var Helper Object
     */
    private $dataHelper;
    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @param Context $context
     * @param Data $dataHelper
     */
    public function __construct(
        Context $context,
        Data $dataHelper
    ) {
        $this->request = $context->getRequest();
        $this->dataHelper = $dataHelper;
        parent::__construct($context);
    }

    public function execute()
    {
        $credentials = [];
        foreach ($this->getRequest()->getParams() as $key => $data) {
            $credentials[$key] = htmlspecialchars($data, ENT_QUOTES);
        }
        //Need to check whether the credentials array is empty or not
        
        if($credentials['carrierName'] == 'wweLTL'){
            $post = [
                'platform'                  => 'magento2',
                'carrier_mode'              => 'test',
                'ApiVersion'                => '2.0',
                'carrierName'               => $credentials['carrierName'],
                'speed_freight_username'    => $credentials['username'] ?? '',
                'speed_freight_password'    => $credentials['password'] ?? '',
                'clientId'                  => $credentials['clientId'],
                'clientSecret'                 => $credentials['clientSecret'],
                'plugin_licence_key'        => $credentials['pluginLicenceKey'],
                'plugin_domain_name'        => $this->request->getServer('SERVER_NAME'),
                'isUnishipperNewApi'        => 'yes',
            ];
            $url = EnConstants::TEST_CONN_URL_WWE;
        }else{
            $post = [
                'platform'      => 'magento2',
                'carrier_mode'  => 'test',
                'carrierName'   => $credentials['carrierName'],
                'username'      => $credentials['username'],
                'password'      => $credentials['password'],
                'accessKey'     => $credentials['accessKey'],
                'shipperID'     => $credentials['shipperID'] ?? '',
                'customerID'    => $credentials['customerID'] ?? '',
                'licence_key'   => $credentials['pluginLicenceKey'],
                'serverName'    => $this->request->getServer('SERVER_NAME'),
            ];
            $url = EnConstants::INDEX;
        }

        $response = $this->dataHelper->cerasisSendCurlRequest($url, $post);
        $result = $this->gtLtlTestConnResponse($response);
        $this->getResponse()->setHeader('Content-type', 'application/json');
        $this->getResponse()->setBody($result);
    }

    /**
     * @param $result
     * @return false|string
     */

    public function gtLtlTestConnResponse($result)
    {
        $response = [];
        $errorMsg = 'The credentials entered did not result in a successful test. Confirm your credentials and try again.';
        if (isset($result->severity) && $result->severity == 'ERROR') {
            $response = ['Error' => $result->Message];
        } elseif (isset($result->severity) && $result->severity == "SUCCESS") {
            $response = ['Success' => 'The test resulted in a successful connection.'];
        } elseif (isset($result->error)) {
            $response = ['Error' => $result->error];
        }
        return json_encode($response);
    }
}
