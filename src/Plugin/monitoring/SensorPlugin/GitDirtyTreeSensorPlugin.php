<?php
/**
 * @file
 * Contains \Drupal\monitoring\Plugin\monitoring\SensorPlugin\GitDirtyTreeSensorPlugin.
 */

namespace Drupal\monitoring\Plugin\monitoring\SensorPlugin;

use Drupal\Core\Form\FormStateInterface;
use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\SensorPlugin\SensorPluginBase;
use Drupal\monitoring\SensorPlugin\ExtendedInfoSensorPluginInterface;

/**
 * Monitors the git repository for dirty files.
 *
 * @SensorPlugin(
 *   id = "monitoring_git_dirty_tree",
 *   label = @Translation("Git Dirty Tree"),
 *   description = @Translation("Monitors the git repository for dirty files."),
 *   addable = FALSE
 * )
 *
 * Tracks both changed and untracked files.
 * Also supports git submodules.
 *
 * Limitations:
 * - Does not work as long as submodules are not initialized.
 * - Does not check branch / tag.
 */
class GitDirtyTreeSensorPlugin extends SensorPluginBase implements ExtendedInfoSensorPluginInterface {

  /**
   * The executed command output.
   *
   * @var array
   */
  protected $status;
  protected $distance;
  protected $branches;
  protected $actualBranch;
  protected $submodules;

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    // For security reasons, some hostings disable exec() related functions.
    // Since they fail silently, we challenge exec() and check if the result
    // matches the expectations.
    if (exec('echo "enabled"') != 'enabled') {
      $result->addStatusMessage('The function exec() is disabled. You need to enable it.');
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
      return;
    }

    // Run commands.
    $branch_control = $this->sensorConfig->getSetting('check_branch');
    if ($branch_control) {
      $branch = $this->runSensorCommand($result, 'actual_branch_cmd');
      $this->actualBranch = $branch[0];
    }
    $this->status = $this->runSensorCommand($result, 'status_cmd');
    $this->distance = $this->runSensorCommand($result, 'ahead_cmd');
    $this->submodules = $this->runSensorCommand($result, 'submodules_cmd');

    $wrong_submodules = [];
    foreach ($this->submodules as $submodule) {
      $prefix = substr($submodule, 0, 1);
      if ($prefix == '-' || $prefix == '+') {
        array_push($wrong_submodules, $submodule);
      }
    }

