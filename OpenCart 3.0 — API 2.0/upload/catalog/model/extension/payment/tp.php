<?php

/**
 * Class ModelExtensionPaymentTp
 */
class ModelExtensionPaymentTp extends Model
{
    /**
     * @var string
     */
    private $transactions_table_name;

    /**
     * @var string
     */
    private $globalLabel = "TRANZZO";

    /**
     * ModelExtensionPaymentTp constructor.
     * @param $registry
     */
    public function __construct($registry) {
        parent::__construct($registry);
        $this->transactions_table_name = DB_PREFIX . 'tp_gateway_transactions';
    }

    /**
     * @param $address
     * @param int $total
     * @return array|bool
     */
    public function getMethod($address, $total = 0)
    {

        if (!in_array($this->session->data['currency'], array('USD', 'EUR', 'UAH', 'RUB'))) {
            return false;
        }

        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('tp_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");

        if ($this->config->get('tp_total') > 0 && $this->config->get('tp_total') > $total) {
            $status = false;
        } elseif (!$this->config->get('tp_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $method_data = array();

        if ($status) {
            $this->load->language('extension/payment/tp');
            $method_data = array(
                'code' => 'tp',
                'title' => sprintf($this->language->get('text_title'),$this->globalLabel),
                'terms' => '',
                'sort_order' => $this->config->get('tp_sort_order')
            );
        }

        return $method_data;
    }

    /**
     * @param $order_id
     * @param $data
     */
    public function addPaymentData($order_id, $data)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "order SET tracking = '" . $this->db->escape($data['order_id']) . "', tp_payment = '" . $this->db->escape(json_encode($data)) . "' WHERE order_id = '" . (int)$order_id . "'");
    }

    /**
     * @param $tranzo_id
     * @return mixed
     */
    public function getOrderId($tranzo_id)
    {
        $sql = "SELECT order_id FROM " . DB_PREFIX . "order WHERE tracking = '" . (int)$tranzo_id . "'";
        $query = $this->db->query($sql);
        return $query;
    }

    /**
     * @param $id
     * @param $lang
     * @return mixed
     */
    public function getStatusName($id, $lang)
    {
        $sql = "SELECT name FROM " . DB_PREFIX . "order_status WHERE order_status_id = '" . (int)$id . "' AND language_id = '" . (int)$lang . "'";
        $query = $this->db->query($sql);
        return $query;
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public function getPaymentData($order_id)
    {
        $sql = "SELECT tp_payment FROM " . DB_PREFIX . "order WHERE order_id = '" . (int)$order_id . "'";
        $query = $this->db->query($sql);

        $this->load->library('ServiceApi');
        $this->ServiceApi->writeLog(array('getPaymentData $sql', $sql));
        $this->ServiceApi->writeLog(array('getPaymentData', $query));

        return $query->row['tp_payment'];
    }

    /**
     * @param $type
     * @param $amount
     * @param $order_id
     * @return mixed
     */
    public function createTransaction($type, $amount, $order_id) {
        $this->db->query("INSERT INTO " . $this->transactions_table_name . " SET type = '" . $this->db->escape($type) . "', amount = '" . (float)$amount . "', order_id = '" . (int)$order_id . "'");

        return $this->db->getLastId();
    }

    /**
     * @param $id
     * @param $type
     * @param $amount
     * @param $order_id
     */
    public function updateTransaction($id, $type, $amount, $order_id) {
        $this->db->query("UPDATE " . $this->transactions_table_name . " SET type = '" . $this->db->escape($type) . "', amount = '" . (float)$amount . "', order_id = '" . (int)$order_id . "' WHERE id = '" . (int)$id . "'");
    }

    /**
     * @param $id
     */
    public function deleteTransaction($id) {
        $this->db->query("DELETE FROM " . $this->transactions_table_name . " WHERE id = '" . (int)$id . "'");
    }

    /**
     * @param $id
     * @return mixed
     */
    public function getTransaction($id) {
        $query = $this->db->query("SELECT * FROM " . $this->transactions_table_name . " WHERE id = '" . (int)$id . "'");

        return $query->row;
    }

    /**
     * @param $order_id
     * @return mixed
     */
    public function searchTransactionsByOrderId($order_id) {
        $query = $this->db->query("SELECT * FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' ORDER BY date DESC");

        return $query->rows;
    }

    /**
     * @param $order_id
     * @return float
     */
    public function getTotalCapturedAmount($order_id) {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' AND type = 'capture'");

        return (float)$query->row['total'];
    }

    /**
     * @param $order_id
     * @return float
     */
    public function getTotalRefundedAmount($order_id) {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' AND type = 'refund'");

        return (float)$query->row['total'];
    }

    /**
     * @param $order_id
     * @return float
     */
    public function getTotalVoidedAmount($order_id) {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' AND type = 'void'");

        return (float)$query->row['total'];
    }

    /**
     * @param $order_id
     * @return float
     */
    public function getTotalPurchasedAmount($order_id) {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' AND type = 'purchase'");

        return (float)$query->row['total'];
    }

    /**
     * @param $order_id
     * @return float
     */
    public function getTotalHoldAmount($order_id) {
        $query = $this->db->query("SELECT SUM(amount) AS total FROM " . $this->transactions_table_name . " WHERE order_id = '" . (int)$order_id . "' AND type = 'auth'");

        return (float)$query->row['total'];
    }
}
