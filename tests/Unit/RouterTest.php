<?php
/**
 * Router Tests
 *
 * @package Pastane\Tests\Unit
 */

declare(strict_types=1);

namespace Pastane\Tests\Unit;

use Pastane\Tests\TestCase;
use Pastane\Router\Router;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        // Her test için temiz bir router instance oluştur
        $this->router = Router::getInstance();
    }

    /**
     * @test
     */
    public function it_registers_get_routes(): void
    {
        $called = false;
        $this->router->get('/test/router-get', function() use (&$called) {
            $called = true;
            return 'ok';
        });

        $result = $this->router->dispatch('GET', '/test/router-get');

        $this->assertTrue($called);
    }

    /**
     * @test
     */
    public function it_registers_post_routes(): void
    {
        $called = false;
        $this->router->post('/test/router-post', function() use (&$called) {
            $called = true;
            return 'ok';
        });

        $result = $this->router->dispatch('POST', '/test/router-post');

        $this->assertTrue($called);
    }

    /**
     * @test
     */
    public function it_extracts_route_parameters(): void
    {
        $capturedId = null;
        $this->router->get('/test/items/{id}', function($params) use (&$capturedId) {
            $capturedId = $params['id'];
            return 'ok';
        });

        $this->router->dispatch('GET', '/test/items/42');

        $this->assertEquals('42', $capturedId);
    }

    /**
     * @test
     */
    public function it_extracts_multiple_parameters(): void
    {
        $capturedParams = [];
        $this->router->get('/test/{category}/{id}', function($params) use (&$capturedParams) {
            $capturedParams = $params;
            return 'ok';
        });

        $this->router->dispatch('GET', '/test/pasta/7');

        $this->assertEquals('pasta', $capturedParams['category']);
        $this->assertEquals('7', $capturedParams['id']);
    }
}
