<?php namespace Mikhailkozlov\RetsLaravel\Rets;

interface RetsInterface
{
    public function connect();

    public function getResource($resourceID = null);

    public function getClass($classID);

    public function getTable($ResourceID, $classID);
}