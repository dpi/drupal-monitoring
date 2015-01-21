<?php
/**
 * @file
 * Contains \Drupal\monitoring\Tests\MonitoringPastTest.
 */


namespace Drupal\monitoring\Tests;

/**
 * Tests for the past sensors in monitoring.
 *
 * @group past
 * @requires module past_db
 */
class MonitoringPastTest extends MonitoringUnitTestBase {

  public static $modules = array('past', 'past_db', 'past_db_test');

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Install the past entities and tables.
    $this->installEntitySchema('past_event');
    $this->installSchema('past_db', array('past_event_argument', 'past_event_data'));

    monitoring_modules_installed(array('past_db'));
  }

  /**
   * Tests the sensors that monitors past events.
   */
  public function testPastSensors() {

    // Creates dummy events for testing.
    $this->createEvents();

    // Run each sensor and test output.
    $result = $this->runSensor('past_db_critical');
    $this->assertEqual($result->getMessage(), '2 events in 1 day');

    $result = $this->runSensor('past_db_debug');
    $this->assertEqual($result->getMessage(), '3 events in 1 day');

    $result = $this->runSensor('past_db_emergency');
    $this->assertEqual($result->getMessage(), '2 events in 1 day');

    $result = $this->runSensor('past_db_error');
    $this->assertEqual($result->getMessage(), '3 events in 1 day');

    $result = $this->runSensor('past_db_info');
    $this->assertEqual($result->getMessage(), '3 events in 1 day');

    $result = $this->runSensor('past_db_notice');
    $this->assertEqual($result->getMessage(), '3 events in 1 day');

    $result = $this->runSensor('past_db_warning');
    $this->assertEqual($result->getMessage(), '3 events in 1 day');
  }

  /**
   * Creates some sample events.
   */
  protected function createEvents($count = 20) {
    // Set some for log creation.
    $machine_name = 'machine name';
    $severities = past_event_severities();
    $severities_codes = array_keys($severities);
    $severities_count = count($severities);
    $event_desc = 'message #';

    // Prepare some logs.
    for ($i = 0; $i <= $count; $i++) {
      $event = past_event_create('past_db', $machine_name, $event_desc . ($i + 1));
      $event->setReferer('http://example.com/test-referer');
      $event->setLocation('http://example.com/this-url-gets-heavy-long/testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttest-testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttest-testtesttesttesttesttesttesttesttesttesttesttesttesttesttesttest-testtesttesttesttesttesttest/seeme.htm');
      $event->addArgument('arg1', 'First Argument');
      $event->addArgument('arg2', new \stdClass());
      $event->addArgument('arg3', FALSE);
      $event->setSeverity($severities_codes[$i % $severities_count]);
      $event->save();
    }
  }

}
