<?php

namespace Drupal\Tests\monitoring_multigraph\Unit\Entity;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring_multigraph\Entity\Multigraph;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @coversDefaultClass \Drupal\monitoring_multigraph\Entity\Multigraph
 *
 * @group monitoring
 */
class MultigraphUnitTest extends UnitTestCase {

  /**
   * A mock entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::calculateDependencies
   */
  public function testCalculateDependencies() {
    // Mock a couple of sensors.
    $sensor1_id = $this->getRandomGenerator()->word(16);
    $sensor1 = $this->getMockSensor($sensor1_id);

    $sensor2_id = $this->getRandomGenerator()->word(16);
    $sensor2 = $this->getMockSensor($sensor2_id);

    // Create a Multigraph containing the sensors.
    $multigraph = new Multigraph(array(
      'sensors' => array(
        $sensor1_id => array('weight' => 0, 'label' => ''),
        $sensor2_id => array('weight' => 1, 'label' => ''),
      ),
    ), 'monitoring_multigraph');

    // Mock whatever is used in calculateDependencies().
    $sensor_storage = $this->createMock(ConfigEntityStorageInterface::class);
    $sensor_storage->expects($this->any())
      ->method('load')
      ->willReturnMap(array(
        array($sensor1_id, $sensor1),
        array($sensor2_id, $sensor2),
      ));

    $this->entityTypeManager->expects($this->any())
      ->method('getStorage')
      ->with('monitoring_sensor_config')
      ->willReturn($sensor_storage);

    // Assert dependencies are calculated correctly for the Multigraph.
    $dependencies = $multigraph->calculateDependencies();
    $this->assertEquals(array('entity' => array("sensor.$sensor1_id", "sensor.$sensor2_id")), $dependencies);
  }

  /**
   * Returns a mock SensorConfig entity.
   *
   * @param array $id
   *   An ID to set on the sensor.
   *
   * @return \Drupal\monitoring\Entity\SensorConfig|\PHPUnit_Framework_MockObject_MockObject
   *   The mock sensor object.
   */
  protected function getMockSensor($id) {
    $sensor1 = $this->getMockBuilder(SensorConfig::class)
      ->setConstructorArgs([[], 'monitoring_sensor_config'])
      ->getMock();
    $sensor1->expects($this->any())
      ->method('getConfigDependencyName')
      ->willReturn("sensor.$id");
    return $sensor1;
  }

}
