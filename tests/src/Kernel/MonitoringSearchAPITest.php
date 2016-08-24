<?php
/**
 * @file
 * Contains \Drupal\Tests\monitoring\Kernel\MonitoringSearchAPITest.
 */

namespace Drupal\Tests\monitoring\Kernel;

use Drupal\entity_test\Entity\EntityTestMulRevChanged;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\search_api\Entity\Index;

/**
 * Tests for search API sensor.
 *
 * @group monitoring
 * @dependencies search_api
 */
class MonitoringSearchAPITest extends MonitoringUnitTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array(
    'field',
    'search_api',
    'search_api_db',
    'search_api_test_db',
    'node',
    'entity_test',
    'text',
    'taxonomy',
  );

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
    // Install required database tables for each module.
    $this->installSchema('search_api', ['search_api_item']);
    $this->installEntitySchema('search_api_task');
    $this->installSchema('system', ['router', 'queue', 'key_value_expire']);
    $this->installSchema('user', ['users_data']);

    // Install the schema for entity entity_test_mulrev_changed.
    $this->installEntitySchema('entity_test_mulrev_changed');

    // Set up the required bundles.
    $this->createEntityTestBundles();
    // Install the test search API index and server used by the test.
    $this->installConfig(['search_api', 'search_api_test_db']);

    \Drupal::service('search_api.index_task_manager')
      ->addItemsAll(Index::load('database_search_index'));
  }

  /**
   * Tests individual sensors.
   */
  public function testSensors() {

    // Create content first to avoid a Division by zero error.
    // Two new articles, none indexed.
    $entity = EntityTestMulRevChanged::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTestMulRevChanged::create(array('type' => 'article'));
    $entity->save();

    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 2);

    // Update the index to test sensor result.
    $index = Index::load('database_search_index');
    $index->indexItems();

    $entity = EntityTestMulRevChanged::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTestMulRevChanged::create(array('type' => 'article'));
    $entity->save();
    $entity = EntityTestMulRevChanged::create(array('type' => 'article'));
    $entity->save();

    // New articles are not yet indexed.
    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 3);

    $index = Index::load('database_search_index');
    $index->indexItems();

    // Everything should be indexed.
    $result = $this->runSensor('search_api_database_search_index');
    $this->assertEqual($result->getValue(), 0);

    // Verify that hooks do not break when sensors unexpectedly do exist or
    // don't exist.
    $sensor = SensorConfig::create(array(
      'id' => 'search_api_existing',
      'label' => 'Existing sensor',
      'plugin_id' => 'search_api_unindexed',
      'settings' => array(
        'index_id' => 'existing',
      ),
    ));
    $sensor->save();

    $index_existing = Index::create([
      'id' => 'existing',
      'status' => FALSE,
      'name' => 'Existing',
      'tracker' => 'default',
    ]);
    $index_existing->save();

    // Manually delete the sensor and then the index.
    $sensor->delete();
    $index_existing->delete();
  }

  /**
   * Sets up the necessary bundles on the test entity type.
   */
  protected function createEntityTestBundles() {
    entity_test_create_bundle('item', NULL, 'entity_test_mulrev_changed');
    entity_test_create_bundle('article', NULL, 'entity_test_mulrev_changed');
  }

}
