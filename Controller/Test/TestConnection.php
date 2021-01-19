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
            $credentials[$key] = filter_var($data, FILTER_SANITIZE_STRING);
        }
        //Need to check whether the credentials array is empty or not
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
