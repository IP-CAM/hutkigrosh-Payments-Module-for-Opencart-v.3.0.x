<?php
/**
 * Created by PhpStorm.
 * User: nikit
 * Date: 27.09.2018
 * Time: 13:08
 */

namespace esas\hutkigrosh\wrappers;

use Cart\Cart;
use Cart\Currency;
use ModelCheckoutOrder;
use ModelExtensionPaymentHutkigrosh;
use Registry;
use Throwable;

class OrderWrapperOpencart extends OrderSafeWrapper
{
    private $localOrderInfo;

    /**
     * @var ModelCheckoutOrder
     */
    private $model_checkout_order;

    /**
     * @var ModelExtensionPaymentHutkigrosh
     */
    private $model_extension_payment_hutkigrosh;

    /**
     * @var Currency
     */
    private $currency;

    /**
     * @var Cart
     */
    private $cart;

    /**
     * OrderWrapperOpencart constructor.
     */
    public function __construct($orderId, Registry $registry)
    {
        parent::__construct();
        $loader = $registry->get("load");
        $loader->model('checkout/order');
        $this->model_checkout_order = $registry->get('model_checkout_order');
        $loader->model('extension/payment/hutkigrosh');
        $this->model_extension_payment_hutkigrosh = $registry->get('model_extension_payment_hutkigrosh');
        $this->localOrderInfo = $this->model_checkout_order->getOrder($orderId);
        $this->currency = $registry->get("currency");
        $this->cart = $registry->get("cart");
    }


    /**
     * Уникальный номер заказ в рамках CMS
     * @return string
     * @throws Throwable
     */
    public function getOrderIdUnsafe()
    {
        return $this->localOrderInfo["order_id"];
    }

    /**
     * Полное имя покупателя
     * @throws Throwable
     * @return string
     */
    public function getFullNameUnsafe()
    {
        return $this->localOrderInfo['firstname'] . ' ' . $this->localOrderInfo['lastname'];
    }

    /**
     * Мобильный номер покупателя для sms-оповещения
     * (если включено администратором)
     * @throws Throwable
     * @return string
     */
    public function getMobilePhoneUnsafe()
    {
        return preg_replace("/[^0-9]/", '', $this->localOrderInfo['telephone']);
    }

    /**
     * Email покупателя для email-оповещения
     * (если включено администратором)
     * @throws Throwable
     * @return string
     */
    public function getEmailUnsafe()
    {
        return $this->localOrderInfo['email'];
    }

    /**
     * Физический адрес покупателя
     * @throws Throwable
     * @return string
     */
    public function getAddressUnsafe()
    {
        return $this->localOrderInfo['payment_address_1'] . ' ' . $this->localOrderInfo['payment_address_2'] . ' ' . $this->localOrderInfo['payment_zone'];
    }

    /**
     * Общая сумма товаров в заказе
     * @throws Throwable
     * @return string
     */
    public function getAmountUnsafe()
    {
        return $this->formatAmount($this->localOrderInfo['total']);
    }

    /**
     * Валюта заказа (буквенный код)
     * @throws Throwable
     * @return string
     */
    public function getCurrencyUnsafe()
    {
        return $this->localOrderInfo['currency_code'];
    }

    /**
     * Массив товаров в заказе
     * @throws Throwable
     * @return OrderProductWrapper[]
     */
    public function getProductsUnsafe()
    {
        $products = $this->cart->getProducts();
        foreach ($products as $product)
            $productsWrappers[] = new OrderProductWrapperOpencart($product, $this->formatAmount($product['total']));
        return $productsWrappers;
    }

    private function formatAmount($amount) {
        return $this->currency->format($amount, $this->localOrderInfo['currency_code'], $this->localOrderInfo['currency_value'], false);
    }

    /**
     * BillId (идентификатор хуткигрош) успешно выставленного счета
     * @throws Throwable
     * @return mixed
     */
    public function getBillIdUnsafe()
    {
        return $this->localOrderInfo['payment_custom_field'];
    }

    /**
     * Текущий статус заказа в CMS
     * @return mixed
     * @throws Throwable
     */
    public function getStatusUnsafe()
    {
        return $this->localOrderInfo['order_status_id'];
    }

    /**
     * Обновляет статус заказа в БД
     * @param $newStatus
     * @return mixed
     * @throws Throwable
     */
    public function updateStatus($newStatus)
    {
        $this->model_checkout_order->addOrderHistory($this->getOrderId(), $newStatus);
    }

    /**
     * Сохраняет привязку billid к заказу
     * @param $billId
     * @return mixed
     * @throws Throwable
     */
    public function saveBillId($billId)
    {
        $this->model_extension_payment_hutkigrosh->saveBillId($this->getOrderId(), $billId);
        $this->localOrderInfo['payment_custom_field'] = $billId;
    }
}