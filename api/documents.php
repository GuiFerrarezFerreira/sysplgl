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

/**
 * Listar documentos de uma disputa
 */
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
    
    try {
        $documents = $db->getDocumentsByDispute($disputeId);
        
        // Adicionar URL completa para download
        foreach ($documents as &$doc) {
            $doc['download_url'] = BASE_URL . '/api/documents.php?action=download&id=' . $doc['id'];
            $doc['file_size_formatted'] = formatFileSize($doc['file_size']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $documents
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar documentos']);
    }
}

/**
 * Upload de documento
 */
function handleUploadDocument($db, $auth, $fileHandler, $user) {
    // Não definir Content-Type aqui pois pode interferir com o upload
    
    // Verificar se há arquivo
    if (empty($_FILES['document'])) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nenhum arquivo enviado']);
        return;
    }
    
    // Verificar se houve erro no upload
    if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'message' => getUploadErrorMessage($_FILES['document']['error'])
        ]);
        return;
    }
    
    $disputeId = $_POST['dispute_id'] ?? null;
    
    if (!$disputeId) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da disputa é obrigatório']);
        return;
    }
    
    // Verificar permissão
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        header('Content-Type: application/json');
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
        
        // Definir header JSON apenas após o processamento do upload
        header('Content-Type: application/json');
        
        echo json_encode([
            'success' => true,
            'message' => 'Documento enviado com sucesso',
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Deletar documento
 */
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

/**
 * Download de documento (adicionar esta funcionalidade)
 */
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    handleDownloadDocument($db, $fileHandler, $user);
    exit;
}

function handleDownloadDocument($db, $fileHandler, $user) {
    $documentId = $_GET['id'] ?? null;
    
    if (!$documentId) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID do documento é obrigatório']);
        return;
    }
    
    try {
        $fileInfo = $fileHandler->downloadFile($documentId, $user['id']);
        
        // Enviar arquivo para download
        header('Content-Type: ' . $fileInfo['type']);
        header('Content-Disposition: attachment; filename="' . $fileInfo['name'] . '"');
        header('Content-Length: ' . filesize($fileInfo['path']));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        
        readfile($fileInfo['path']);
        exit;
        
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Funções auxiliares
 */
function getUploadErrorMessage($errorCode) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor',
        UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido',
        UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
        UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
        UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
        UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
        UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
    ];
    
    return $errors[$errorCode] ?? 'Erro desconhecido no upload';
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}