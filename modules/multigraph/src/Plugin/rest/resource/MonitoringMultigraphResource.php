<?php

/**
 * @file
 * Contains \Drupal\monitoring_multigraph\Plugin\rest\resource\MonitoringMultigraphResource.
 */

namespace Drupal\monitoring_multigraph\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Route;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\monitoring_multigraph\Entity\Multigraph;

/**
 * Provides a resource for monitoring multigraphs.
 *
 * @RestResource(
 *   id = "monitoring-multigraph",
 *   label = @Translation("Monitoring multigraph")
 * )
 */
class MonitoringMultigraphResource extends ResourceBase {

  /**
   * {@inheritdoc}
   */
  public function routes() {
    $path_prefix = strtr($this->pluginId, ':', '/');
    $route_name = strtr($this->pluginId, ':', '.');

    $collection = parent::routes();
    $route = new Route("/$path_prefix", array(
      '_controller' => 'Drupal\rest\RequestHandler::handle',
      // Pass the resource plugin ID along as default property.
      '_plugin' => $this->pluginId,
    ), array(
      '_permission' => "restful get $this->pluginId",
    ));
    $route->setMethods(['GET']);
    foreach ($this->serializerFormats as $format_name) {
      // Expose one route per available format.
      $format_route = clone $route;
      $format_route->addRequirements(array('_format' => $format_name));
      $collection->add("$route_name.list.$format_name", $format_route);
    }
    return $collection;
  }

  /**
   * Responds to multigraph GET requests.
   *
   * @param string $multigraph_name
   *   (optional) The multigraph name, returns a list of all multigraphs when
   *   empty.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The response containing the multigraph.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   */
  public function get($multigraph_name = NULL) {

    $request = \Drupal::request();
    $format = $request->getRequestFormat('ĵson');

    if ($multigraph_name) {
      /** @var \Drupal\monitoring_multigraph\Entity\Multigraph $multigraph */
      $multigraph = \Drupal::entityManager()
        ->getStorage('monitoring_multigraph')
        ->load($multigraph_name);
      if ($multigraph == NULL) {
        throw new NotFoundHttpException('No multigraph with name "' . $multigraph_name . '"');
      }
      $response = $multigraph->getDefinition();
      $url = Url::fromRoute('rest.monitoring-multigraph.GET.' . $format , ['id' => $multigraph_name, '_format' => $format])->setAbsolute()->toString(TRUE);
      $response['uri'] = $url->getGeneratedUrl();
      $response = new ResourceResponse($response);
      $response->addCacheableDependency($multigraph);
      $response->addCacheableDependency($url);
      return $response;
    }

    $list = array();
    $multigraphs = \Drupal::entityManager()
      ->getStorage('monitoring_multigraph')
      ->loadMultiple();
    $cacheable_metadata = new CacheableMetadata();
    foreach ($multigraphs as $name => $multigraph) {
      /** @var \Drupal\monitoring_multigraph\Entity\Multigraph $multigraph */
      $list[$name] = $multigraph->getDefinition();
      $url = Url::fromRoute('rest.monitoring-multigraph.GET.' . $format , ['id' => $name, '_format' => $format])->setAbsolute()->toString(TRUE);
      $list[$name]['uri'] = $url->getGeneratedUrl();

      $cacheable_metadata = $cacheable_metadata->merge($url);
      $cacheable_metadata = $cacheable_metadata->merge(CacheableMetadata::createFromObject($multigraph));

    }
    $response = new ResourceResponse($list);
    return $response->addCacheableDependency($cacheable_metadata);
  }
}
