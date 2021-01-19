<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model;

use Magento\Directory\Model\Country;
use Magento\Framework\ObjectManagerInterface;

class WarehouseFactory
{
    /**
     * @var ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @param ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create new country model
     *
     * @param array $arguments
     * @return Country
     */
    public function create(array $arguments = [])
    {
        return $this->objectManager->create('Eniture\GlobalTranzLTLFreightQuotes\Model\Warehouse', $arguments, false);
    }
}
