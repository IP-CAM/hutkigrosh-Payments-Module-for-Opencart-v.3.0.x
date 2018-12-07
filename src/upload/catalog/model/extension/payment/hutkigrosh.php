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

    public function saveBillId($orderId, $billId) {

        $sql = 'UPDATE
                        '.DB_PREFIX.'order     
                    SET
                   	    payment_custom_field = "'. $billId . '"
                    WHERE
                        order_id = \''.(int)$orderId.'\'';
        $this->db->query($sql);
    }
}

?>