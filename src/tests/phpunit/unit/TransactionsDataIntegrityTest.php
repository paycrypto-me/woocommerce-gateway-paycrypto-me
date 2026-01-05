<?php

use PHPUnit\Framework\TestCase;

class TransactionsDataIntegrityTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;

        $wpdb = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;
            public $rows = [];
            public $last_query = null;

            public function prepare($query)
            {
                $args = array_slice(func_get_args(), 1);
                foreach ($args as $arg) {
                    if (is_int($arg)) {
                        $query = preg_replace('/%d/', (int)$arg, $query, 1);
                    } else {
                        $query = preg_replace('/%s/', $arg, $query, 1);
                    }
                }
                return $query;
            }

            public function get_row($sql, $output = null)
            {
                $this->last_query = $sql;
                // detect lookup by order_id
                if (stripos($sql, 'FROM ' . $this->prefix . 'paycrypto_me_bitcoin_transactions_data') !== false) {
                    if (preg_match('/order_id\s*=\s*(\d+)/', $sql, $m)) {
                        $order_id = (int)$m[1];
                        foreach ($this->rows as $r) {
                            if ((int)$r['order_id'] === $order_id) {
                                return $r;
                            }
                        }
                    }
                }

                return null;
            }

            public function insert($table, $data, $formats)
            {
                $this->insert_id++;
                $row = $data;
                $row['id'] = $this->insert_id;
                $this->rows[] = $row;
                return 1;
            }

            public function query($q)
            {
                $this->last_query = $q;
                return true;
            }
        };

        require_once __DIR__ . '/../../../includes/services/pay-crypto-me-db-statements-service.php';
    }

    public function test_insert_address_and_prevent_duplicates()
    {
        $svcClass = '\\PayCryptoMe\\WooCommerce\\PayCryptoMeDBStatementsService';
        $svc = new $svcClass();

        $orderId = 4242;
        $derivationIndex = 0;
        $address = 'btc:1ExampleAddress';
        $walletId = 1;

        $this->assertFalse($svc->exists_for_order($orderId));

        $ok = $svc->insert_address($orderId, $derivationIndex, $address, $walletId);
        $this->assertTrue($ok, 'insert_address should succeed when order not present');

        $this->assertTrue($svc->exists_for_order($orderId));

        // Second insert for same order must be rejected
        $ok2 = $svc->insert_address($orderId, $derivationIndex + 1, 'btc:1Other', $walletId);
        $this->assertFalse($ok2, 'insert_address must return false for duplicate order');
    }
}
