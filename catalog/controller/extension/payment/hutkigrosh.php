<?php
header('Content-Type: text/html; charset=utf-8');
include_once 'hutkigrosh_api.php';

class ControllerExtensionPaymentHutkiGrosh extends Controller
{
    const BASE_PATH = 'extension/payment/hutkigrosh';
    const HUTKIGROSH_STOREID = 'payment_hutkigrosh_storeid';
    const HUTKIGROSH_STORE_NAME = 'payment_hutkigrosh_store';
    const HUTKIGROSH_LOGIN = 'payment_hutkigrosh_login';
    const HUTKIGROSH_PASSWORD = 'payment_hutkigrosh_pswd';
    const HUTKIGROSH_SANDBOX = 'payment_hutkigrosh_sandbox';
    const HUTKIGROSH_MODULE_STATUS = 'payment_hutkigrosh_status';
    const HUTKIGROSH_MODULE_SORT_ORDER = 'payment_hutkigrosh_sort_order';
    const HUTKIGROSH_ORDER_STATUS_PENDING = 'payment_hutkigrosh_order_status_pending';
    const HUTKIGROSH_ORDER_STATUS_PAYED = 'payment_hutkigrosh_order_status_payed';
    const HUTKIGROSH_ORDER_STATUS_ERROR = 'payment_hutkigrosh_order_status_error';
    const HUTKIGROSH_ERIP_TREE_PATH = 'payment_hutkigrosh_erip_tree_path';
    const HUTKIGROSH_EMAIL_NOTIFICATION = 'payment_hutkigrosh_email_notification';
    const HUTKIGROSH_SMS_NOTIFICATION = 'payment_hutkigrosh_sms_notification';

    // Транслитерация строк.

