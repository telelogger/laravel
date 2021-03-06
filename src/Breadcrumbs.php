<?php
declare(strict_types = 1);

namespace Telelogger;


class Breadcrumbs
{
    private $breadcrumbs = [];

    private static $instance = null;

    public static function getInstance() : Breadcrumbs
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function add($data)
    {
        $this->breadcrumbs [] = json_encode($data);
    }

    public function getBreadcrumbs()
    {
        return $this->breadcrumbs;
    }
}
