<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Source;


class QuoteServiceOptions
{
    /**
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            'gtLtlQuoteServices' => ['value' => 'LCR',  'label'  => 'Lowest Cost Rate'],
            ['value' => 'QTR',  'label'  => 'Quickest Transit Rate'],
        ];
    }
}