    public function index()
    {
        $this->language->load(self::BASE_PATH);
        $data['text_sandbox'] = $this->language->get('text_sandbox');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['sandbox'] = $this->config->get(self::HUTKIGROSH_SANDBOX);
        $data['action'] = $this->url->link('extension/payment/hutkigrosh/pay');
        $data['continue'] = $this->url->link('checkout/success');

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . self::BASE_PATH)) {
            return $this->load->view($this->config->get('config_template') . self::BASE_PATH, $data);
        } else {
            return $this->load->view(self::BASE_PATH, $data);
        }
    }


    public function pay()
    {
        //инициализируем URL для HG (тестовы/рабочий)
        try {
            $this->language->load(self::BASE_PATH);
            if (!isset($this->session->data['order_id'])) {
                $this->redirect($this->url->link('checkout/checkout'));
                return false;
            }
            $this->load->model('checkout/order');
            $localOrderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get(self::HUTKIGROSH_ORDER_STATUS_PENDING));
            $hg = new \Alexantr\HootkiGrosh\HootkiGrosh($this->config->get(self::HUTKIGROSH_SANDBOX));
            $res = $hg->apiLogIn($this->config->get(self::HUTKIGROSH_LOGIN), $this->config->get(self::HUTKIGROSH_PASSWORD));

            // Ошибка авторизации
            if (!$res) {
                $error = $hg->getError();
                $hg->apiLogOut(); // Завершаем сеанс
                return $this->failure($error);
            }

            /// создаем заказ
            $line_items = $this->cart->getProducts();
            if (is_array($line_items)) {
                foreach ($line_items as $line_item) {
                    //$arItem['invItemId'] = $line_item['product_id'];
                    $arItem['invItemId'] = $line_item['product_id'];
                    $arItem['desc'] = $line_item['name'] . ' ' . $line_item['model'];
                    $arItem['count'] = round($line_item['quantity']);
                    $arItem['amt'] = $line_item['total'];
                    $arItems[] = $arItem;
                    unset($arItem);
                }
            }
//
            $billNewRq = new \Alexantr\HootkiGrosh\BillNewRq();
            $billNewRq->eripId = $this->config->get(self::HUTKIGROSH_STOREID);
            $billNewRq->invId = $localOrderInfo["order_id"];
            $billNewRq->fullName = $localOrderInfo['firstname'] . ' ' . $localOrderInfo['lastname'];
            $billNewRq->mobilePhone = $localOrderInfo['telephone'];
            $billNewRq->email = $localOrderInfo['email'];
            $billNewRq->fullAddress = $localOrderInfo['payment_address_1'] . ' ' . $localOrderInfo['payment_address_2'] . ' ' . $localOrderInfo['payment_zone'];
            $billNewRq->amount = $localOrderInfo['total'];
            $billNewRq->currency = $localOrderInfo['currency_code'];
            $billNewRq->notifyByEMail = $this->config->get(self::HUTKIGROSH_EMAIL_NOTIFICATION);
            $billNewRq->notifyByMobilePhone = $this->config->get(self::HUTKIGROSH_SMS_NOTIFICATION);
            $billNewRq->products = $arItems;


            $this->_billID = $hg->apiBillNew($billNewRq);
            if (!$this->_billID) {
                $error = $hg->getError();
                $hg->apiLogOut(); // Завершаем сеанс
                return $this->failure($error);
            }

            $webPayRq = new \Alexantr\HootkiGrosh\WebPayRq();
            $webPayRq->billId = $this->_billID;
            $webPayRq->returnUrl = $this->url->link('extension/payment/hutkigrosh/callback') . "&" . "purchaseid=" . $this->_billID . "&status=complete";
            $webPayRq->cancelReturnUrl = $this->url->link('extension/payment/hutkigrosh/callback') . "&" . "purchaseid=" . $this->_billID . "&status=error";

            $webpayform = $hg->apiWebPay($webPayRq);
            $hg->apiLogOut();

            $this->createPage($this->_billID, $localOrderInfo, $webpayform, null);
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        }
    }


    public function alfaclick()
    {
        $hg = new \Alexantr\HootkiGrosh\HootkiGrosh($this->config->get(self::HUTKIGROSH_SANDBOX));
        $res = $hg->apiLogIn($this->config->get(self::HUTKIGROSH_LOGIN), $this->config->get(self::HUTKIGROSH_PASSWORD));
        if (!$res) {
            echo $hg->getError();
            $hg->apiLogOut();
            exit;
        }
        $alfaclickRq = new \Alexantr\HootkiGrosh\AlfaclickRq();
        $alfaclickRq->billId = $this->request->post['billid'];
        $alfaclickRq->phone = $this->request->post['phone'];

        $responceXML = $hg->apiAlfaClick($alfaclickRq);
        $hg->apiLogOut();
        echo intval($responceXML->__toString()) == '0' ? "error" : "ok";
    }

    protected function failure($error)
    {
        $this->session->data['error'] = $error;
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    #уведомление об оплате
    public function callback()
    {
        try {
            $biilId = $this->request->get['purchaseid'];
            $this->checkOrderStatus($biilId);
            $hg = new \Alexantr\HootkiGrosh\HootkiGrosh($this->config->get(self::HUTKIGROSH_SANDBOX));
            $res = $hg->apiLogIn($this->config->get(self::HUTKIGROSH_LOGIN), $this->config->get(self::HUTKIGROSH_PASSWORD));
            if (!$res) {
                $error = $hg->getError();
                $hg->apiLogOut(); // Завершаем сеанс
                throw new Exception($error);
            }
            $localOrderInfo = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $webPayRq = new \Alexantr\HootkiGrosh\WebPayRq();
            $webPayRq->billId = $biilId;
            $webPayRq->returnUrl = $this->url->link('extension/payment/hutkigrosh/callback') . "&" . "purchaseid=" . $biilId . "&status=complete";
            $webPayRq->cancelReturnUrl = $this->url->link('extension/payment/hutkigrosh/callback') . "&" . "purchaseid=" . $biilId . "&status=error";
            $webpayform = $hg->apiWebPay($webPayRq);
            $hg->apiLogOut();
            $this->createPage($biilId, $localOrderInfo, $webpayform, $this->language->get('text_webpay_error'));
        } catch (Exception $e) {
            return $this->failure($e->getMessage());
        }
    }

    #уведомление об оплате
    public function notify()
    {
        try {
            $this->checkOrderStatus($this->request->get['purchaseid']);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    protected function checkOrderStatus($purchaseid)
    {
        $this->language->load('extension/payment/hutkigrosh');
        $pendingStatusId = $this->config->get(self::HUTKIGROSH_ORDER_STATUS_PENDING);
        $payedStatusId = $this->config->get(self::HUTKIGROSH_ORDER_STATUS_PAYED);
        $errorStatusId = $this->config->get(self::HUTKIGROSH_ORDER_STATUS_ERROR);

        if (!is_numeric($pendingStatusId) || !is_numeric($payedStatusId) || !is_numeric($errorStatusId)) {
            throw new Exception('Incorrect module configuration');
        }
        if (!isset($this->request->get['purchaseid'])) {
            throw new Exception('Wrong purchaseid');
        }

        $hg = new \Alexantr\HootkiGrosh\HootkiGrosh($this->config->get(self::HUTKIGROSH_SANDBOX));
        $res = $hg->apiLogIn($this->config->get(self::HUTKIGROSH_LOGIN), $this->config->get(self::HUTKIGROSH_PASSWORD));
        if (!$res) {
            $error = $hg->getError();
            $hg->apiLogOut(); // Завершаем сеанс
            throw new Exception($error);
        }
        #дополнительно проверим статус счета в hg
        $hgBillInfo = $hg->apiBillInfo($purchaseid);
        if (empty($hgBillInfo)) {
            $error = $hg->getError();
            $hg->apiLogOut(); // Завершаем сеанс
            throw new Exception($error);
        } else {
            $this->load->model('checkout/order');
            $localOrderInfo = $this->model_checkout_order->getOrder($hgBillInfo['invId']);
            if ($localOrderInfo['firstname'] . ' ' . $localOrderInfo['lastname'] != $hgBillInfo['fullName']
                && $localOrderInfo['total'] != $hgBillInfo['amt']) {
                throw new Exception("Unmapped purchaseid");
            }
            if ($hgBillInfo['statusEnum'] == 'Payed') {
                if (is_numeric($payedStatusId))
                    $this->model_checkout_order->addOrderHistory(IntVal($hgBillInfo['invId']), $payedStatusId);
            } elseif (in_array($hgBillInfo['statusEnum'], array('Outstending', 'DeletedByUser', 'PaymentCancelled'))) {
                if (is_numeric($errorStatusId))
                    $this->model_checkout_order->addOrderHistory(IntVal($hgBillInfo['invId']), $errorStatusId);
            } elseif (in_array($hgBillInfo['statusEnum'], array('PaymentPending', 'NotSet'))) {
                if (is_numeric($pendingStatusId))
                    $this->model_checkout_order->addOrderHistory(IntVal($hgBillInfo['invId']), $pendingStatusId);
            }
        }
        $hg->apiLogOut();
    }

    protected function createPage($billId, $localOrderInfo, $webpayform, $message)
    {
        $this->document->setTitle($this->language->get('heading_title'));
        $data['webpayform'] = $webpayform;
        $data['message'] = $message;

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_basket'),
            'href' => $this->url->link('checkout/cart')
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_checkout'),
            'href' => $this->url->link('checkout/checkout', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_success'),
            'href' => $this->url->link('checkout/success')
        );

        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_message'] = sprintf($this->language->get('text_erip_instruction'), $this->session->data['order_id'], $this->config->get(self::HUTKIGROSH_ERIP_TREE_PATH), $this->session->data['order_id']);
        $data['button_continue'] = $this->language->get('button_continue');
        $data['continue'] = $this->url->link('checkout/success');

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $data['alfaclickbillID'] = $billId;
        $data['alfaclickTelephone'] = preg_replace("/[^0-9]/", '', $localOrderInfo['telephone']);
        $data['alfaclickUrl'] = $this->url->link('extension/payment/hutkigrosh/alfaclick');
        $data['alfaclickButtonText'] = $this->language->get('alfaclick_button_text');;

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/payment/hutkigrosh_checkout_success')) {
            $templateView = $this->config->get('config_template') . 'extension/payment/hutkigrosh_checkout_success';
        } else {
            $templateView = 'extension/payment/hutkigrosh_checkout_success';
        }
        $this->response->setOutput($this->load->view($templateView, $data));
    }
}
