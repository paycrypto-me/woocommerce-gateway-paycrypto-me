<?php

use PHPUnit\Framework\TestCase;

class WalletXpubPersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;

        $wpdb = new class {
            public $prefix = 'wp_';
            public $insert_id = 0;

            public function prepare($query)
            {
                // return the raw query for our stubs
                return $query;
            }

            public function insert($table, $data, $formats)
            {
                // simulate successful insert and set insert_id
                $this->insert_id = 999;
                return 1;
            }

            public function get_row($sql, $output = null)
            {
                if (stripos($sql, 'SELECT id FROM') !== false) {
                    if ($this->insert_id === 0) {
                        return null;
                    }
                    return ['id' => $this->insert_id];
                }

                return null;
            }
        };

        // include the DB service
        require_once __DIR__ . '/../../../includes/services/pay-crypto-me-db-statements-service.php';
    }

    public function test_insert_and_get_wallet_xpubkey_id()
    {
        $svcClass = '\\PayCryptoMe\\WooCommerce\\PayCryptoMeDBStatementsService';
        $svc = new $svcClass();

        // initially not present
        $id = $svc->get_wallet_xpubkey_id('xpub_test', 'btc');
        $this->assertNull($id, 'Esperado null quando não existe wallet_xpubkey');

        // insert
        $newId = $svc->insert_wallet_xpubkey('xpub_test', 'btc');
        $this->assertIsInt($newId, 'insert_wallet_xpubkey deve retornar insert_id inteiro');
        $this->assertGreaterThan(0, $newId);

        // now should be found
        $found = $svc->get_wallet_xpubkey_id('xpub_test', 'btc');
        $this->assertSame($newId, $found);
    }
}
