<?php

class ModelExtensionPaymentHutkiGrosh extends Model
{
    public function getMethod($address, $total)
    {
        $this->language->load('extension/payment/hutkigrosh');

        $status = true;

        if ($status) {
            return array(
                'code' => 'hutkigrosh',
                'title' => $this->language->get('text_title'),
                'terms' => '',
                'sort_order' => $this->config->get('hutkigrosh_sort_order')
            );
        } else {
            return array();
        }
    }
}

?>