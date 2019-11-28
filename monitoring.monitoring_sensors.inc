<?php
/**
 * @file
 * Define default sensors for core and contrib modules.
 *
 * Default sensors provide sensors for core and contrib modules with
 * monitoring_MODULE_monitoring_sensor_info()
 *
 * The default integration for MODULE is omitted if that module provides a
 * SensorPlugin plugin of its own.
 *
 * @see \Drupal\monitoring\Annotation\SensorPlugin
 * @see \Drupal\monitoring\SensorPlugin\SensorPluginInterface
 */

use Drupal\Component\Render\FormattableMarkup;

/**
 * Implements monitoring_MODULE_monitoring_sensor_info().
 *
 * Module: elysia_cron
 */
function monitoring_elysia_cron_monitoring_sensor_info() {
  $info = array();

  $result = \Drupal::database()->select('elysia_cron', 'e')->fields('e', array('name'))->execute()->fetchAll();

  foreach ($result as $row) {
    // Remove the colon from the name as it breaks munin graphs.
    $name = str_replace(':', '', $row->name);
    $info['elysia_cron_' . $name . '_last_execution'] = array(
      'label' => new FormattableMarkup('Elysia @name execution', array('@name' => $row->name)),
      'description' => new FormattableMarkup('Last execution duration for elysia @name cron', array('@name' => $row->name)),
      'plugin_id' => 'elysia_cron',
      'value_type' => 'time_interval',
      'settings' => array(
        'category' => 'Elysia Cron',
        'enabled' => FALSE,
        'name' => $row->name,
        'metric' => 'last_execution_time',
      ),
    );
    $info['elysia_cron_' . $name . '_last_run'] = array(
      'label' => new FormattableMarkup('Elysia @name run', array('@name' => $row->name)),
      'description' => new FormattableMarkup('Time since last elysia @name run', array('@name' => $row->name)),
      'plugin_id' => 'elysia_cron',
      'value_type' => 'time_interval',
      'settings' => array(
        'category' => 'Elysia Cron',
        'enabled' => FALSE,
        'name' => $row->name,
        'metric' => 'last_run',
      ),
    );
  }

  return $info;
}
