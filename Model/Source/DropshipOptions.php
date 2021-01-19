<?php

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;

use Eniture\GlobalTranzLTLFreightQuotes\Helper\Data;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Source class for Warehouse and Dropship
 */
class DropshipOptions extends AbstractSource
{
    protected $_dataHelper;
    protected $_options = [];

    public function __construct(
        Data $dataHelper
    ) {
        $this->_dataHelper = $dataHelper;
    }

    /**
     * Abstract method of source class
     * @return array
     */
    public function getAllOptions()
    {
        $get_dropship = $this->_dataHelper->fetchWarehouseSecData('dropship');

        foreach ($get_dropship as $manufacturer) {
            (isset($manufacturer['nickname']) && $manufacturer['nickname'] == '') ? $nickname = '' : $nickname = html_entity_decode($manufacturer['nickname'], ENT_QUOTES) . ' - ';
            $city = $manufacturer['city'];
            $state = $manufacturer['state'];
            $zip = $manufacturer['zip'];
            $dropship = $nickname . $city . ', ' . $state . ', ' . $zip;
            $this->_options[] = [
                'label' => __($dropship),
                'value' => $manufacturer['warehouse_id'],
            ];
        }
        return $this->_options;
    }

    /**
     * Abstract method of source class that returns data
     * @param $value
     * @return boolean
     */
    public function getOptionText($value)
    {
        $options = $this->getAllOptions(false);

        foreach ($options as $item) {
            if ($item['value'] == $value) {
                return $item['label'];
            }
        }
        return false;
    }
}
