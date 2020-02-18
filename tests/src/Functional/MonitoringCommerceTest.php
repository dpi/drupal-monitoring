<?php

namespace Drupal\Tests\monitoring\Functional;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\monitoring\Entity\SensorConfig;

/**
 * Tests the commerce turnover sensor.
 *
 * @group monitoring
 */
class MonitoringCommerceTest extends MonitoringTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = ['commerce', 'commerce_order', 'node'];

  /**
   * The account for testing.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->account = $this->drupalCreateUser([
      'administer commerce_order',
      'administer commerce_currency',
      'administer commerce_store',
      'administer commerce_order_type',
      'administer monitoring',
      'monitoring reports',
    ]);
    $this->drupalLogin($this->account);

    // Enable currencies.
    $edit = [
      'currency_codes[]' => ['CHF', 'EUR'],
    ];
    $this->drupalPostForm('admin/commerce/config/currencies/add', $edit, t('Add'));

    // Create a default store.
    $edit = [
      'name[0][value]' => 'Default',
      'mail[0][value]' => 'test@example.com',
      'address[0][address][country_code]' => 'CH',
      'address[0][address][address_line1]' => 'Demo street',
      'address[0][address][locality]' => 'Demo city',
      'address[0][address][postal_code]' => '1234',
      'default_currency' => 'CHF',
    ];
    $this->drupalPostForm('store/add/online', $edit, t('Save'));

    // Create an order item type
    $edit = [
      'id' => 'test',
      'label' => 'Test',
      'orderType' => 'default'
    ];
    $this->drupalPostForm('admin/commerce/config/order-item-types/add', $edit, t('Save'));
  }

  /**
   * Tests the commerce turnover sensor.
   */
  public function testTurnoverSensor() {
    // Create commerce turnover sensor.
    $sensor = SensorConfig::create([
      'id' => 'commerce_total_turnover',
      'label' => 'Total turnover',
      'plugin_id' => 'commerce_turnover',
      'status' => 1,
      'settings' => [
        'entity_type' => 'commerce_order',
        'commerce_order_paid_states' => [],
        'commerce_order_currency' => 'CHF',
        'time_interval_field' => 'created',
        'time_interval_value' => 86400,
      ],
    ]);
    $sensor->save();
    // Assert there is no value if there are no orders.
    $result = $this->runSensor('commerce_total_turnover');
    $this->assertEqual($result->getMessage(), 'No value');

    // Create some orders with different states and currencies.
    $this->createEmptyOrderWithPrice('canceled');
    $this->createEmptyOrderWithPrice('draft');
    $this->createEmptyOrderWithPrice('draft', 200, 'CHF');
    $this->createEmptyOrderWithPrice('draft', 1000, 'EUR');
    $this->createEmptyOrderWithPrice('completed', 1500, 'CHF');

    $result = $this->runSensor('commerce_total_turnover');
    $this->assertEqual($result->getMessage(), 'Value CHF 1’900.00 in 1 day');

    // Now only consider completed orders.
    $edit = [
      'settings[commerce_order_paid_states][completed]' => TRUE,
    ];
    $this->drupalPostForm('admin/config/system/monitoring/sensors/commerce_total_turnover', $edit, t('Save'));

    $sensor = SensorConfig::load('commerce_total_turnover');
    $paid_states = $sensor->getSetting('commerce_order_paid_states');
    $this->assertNotContains('draft', $paid_states);
    $this->assertContains('completed', $paid_states);

    $result = $this->runSensor('commerce_total_turnover');
    $this->assertEqual($result->getMessage(), 'Value CHF 1’500.00 in 1 day');

    // Change currency.
    $this->createEmptyOrderWithPrice('completed', 250, 'EUR');
    $edit = [
      'settings[commerce_order_currency]' => 'EUR',
    ];
    $this->drupalPostForm('admin/config/system/monitoring/sensors/commerce_total_turnover', $edit, t('Save'));

    $result = $this->runSensor('commerce_total_turnover');
    $this->assertEqual($result->getMessage(), 'Value € 250.00 in 1 day');
    $this->drupalLogout();
    $result = $this->runSensor('commerce_total_turnover');
    $this->assertEqual($result->getMessage(), 'Value € 250.00 in 1 day');
  }

  /**
   * Create an order for testing purposes.
   *
   * @param string $state
   *   State in which the order should be created.
   * @param mixed $amount
   *   The total price of the order.
   * @param string $currency
   *   Currency of the total price.
   */
  protected function createEmptyOrderWithPrice($state, $amount = 100, $currency = 'CHF') {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price((string) $amount, $currency),
      'overridden_unit_price' => TRUE,
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'state' => $state,
      'store_id' => \Drupal::service('commerce_store.current_store')->getStore()->id(),
      'order_items' => [$order_item],
    ]);
    $order->save();
  }

}
