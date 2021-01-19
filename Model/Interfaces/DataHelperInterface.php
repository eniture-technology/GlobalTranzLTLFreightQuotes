<?php
/*
 * Dated: 25 Fab. 2020
 * This interface is defined for Data Helper class.
 * Author: Eniture / Azeem
 * */

namespace Eniture\GlobalTranzLTLFreightQuotes\Model\Interfaces;

interface DataHelperInterface
{
    /**
     * @return array
     */
    public function planInfo();

    /**
     * @param $location
     * @return array
     */
    public function fetchWarehouseSecData($location);

    /**
     * @param $location
     * @param $warehouseId
     * @return array
     */
    public function fetchWarehouseWithID($location, $warehouseId);

    /**
     * @param $data
     * @param $whereClause
     * @return bool
     */
    public function updateWarehouseData($data, $whereClause);

    /**
     * @param $data
     * @param $id
     * @return array
     */
    public function insertWarehouseData($data, $id);

    /**
     * @param $data
     * @return bool
     */
    public function deleteWarehouseSecData($data);

    /**
     * @param $inputData
     * @return array
     */
    public function originArray($inputData);

    /**
     * @param $getWarehouse
     * @param $validateData
     * @return string
     */
    public function checkUpdateInstorePickupDelivery($getWarehouse, $validateData);

    /**
     * @param $data
     * @return array
     */
    public function getWarehouseData($data);

    /**
     * @param array $servicesArr
     * @param $hazShipmentArr
     * @return mixed
     */
    public function setOrderDetailWidgetData(array $servicesArr, $hazShipmentArr);

    /**
     * @param $service
     * @return string
     */
    public function getAutoResidentialTitle($service);

    /**
     * @param $scopeConfig
     * @return array
     */
    public function getAllConfigServicesArray($scopeConfig);

    /**
     * @param $cost
     * @return int
     */
    public function calculateHandlingFee($cost);

    /**
     * @param $confPath
     * @return mixed
     */
    public function getConfigData($confPath);
}
