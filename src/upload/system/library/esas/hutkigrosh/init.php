<?php
require_once(dirname(dirname(__FILE__)) . '/vendor/esas/hutkigrosh-api-php/src/esas/hutkigrosh/CmsPlugin.php');
use esas\hutkigrosh\CmsPlugin;
use esas\hutkigrosh\RegistryOpencart;


(new CmsPlugin(dirname(dirname(__FILE__)) . '/vendor', dirname(dirname(dirname(__FILE__)))))
    ->setRegistry(new RegistryOpencart($registry))
    ->init();
