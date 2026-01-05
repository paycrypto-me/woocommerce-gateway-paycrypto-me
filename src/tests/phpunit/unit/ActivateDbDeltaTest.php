<?php

use PHPUnit\Framework\TestCase;

/**
 * Verifica que a rotina de ativação chama dbDelta com SQL contendo as tabelas esperadas.
 * Este teste finge o $wpdb e implementa uma função dbDelta que captura o SQL.
 */
class ActivateDbDeltaTest extends TestCase
{
    protected function setUp(): void
    {
        // Criar um $wpdb falso mínimo
        global $wpdb;

        $wpdb = new class {
            public $prefix = 'wp_';
            public $charset = 'utf8mb4';
            public $collate = 'utf8mb4_unicode_ci';

            public function get_charset_collate()
            {
                return "DEFAULT CHARACTER SET {$this->charset} COLLATE {$this->collate}";
            }
        };

        // Garantir ABSPATH para includes do WP
        if (!defined('ABSPATH')) {
            define('ABSPATH', '/var/www/html/');
        }

        // stub para get_charset_collate se usado
        if (!function_exists('get_charset_collate')) {
            function get_charset_collate()
            {
                global $wpdb;
                return "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
            }
        }

        // Captura queries passadas para dbDelta
        if (!isset($GLOBALS['__dbdelta_captured'])) {
            $GLOBALS['__dbdelta_captured'] = [];
        }

        // Define dbDelta se não existir
        if (!function_exists('dbDelta')) {
            function dbDelta($queries)
            {
                if (is_string($queries)) {
                    $q = $queries;
                } elseif (is_array($queries)) {
                    $q = implode("\n", $queries);
                } else {
                    $q = '';
                }
                $GLOBALS['__dbdelta_captured'][] = $q;
                return true;
            }
        }

        // Garantir que o arquivo upgrade.php exista no caminho resolvido por ABSPATH
        $upgrade_path = ABSPATH . 'wp-admin/includes/upgrade.php';
        if (!file_exists($upgrade_path)) {
            $upgrade_dir = dirname($upgrade_path);
            if (!is_dir($upgrade_dir)) {
                @mkdir($upgrade_dir, 0777, true);
            }
            // cria um stub mínimo que define dbDelta quando incluído
                $stub = "<?php\nif (!function_exists('dbDelta')) { function dbDelta(\$queries) { global \$__dbdelta_captured; if (is_string(\$queries)) { \$q=\$queries; } elseif (is_array(\$queries)) { \$q=implode(\"\\n\", \$queries); } else { \$q=''; } \$GLOBALS['__dbdelta_captured'][] = \$q; return true; } }\n";
            @file_put_contents($upgrade_path, $stub);
        }

        // load the activation class file
        $activate_path = __DIR__ . '/../../../includes/services/class-paycrypto-me-bitcoin-gateway-activate.php';
        if (file_exists($activate_path)) {
            require_once $activate_path;
        }
    }

    public function test_activate_creates_expected_tables()
    {
        // Garantir ambiente limpo
        $GLOBALS['__dbdelta_captured'] = [];

        // Chamar o método de ativação na classe namespaced
        $fqcn = '\\PayCryptoMe\\WooCommerce\\PayCryptoMeBitcoinGatewayActivate';
        if (class_exists($fqcn)) {
            // método estático
            $fqcn::activate();
        } else {
            $this->fail('Classe ' . $fqcn . ' não encontrada');
        }

        $this->assertNotEmpty($GLOBALS['__dbdelta_captured'], 'Nenhum SQL foi passado para dbDelta');

        $all_sql = implode("\n", $GLOBALS['__dbdelta_captured']);

        // Verificações essenciais nas CREATE TABLEs
        $this->assertStringContainsString('CREATE TABLE', $all_sql);

        // wallet_xpubkeys table
        $this->assertStringContainsString('paycrypto_me_bitcoin_wallet_xpubkeys', $all_sql, 'Tabela wallet_xpubkeys ausente');
        $this->assertStringContainsString('AUTO_INCREMENT', $all_sql, 'AUTO_INCREMENT esperado para id na criação das tabelas');
        $this->assertStringContainsString('xpub', $all_sql, 'Coluna xpub não encontrada na definição');

        // derivation_indexes table
        $this->assertStringContainsString('paycrypto_me_bitcoin_derivation_indexes', $all_sql, 'Tabela derivation_indexes ausente');
        $this->assertStringContainsString('PRIMARY KEY', $all_sql, 'PRIMARY KEY esperado na tabela derivation_indexes');
        $this->assertStringContainsString('derivation_index', $all_sql, 'Coluna derivation_index não encontrada');
        $this->assertStringContainsString('wallet_xpubkeys_id', $all_sql, 'Coluna wallet_xpubkeys_id não encontrada');

        // transactions table
        $this->assertStringContainsString('paycrypto_me_bitcoin_transactions_data', $all_sql, 'Tabela transactions_data ausente');
        $this->assertStringContainsString('order_id', $all_sql, 'Coluna order_id não encontrada');
        $this->assertStringContainsString('payment_address', $all_sql, 'Coluna payment_address não encontrada');

        // Verifica existência de FK (se presente)
        $this->assertTrue(
            (strpos($all_sql, 'FOREIGN KEY') !== false) || (strpos($all_sql, 'REFERENCES') !== false),
            'Esperado FOREIGN KEY ou REFERENCES em alguma definição de tabela'
        );
    }
}
