<?php

use PHPUnit\Framework\TestCase;

class ResetDerivationFkSafetyTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;

        $wpdb = new class {
            public $prefix = 'wp_';
            public $last_query = null;

            public function query($q)
            {
                $this->last_query = $q;
                // Simulate successful TRUNCATE returning true-ish
                return true;
            }
        };

        // include the DB service
        require_once __DIR__ . '/../../../includes/services/pay-crypto-me-db-statements-service.php';
    }

    public function test_reset_calls_truncate_on_indexes_table()
    {
        $serviceClass = '\\PayCryptoMe\\WooCommerce\\PayCryptoMeDBStatementsService';
        $svc = new $serviceClass();

        $result = $svc->reset_derivation_indexes();
        $this->assertTrue($result, 'reset_derivation_indexes deve retornar true em caso de sucesso');

        global $wpdb;
        $this->assertNotNull($wpdb->last_query, 'Nenhuma query foi executada no stub $wpdb');
        $this->assertStringContainsString('TRUNCATE TABLE', strtoupper($wpdb->last_query));
        $this->assertStringContainsString('paycrypto_me_bitcoin_derivation_indexes', $wpdb->last_query);
    }
}