    $result->setExpectedValue(0);
    $is_expected_branch = $this->actualBranch === $this->sensorConfig->getSetting('expected_branch');
    if ($this->status || $this->distance || (!$is_expected_branch && $branch_control) || $wrong_submodules) {
      $result->setValue(count($this->status));

      // Critical situations.
      if ($this->status) {
        $result->addStatusMessage('Files in unexpected state: @files', array('@files' => $this->getShortFileList($this->status, 2)));
        $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
      }

      // Warnings.
      if ($this->distance) {
        $result->addStatusMessage('Branch is @distance ahead of origin', array('@distance' => count($this->distance)));
        if ($result->getStatus() != SensorResultInterface::STATUS_CRITICAL) {
          $result->setStatus(SensorResultInterface::STATUS_WARNING);
        }
      }
      if (!$is_expected_branch && $branch_control) {
        $result->addStatusMessage('Active branch @actual_branch, expected @expected_branch', array(
          '@actual_branch' => $this->actualBranch,
          '@expected_branch' => $this->sensorConfig->getSetting('expected_branch'),
        ));
        if ($result->getStatus() != SensorResultInterface::STATUS_CRITICAL) {
          $result->setStatus(SensorResultInterface::STATUS_WARNING);
        }
      }
      if ($wrong_submodules) {
        $result->addStatusMessage('Submodules in unexpected state: @submodules', array('@submodules' => $this->getShortFileList($wrong_submodules, 2)));
        if ($result->getStatus() != SensorResultInterface::STATUS_CRITICAL) {
          $result->setStatus(SensorResultInterface::STATUS_WARNING);
        }
      }
    }
    else {
      $result->setValue(0);
      $result->addStatusMessage('Git repository clean');
      $result->setStatus(SensorResultInterface::STATUS_OK);
    }
  }

  /**
   * Returns a shortened file list for the status message.
   *
   * @param string $input
   *   Result from running the git command.
   * @param int $max_files
   *   Limit the number of files returned.
   * @param int $max_length
   *   Limit the length of the path to the file.
   *
   * @return string
   *   File names from $output.
   */
  protected function getShortFileList($input, $max_files = 2, $max_length = 50) {
    $output = array();
    // Remove unnecessary whitespace.
    foreach (array_slice($input, 0, $max_files) as $line) {
      // Separate type of modification and path to file.
      $parts = explode(' ', $line, 2);
      if (strlen($parts[1]) > $max_length) {
        // Put together type of modification and path to file limited by
        // $pathLength.
        $output[] = $parts[0] . ' …' . substr($parts[1], -$max_length);
      }
      else {
        // Return whole line if path is shorter then $pathLength.
        $output[] = $line;
      }
    }
    return implode(', ', $output);
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = [];

    $branch_control = $this->sensorConfig->getSetting('check_branch');
    if ($branch_control) {
      $output['check_branch'] = array(
        '#type' => 'fieldset',
        '#title' => t('Check branch'),
        '#attributes' => array(),
      );
      $output['check_branch']['cmd'] = array(
        '#type' => 'item',
        '#title' => t('Command'),
        '#markup' => $this->buildCommand('actual_branch_cmd'),
      );
      $output['check_branch']['output'] = array(
        '#type' => 'item',
        '#title' => t('Output'),
        '#markup' => $this->actualBranch,
        '#description' => t('Shows the current branch.'),
        '#description_display' => 'after',
      );
    }
    $output['ahead'] = array(
      '#type' => 'fieldset',
      '#title' => t('Ahead'),
      '#attributes' => array(),
    );
    $output['ahead']['cmd'] = array(
      '#type' => 'item',
      '#title' => t('Command'),
      '#markup' => $this->buildCommand('ahead_cmd'),
    );
    $output['ahead']['output'] = array(
      '#type' => 'item',
      '#title' => t('Output'),
      '#markup' => '<pre>' . implode("\n", $this->distance) . '</pre>',
      '#description' => t('Shows local commits that have not been pushed.'),
      '#description_display' => 'after',
    );
    $output['status'] = array(
      '#type' => 'fieldset',
      '#title' => t('Status'),
      '#attributes' => array(),
    );
    $output['status']['cmd'] = array(
      '#type' => 'item',
      '#title' => t('Command'),
      '#markup' => $this->buildCommand('status_cmd'),
    );
    $output['status']['output'] = array(
      '#type' => 'item',
      '#title' => t('Output'),
      '#markup' => '<pre>' . implode("\n", $this->status) . '</pre>',
      '#description' => t('Shows uncommitted, changed and deleted files.'),
      '#description_display' => 'after',
    );
    $output['submodules'] = array(
      '#type' => 'fieldset',
      '#title' => t('Submodules'),
      '#attributes' => array(),
    );
    $output['submodules']['cmd'] = array(
      '#type' => 'item',
      '#title' => t('Command'),
      '#markup' => $this->buildCommand('submodules_cmd'),
    );
    $output['submodules']['output'] = array(
      '#type' => 'item',
      '#title' => t('Output'),
      '#markup' => '<pre>' . implode("\n", $this->submodules) . '</pre>',
      '#description' => t('List all submodules and with a "-" prefix those not initialized and with "+" those in unexpected state.'),
      '#description_display' => 'after',
    );

    return $output;
  }

  /**
   * Build the command to be passed into shell_exec().
   *
   * @param string $cmd
   *   Command we want to run.
   *
   * @return string
   *   Shell command.
   */
  protected function buildCommand($cmd) {
    $repo_path = DRUPAL_ROOT . '/' . $this->sensorConfig->getSetting('repo_path');
    $cmd = $this->sensorConfig->getSetting($cmd);
    return 'cd ' . escapeshellarg($repo_path) . "\n$cmd  2>&1";
  }

  /**
   * Run the command and set the status message and the status to the result.
   *
   * @param \Drupal\monitoring\Result\SensorResultInterface $result
   *   Sensor result object.
   * @param string $cmd
   *   Command we want to run.
   *
   * @return array
   *   Output of executing the Shell command.
   */
  protected function runSensorCommand(SensorResultInterface &$result, $cmd) {
    $exit_code = 0;
    $command = $this->buildCommand($cmd);
    exec($command, $output, $exit_code);
    if ($exit_code > 0) {
      $result->addStatusMessage('Non-zero exit code @exit_code for command @command', array(
        '@exit_code' => $exit_code,
        '@command' => $command,
      ));
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['repo_path'] = array(
      '#type' => 'textfield',
      '#default_value' => $this->sensorConfig->getSetting('repo_path'),
      '#title' => t('Repository path'),
      '#description' => t('Path to the Git repository relative to the Drupal root directory.'),
    );

    $this->branches = $this->runCommand('branches_cmd', t('Failing to get Git branches, Git might not be available.'));
    $expected_branch = $this->sensorConfig->getSetting('expected_branch');
    if (empty($expected_branch)) {
      $expected_branch = $this->runCommand('actual_branch_cmd', t('Failing to get the actual branch, Git might not be available.'));
    }
    $options = array();
    foreach ($this->branches as $branch) {
      $options[$branch] = $branch;
    }

    $form['check_branch'] = array(
      '#type' => 'checkbox',
      '#default_value' => $this->sensorConfig->getSetting('check_branch'),
      '#title' => t('Branch control'),
      '#description' => t('Check if the current branch is different from the selected.'),
      '#disabled' => !$options,
    );

    $form['expected_branch'] = array(
      '#type' => 'select',
      '#default_value' => $expected_branch,
      '#maxlength' => 255,
      '#empty_option' => t('- Select -'),
      '#options' => $options,
      '#title' => t('Expected active branch'),
      '#description' => t('The branch that is going to be checked out.'),
      '#states' => array(
        // Hide the branch selector when the check_branch checkbox is disabled.
        'invisible' => array(
          ':input[name="settings[check_branch]"]' => array('checked' => FALSE),
        ),
      ),
    );

    return $form;
  }

  /**
   * Run a command providing an error message.
   *
   * @param string $cmd
   *   Command we want to run.
   * @param string $error
   *   Error message to show when failing.
   *
   * @return array
   *   Output of executing the Shell command.
   */
  private function runCommand($cmd, $error) {
    $exit_code = 0;
    exec($this->buildCommand($cmd), $output, $exit_code);
    if ($exit_code > 0) {
      drupal_set_message($error, 'error');
    }
    return $output;
  }

}