<?php

namespace Drupal\monitoring\Commands;

use Consolidation\AnnotatedCommand\CommandResult;
use Consolidation\OutputFormatters\StructuredData\PropertyList;
use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\SensorManager;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile for the Monitoring module.
 */
class MonitoringCommands extends DrushCommands {

  /**
   * Exit code for an unknown sensor status.
   */
  public const SENSOR_STATUS_UNKNOWN = 3;

  /**
   * Exit code for a critical sensor status.
   */
  public const SENSOR_STATUS_CRITICAL = 2;

  /**
   * Exit code for a warning sensor status.
   */
  public const SENSOR_STATUS_WARNING = 1;

  /**
   * Exit code for a proper run with OK status.
   */
  public const SENSOR_STATUS_OK = 0;

  /**
   * The Sensor Manager service.
   *
   * @var \Drupal\monitoring\Sensor\SensorManager
   */
  protected $sensorManager;

  /**
   * The Date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * MonitoringCommands constructor.
   *
   * @param \Drupal\monitoring\Sensor\SensorManager $sensor_manager
   *   The Sensor Manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The Date formatter service.
   */
  public function __construct(SensorManager $sensor_manager, DateFormatterInterface $date_formatter) {
    parent::__construct();
    $this->sensorManager = $sensor_manager;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * List all sensors.
   *
   * @validate-module-enabled monitoring
   *
   * @command monitoring:list-sensors
   * @aliases monitoring-list-sensors
   * @field-labels
   *   label: Label
   *   name: Name
   *   category: Category
   *   enabled: Enabled
   *   description: Description
   *   value_info: Value info
   *   caching_time: Caching time
   *   has_thresholds: Has thresholds
   * @default-fields label,name,category,enabled
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   A list with sensors.
   */
  public function sensorConfigAll() {
    $sensor_config_list = $this->sensorManager->getAllSensorConfig();

    $rows = [];
    foreach ($sensor_config_list as $name => $sensor_config) {
      $rows[] = [
        'label' => $sensor_config->label(),
        'name' => $name,
        'category' => $sensor_config->getCategory(),
        'description' => $sensor_config->getDescription(),
        'value_info' => new FormattableMarkup('type: @type, label: @label, numeric: @numeric', [
          '@type' => $sensor_config->getValueType() ?: dt('N/A'),
          '@label' => $sensor_config->getValueLabel() ?: dt('N/A'),
          '@numeric' => $sensor_config->isNumeric() ? dt('Yes') : dt('No'),
        ]),
        'caching_time' => $this->dateFormatter->formatInterval($sensor_config->getCachingTime()),
        'enabled' => $sensor_config->isEnabled() ? dt('Yes') : dt('No'),
        'has_thresholds' => $sensor_config->isDefiningThresholds() ? dt('Yes') : dt('No'),
      ];
    }

    return new RowsOfFields($rows);
  }

  /**
   * Displays detailed information about the sensor config.
   *
   * @param string $sensor_name
   *   Specific sensor name for which we want to display info.
   *
   * @usage drush monitoring-sensor-config node_new
   *   Prints info of the node_new sensor.
   * @validate-module-enabled monitoring
   *
   * @command monitoring:sensor-config
   * @aliases monitoring-sensor-config
   * @field-labels
   *   label: Label
   *   category: Category
   *   description: Description
   *   value_info: Value info
   *   caching_time: Caching time
   *   enabled: Enabled
   *   has_thresholds: Has thresholds
   *
   * @return \Consolidation\OutputFormatters\StructuredData\PropertyList
   *   A list with sensor config properties.
   */
  public function sensorConfig($sensor_name = NULL) {
    $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);

    $data = [
      'label' => $sensor_config->label(),
      'category' => $sensor_config->getCategory(),
      'description' => $sensor_config->getDescription(),
      'value_info' => new FormattableMarkup('type: @type, label: @label, numeric: @numeric', [
        '@type' => $sensor_config->getValueType() ?: dt('N/A'),
        '@label' => $sensor_config->getValueLabel() ?: dt('N/A'),
        '@numeric' => $sensor_config->isNumeric() ? dt('Yes') : dt('No'),
      ]),
      'caching_time' => $this->dateFormatter->formatInterval($sensor_config->getCachingTime()),
      'enabled' => $sensor_config->isEnabled() ? dt('Yes') : dt('No'),
      'has_thresholds' => $sensor_config->isDefiningThresholds() ? dt('Yes') : dt('No'),
    ];

    return new PropertyList($data);
  }

