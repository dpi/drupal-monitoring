<?php

/**
 * @file
 * Contains \Drupal\monitoring\SensorConfigAccessControlHandler.
 */

namespace Drupal\monitoring;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for sensor config.
 *
 * @see \Drupal\monitoring\Entity\SensorConfig
 */
class SensorConfigAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\monitoring\Entity\SensorConfig $entity */
    $plugin_definition = $entity->getPlugin()->getPluginDefinition();

    $cacheability = new CacheableMetadata();
    if ($operation == 'delete' && !$plugin_definition['addable']) {
      return AccessResult::forbidden('Cannot delete non-addable sensor configuration instances.');
    }
    elseif ($operation == 'force run') {
      return AccessResult::allowedIfHasPermission($account, 'monitoring force run');
    }
    elseif ($operation == 'view') {
      $cacheability->addCacheableDependency($entity);
      if (!$entity->isEnabled()) {
        return AccessResult::forbidden('Sensor is not enabled.')->addCacheableDependency($cacheability);
      }

      // We're testing against permission, so add vary by permission.
      $cacheability->addCacheContexts(['user.permissions']);
      if ($account->hasPermission('monitoring reports')) {
        return AccessResult::allowed()->addCacheableDependency($cacheability);
      }
    }

    return parent::checkAccess($entity, $operation, $account)->addCacheableDependency($cacheability);
  }

}
