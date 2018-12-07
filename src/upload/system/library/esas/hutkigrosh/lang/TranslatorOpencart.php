<?php
/**
 * Created by PhpStorm.
 * User: nikit
 * Date: 27.09.2018
 * Time: 13:09
 */

namespace esas\hutkigrosh\lang;


use Registry;

class TranslatorOpencart extends TranslatorImpl
{

    /**
     * @var Registry
     */
    private $registry;

    /**
     * TranslatorOpencart constructor.
     * @param $registry
     */
    public function __construct($registry)
    {
        $this->registry = $registry;
    }


    public function getLocale()
    {
        return $this->registry->get("language")->get("code");
    }


}