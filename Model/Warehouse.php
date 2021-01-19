<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model;

use Magento\Framework\Model\AbstractModel;

class Warehouse extends AbstractModel
{
    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('Eniture\GlobalTranzLTLFreightQuotes\Model\ResourceModel\Warehouse');
    }
}
