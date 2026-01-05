<?php
use PHPUnit\Framework\TestCase;

// Minimal WP helper fallbacks for AJAX tests using test-controlled globals
if (!function_exists('current_user_can')) {
    function current_user_can($cap) {
        global $TEST_CURRENT_USER_CAN;
        return isset($TEST_CURRENT_USER_CAN) ? (bool) $TEST_CURRENT_USER_CAN : true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($a, $b) {
        global $TEST_CHECK_AJAX_REFERER;
        return isset($TEST_CHECK_AJAX_REFERER) ? (bool) $TEST_CHECK_AJAX_REFERER : true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) { throw new \Exception('WP_JSON_SUCCESS:' . json_encode($data)); }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) { throw new \Exception('WP_JSON_ERROR:' . json_encode($data)); }
}

class WCGatewayAjaxTest extends TestCase
{
    private function setPrivateProperty(object $obj, string $name, $value): void
    {
        $rc = new \ReflectionObject($obj);
        while (!$rc->hasProperty($name) && $rc->getParentClass()) {
            $rc = $rc->getParentClass();
        }
        $prop = $rc->getProperty($name);
        $prop->setAccessible(true);
        $prop->setValue($obj, $value);
    }

    public function test_ajax_reset_derivation_index_permission_denied()
    {
        // ensure current_user_can returns false for this test
        global $TEST_CURRENT_USER_CAN, $TEST_CHECK_AJAX_REFERER;
        $TEST_CURRENT_USER_CAN = false;
        $TEST_CHECK_AJAX_REFERER = true;

        $gateway = $this->getMockBuilder(\PayCryptoMe\WooCommerce\WC_Gateway_PayCryptoMe::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WP_JSON_ERROR');

        // Call the ajax handler
        $m = new \ReflectionMethod(\PayCryptoMe\WooCommerce\WC_Gateway_PayCryptoMe::class, 'ajax_reset_derivation_index');
        $m->setAccessible(true);
        $m->invoke($gateway);
    }

    public function test_ajax_reset_derivation_index_success_calls_reset()
    {
        // ensure helpers behave normally
        global $TEST_CURRENT_USER_CAN, $TEST_CHECK_AJAX_REFERER;
        $TEST_CURRENT_USER_CAN = true;
        $TEST_CHECK_AJAX_REFERER = true;

        $gateway = $this->getMockBuilder(\PayCryptoMe\WooCommerce\WC_Gateway_PayCryptoMe::class)
            ->disableOriginalConstructor()
            ->getMock();

        // create fake db service
        $db = $this->getMockBuilder(\PayCryptoMe\WooCommerce\PayCryptoMeDBStatementsService::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['reset_derivation_indexes'])
            ->getMock();

        $db->expects($this->once())->method('reset_derivation_indexes')->willReturn(true);

        // inject fake db into gateway instance
        $this->setPrivateProperty($gateway, 'db_statements_service', $db);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('WP_JSON_SUCCESS');

        $m = new \ReflectionMethod(\PayCryptoMe\WooCommerce\WC_Gateway_PayCryptoMe::class, 'ajax_reset_derivation_index');
        $m->setAccessible(true);
        $m->invoke($gateway);
    }
}
