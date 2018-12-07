<?php
header('Content-Type: text/html; charset=utf-8');

use esas\hutkigrosh\controllers\ControllerAddBill;
use esas\hutkigrosh\controllers\ControllerAlfaclick;
use esas\hutkigrosh\controllers\ControllerNotify;
use esas\hutkigrosh\controllers\ControllerWebpayFormSimple;
use esas\hutkigrosh\utils\Logger;
use esas\hutkigrosh\Registry as HutkigroshRegistry;
use esas\hutkigrosh\view\client\CompletionPanel;
use esas\hutkigrosh\wrappers\OrderWrapperOpencart;

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/system/library/esas/hutkigrosh/init.php');

class ControllerExtensionPaymentHutkiGrosh extends Controller
{
    const BASE_PATH = 'extension/payment/hutkigrosh';

    public function index()
    {
        $this->language->load(self::BASE_PATH);
        $data['sandbox'] = HutkigroshRegistry::getRegistry()->getConfigurationWrapper()->isSandbox();
        $data['action'] = $this->url->link(self::BASE_PATH . '/pay');
        $data['continue'] = $this->url->link('checkout/success');

        $this->i18n($data, ['text_sandbox', 'button_confirm', 'text_loading']);
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . self::BASE_PATH)) {
            return $this->load->view($this->config->get('config_template') . self::BASE_PATH, $data);
        } else {
            return $this->load->view(self::BASE_PATH, $data);
        }
    }

    //todo перенести в utils
    private function i18n(&$data, array $fields)
    {
        foreach ($fields as $field) {
            $data[$field] = $this->language->get($field);
        }
    }

    public function pay()
    {
        try {
            $orderId = $this->session->data['order_id'];
            if (!isset($orderId)) {
                $this->redirect($this->url->link('checkout/checkout'));
                return false;
            }
            $configurationWrapper = HutkigroshRegistry::getRegistry()->getConfigurationWrapper();
            $orderWrapper = new OrderWrapperOpencart($orderId, $this->registry);
            // проверяем, привязан ли к заказу billid, если да,
            // то счет не выставляем, а просто прорисовываем старницу
            if (empty($orderWrapper->getBillId())) {
                $controller = new ControllerAddBill();
                $controller->process($orderWrapper);
            }
            $completionPanel = new CompletionPanel($orderWrapper);
            if ($configurationWrapper->isAlfaclickButtonEnabled()) {
                $completionPanel->setAlfaclickUrl($this->url->link(self::BASE_PATH . '/alfaclick'));
            }
            if ($configurationWrapper->isWebpayButtonEnabled()) {
                $controller = new ControllerWebpayFormSimple($this->url->link(self::BASE_PATH . '/pay'));
                $webpayResp = $controller->process($orderWrapper);
                $completionPanel->setWebpayForm($webpayResp->getHtmlForm());
                if (array_key_exists('webpay_status', $_REQUEST))
                    $completionPanel->setWebpayStatus($_REQUEST['webpay_status']);
            }
            $completionPanel->getViewStyle()
                ->setAlfaclickButtonClass("btn btn-primary")
                ->setWebpayButtonClass("btn btn-primary")
                ->setMsgSuccessClass("alert alert-info")
                ->setMsgUnsuccessClass("alert alert-danger");
            $data['completionPanel'] = $completionPanel;

            $this->language->load(self::BASE_PATH);
            $this->document->setTitle($this->language->get('heading_title'));
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
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['column_right'] = $this->load->controller('common/column_right');
            $data['content_top'] = $this->load->controller('common/content_top');
            $data['content_bottom'] = $this->load->controller('common/content_bottom');
            $data['footer'] = $this->load->controller('common/footer');
            $data['header'] = $this->load->controller('common/header');
            $data['button_continue_link'] = $this->url->link('checkout/success');
            $this->i18n($data, ['button_continue']);
            if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . 'extension/payment/hutkigrosh_checkout_success')) {
                $templateView = $this->config->get('config_template') . 'extension/payment/hutkigrosh_checkout_success';
            } else {
                $templateView = 'extension/payment/hutkigrosh_checkout_success';
            }
            $this->response->setOutput($this->load->view($templateView, $data));
        } catch (Throwable $e) {
            Logger::getLogger("payment")->error("Exception:", $e);
            return $this->failure($e->getMessage());
        }
    }


    public function alfaclick()
    {
        try {
            $controller = new ControllerAlfaclick();
            $controller->process($this->request->post['billid'], $this->request->post['phone']);
        } catch (Throwable $e) {
            Logger::getLogger("alfaclick")->error("Exception: ", $e);
        }
    }

    protected function failure($error)
    {
        $this->session->data['error'] = $error;
        $this->response->redirect($this->url->link('checkout/cart', '', true));
    }

    public function notify()
    {
        try {
            $billId = $this->request->get['purchaseid'];
            $controller = new ControllerNotify();
            $controller->process($billId);
        } catch (Throwable $e) {
            Logger::getLogger("notify")->error("Exception:", $e);
        }
    }

}
