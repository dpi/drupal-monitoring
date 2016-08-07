<?php

namespace Drupal\monitoring\Controller;

use Drupal\Core\Controller\ControllerBase;
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

    // Set message to inform the user that there were no updated sensors.
    if($updated_sensors == FALSE) {
      drupal_set_message($this->t('No changes were made.'));
    }
    return $this->redirect('monitoring.sensors_overview_settings');
  }
}
