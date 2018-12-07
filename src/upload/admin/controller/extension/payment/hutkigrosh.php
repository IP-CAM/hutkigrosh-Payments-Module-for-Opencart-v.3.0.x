<?php

use esas\hutkigrosh\view\admin\fields\ConfigFieldList;
use esas\hutkigrosh\view\admin\fields\ConfigFieldNumber;
use esas\hutkigrosh\view\admin\ConfigFormOpencart;
use esas\hutkigrosh\view\admin\fields\ListOption;
use esas\hutkigrosh\utils\Logger as HutkigroshLogger;
use esas\hutkigrosh\view\admin\validators\ValidatorInteger;
use Throwable as Th;

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))) . '/system/library/esas/hutkigrosh/init.php');

class ControllerExtensionPaymentHutkiGrosh extends Controller
{
    public function index()
    {
        try {
            $this->load->language('extension/payment/hutkigrosh');
            $this->document->setTitle($this->language->get('heading_title'));
            $configForm = $this->createForm();
            $data['configForm'] = $configForm;// Сохранение или обновление данных
            if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($configForm->validateAll($this->request->post))) {
                $this->load->model('setting/setting');
                $this->model_setting_setting->editSetting('payment_hutkigrosh', $this->request->post);
                $this->session->data['success'] = $this->language->get('text_success');
                $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', 'SSL'));
            }
            // Генерация хлебных крошек
            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
                'separator' => false
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('text_extension'),
                'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
            );

            $data['breadcrumbs'][] = array(
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/hutkigrosh', 'user_token=' . $this->session->data['user_token'], true),
                'separator' => ' :: '
            );

            // Кнопки
            $data['action'] = $this->url->link('extension/payment/hutkigrosh', 'user_token=' . $this->session->data['user_token'], true);
            $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);
            $data['header'] = $this->load->controller('common/header');
            $data['column_left'] = $this->load->controller('common/column_left');
            $data['footer'] = $this->load->controller('common/footer');

            $this->i18n($data, ['heading_title', 'text_status', 'text_enabled', 'text_disabled', 'text_save', 'text_cancel']);
            $this->response->setOutput($this->load->view('extension/payment/hutkigrosh', $data));
        } catch (Th $e) {
            HutkigroshLogger::getLogger("ControllerExtensionPaymentHutkiGrosh")->error("Exception", $e);
        }
    }

    private function i18n(&$data, array $fields)
    {
        foreach ($fields as $field) {
            $data[$field] = $this->language->get($field);
        }
    }

    private function createForm()
    {
        $configForm = new ConfigFormOpencart($this->registry);
        $configForm->addRequired();
        $configForm->addField(new ConfigFieldNumber(
            "payment_hutkigrosh_sort_order",
            $this->language->get('module_sort_order_label'),
            $this->language->get('module_sort_order_description'),
            true,
            new ValidatorInteger(1, 20),
            1,
            20));
        $configForm->addField(new ConfigFieldList(
            "payment_hutkigrosh_status",
            $this->language->get('module_status_label'),
            $this->language->get('module_status_description'),
            true,
            [new ListOption("1", $this->language->get('module_status_enable')), new ListOption("0", $this->language->get('module_status_disable'))]));
        return $configForm;
    }
}
