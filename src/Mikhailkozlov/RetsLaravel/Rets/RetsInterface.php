<?php namespace Mikhailkozlov\RetsLaravel\Rets;

interface RetsInterface
{
    public function connect();

    public function getResource($resourceID = null);

    public function getClass($classID);

    public function getTable($ResourceID, $classID);

    public function getFieldMetadata($ResourceID, $fieldID);

    public function search($query = null, $searchType = 'Property', $class = 'A', $queryType = 'DMQL2');
}