  /**
   * Runs all sensors or a specific sensor and provides verbose data.
   *
   * @param string $sensor_name
   *   Sensor name to invoke or keep empty to invoke all sensors.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option force
   *   If the sensor execution should be forced in case cached result is
   *   available.
   * @option output
   *   (deprecated) Use --format for standard outputs and --sensu-output for
   *   a format suitable for sensu.
   * @option expand
   *   Relevant only for the json output. Currently "sensor" value supported.
   * @option sensu-source
   *   Relevant only for sensu output. The sensu source. Defaults to the
   *   host name.
   * @option sensu-ttl
   *   Relevant only for sensu output. Sensu TTL (Time to live)
   * @option sensu-handlers
   *   Relevant only for sensu output. A comma separated list of Sensu handlers
   *   (names).
   * @option sensu-metric-handlers
   *   Relevant only for sensu output. Defaults to the Sensu handlers.
   * @option sensu-metrics
   *   Relevant only for sensu output. Expose numeric sensors additionally as
   *   metrics. Enabled by default.
   * @option sensu-output
   *   Use this option to format the output for sensu. To get other formats use
   *   the drush option --format.
   *
   * @usage drush monitoring-run monitoring_git_dirty_tree
   *   Runs sensor to monitor the git status.
   * @usage drush monitoring-run --verbose monitoring_git_dirty_tree
   *   Runs sensor to monitor the git status and displays verbose information.
   * @usage drush monitoring-run --output=json --watchdog=disable
   *   Will output the sensor results in json format. The option
   *   --watchdog=disable will suppress watchdog output to console.
   * @usage drush monitoring-run --output=sensu --sensu-source=example.org --sensu-ttl=600 --sensu-handlers=email,pagerduty
   *   Will output the sensor results in sensu format. The option
   *   --sensu-source=example.org will provide the sensu source. The option
   *   --sensu-ttl=600 will provide the sensor 600 seconds time to live.
   * @validate-module-enabled monitoring
   *
   * @command monitoring:run
   * @aliases monitoring-run
   * @field-labels
   *   sensor_name: ID
   *   label: Label
   *   status: Status
   *   message: Message
   *   exec_time: Execution time
   *   result_age: Result age
   *   value: Value
   *   verbose: Verbose
   *   sensor: Sensor data
   *
   * @return \Consolidation\AnnotatedCommand\CommandResult
   *   The result list with exit code.
   */
  public function run($sensor_name = NULL, array $options = [
    'force' => NULL,
    'output' => NULL,
    'expand' => NULL,
    'show-exec-time' => NULL,
    'sensu-source' => NULL,
    'sensu-ttl' => NULL,
    'sensu-handlers' => NULL,
    'sensu-metric-handlers' => NULL,
    'sensu-metrics' => true,
  ]) {

    $sensor_names = [];
    if (!empty($sensor_name)) {
      $sensor_names[] = $sensor_name;
    }
    $results = monitoring_sensor_run_multiple($sensor_names, $options['force'], $options['verbose']);

    $rows = [];
    foreach ($results as $key => $result) {
      $rows[$key] = [
        'sensor_name' => $result->getSensorId(),
        'label' => $result->getSensorConfig()->getLabel(),
        'status' => $result->getStatusLabel(),
        'message' => $result->getMessage(),
        'exec_time' => $result->getExecutionTime(),
        'result_age' => $this->dateFormatter->formatInterval(time() - $result->getTimestamp()),
      ];
      if ($options['format'] != 'table') {
        $rows[$key]['value'] = $result->getValue();
        if ($options['expand'] == 'sensor') {
          $rows[$key]['sensor'] = $result->getSensorConfig()->toArray();
        }
      }

      // Add the verbose output if requested.
      if ($verbose_output = $result->getVerboseOutput()) {
        // @todo Improve plaintext rendering for tables(view sensor).
        $rows[$key]['verbose'] = strip_tags(\Drupal::service('renderer')->renderRoot($verbose_output));
      }
    }

    $status = self::SENSOR_STATUS_OK;
    foreach ($results as $result) {
      if ($status < self::SENSOR_STATUS_CRITICAL && $result->isCritical()) {
        $status = self::SENSOR_STATUS_CRITICAL;
        // We already have the highest status so we can stop looping now.
        break;
      }
      if ($status < self::SENSOR_STATUS_UNKNOWN && $result->isUnknown()) {
        $status = self::SENSOR_STATUS_UNKNOWN;
      }
      elseif ($status < self::SENSOR_STATUS_WARNING && $result->isWarning()) {
        $status = self::SENSOR_STATUS_WARNING;
      }
    }

    if ($options['output'] ==  'sensu' || $options['sensu-output']) {
      $source = $options['sensu-source'] ?: \Drupal::request()->getHost();
      $ttl = (int) $options['sensu-ttl'];
      $handlers = explode(',', $options['sensu-handlers']);
      $metric_handlers = explode(',', $options['sensu-metric-handlers']);
      $this->outputSensuFormat($results, $source, $ttl, $handlers, $metric_handlers, $options['sensu-metrics']);
      return CommandResult::exitCode($status);
    }
    elseif ($options['output'] && $options['output'] !== 'sensu') {
      $this->logger()->error(dt('Unsupported output option @output. Use the --format option to specify formatting options.', ['@output' => $options['output']]));
      return CommandResult::exitCode(MONITORING_DRUSH_SENSOR_STATUS_UNKNOWN);
    }

    return CommandResult::dataWithExitCode(new RowsOfFields($rows), $status);
  }

