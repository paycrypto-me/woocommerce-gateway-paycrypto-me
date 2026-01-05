<?php
use PHPUnit\Framework\TestCase;
use PayCryptoMe\WooCommerce\PayCryptoMeDBStatementsService;

// Minimal i18n fallback used in some code paths
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}

class FakeWPDB
{
    public $insert_id = 0;
    public $prefix = 'wp_';
    public $last_query = '';

    public function prepare($query /*, ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        if (count($args) === 0) {
            return $query;
        }
        // Use vsprintf to expand %s/%d placeholders for test purposes
        return vsprintf($query, $args);
    }

    public function get_row($query, $output = ARRAY_A)
    {
        $this->last_query = $query;

        // Emulate lookup by xpub/network
        if (stripos($query, 'FROM wp_paycrypto_me_bitcoin_wallet_xpubkeys') !== false) {
            return ['id' => 321];
        }

        // No matching row for other queries (e.g. transactions)
        return null;
    }

    public function get_var($query)
    {
        $this->last_query = $query;

        if (stripos($query, 'GET_LOCK') !== false) {
            return '1';
        }

        if (stripos($query, 'MAX(derivation_index)') !== false) {
            return null; // simulate empty set on first reservation
        }

        if (stripos($query, 'RELEASE_LOCK') !== false) {
            return '1';
        }

        return null;
    }

    public function insert($table, $data, $formats = null)
    {
        $this->last_query = 'INSERT INTO ' . $table;
        // return 1 on success
        return 1;
    }

    public function query($query)
    {
        $this->last_query = $query;
        return true;
    }
}

class PayCryptoMeDBStatementsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new FakeWPDB();
    }

    public function test_insert_wallet_xpubkey_returns_insert_id()
    {
        global $wpdb;
        $wpdb->insert_id = 123;

        $svc = new PayCryptoMeDBStatementsService();
        $id = $svc->insert_wallet_xpubkey('xpub_test', 'mainnet');

        $this->assertIsInt($id);
        $this->assertEquals(123, $id);
    }

    public function test_get_wallet_xpubkey_id_returns_id()
    {
        $svc = new PayCryptoMeDBStatementsService();
        $id = $svc->get_wallet_xpubkey_id('xpub_test', 'mainnet');

        $this->assertIsInt($id);
        $this->assertEquals(321, $id);
    }

    public function test_reserve_derivation_index_for_wallet_returns_zero_and_inserts()
    {
        $svc = new PayCryptoMeDBStatementsService();

        $next = $svc->reserve_derivation_index_for_wallet(1, 1);

        $this->assertSame(0, $next, 'First reserved derivation index should be 0');
    }

    public function test_insert_address_returns_true_when_order_missing()
    {
        $svc = new PayCryptoMeDBStatementsService();

        // choose an order id that our FakeWPDB does not return
        $result = $svc->insert_address(1000, 0, 'tb1address', 1);

        $this->assertTrue($result);
    }

    public function test_reset_derivation_indexes_truncates()
    {
        $svc = new PayCryptoMeDBStatementsService();
        $res = $svc->reset_derivation_indexes();
        $this->assertTrue($res);
    }
}
