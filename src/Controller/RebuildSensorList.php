<?php

namespace Drupal\monitoring\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\monitoring\Entity\SensorConfig;

class RebuildSensorList extends ControllerBase {
  /**
   * Rebuilds updated requirements sensors.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the updated sensor list.
   */
  public function rebuild() {
    // Declaring a flag for updated sensors.
    $updated_sensors = FALSE;

    // Load .install files
    include DRUPAL_ROOT . '/core/includes/install.inc';
    drupal_load_updates();

    // Iterate through the installed implemented modules to see if
    // there are any new requirements hook updates and initialize them.
    foreach (\Drupal::moduleHandler()->getImplementations('requirements') as $module) {
      if(!SensorConfig::load('core_requirements_' . $module)) {
        if (initialize_requirements_sensors($module)) {
          drupal_set_message($this->t('The sensor @sensor has been added.', ['@sensor' => SensorConfig::load('core_requirements_' . $module)->getLabel()]));
          $updated_sensors = TRUE;
        }
      }
    }

    // Delete any updated sensors that are not implemented in the requirements
    // hook anymore.
    $sensor_ids = \Drupal::entityQuery('monitoring_sensor_config')
      ->condition('plugin_id', 'core_requirements')
      ->execute();
    foreach (SensorConfig::loadMultiple($sensor_ids) as $sensor) {
      $module = $sensor->getSetting('module');
      if (!(\Drupal::moduleHandler()->implementsHook($module, 'requirements'))) {
        drupal_set_message($this->t('The sensor @sensor has been removed.', ['@sensor' => $sensor->getLabel()]));
        $sensor->delete();
        $updated_sensors = TRUE;
      }
    }

    // Rebuilds all non-addable sensors.
    $definitions = \Drupal::service('monitoring.sensor_manager')->getDefinitions();
    foreach ($definitions as $sensor_definition) {
      if (!$sensor_definition['addable']) {
        // Checks if the sensor is not created.
        if (!SensorConfig::load($sensor_definition['id'])) {
          $content = NULL;
          // Check the two directories install and optional for sensors that need to be created.
          foreach (['install', 'optional'] as $directory) {
            $config_path = drupal_get_path('module', 'monitoring') . '/config/' . $directory . '/monitoring.sensor_config.' . $sensor_definition['id'] . '.yml';
            if (file_exists($config_path)) {
              $content = file_get_contents($config_path);
              break;
            }
          }
          // Create the sensor.
          if ($content) {
            $data = Yaml::decode($content);
            SensorConfig::create($data)->trustData()->save();
            drupal_set_message($this->t('The sensor @sensor has been created.', ['@sensor' => (string) $sensor_definition['label']]));
          }
        }
      }
    }

    // Set message to inform the user that there were no updated sensors.
    if($updated_sensors == FALSE) {
      drupal_set_message($this->t('No changes were made.'));
    }
    return $this->redirect('monitoring.sensors_overview_settings');
  }
}
