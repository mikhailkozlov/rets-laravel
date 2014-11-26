<?php namespace Mikhailkozlov\RetsLaravel\Rets;

interface RetsInterface
{
    public function connect();

    public function getResource($resourceID = null);

    public function getClass($classID = null);

    public function getTable($classID = null, $type);
}