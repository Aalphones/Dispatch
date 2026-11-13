<?php

declare(strict_types=1);

use Dispatch\Api\AuthController;
use Dispatch\Api\CategoryController;
use Dispatch\Api\ChangeController;
use Dispatch\Api\DeployController;
use Dispatch\Api\HitController;
use Dispatch\Api\OAuthClientController;
use Dispatch\Api\OAuthController;
use Dispatch\Api\OverviewController;
use Dispatch\Api\PostController;
use Dispatch\Api\PreviewController;
use Dispatch\Api\QueueController;
use Dispatch\Api\ReactionController;
use Dispatch\Api\ScheduleController;
use Dispatch\Api\ScheduleTrigger;
use Dispatch\Api\StatsController;
use Dispatch\Api\TokenController;
use Dispatch\Http\Request;
use Dispatch\Http\Router;
use Dispatch\Mcp\McpController;
use Dispatch\OAuth\AuthorizeController;
use Dispatch\OAuth\DiscoveryController;
use Dispatch\OAuth\TokenController as OAuthTokenController;

$config = require __DIR__ . '/bootstrap.php';

$request = Request::fromGlobals();

$router = new Router();
$router->add('POST', '/dispatch/api/deploy', static fn () => (new DeployController($config))->handle($request));
$router->add('POST', '/dispatch/api/categories', static fn () => (new CategoryController($config))->handle($request));
$router->add('POST', '/dispatch/api/tokens', static fn () => (new TokenController($config))->handle($request));
$router->add('GET', '/dispatch/api/tokens', static fn () => (new TokenController($config))->list($request));
$router->add('DELETE', '/dispatch/api/tokens/{id}', static fn (array $params) => (new TokenController($config))->revokeById($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/categories', static fn () => (new CategoryController($config))->list($request));
$router->add('PATCH', '/dispatch/api/categories/{id}', static fn (array $params) => (new CategoryController($config))->patch($request, (int) $params['id']));
$router->add('DELETE', '/dispatch/api/categories/{id}', static fn (array $params) => (new CategoryController($config))->delete($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/queue', static fn () => (new QueueController($config))->list($request));
$router->add('POST', '/dispatch/api/queue',static fn () => (new QueueController($config))->add($request));
$router->add('POST', '/dispatch/api/posts/{id}/reaction', static fn (array $params) => (new ReactionController())->set($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/reactions', static fn () => (new ReactionController())->list($request));
$router->add('GET', '/dispatch/api/stats', static fn () => (new StatsController())->overview($request));
$router->add('GET', '/dispatch/api/stats/posts/{id}', static fn (array $params) => (new StatsController())->post($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/hit', static fn () => (new HitController($config))->handle($request));
$router->add('POST', '/dispatch/api/auth/login', static fn () => (new AuthController())->login($request));
$router->add('POST', '/dispatch/api/auth/refresh', static fn () => (new AuthController())->refresh($request));
$router->add('POST', '/dispatch/api/auth/logout', static fn () => (new AuthController())->logout($request));
$router->add('POST', '/dispatch/api/auth/logout-all', static fn () => (new AuthController())->logoutAll($request));
$router->add('GET', '/dispatch/api/overview', static fn () => (new OverviewController())->show($request));
$router->add('GET', '/dispatch/api/posts', static fn () => (new PostController($config))->list($request));
$router->add('GET', '/dispatch/api/posts/by-slug/{slug}', static fn (array $params) => (new PostController($config))->bySlug($request, (string) $params['slug']));
$router->add('GET', '/dispatch/api/posts/{id}/preview', static fn (array $params) => (new PreviewController($config))->byLogin($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/posts/{id}/diff', static fn (array $params) => (new PostController($config))->diff($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/preview',static fn () => (new PreviewController($config))->bySignature($request));
$router->add('GET', '/dispatch/api/preview-media', static fn () => (new PreviewController($config))->media($request));
$router->add('POST', '/dispatch/api/posts/{id}/approve', static fn (array $params) => (new PostController($config))->approve($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/posts/{id}/schedule', static fn (array $params) => (new PostController($config))->schedule($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/posts/{id}/unschedule', static fn (array $params) => (new PostController($config))->unschedule($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/schedule', static fn () => (new ScheduleController($config))->show($request));
$router->add('PATCH', '/dispatch/api/schedule', static fn () => (new ScheduleController($config))->patch($request));
$router->add('POST', '/dispatch/api/posts/{id}/return', static fn (array $params) => (new PostController($config))->return($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/posts/{id}/unpublish', static fn (array $params) => (new PostController($config))->unpublish($request, (int) $params['id']));
$router->add('DELETE', '/dispatch/api/posts/{id}', static fn (array $params) => (new PostController($config))->discard($request, (int) $params['id']));
$router->add('GET', '/dispatch/api/changes', static fn () => (new ChangeController($config))->list($request));
$router->add('POST', '/dispatch/api/changes/{id}/apply', static fn (array $params) => (new ChangeController($config))->apply($request, (int) $params['id']));
$router->add('POST', '/dispatch/api/changes/{id}/reject', static fn (array $params) => (new ChangeController($config))->reject($request, (int) $params['id']));
$router->add('GET', '/.well-known/oauth-protected-resource', static fn () => (new DiscoveryController($config))->protectedResource());
$router->add('GET', '/.well-known/oauth-protected-resource/dispatch/mcp', static fn () => (new DiscoveryController($config))->protectedResource());
$router->add('GET', '/.well-known/oauth-authorization-server', static fn () => (new DiscoveryController($config))->authorizationServer());
$router->add('GET', '/dispatch/oauth/authorize', static fn () => (new AuthorizeController($config))->handle($request));
$router->add('POST', '/dispatch/oauth/token', static fn () => (new OAuthTokenController($config))->token($request));
$router->add('POST', '/dispatch/oauth/revoke', static fn () => (new OAuthTokenController($config))->revoke($request));
$router->add('GET', '/dispatch/api/oauth/requests/{slug}', static fn (array $params) => (new OAuthController($config))->request($request, (string) $params['slug']));
$router->add('POST', '/dispatch/api/oauth/consent', static fn () => (new OAuthController($config))->consent($request));
$router->add('GET', '/dispatch/api/oauth/grants', static fn () => (new OAuthController($config))->grants($request));
$router->add('DELETE', '/dispatch/api/oauth/grants/{slug}', static fn (array $params) => (new OAuthController($config))->disconnect($request, (string) $params['slug']));
$router->add('POST', '/dispatch/api/oauth-clients', static fn () => (new OAuthClientController($config))->create($request));
$router->add('GET', '/dispatch/api/oauth-clients', static fn () => (new OAuthClientController($config))->list($request));
$router->add('DELETE', '/dispatch/api/oauth-clients/{id}', static fn (array $params) => (new OAuthClientController($config))->revoke($request, (int) $params['id']));
// Der MCP-Endpunkt antwortet in JSON-RPC und prüft Origin und Anmeldung selbst.
$router->add('POST', '/dispatch/mcp', static fn () => (new McpController($config))->handle($request));

// Eingeplante Artikel erscheinen beim ersten Aufruf ab ihrem Termin (ADR-028).
ScheduleTrigger::run($config);

$router->dispatch($request);
