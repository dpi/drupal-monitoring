<?php

namespace Drupal\monitoring_munin\Form;


use Drupal\Core\Form\FormInterface;

class MuninGraphSettingsForm implements FormInterface {


  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'monitoring_munin_graph_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state, $sensor_name = '') {
    $form['multigraphs'] = array(
      '#type' => 'fieldset',
      '#title' => t('Munin multigraph definitions'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    $options = array();
    foreach (monitoring_munin_multigraphs() as $multigraph) {
      $options[$multigraph['title']] = array('title' => $multigraph['title'], 'vlabel' => $multigraph['vlabel']);
    }

    $form['multigraphs']['delete_multigraphs'] = array(
      '#type' => 'tableselect',
      '#header' => array('title' => t('Title'), 'vlabel' => t('Graph units')),
      '#options' => $options,
    );
    $form['multigraphs']['title'] = array(
      '#type' => 'textfield',
      '#title' => t('Multigraph name'),
      '#description' => t('Enter name of a new multigraph.')
    );
    $form['multigraphs']['vlabel'] = array(
      '#type' => 'textfield',
      '#title' => t('Value label'),
      '#description' => t('Enter common value label.')
    );
    $form['multigraphs']['actions']['add_multigraph'] = array(
      '#type' => 'submit',
      '#value' => t('Add multigraph'),
      '#submit' => array('monitoring_munin_multigraph_add_submit')
    );
    $form['multigraphs']['actions']['delete_multigraphs'] = array(
      '#type' => 'submit',
      '#value' => t('Delete selected'),
      '#submit' => array('monitoring_munin_multigraph_delete_submit')
    );

    $options = array();
    foreach (monitoring_munin_multigraphs() as $multigraph) {
      $options[$multigraph['title']] = $multigraph['title'];
    }

    foreach (monitoring_sensor_manager()->getEnabledSensorInfo() as $sensor_name => $sensor_info) {
      $munin_settings = $sensor_info->getSetting('munin', array(
        'multigraphs' => array(),
        'munin_enabled' => FALSE,
        'graph_args' => '',
      ));

      $form[$sensor_name] = array(
        '#type' => 'fieldset',
        '#title' => $sensor_info->getLabel(),
        '#description' => $sensor_info->getDescription(),
        '#tree' => TRUE,
      );
      $form[$sensor_name]['munin_enabled'] = array(
        '#type' => 'checkbox',
        '#title' => t('Enabled'),
        '#default_value' => $munin_settings['munin_enabled'],
        '#attributes' => array('id' => $sensor_name . '_enabled'),
      );
      $form[$sensor_name]['multigraphs'] = array(
        '#type' => 'select',
        '#title' => t('Presence in multigraphs'),
        '#multiple' => TRUE,
        '#options' => $options,
        '#default_value' => $munin_settings['multigraphs'],
        '#states' => array(
          'visible' => array(
            ':input[id="' . $sensor_name . '_enabled"]' => array('checked' => TRUE),
          ),
        ),
      );
    }
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Save configuration'),
    );
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    foreach (monitoring_sensor_info() as $sensor_name => $sensor_info) {
      if ($sensor_info->isEnabled() && !empty($form_state['values'][$sensor_name])) {
        $settings = monitoring_sensor_settings_get($sensor_name);
        $settings['munin'] = $form_state['values'][$sensor_name];
        monitoring_sensor_settings_save($sensor_name, $settings);
      }
    }
  }

  public function multigraphAddSubmit(array &$form, array &$form_state) {
    if (!empty($form_state['values']['title'])) {
      monitoring_munin_multigraph_save($form_state['values']['title'], $form_state['values']['vlabel']);
    }
  }

  public function multigraphDeleteSubmit(array &$form, array &$form_state) {
    foreach ($form_state['values']['delete_multigraphs'] as $title) {
      monitoring_munin_multigraph_delete($title);
    }
  }
}
