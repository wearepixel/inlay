<?php

declare(strict_types=1);

namespace WeArePixel\Inlay\Pages;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Laravel answers a request with the first registered route that matches
 * it. Inlay's page route is a catch-all, so it must come after every other
 * route: a route registered before it that matches a page's URL wins, and
 * the page never shows; a route registered after it never gets a request.
 * This asks the router which route really answers a URL, to explain either
 * problem before someone goes looking for it.
 */
final class RouteConflicts
{
    public const PAGE_ROUTE = 'inlay.pages.show';

    public function __construct(private readonly Router $router) {}

    /**
     * The route that answers $url instead of the page, or null when the page
     * is served there (or Inlay is not serving pages at all).
     */
    public function routeTaking(?string $url): ?string
    {
        if ($url === null || ! $this->router->has(self::PAGE_ROUTE)) {
            return null;
        }

        try {
            $route = $this->router->getRoutes()->match(Request::create($url, 'GET'));
        } catch (NotFoundHttpException) {
            return null;
        } catch (MethodNotAllowedHttpException) {
            return "a route that does not accept GET requests at {$url}";
        }

        if ($route->getName() === self::PAGE_ROUTE) {
            return null;
        }

        return self::describe($route);
    }

    /**
     * App routes registered after Inlay's page route, which it answers
     * first so they never run - Inlay::pageRoutes() was called too early.
     * Only routes without parameters are checked, since only those have a
     * single URL to try.
     *
     * @return array<int, string>
     */
    public function routesHiddenByPages(): array
    {
        $hidden = [];
        $pageRouteSeen = false;

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if ($route->getName() === self::PAGE_ROUTE) {
                $pageRouteSeen = true;

                continue;
            }

            if (! $pageRouteSeen || ! in_array('GET', $route->methods(), true) || str_contains($route->uri(), '{')) {
                continue;
            }

            $url = '/'.ltrim($route->uri(), '/');

            if ($this->answeredByPageRoute($url)) {
                $hidden[] = self::describe($route);
            }
        }

        return $hidden;
    }

    private function answeredByPageRoute(string $url): bool
    {
        try {
            return $this->router->getRoutes()->match(Request::create($url, 'GET'))->getName() === self::PAGE_ROUTE;
        } catch (NotFoundHttpException|MethodNotAllowedHttpException) {
            return false;
        }
    }

    private static function describe(Route $route): string
    {
        $url = '/'.ltrim($route->uri(), '/');
        $name = $route->getName();
        $action = $route->getActionName();
        $handler = $action === 'Closure' ? 'a closure' : $action;

        return $name !== null
            ? "the \"{$name}\" route ({$url}, {$handler})"
            : "the route for {$url} ({$handler})";
    }
}
