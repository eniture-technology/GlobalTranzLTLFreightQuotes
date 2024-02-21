<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;


class ApiEndPoint
{
    public function toOptionArray()
    {
        return ['endPoint' =>
                    ['value' => '2', 'label' => 'GlobalTranz'],
                    ['value' => '3', 'label' => 'New API']
                ];
    }
}
