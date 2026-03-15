<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\GroupController;
use App\Controllers\ItemController;
use App\Controllers\LoanController;
use App\Controllers\RepairController;

$router->get('/', [AuthController::class, 'showLogin']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->get('/password/forgot', [AuthController::class, 'showForgotPassword']);
$router->post('/password/forgot', [AuthController::class, 'sendReset']);
$router->get('/password/reset', [AuthController::class, 'showResetPassword']);
$router->post('/password/reset', [AuthController::class, 'resetPassword']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/dashboard', [DashboardController::class, 'index']);

$router->get('/groups', [GroupController::class, 'index']);
$router->post('/groups/create', [GroupController::class, 'create']);
$router->post('/groups/join', [GroupController::class, 'join']);

$router->get('/items', [ItemController::class, 'index']);
$router->get('/items/new', [ItemController::class, 'createForm']);
$router->post('/items/create', [ItemController::class, 'create']);
$router->get('/items/show', [ItemController::class, 'show']);

$router->get('/loans', [LoanController::class, 'index']);
$router->post('/loans/request', [LoanController::class, 'request']);
$router->post('/loans/approve', [LoanController::class, 'approve']);
$router->post('/loans/return', [LoanController::class, 'return']);

$router->get('/repairs', [RepairController::class, 'index']);
$router->post('/repairs/create', [RepairController::class, 'create']);
$router->post('/repairs/update-status', [RepairController::class, 'updateStatus']);

$router->get('/admin', [AdminController::class, 'index']);
$router->post('/admin/categories/create', [AdminController::class, 'createCategory']);
$router->get('/admin/export/csv', [AdminController::class, 'exportCsv']);
