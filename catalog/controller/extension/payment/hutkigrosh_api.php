<?php

namespace Alexantr\HootkiGrosh;

/**
 * HootkiGrosh class
 *
 * @author Alex Yashkin <alex.yashkin@gmail.com>
 */
class HootkiGrosh
{
    private static $cookies_file;

    private $base_url; // url api

    private $ch; // curl object
    private $error; // ошибка запроса (если есть)
    private $response; // тело ответа
    private $status; // код статуса

    public $cookies_dir;

    // api url
    private $api_url = 'https://www.hutkigrosh.by/API/v1/'; // рабочий
    private $test_api_url = 'https://trial.hgrosh.by/API/v1/'; // тестовый

    // Список ошибок
    private $status_error = array(
        '3221291009' => 'Общая ошибка сервиса',
        '3221291521' => 'Нет информации о счете',
        '3221291522' => 'Нет возможности удалить счет',
        '3221291523' => 'Общая ошибка выставления счета',
        '3221291524' => 'Не указан номер счета',
        '3221291525' => 'Счет не уникальный',
        '3221291526' => 'Счет уже выставлен, но срок оплаты прошел',
        '3221291527' => 'Счет выставлен и оплачен',
        '3221291528' => 'Не указано количество товаров/услуг в заказе',
        '3221291529' => 'Не указана сумма счета',
        '3221291530' => 'Не указано наименование товара',
        '3221291531' => 'Общая сумма счета меньше нуля',
        '3221291601' => 'Возвращены не все счета',
        '3221292033' => 'Общая ошибка установки курсов валют',
        '3221292034' => 'Не указан коэффициент к курсу НБ РБ',
        '3221292035' => 'Не определены курсы валют поставщика услуг',
        '3221292036' => 'Не установлен режим пересчета курсов валют',
        '3221292289' => 'Общая ошибка при получении курсов валют',
    );

    // Список статусов счета
    private $purch_item_status = array(
        'NotSet' => 'Не установлено',
        'PaymentPending' => 'Ожидание оплаты',
        'Outstending' => 'Просроченный',
        'DeletedByUser' => 'Удален',
        'PaymentCancelled' => 'Прерван',
        'Payed' => 'Оплачен',
    );

    // Доступные валюты
    private $currencies = array('BYN', 'USD', 'EUR', 'RUB');

    /**
     * @param bool $is_test Использовать ли тестовый api
     */
    public function __construct($is_test = false)
    {
        if ($is_test) {
            $this->base_url = $this->test_api_url;
        } else {
            $this->base_url = $this->api_url;
        }

        if (!isset(self::$cookies_file)) {
            self::$cookies_file = 'cookies-' . time() . '.txt';
        }

        $this->setCookiesDir(dirname(__FILE__));
    }

    /**
     * Задать путь к папке, где будет находиться файл cookies
     *
     * @param string $dir
     */
    public function setCookiesDir($dir)
    {
        $dir = rtrim($dir, '\\/');
        if (is_dir($dir)) {
            $this->cookies_dir = $dir;
        } else {
            $this->cookies_dir = dirname(__FILE__);
        }
    }

    /**
     * Аутентифицирует пользователя в системе
     *
     * @param string $user
     * @param string $pwd
     *
     * @return bool
     */
    public function apiLogIn($user, $pwd)
    {
        // формируем xml
        $Credentials = new \SimpleXMLElement("<Credentials></Credentials>");
        $Credentials->addAttribute('xmlns', 'http://www.hutkigrosh.by/api');
        $Credentials->addChild('user', trim($user));
        $Credentials->addChild('pwd', trim($pwd));

        $xml = $Credentials->asXML();

        // запрос
        $res = $this->requestPost('Security/LogIn', $xml);

        // проверим, верны ли логин/пароль
        if ($res && !preg_match('/true/', $this->response)) {
            $this->error = 'Ошибка авторизации';
            return false;
        }

        return $res;
    }

