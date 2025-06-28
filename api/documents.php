<?php
/**
 * Arbitrivm - Endpoint de Documentos
 * api/documents.php
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = new Database();
$auth = new Auth();
$fileHandler = new FileHandler();
$user = getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetDocuments($db, $auth, $user);
        break;
        
    case 'POST':
        handleUploadDocument($db, $auth, $fileHandler, $user);
        break;
        
    case 'DELETE':
        handleDeleteDocument($db, $fileHandler, $user);
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

function handleGetDocuments($db, $auth, $user) {
    header('Content-Type: application/json');
    
    $disputeId = $_GET['dispute_id'] ?? null;
    
    if (!$disputeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da disputa é obrigatório']);
        return;
    }
    
    // Verificar permissão
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        return;
    }
    
    $documents = $db->getDocumentsByDispute($disputeId);
    
    echo json_encode([
        'success' => true,
        'data' => $documents
    ]);
}

function handleUploadDocument($db, $auth, $fileHandler, $user) {
    header('Content-Type: application/json');
    
    if (empty($_FILES['document'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
        return;
    }
    
    $disputeId = $_POST['dispute_id'] ?? null;
    
    if (!$disputeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da disputa é obrigatório']);
        return;
    }
    
    // Verificar permissão
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        return;
    }
    
    try {
        $result = $fileHandler->uploadFile(
            $_FILES['document'],
            $disputeId,
            $user['id'],
            $_POST['document_type'] ?? 'other',
            $_POST['description'] ?? ''
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento enviado com sucesso',
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function handleDeleteDocument($db, $fileHandler, $user) {
    header('Content-Type: application/json');
    
    $documentId = $_GET['id'] ?? null;
    
    if (!$documentId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do documento é obrigatório']);
        return;
    }
    
    try {
        $fileHandler->deleteFile($documentId, $user['id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento removido com sucesso'
        ]);
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}