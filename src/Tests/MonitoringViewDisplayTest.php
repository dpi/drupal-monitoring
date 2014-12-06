<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringViewDisplayTest.
 */

namespace Drupal\monitoring\Tests;

/**
 * Tests the view display sensor.
 */
class MonitoringViewDisplayTest extends MonitoringTestBase {

  public static $modules = array('views');

  public static function getInfo() {
    return array(
      'name' => 'Monitoring View Display',
      'description' => 'Monitoring view display test.',
      'group' => 'Monitoring',
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function testViewDisplaySensor() {
    $account = $this->drupalCreateUser(array('administer monitoring', 'monitoring reports'));
    $this->drupalLogin($account);

    // Add sensor type views display aggregator
    $this->drupalGet('admin/config/system/monitoring/sensors/add');
    $this->drupalPostForm(NULL, array(
      'label' => 'All users',
      'description' => 'Count all users through the users view.',
      'id' => 'view_user_count',
      'value_label' => 'Users',
      'value_type' => 'number',
      'caching_time' => 0,
      'plugin_id' => 'view_display_aggregator',
    ), t('Select sensor'));
    // Select view
    $this->assertText('Sensor plugin settings');
    $this->drupalPostForm(NULL, array(
      'settings[view]' => 'user_admin_people',
    ), t('Select view'));
    $this->drupalPostForm(NULL, array(
      'settings[display]' => 'default',
    ), t('Save'));
    $this->assertText('Sensor settings saved.');

    // Call sensor and verify status and message.
    $result = $this->runSensor('view_user_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '2 users');

    // Create an additional user.
    $this->drupalCreateUser();

    // Call sensor and verify status and message.
    $result = $this->runSensor('view_user_count');
    $this->assertTrue($result->isOk());
    $this->assertEqual($result->getMessage(), '3 users');

  }

}