    /**
     * Завершает сессию
     * @return bool
     */
    public function apiLogOut()
    {
        $res = $this->requestPost('Security/LogOut');
        // удалим файл с cookies
        $cookies_path = $this->cookies_dir . DIRECTORY_SEPARATOR . self::$cookies_file;
        if (is_file($cookies_path)) {
            @unlink($cookies_path);
        }
        return $res;
    }

    /**
     * Добавляет новый счет в систему
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function apiBillNew(BillNewRq $billNewRq)
    {
        // выберем валюту
        $billNewRq->currency = isset($billNewRq->currency) ? trim($billNewRq->currency) : 'BYN';
        if (!in_array($billNewRq->currency, $this->currencies)) {
            $billNewRq->currency = $this->currencies[0];
        }

        // формируем xml
        $Bill = new \SimpleXMLElement("<Bill></Bill>");
        $Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/api/invoicing');
        $Bill->addChild('eripId', trim($billNewRq->eripId));
        $Bill->addChild('invId', trim($billNewRq->invId));
        $Bill->addChild('dueDt', date('c', strtotime('+1 day'))); // +1 день
        $Bill->addChild('addedDt', date('c'));
        $Bill->addChild('fullName', trim($billNewRq->fullName));
        $Bill->addChild('mobilePhone', trim($billNewRq->mobilePhone));
        $Bill->addChild('notifyByMobilePhone', $billNewRq->notifyByMobilePhone ? "true" : "false");
        if (isset($billNewRq->email)) {
            $Bill->addChild('email', trim($billNewRq->email)); // опционально
            $Bill->addChild('notifyByEMail', $billNewRq->notifyByEMail ? "true" : "false");
        }
        if (isset($billNewRq->fullAddress)) {
            $Bill->addChild('fullAddress', trim($billNewRq->fullAddress)); // опционально
        }
        $Bill->addChild('amt', (float)$billNewRq->amount); // опционально
        $Bill->addChild('curr', $billNewRq->currency);
        $Bill->addChild('statusEnum', 'NotSet');
        // Список товаров/услуг
        if (isset($billNewRq->products) && !empty($billNewRq->products)) {
            $products = $Bill->addChild('products');
            foreach ($billNewRq->products as $pr) {
                $ProductInfo = $products->addChild('ProductInfo');
                if (isset($pr['invItemId'])) {
                    $ProductInfo->addChild('invItemId', trim($pr['invItemId'])); // опционально
                }
                $ProductInfo->addChild('desc', trim($pr['desc']));
                $ProductInfo->addChild('count', (int)$pr['count']);
                if (isset($pr['amt'])) {
                    $ProductInfo->addChild('amt', (float)$pr['amt']); // опционально
                }
            }
        }

        $xml = $Bill->asXML();

        // запрос
        $res = $this->requestPost('Invoicing/Bill', $xml);

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['billID'])) {
                $this->status = (int)$array['status'];
                $billID = trim("{$array['billID']}");

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $billID;
            } else {
                $this->error = 'Неверный ответ сервера';
            }
        }

        return false;
    }

    /**
     * Добавляет новый счет в систему БелГазПромБанк
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function apiBgpbPay($data)
    {
        // формируем xml
        $Bill = new \SimpleXMLElement("<BgpbPayParam></BgpbPayParam>");
        $Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/API/PaymentSystems');
        $Bill->addChild('billId', $data['billId']);
//        $products = $Bill->addChild('orderData');
//        $products->addChild('eripId',$data['eripId']);
//        $products->addChild('spClaimId',$data['spClaimId']);
//        $products->addChild('amount', $data['amount']);
//        $products->addChild('currency', '933');
//        $products->addChild('clientFio', $data['clientFio']);
//        $products->addChild('clientAddress', $data['clientAddress']);
//        $products->addChild('trxId');
        $Bill->addChild('returnUrl', htmlspecialchars($data['returnUrl']));
        $Bill->addChild('cancelReturnUrl', htmlspecialchars($data['cancelReturnUrl']));
        $Bill->addChild('submitValue', 'Оплатить картой на i24.by(БГПБ)');

        $xml = $Bill->asXML();
        // запрос
        $this->requestPost('Pay/BgpbPay', $xml);
        $responseXML = simplexml_load_string($this->response);
        return $responseXML->form->__toString();
    }


    /**
     * Добавляет новый счет в систему AllfaClick
     *
     * @param array $data
     *
     * @return bool|string
     */
    public function apiAlfaClick(AlfaclickRq $alfaclickRq)
    {
        // формируем xml
        $Bill = new \SimpleXMLElement("<AlfaClickParam></AlfaClickParam>");
        $Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/API/PaymentSystems');
        $Bill->addChild('billId', $alfaclickRq->billId);
        $Bill->addChild('phone', $alfaclickRq->phone);
        $xml = $Bill->asXML();
        // запрос
        $res = $this->requestPost('Pay/AlfaClick', $xml);
        $responseXML = simplexml_load_string($this->response); // 0 – если произошла ошибка, billId – если удалось выставить счет в AlfaClick
        return $responseXML;
    }

