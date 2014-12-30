<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\ViewDisplayAggregatorSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\views\Views;

/**
 * Execute a view display and count the results.
 *
 * @SensorPlugin(
 *   id = "view_display_aggregator",
 *   label = @Translation("View Display Aggregator"),
 *   description = @Translation("Execute a view display and count the results."),
 *   provider = "views",
 *   addable = TRUE
 * )
 */
class ViewDisplayAggregatorSensorPlugin extends SensorPluginBase implements ExtendedInfoSensorPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {

    $view_executable = Views::getView($this->sensorConfig->getSetting('view'));
    $view_executable->build($this->sensorConfig->getSetting('display'));

    // Get the query and arguments of the view.
    $query = $view_executable->getQuery()->query();
    $arguments = $query->arguments();

    // Get the preview of the view for current display.
    $preview = $view_executable->preview($this->sensorConfig->getSetting('display'));

    $verbose = array();
    $verbose[] = "<pre>";
    $verbose[] = "Query:\n$query";
    $verbose[] = "Arguments:\n" . var_export($arguments, TRUE);
    $verbose[] = "</pre>";
    // @todo Pagers and exposed filters are output, but broken. See https://www.drupal.org/node/2399437
    $verbose[] = drupal_render($preview);

    return implode("\n", $verbose);
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {

    $view_executable = Views::getView($this->sensorConfig->getSetting('view'));
    // Execute the view query and get the total rows.
    $view_executable->preview($this->sensorConfig->getSetting('display'));
    $records_count = $view_executable->total_rows;
    $result->setValue($records_count);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // View selection.
    $form['view'] = array(
      '#type' => 'select',
      '#options' => $this->getViewsOptions(),
      '#title' => t('View'),
      '#default_value' => $this->sensorConfig->getSetting('view'),
      '#required' => TRUE,
      '#limit_validation_errors' => array(array('settings', 'view')),
      '#submit' => array('::submitSelectPlugin'),
      '#executes_submit_callback' => TRUE,
      '#ajax' => array(
        'callback' => '::updateSelectedPluginType',
        'wrapper' => 'monitoring-sensor-plugin',
        'method' => 'replace',
      ),
    );
    $form['view_update'] = array(
      '#type' => 'submit',
      '#value' => t('Select view'),
      '#limit_validation_errors' => array(array('settings', 'view')),
      '#submit' => array('::submitSelectPlugin'),
      '#attributes' => array('class' => array('js-hide')),
    );

    // Show display selection if a view is selected.
    if ($view = $this->sensorConfig->getSetting('view')) {
      $form['display'] = array(
        '#type' => 'select',
        '#title' => t('Display'),
        '#required' => TRUE,
        '#options' => $this->getDisplayOptions($view),
        '#default_value' => $this->sensorConfig->getSetting('display'),
      );
    }

    return $form;
  }

  /**
   * Gets the available views.
   *
   * @return array
   *   Available views list.
   */
  protected function getViewsOptions() {
    $options = [];
    $views = Views::getAllViews();
    foreach ($views as $view) {
      $options[$view->id()] = $view->label();
    }
    return $options;
  }

  /**
   * Gets the display list for selected view.
   *
   * @param string $view_id
   *   Selected view.
   *
   * @return array
   *   Available displays list.
   */
  protected function getDisplayOptions($view_id) {
    $options = [];

    $displays = Views::getView($view_id)->storage->get('display');
    foreach ($displays as $display) {
      $options[$display['id']] = $display['display_title'];
    }
    return $options;
  }
}