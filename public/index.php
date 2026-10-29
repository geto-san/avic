<?php
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Avic\Controllers\ActivityController as Act;
use Avic\Controllers\AuthController as Auth;
use Avic\Controllers\ClaimController as Claim;
use Avic\Controllers\DashboardController as Dash;
use Avic\Controllers\FinancialController as Fin;
use Avic\Controllers\FnolController as Fnol;
use Avic\Controllers\PolicyController as Pol;
use Avic\Core\Env;
use Avic\Core\HttpException;
use Avic\Core\Request;
use Avic\Core\Router;
use Avic\Core\Session;
use Avic\Core\View;

// php -S: let the built-in server hand out real files (CSS, JS) from public/.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . (string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) {
        return false;
    }
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

Session::start();

$router = new Router();
$router->get('/', [Auth::class, 'home']);
$router->get('/login', [Auth::class, 'showLogin']);
$router->post('/login', [Auth::class, 'login']);
$router->get('/register', [Auth::class, 'showRegister']);
$router->post('/register', [Auth::class, 'register']);
$router->post('/logout', [Auth::class, 'logout']);

$router->get('/dashboard', [Dash::class, 'index']);
$router->get('/policies', [Pol::class, 'index']);
$router->post('/policies/link', [Pol::class, 'link']);

$router->get('/fnol', [Fnol::class, 'search']);
$router->post('/fnol/search', [Fnol::class, 'find']);
$router->get('/fnol/details', [Fnol::class, 'showDetails']);
$router->post('/fnol/details', [Fnol::class, 'saveDetails']);
$router->get('/fnol/review', [Fnol::class, 'review']);
$router->post('/fnol/submit', [Fnol::class, 'submit']);

$router->get('/claims', [Claim::class, 'index']);
$router->get('/claims/{id}', [Claim::class, 'show']);
$router->post('/claims/{id}/transition', [Claim::class, 'transition']);
$router->post('/claims/{id}/assign', [Claim::class, 'assign']);
$router->post('/claims/{id}/exposures', [Claim::class, 'addExposure']);
$router->post('/claims/{id}/documents', [Claim::class, 'upload']);
$router->get('/documents/{id}', [Claim::class, 'download']);

$router->get('/activities', [Act::class, 'index']);
$router->post('/claims/{id}/activities', [Act::class, 'create']);
$router->post('/activities/{id}/complete', [Act::class, 'complete']);
$router->post('/activities/{id}/skip', [Act::class, 'skip']);

$router->post('/claims/{id}/reserve', [Fin::class, 'reserve']);
$router->post('/claims/{id}/payments', [Fin::class, 'payment']);
$router->post('/claims/{id}/recoveries', [Fin::class, 'recovery']);

try {
    $router->dispatch(Request::capture());
} catch (HttpException $e) {
    $messages = [403 => 'You do not have access to this page.', 404 => 'That page does not exist.', 419 => $e->getMessage()];
    View::render('errors/error', ['title' => 'Error ' . $e->status, 'code' => $e->status, 'message' => $messages[$e->status] ?? 'Something went wrong.'], $e->status);
} catch (Throwable $e) {
    error_log((string)$e);
    $detail = Env::isProduction() ? null : $e->getMessage();
    View::render('errors/error', ['title' => 'Error', 'code' => 500, 'message' => 'Something went wrong on our side.', 'detail' => $detail], 500);
}
