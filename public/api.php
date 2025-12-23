<?php
declare(strict_types=1);

/**
 * API Endpoint
 * 
 * RESTful API for programmatic access
 */

// Load autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Bot;
use App\Api\ApiController;

// Set JSON header
header('Content-Type: application/json');

// Only allow API methods
$allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, $allowedMethods)) {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get API key from header
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

if (empty($apiKey)) {
    http_response_code(401);
    echo json_encode(['error' => 'API key required']);
    exit;
}

// Get endpoint
$endpoint = $_SERVER['PATH_INFO'] ?? '/';

// Get request data
$data = [];
if ($method === 'POST' || $method === 'PUT') {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true) ?? [];
} else {
    $data = $_GET;
}

// Initialize bot and API controller
try {
    $bot = new Bot();
    $apiController = new ApiController($bot->getContainer());
    
    $response = $apiController->handle($method, $endpoint, $data, $apiKey);
    
    http_response_code($response['code'] ?? 200);
    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
