<?php


namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Plugin\Product;

use Closure;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Ui\DataProvider\CatalogEavValidationRules;

class ValidationRules
{
    /**
     * @param CatalogEavValidationRules $rulesObject
     * @param callable $proceed
     * @param ProductAttributeInterface $attribute,
     * @param array $data
     * @return array
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundBuild(
        CatalogEavValidationRules $rulesObject,
        Closure $proceed,
        ProductAttributeInterface $attribute,
        array $data
    ){
        $rules = $proceed($attribute,$data);
        $dims = ['en_length', 'en_width', 'en_height'];
        if(in_array($attribute->getAttributeCode(), $dims)){ //custom filter
            $validationClasses = empty($attribute->getFrontendClass()) ? [] : explode(' ', $attribute->getFrontendClass());
            foreach ($validationClasses as $class) {
                $rules[$class] = true;
            }
        }
        return $rules;
    }
}
