<?php

/*
 * Copyright 2018 Google Inc.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 2 as published by the
 * Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
 * or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public
 * License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc., 51
 * Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 */

namespace Drupal\Tests\apigee_m10n\Kernel\Entity;

use Drupal\apigee_edge\Entity\EdgeEntityType;
use Drupal\apigee_m10n\Entity\Package;
use Drupal\apigee_m10n\Entity\PackageInterface;
use Drupal\apigee_m10n\Entity\Routing\MonetizationEntityRouteProvider;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Url;
use Drupal\Tests\apigee_m10n\Kernel\MonetizationKernelTestBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Test the `package` entity.
 *
 * @group apigee_m10n
 * @group apigee_m10n_kernel
 */
class PackageEntityKernelTest extends MonetizationKernelTestBase {

  /**
   * A test product bundle.
   *
   * @var \Drupal\apigee_m10n\Entity\PackageInterface
   */
  protected $product_bundle;

  /**
   * A test user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->product_bundle = $this->createProductBundle();
    static::assertInstanceOf(Package::class, $this->product_bundle);

    // Prepare to create a user.
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);

    $this->user = $this->createAccount(array_keys($this->container->get('user.permissions')->getPermissions()));
    $this->setCurrentUser($this->user);
  }

  /**
   * Test loading a rate plan from drupal entity storage.
   *
   * @throws \Exception
   */
  public function testPackageEntity() {
    $this->setCurrentRouteMatch();

    // Get the package entity definition.
    $entity_type = $this->container->get('entity_type.manager')->getDefinition('package');
    static::assertInstanceOf(EdgeEntityType::class, $entity_type);

    // Check that the entity class remains unchanged.
    static::assertSame(Package::class, $entity_type->getClass());

    // Make sure we are using our custom route provider.
    static::assertSame(MonetizationEntityRouteProvider::class, $entity_type->getRouteProviderClasses()['html']);

    // Test that package canonical urls are redirecting to developer urls.
    $request = Request::create(Url::fromRoute('entity.package.canonical', ['package' => $this->product_bundle->id()])->toString(), 'GET');
    $response = $this->container->get('http_kernel')->handle($request);
    static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    static::assertSame("http://localhost/user/{$this->user->id()}/monetization/package/{$this->product_bundle->id()}", $response->headers->get('location'));

    // Make sure we get a team context when getting a package url.
    $url = $this->product_bundle->toUrl('canonical');
    static::assertSame("/user/1/monetization/package/{$this->product_bundle->id()}", $url->toString());
    static::assertSame('entity.package.developer', $url->getRouteName());

    // Load the cached package.
    $product_bundle = Package::load($this->product_bundle->id());

    static::assertInstanceOf(PackageInterface::class, $product_bundle);
    static::assertSame(gettype($product_bundle), gettype($this->product_bundle));

    // Check properties.
    static::assertSame($this->product_bundle->id(), $product_bundle->id());
    static::assertSame($this->product_bundle->getDisplayName(), $product_bundle->getDisplayName());
    static::assertSame($this->product_bundle->getName(), $product_bundle->getName());
    static::assertSame($this->product_bundle->getDescription(), $product_bundle->getDescription());
    static::assertSame($this->product_bundle->getStatus(), $product_bundle->getStatus());
    // Get the package products.
    $products = $product_bundle->getApiProducts();
    static::assertGreaterThan(0, $this->count($products));
    static::assertCount(count($this->product_bundle->getApiProducts()), $products);

    foreach ($this->product_bundle->getApiProducts() as $product_id => $product) {
      static::assertArrayHasKey($product_id, $products);
    }
  }

  /**
   * Helper to set up a mock current route match.
   */
  private function setCurrentRouteMatch() {
    // Create a request stack for the user page.
    $request = Request::create(Url::fromRoute('entity.user.canonical', ['user' => $this->user->id()])->toString(), 'GET');
    $route = $this->container->get('router.no_access_checks')->matchRequest($request);
    $request->attributes->replace($route);
    $request_stack = new RequestStack();
    $request_stack->push($request);
    $current_route_match = new CurrentRouteMatch($request_stack);
    // Set the current route match to the current user route.
    $this->container->set('current_route_match', $current_route_match);
  }

}
