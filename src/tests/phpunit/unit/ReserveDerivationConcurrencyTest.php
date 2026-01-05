<?php
use PHPUnit\Framework\TestCase;
use PayCryptoMe\WooCommerce\PayCryptoMeDBStatementsService;

// Minimal fallback for WP constants/functions used in code
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../../..');
}
if (!function_exists('__')) {
    function __($text, $domain = null) { return $text; }
}

class FakeWPDBConcurrency
{
    public $prefix = 'wp_';
    public $last_query = '';
    public $inserted_rows = [];
    public $forceLockFail = false;

    // Simulated global lock map per lock name
    private static $locks = [];

    public function prepare($query /*, ...$args */)
    {
        $args = array_slice(func_get_args(), 1);
        if (count($args) === 0) {
            return $query;
        }
        // Very small placeholder replacement for %s/%d used in service
        return vsprintf($query, $args);
    }

    public function get_var($query)
    {
        $this->last_query = $query;

        $ql = strtoupper($query);
        if (strpos($ql, 'GET_LOCK(') !== false) {
            if ($this->forceLockFail) {
                return '0';
            }
            // extract lock name
            preg_match("/GET_LOCK\((?:'|\")?([^,'\"]+)(?:'|\")?,\s*(\d+)\)/i", $query, $m);
            $name = $m[1] ?? 'lock';
            // grant lock if not held
            if (!isset(self::$locks[$name]) || self::$locks[$name] === false) {
                self::$locks[$name] = true;
                return '1';
            }
            return '0';
        }

        if (strpos($ql, 'RELEASE_LOCK(') !== false) {
            preg_match("/RELEASE_LOCK\((?:'|\")?([^,'\"]+)(?:'|\")?\)/i", $query, $m);
            $name = $m[1] ?? 'lock';
            self::$locks[$name] = false;
            return '1';
        }

        if (strpos($ql, 'MAX(DERIVATION_INDEX)') !== false) {
            // parse wallet id from WHERE wallet_xpubkeys_id = %d
            preg_match('/wallet_xpubkeys_id\s*=\s*(\d+)/i', $query, $m);
            $wallet = isset($m[1]) ? (int)$m[1] : 0;
            $max = null;
            foreach ($this->inserted_rows as $r) {
                if ($r['wallet_xpubkeys_id'] === $wallet) {
                    $max = ($max === null) ? $r['derivation_index'] : max($max, $r['derivation_index']);
                }
            }
            return $max;
        }

        return null;
    }

    public function insert($table, $data, $formats = null)
    {
        $this->last_query = 'INSERT INTO ' . $table;
        $this->inserted_rows[] = $data;
        return 1; // success
    }

    public function query($query)
    {
        $this->last_query = $query;
        return true;
    }
}

class ReserveDerivationConcurrencyTest extends TestCase
{
    protected function setUp(): void
    {
        global $wpdb;
        $wpdb = new FakeWPDBConcurrency();
    }

    public function test_sequential_reservations_increment_indexes()
    {
        global $wpdb;
        $svc = new PayCryptoMeDBStatementsService();

        $first = $svc->reserve_derivation_index_for_wallet(1, 1);
        $this->assertSame(0, $first, 'First reserved index should be 0');

        $second = $svc->reserve_derivation_index_for_wallet(1, 1);
        $this->assertSame(1, $second, 'Second reserved index should be 1');

        // verify that two rows were inserted with correct wallet id
        $this->assertCount(2, $wpdb->inserted_rows);
        $this->assertEquals(1, $wpdb->inserted_rows[0]['wallet_xpubkeys_id']);
        $this->assertEquals(0, $wpdb->inserted_rows[0]['derivation_index']);
        $this->assertEquals(1, $wpdb->inserted_rows[1]['derivation_index']);
    }

    public function test_lock_failure_throws_exception()
    {
        global $wpdb;
        $wpdb->forceLockFail = true;

        $this->expectException(\RuntimeException::class);
        $svc = new PayCryptoMeDBStatementsService();
        $svc->reserve_derivation_index_for_wallet(2, 1);
    }
}
