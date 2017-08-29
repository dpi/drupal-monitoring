<?php

namespace Drupal\monitoring\Tests;

use Drupal\monitoring\Entity\SensorConfig;

/**
 * Tests the updating of the sensor list.
 *
 * @group monitoring
 */
class MonitoringRebuildTest extends MonitoringTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'ultimate_cron');

  /**
   * Tests creating non-addable sensors.
   *
   * @see \Drupal\monitoring\Controller\RebuildSensorList::rebuild()
   */
  public function testRebuildNonAddable() {
    // Create and login user with permission to view monitoring reports.
    $test_user = $this->drupalCreateUser([
      'monitoring reports',
      'administer monitoring',
    ]);
    $this->drupalLogin($test_user);

    // Delete sensors from install and optional directory.
    SensorConfig::load('twig_debug_mode')->delete();
    SensorConfig::load('ultimate_cron_errors')->delete();

    // Rebuild and make sure they are created again.
    $this->drupalGet('/admin/config/system/monitoring/sensors');
    $this->clickLink('Rebuild sensor list');
    $this->assertText('The sensor Ultimate cron errors has been created.');
    $this->assertText('The sensor Twig debug mode has been created.');
    $this->assertNotNull(SensorConfig::load('twig_debug_mode'));
    $this->assertNotNull(SensorConfig::load('ultimate_cron_errors'));
  }

}
