<?php

class ControllerExtensionPaymentHutkiGrosh extends Controller
{
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
    const HUTKIGROSH_SMS_NOTIFICATION = 'payment_hutkigrosh_sms_notification';
    const HUTKIGROSH_EMAIL_NOTIFICATION = 'payment_hutkigrosh_email_notification';


    private $errors = array();

    public function index()
    {
        $this->load->language('extension/payment/hutkigrosh');

        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        // Сохранение или обновление данных
        if (($this->request->server['REQUEST_METHOD'] == 'POST') && ($this->validate())) {
            $this->model_setting_setting->editSetting('payment_hutkigrosh', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', 'SSL'));
        }


        // Установка языковых констант
        //TODO переделать на цикл
        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_payment'] = $this->language->get('text_payment');
        $data['text_success'] = $this->language->get('text_success');

        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_STOREID, true);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_STORE_NAME, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_LOGIN, true);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_PASSWORD, true);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_ERIP_TREE_PATH, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_ORDER_STATUS_PENDING, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_ORDER_STATUS_PAYED, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_ORDER_STATUS_ERROR, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_MODULE_SORT_ORDER, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_SANDBOX, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_SMS_NOTIFICATION, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_EMAIL_NOTIFICATION, false);
        $data['configFields'][] = $this->createConfigurationField(self::HUTKIGROSH_MODULE_STATUS, false);


        $data['text_status'] = $this->language->get('text_status');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['button_save'] = $this->language->get('text_save');
        $data['button_cancel'] = $this->language->get('text_cancel');


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


        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Кнопки
        $data['action'] = $this->url->link('extension/payment/hutkigrosh', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        // Рендеринг шаблона
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $data['errors'] = $this->errors;
        $this->response->setOutput($this->load->view('extension/payment/hutkigrosh', $data));
    }

    protected function createConfigurationField($fieldName, $required)
    {
        $configurationField = new ConfigurationField();
        $configurationField->key = $fieldName;
        $configurationField->label = $this->language->get($fieldName . "_label");
        $configurationField->description = $this->language->get($fieldName . "_description");
        $configurationField->required = $required;
        if (isset($this->request->post[$fieldName])) {
            $configurationField->value = $this->request->post[$fieldName];
        } else {
            $configurationField->value = $this->config->get($fieldName);
        }
        return $configurationField;
    }

    protected function parseParam($paramName, $configuration)
    {
        if (isset($this->request->post[$paramName])) {
            $configuration[$paramName]->value = $this->request->post[$paramName];
        } else {
            $configuration[$paramName]->value = $this->config->get($paramName);
        }
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'extension/payment/hutkigrosh')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        //TODO проверка на основе $required
        $this->requiredParam(self::HUTKIGROSH_STOREID);
        $this->requiredParam(self::HUTKIGROSH_LOGIN);
        $this->requiredParam(self::HUTKIGROSH_PASSWORD);

        return !$this->errors;
    }

    protected function requiredParam($paramName)
    {
        if (!$this->request->post[$paramName]) {
            $this->errors[$paramName] = sprintf($this->language->get('text_config_field_required'), $this->language->get($paramName . "_label"));
        }
    }
}

class ConfigurationField
{
    public $key;
    public $value;
    public $description;
    public $label;
    public $required;
    public $type;
    public $data;
}