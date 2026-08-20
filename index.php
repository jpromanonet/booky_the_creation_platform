<?php

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$router = new Router();

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout']);

$router->get('/', [DashboardController::class, 'index']);
$router->get('/milestones', [MilestoneController::class, 'index']);

$router->get('/autores', [AuthorController::class, 'index']);
$router->get('/autores/nuevo', [AuthorController::class, 'create']);
$router->post('/autores', [AuthorController::class, 'store']);
$router->get('/autores/{id}', [AuthorController::class, 'show']);
$router->get('/autores/{id}/editar', [AuthorController::class, 'edit']);
$router->post('/autores/{id}', [AuthorController::class, 'update']);
$router->post('/autores/{id}/eliminar', [AuthorController::class, 'destroy']);

$router->get('/libros', [BookController::class, 'index']);
$router->get('/libros/nuevo', [BookController::class, 'create']);
$router->post('/libros', [BookController::class, 'store']);
$router->get('/libros/{id}', [BookController::class, 'show']);
$router->get('/libros/{id}/editar', [BookController::class, 'edit']);
$router->post('/libros/{id}', [BookController::class, 'update']);
$router->post('/libros/{id}/eliminar', [BookController::class, 'destroy']);
$router->post('/libros/{id}/outline', [BookController::class, 'uploadMilestone']);
$router->post('/libros/{id}/sinopsis', [BookController::class, 'uploadMilestone']);
$router->post('/libros/{id}/capitulos/{chapterId}', [BookController::class, 'uploadChapter']);
$router->post('/libros/{id}/capitulos/{chapterId}/pdf', [BookController::class, 'uploadChapterPdf']);
$router->post('/libros/{id}/documentos/{docId}/eliminar', [BookController::class, 'deleteDocument']);
$router->get('/libros/{id}/archivos/{docId}', [BookController::class, 'download']);

$router->get('/pdf', [PdfController::class, 'index']);
$router->get('/pdf/{id}/descargar', [PdfController::class, 'download']);

$router->get('/usuarios', [UserController::class, 'index']);
$router->get('/usuarios/nuevo', [UserController::class, 'create']);
$router->post('/usuarios', [UserController::class, 'store']);
$router->get('/usuarios/{id}/editar', [UserController::class, 'edit']);
$router->post('/usuarios/{id}', [UserController::class, 'update']);

$router->get('/configuracion', [SettingsController::class, 'index']);
$router->post('/configuracion/perfil', [SettingsController::class, 'updateProfile']);
$router->post('/configuracion/password', [SettingsController::class, 'changePassword']);

$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