  /**
   * Enable specified monitoring sensor.
   *
   * @param string $sensor_name
   *   Sensor name to enable.
   *
   * @usage drush monitoring-enable monitoring_git_dirty_tree
   *   Enables monitoring_git_dirty_tree sensor.
   * @validate-module-enabled monitoring
   *
   * @command monitoring:enable
   * @aliases monitoring-enable
   */
  public function enable($sensor_name) {
    $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);

    if ($sensor_config->isEnabled()) {
      $message = dt('The sensor @name is already enabled.', ['@name' => $sensor_config->label()]);
      $this->logger()->notice($message);
      return;
    }
    $this->sensorManager->enableSensor($sensor_name);
    $message = dt('The sensor @name was enabled.', ['@name' => $sensor_config->label()]);
    /* @noinspection PhpUndefinedMethodInspection */
    $this->logger()->success($message);
  }

  /**
   * Disable specified monitoring sensor.
   *
   * @param string $sensor_name
   *   Sensor name to disable.
   *
   * @usage drush monitoring-disable monitoring_git_dirty_tree
   *   Disables monitoring_git_dirty_tree sensor.
   * @validate-module-enabled monitoring
   *
   * @command monitoring:disable
   * @aliases monitoring-disable
   */
  public function disable($sensor_name) {
    $sensor_config = $this->sensorManager->getSensorConfigByName($sensor_name);

    if (!$sensor_config->isEnabled()) {
      $message = dt('The sensor @name is already disabled.', ['@name' => $sensor_config->label()]);
      $this->logger()->notice($message);
      return;
    }
    $this->sensorManager->disableSensor($sensor_name);
    $message = dt('The sensor @name was disabled.', ['@name' => $sensor_config->label()]);
    /* @noinspection PhpUndefinedMethodInspection */
    $this->logger()->success($message);
  }

  /**
   * Rebuild the list of sensors.
   *
   * @validate-module-enabled monitoring
   *
   * @command monitoring:rebuild
   * @aliases monitoring-rebuild
   */
  public function rebuild() {
    $this->sensorManager->rebuildSensors();
    /* @noinspection PhpUndefinedMethodInspection */
    $this->logger()->success(dt('Sensors rebuilt.'));
  }

  /**
   * Results output in sensu format.
   *
   * @param \Drupal\monitoring\Result\SensorResultInterface[] $results
   *   List of sensor result objects.
   * @param string $source
   *   Sensu source.
   * @param int $ttl
   *   Sensor time to live.
   * @param array $handlers
   *   Sensu handlers.
   * @param array $metric_handlers
   *   Sensu metric handlers.
   * @param bool $metrics
   *   Sensu metrics.
   */
  protected function outputSensuFormat(array $results, $source, $ttl, array $handlers, array $metric_handlers, $metrics) {

    $status_codes = [
      SensorResultInterface::STATUS_OK => 0,
      SensorResultInterface::STATUS_WARNING => 1,
      SensorResultInterface::STATUS_CRITICAL => 2,
      SensorResultInterface::STATUS_UNKNOWN => 3,
      SensorResultInterface::STATUS_INFO => 0,
    ];
    foreach ($results as $name => $result) {
      // Build sensu check result.
      $sensu_output = [];
      $sensu_output['name'] = $name;
      $sensu_output['status'] = $status_codes[$result->getStatus()];
      if ($ttl) {
        $sensu_output['ttl'] = $ttl;
      }
      if ($handlers) {
        $sensu_output['handlers'] = $handlers;
      }
      $sensu_output['output'] = $result->getMessage();
      $sensu_output['interval'] = $result->getSensorConfig()->getCachingTime();
      $sensu_output['duration'] = $result->getExecutionTime() / 1000;
      $sensu_output['source'] = $source;
      $this->output()->writeln(Json::encode($sensu_output));

      // Also print numeric sensors as metrics, if enabled.
      if ($result->getSensorConfig()->isNumeric() && $metrics) {
        $sensu_metric_output = $sensu_output;
        $sensu_metric_output['name'] = $name . '_metric';
        $sensu_metric_output['type'] = 'metric';
        if ($metric_handlers) {
          $sensu_metric_output['handlers'] = $metric_handlers;
        }

        // Build the metrics data.
        $reversed_source = implode('.', array_reverse(explode('.', $sensu_output['source'])));
        $value = $result->getValue();
        $executed = $result->getTimestamp();
        $sensu_metric_output['output'] = $reversed_source . '.' . $name . ' ' . $value . ' ' . $executed;
        $this->output()->writeln(Json::encode($sensu_metric_output));
      }
    }
  }

}
