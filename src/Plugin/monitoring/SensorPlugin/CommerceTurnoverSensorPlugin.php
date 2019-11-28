<?php

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use CommerceGuys\Intl\Formatter\CurrencyFormatterInterface;
use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Entity\SensorConfig;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Monitors commerce order turnover stats.
 *
 * Based on SensorEntityDatabaseAggregator using commerce_order table.
 *
 * @SensorPlugin(
 *   id = "commerce_turnover",
 *   label = @Translation("Commerce order turnover"),
 *   description = @Translation("Monitors how much money was earned with commerce orders."),
 *   provider = "commerce_order",
 *   addable = TRUE
 * )
 */
class CommerceTurnoverSensorPlugin extends ContentEntityAggregatorSensorPlugin {

  /**
   * The commerce currency formatter.
   *
   * @var \CommerceGuys\Intl\Formatter\CurrencyFormatterInterface
   */
  protected $currencyFormatter;

  /**
   * The current store service.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The workflow manager.
   *
   * @var \Drupal\state_machine\WorkflowManagerInterface
   */
  protected $workflowManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(SensorConfig $sensor_config, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, CurrencyFormatterInterface $currency_formatter, CurrentStoreInterface $current_store, WorkflowManagerInterface $workflow_manager) {
    parent::__construct($sensor_config, $plugin_id, $plugin_definition, $entity_type_manager, $entity_field_manager);

    $this->currencyFormatter = $currency_formatter;
    $this->currentStore = $current_store;
    $this->workflowManager = $workflow_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, SensorConfig $sensor_config, $plugin_id, $plugin_definition) {
    return new static(
      $sensor_config,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('commerce_price.currency_formatter'),
      $container->get('commerce_store.current_store'),
      $container->get('plugin.manager.workflow')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityQueryAggregate() {
    $query = parent::getEntityQueryAggregate();

    $query->aggregate('total_price.number', 'SUM');
    $query->condition('total_price.currency_code', $this->sensorConfig->getSetting('commerce_order_currency'));

    if ($paid_states = array_filter($this->sensorConfig->getSetting('commerce_order_paid_states'))) {
      $query->condition('state', $paid_states, 'IN');
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    parent::runSensor($result);

    $query_result = $this->getEntityQueryAggregate()->execute();
    $currency_code = $this->sensorConfig->getSetting('commerce_order_currency');
    $sensor_value = 0;

    if (!empty($query_result)) {
      $query_result = reset($query_result);

      $sensor_value = $query_result['total_pricenumber_sum'];
    }

    $result->setValue($this->currencyFormatter->format($sensor_value, $currency_code));
  }

  /**
   * Adds additional settings to the sensor configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['commerce_order_paid_states'] = [
      '#type' => 'checkboxes',
      '#title' => t('"Paid" order states'),
      '#description' => t('Select order states in which the order is considered to be paid.'),
      '#options' => $this->getOrderStates(),
      '#default_value' => $this->sensorConfig->getSetting('commerce_order_paid_states'),
    ];

    $currencies = $this->entityTypeManager->getStorage('commerce_currency')
      ->loadMultiple();

    $current_store = $this->currentStore->getStore();
    $default_currency = $current_store->getDefaultCurrency();

    foreach ($currencies as $currency_code => $currency) {
      $currencies[$currency_code] = $currency->getName();
    }

    $selected_currency = $this->sensorConfig->getSetting('commerce_order_currency') ?: '';

    if (empty($selected_currency) && $default_currency instanceof CurrencyInterface) {
      $selected_currency = $default_currency->getCurrencyCode();
    }

    $form['commerce_order_currency'] = [
      '#type' => 'select',
      '#title' => t('Currency'),
      '#description' => t('Select which currency the orders are using.'),
      '#options' => $currencies,
      '#default_value' => $selected_currency,
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Gets all order states from all workflows.
   *
   * @return array
   *   An array of order states or empty array if no states found.
   */
  protected function getOrderStates() {
    $states = [];

    foreach ($this->workflowManager->getDefinitions() as $workflow) {
      if ($workflow['group'] == 'commerce_order') {
        foreach ($workflow['states'] as $key => $state) {
          $states[$key] = $state['label'];
        }
      }
    }

    return $states;
  }

}
