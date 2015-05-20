<?php
/**
 * @file
 * Contains \Drupal\monitoring\Sensor\Sensors\SensorGitDirtyTree.
 */

namespace Drupal\monitoring\Sensor\Sensors;

use Drupal\monitoring\Result\SensorResultInterface;
use Drupal\monitoring\Sensor\SensorConfigurable;
use Drupal\monitoring\Sensor\SensorExtendedInfoInterface;

/**
 * Monitors the git repository for dirty files.
 *
 * Tracks both changed and untracked files.
 * Also supports git submodules.
 *
 * Limitations:
 * - Does not work as long as submodules are not initialized.
 * - Does not check branch / tag.
 */
class SensorGitDirtyTree extends SensorConfigurable implements SensorExtendedInfoInterface {

  /**
   * The executed command output.
   *
   * @var array
   */
  protected $cmdOutput;

  /**
   * {@inheritdoc}
   */
  public function runSensor(SensorResultInterface $result) {
    $this->cmdOutput = trim(shell_exec($this->buildCMD()));
    $result->setExpectedValue(0);

    if (!empty($this->cmdOutput)) {
      $result->setValue(count(explode("\n", $this->cmdOutput)));
      $result->addStatusMessage('Files in unexpected state: ' . $this->getShortFileList($this->cmdOutput, 2));
      $result->setStatus(SensorResultInterface::STATUS_CRITICAL);
    }
    else {
      $result->setValue(0);
      $result->addStatusMessage('Git repository clean');
      $result->setStatus(SensorResultInterface::STATUS_OK);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resultVerbose(SensorResultInterface $result) {
    $output = 'CMD: ' . $this->buildCMD();;
    $output .= "\n\n" . $this->cmdOutput;
    return $output;
  }

  /**
   * Build the command to be passed into shell_exec().
   *
   * @return string
   *   Shell command.
   */
  protected function buildCMD() {
    $repo_path = DRUPAL_ROOT . '/' . $this->info->getSetting('repo_path');
    $cmd = $this->info->getSetting('cmd');
    return 'cd ' . escapeshellarg($repo_path) . "\n$cmd  2>&1";
  }

  /**
   * Returns a shortened file list for the status message.
   *
   * @param string $input
   *   Result from running the git command.
   *
   * @param int $max_files
   *   Limit the number of files returned.
   *
   * @param int $max_length
   *   Limit the length of the path to the file
   *
   * @return string
   *   File names from $output.
   */
  protected function getShortFileList($input, $max_files = 2, $max_length = 50) {
    $output = array();
    // Remove unnecessary whitespace.
    $input = preg_replace('/\s\s+/', ' ', trim($input));
    $lines = explode(PHP_EOL, $input);
    foreach (array_slice($lines, 0, $max_files) as $line) {
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
}