    /**
     * Получение формы виджета для оплаты картой
     *
     * @param array $data
     *
     * @return bool|string
     */

    public function apiWebPay(WebPayRq $webPayRq)
    {
        // формируем xml
        $Bill = new \SimpleXMLElement("<WebPayParam></WebPayParam>");
        $Bill->addAttribute('xmlns', 'http://www.hutkigrosh.by/API/PaymentSystems');
        $Bill->addChild('billId', $webPayRq->billId);
        $Bill->addChild('returnUrl', htmlspecialchars($webPayRq->returnUrl));
        $Bill->addChild('cancelReturnUrl', htmlspecialchars($webPayRq->cancelReturnUrl));
        $Bill->addChild('submitValue', "Pay with card");
        $xml = $Bill->asXML();
        // запрос
        $res = $this->requestPost('Pay/WebPay', $xml);
        $responseXML = simplexml_load_string($this->response, null, LIBXML_NOCDATA);
        return $responseXML->form->__toString();
    }


    /**
     * Извлекает информацию о выставленном счете
     *
     * @param string $bill_id
     *
     * @return bool|array
     */
    public function apiBillInfo($bill_id)
    {
        // запрос
        $res = $this->requestGet('Invoicing/Bill(' . $bill_id . ')');

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['bill'])) {
                $this->status = (int)$array['status'];
                $bill = (array)$array['bill'];

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $bill;
            } else {
                $this->error = 'Неверный ответ сервера';
            }
        }

        return false;
    }

    /**
     * Удаляет выставленный счет из системы
     *
     * @param string $bill_id
     *
     * @return bool|mixed
     */
    public function apiBillDelete($bill_id)
    {
        $res = $this->requestDelete('Invoicing/Bill(' . $bill_id . ')');

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
                $this->status = (int)$array['status'];
                $purchItemStatus = trim($array['purchItemStatus']); // статус счета

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $purchItemStatus;
            } else {
                $this->error = 'Неверный ответ сервера';
            }
        }

        return false;
    }

    /**
     * Возвращает статус указанного счета
     *
     * @param string $bill_id
     *
     * @return bool|mixed
     */
    public function apiBillStatus($bill_id)
    {
        $res = $this->requestGet('Invoicing/BillStatus(' . $bill_id . ')');

        if ($res) {
            $array = $this->responseToArray();

            if (is_array($array) && isset($array['status']) && isset($array['purchItemStatus'])) {
                $this->status = (int)$array['status'];
                $purchItemStatus = trim($array['purchItemStatus']); // статус счета

                // есть ошибка
                if ($this->status > 0) {
                    $this->error = $this->getStatusError($this->status);
                    return false;
                }

                return $purchItemStatus;
            } else {
                $this->error = 'Неверный ответ сервера';
            }
        }

        return false;
    }

    /**
     * Получить текст ошибки
     *
     * @return string
     */
    public function getError()
    {
        return 'Счет не выставлен! Произошла ошибка: ' . $this->error . '. <br> Повторите заказ.';
    }

    /**
     * Ответ сервера в исходном виде
     *
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Статус ответа
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Статус счета
     *
     * @param string $status
     *
     * @return string
     */
    public function getPurchItemStatus($status)
    {
        return (isset($this->purch_item_status[$status])) ? $this->purch_item_status[$status] : 'Статус не определен';
    }

    /**
     * Подключение GET
     *
     * @param string $path
     * @param string $data
     *
     * @return bool
     */
    private function requestGet($path, $data = '')
    {
        return $this->connect($path, $data, 'GET');
    }

    /**
     * Подключение POST
     *
     * @param string $path
     * @param string $data
     *
     * @return bool
     */
    private function requestPost($path, $data = '')
    {
        return $this->connect($path, $data, 'POST');
    }

    /**
     * Подключение DELETE
     *
     * @param string $path
     * @param string $data
     *
     * @return bool
     */
    private function requestDelete($path, $data = '')
    {
        return $this->connect($path, $data, 'DELETE');
    }

    /**
     * Подключение GET, POST или DELETE
     *
     * @param string $path
     * @param string $data Сформированный для отправки XML
     * @param string $request
     *
     * @return bool
     */
    private function connect($path, $data = '', $request = 'GET')
    {
        $headers = array('Content-Type: application/xml', 'Content-Length: ' . strlen($data));

        $this->ch = curl_init();

        curl_setopt($this->ch, CURLOPT_URL, $this->base_url . $path);
        curl_setopt($this->ch, CURLOPT_HEADER, false); // включение заголовков в выводе
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($this->ch, CURLOPT_VERBOSE, true); // вывод доп. информации в STDERR
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false); // не проверять сертификат узла сети
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, false); // проверка существования общего имени в сертификате SSL
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true); // возврат результата вместо вывода на экран
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers); // Массив устанавливаемых HTTP-заголовков
        if ($request == 'POST') {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        }
        if ($request == 'DELETE') {
            curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $cookies_path = $this->cookies_dir . DIRECTORY_SEPARATOR . self::$cookies_file;

        // если файла еще нет, то создадим его при залогинивании и будем затем использовать при дальнейших запросах
        if (!is_file($cookies_path)) {
            curl_setopt($this->ch, CURLOPT_COOKIEJAR, $cookies_path);
        }
        curl_setopt($this->ch, CURLOPT_COOKIEFILE, $cookies_path);

        $this->response = curl_exec($this->ch);

        if (curl_errno($this->ch)) {
            $this->error = curl_error($this->ch);
            curl_close($this->ch);
            return false;
        } else {
            curl_close($this->ch);
            return true;
        }
    }

    /**
     * Преобразуем XML в массив
     *
     * @return mixed
     */
    private function responseToArray()
    {
        $response = trim($this->response);
        $array = array();
        // проверим, что это xml
        if (preg_match('/^<(.*)>$/', $response)) {
            $xml = simplexml_load_string($response);
            $array = json_decode(json_encode($xml), true);
        }
        return $array;
    }

    /**
     * Описание ошибки на основе ее кода в ответе
     *
     * @param string $status
     *
     * @return string
     */
    private function getStatusError($status)
    {
        return (isset($this->status_error[$status])) ? $this->status_error[$status] : 'Неизвестная ошибка';
    }

    public function getStatusResponce()
    {
        return $this->status;
    }
}

class BillNewRq
{
    public $eripId;
    public $invId;
    public $fullName;
    public $mobilePhone;
    public $email;
    public $fullAddress;
    public $amount;
    public $currency;
    public $products;
    public $notifyByEMail = false;
    public $notifyByMobilePhone = false;
}

class BillInfoRs
{
    public $eripId;
    public $invId;
    public $fullName;
    public $mobilePhone;
    public $email;
    public $fullAddress;
    public $amount;
    public $currency;
    public $products;
}

class WebPayRq
{
    public $billId;
    public $returnUrl;
    public $cancelReturnUrl;
}

class AlfaclickRq
{
    public $billId;
    public $phone;
}