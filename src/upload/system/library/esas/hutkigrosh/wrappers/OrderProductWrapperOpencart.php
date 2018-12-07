<?php
/**
 * Created by PhpStorm.
 * User: nikit
 * Date: 27.09.2018
 * Time: 14:01
 */

namespace esas\hutkigrosh\wrappers;

use Cart\Currency;
use Throwable;

class OrderProductWrapperOpencart extends OrderProductSafeWrapper
{
    private $orderProduct;
    private $unitPrice;

    /**
     * OrderProductWrapperOpencart constructor.
     * @param $orderProduct
     */
    public function __construct($orderProduct, $unitPrice)
    {
        $this->orderProduct = $orderProduct;
        $this->unitPrice = $unitPrice;
    }


    /**
     * Артикул товара
     * @throws Throwable
     * @return string
     */
    public function getInvIdUnsafe()
    {
        return $this->orderProduct['product_id'];
    }

    /**
     * Название или краткое описание товара
     * @throws Throwable
     * @return string
     */
    public function getNameUnsafe()
    {
        return $this->orderProduct['name'] . " " . $this->orderProduct['model'];
    }

    /**
     * Количество товароа в корзине
     * @throws Throwable
     * @return mixed
     */
    public function getCountUnsafe()
    {
        return $this->orderProduct['quantity'];
    }

    /**
     * Цена за единицу товара
     * @throws Throwable
     * @return mixed
     */
    public function getUnitPriceUnsafe()
    {
        return $this->unitPrice;
    }
}