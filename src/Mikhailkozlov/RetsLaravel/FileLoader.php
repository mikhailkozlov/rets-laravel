<?php namespace Mikhailkozlov\RetsLaravel;

/**
 * Class FileLoader
 * @package Mikhailkozlov\RetsLaravel
 *
 *          code by Antonio Carlos Ribeiro
 *          http://stackoverflow.com/users/1959747/antonio-carlos-ribeiro
 *          thanks
 *
 *
 */

class FileLoader extends \Illuminate\Config\FileLoader
{
    public function save($items, $environment, $group, $namespace = null)
    {
        $path = $this->getPath($namespace);

        if (is_null($path))
        {
            return;
        }

        $file = (!$environment || ($environment == 'production'))
            ? "{$path}/{$group}.php"
            : "{$path}/{$environment}/{$group}.php";

        $this->files->put($file, '<?php return ' . var_export($items, true) . ';');
    }
}