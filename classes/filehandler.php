<?php
/**
 * Arbitrivm - Classe de Manipulação de Arquivos
 */

class FileHandler {
    private $db;
    private $uploadPath;
    private $maxFileSize;
    private $allowedTypes;
    
    public function __construct() {
        $this->db = new Database();
        $this->uploadPath = UPLOAD_PATH;
        $this->maxFileSize = MAX_FILE_SIZE;
        $this->allowedTypes = ALLOWED_FILE_TYPES;
    }
    
    /**
     * Upload de arquivo único
     */
    public function uploadFile($file, $disputeId, $userId, $documentType = 'other', $description = '') {
        // Validar arquivo
        $validation = $this->validateFile($file);
        if (!$validation['success']) {
            throw new Exception($validation['message']);
        }
        
        // Gerar nome único
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $fileName = uniqid() . '_' . time() . '.' . $extension;
        
        // Criar diretório por data
        $uploadDir = $this->uploadPath . 'documents/' . date('Y/m/');
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $filePath = $uploadDir . $fileName;
        $relativePath = str_replace($this->uploadPath, '', $filePath);
        
        // Mover arquivo
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Falha ao fazer upload do arquivo');
        }
        
        // Calcular hash para verificação
        $fileHash = hash_file('sha256', $filePath);
        
        // Salvar no banco
        $documentId = $this->db->saveDocument([
            'dispute_id' => $disputeId,
            'uploaded_by' => $userId,
            'file_name' => $file['name'],
            'file_path' => $relativePath,
            'file_size' => $file['size'],
            'file_type' => $file['type'],
            'document_type' => $documentType,
            'description' => $description,
            'hash_verification' => $fileHash,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Log do evento
        $this->db->insert('dispute_events', [
            'dispute_id' => $disputeId,
            'user_id' => $userId,
            'event_type' => 'document_uploaded',
            'description' => "Documento '{$file['name']}' foi adicionado"
        ]);
        
        return [
            'id' => $documentId,
            'file_name' => $file['name'],
            'file_size' => $file['size'],
            'file_type' => $file['type'],
            'document_type' => $documentType,
            'uploaded_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Upload de múltiplos arquivos
     */
    public function uploadMultiple($files, $disputeId, $userId, $documentType = 'other') {
        $uploaded = [];
        $errors = [];
        
        // Reorganizar array de arquivos
        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i]
            ];
            
            try {
                $result = $this->uploadFile($file, $disputeId, $userId, $documentType);
                $uploaded[] = $result;
            } catch (Exception $e) {
                $errors[] = "Erro no arquivo {$file['name']}: " . $e->getMessage();
            }
        }
        
        return [
            'uploaded' => $uploaded,
            'errors' => $errors
        ];
    }
    
    /**
     * Download de arquivo
     */
    public function downloadFile($documentId, $userId) {
        // Buscar documento
        $document = $this->db->fetchOne(
            "SELECT * FROM documents WHERE id = ?",
            [$documentId]
        );
        
        if (!$document) {
            throw new Exception('Documento não encontrado');
        }
        
        // Verificar permissão
        $auth = new Auth();
        if (!$auth->canAccessDispute($userId, $document['dispute_id'])) {
            throw new Exception('Acesso negado');
        }
        
        $filePath = $this->uploadPath . $document['file_path'];
        
        if (!file_exists($filePath)) {
            throw new Exception('Arquivo não encontrado no servidor');
        }
        
        // Verificar integridade
        $currentHash = hash_file('sha256', $filePath);
        if ($currentHash !== $document['hash_verification']) {
            throw new Exception('Falha na verificação de integridade do arquivo');
        }
        
        return [
            'path' => $filePath,
            'name' => $document['file_name'],
            'type' => $document['file_type']
        ];
    }
    
    /**
     * Deletar arquivo
     */
    public function deleteFile($documentId, $userId) {
        // Buscar documento
        $document = $this->db->fetchOne(
            "SELECT * FROM documents WHERE id = ?",
            [$documentId]
        );
        
        if (!$document) {
            throw new Exception('Documento não encontrado');
        }
        
        // Verificar permissão (apenas quem fez upload ou admin)
        $user = getCurrentUser();
        if ($document['uploaded_by'] != $userId && $user['role'] !== 'admin') {
            throw new Exception('Sem permissão para deletar este arquivo');
        }
        
        // Deletar arquivo físico
        $filePath = $this->uploadPath . $document['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        // Deletar do banco
        $this->db->delete('documents', 'id = ?', [$documentId]);
        
        // Log do evento
        $this->db->insert('dispute_events', [
            'dispute_id' => $document['dispute_id'],
            'user_id' => $userId,
            'event_type' => 'document_deleted',
            'description' => "Documento '{$document['file_name']}' foi removido"
        ]);
        
        return true;
    }
    
    /**
     * Validar arquivo
     */
    private function validateFile($file) {
        // Verificar erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'O arquivo excede o tamanho máximo permitido pelo servidor',
                UPLOAD_ERR_FORM_SIZE => 'O arquivo excede o tamanho máximo permitido',
                UPLOAD_ERR_PARTIAL => 'O arquivo foi enviado parcialmente',
                UPLOAD_ERR_NO_FILE => 'Nenhum arquivo foi enviado',
                UPLOAD_ERR_NO_TMP_DIR => 'Pasta temporária não encontrada',
                UPLOAD_ERR_CANT_WRITE => 'Falha ao gravar arquivo no disco',
                UPLOAD_ERR_EXTENSION => 'Upload bloqueado por extensão PHP'
            ];
            
            $message = $errorMessages[$file['error']] ?? 'Erro desconhecido no upload';
            return ['success' => false, 'message' => $message];
        }
        
        // Verificar tamanho
        if ($file['size'] > $this->maxFileSize) {
            $maxSizeMB = $this->maxFileSize / 1024 / 1024;
            return [
                'success' => false,
                'message' => "O arquivo excede o tamanho máximo de {$maxSizeMB}MB"
            ];
        }
        
        // Verificar tipo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedTypes)) {
            return [
                'success' => false,
                'message' => "Tipo de arquivo '{$extension}' não permitido"
            ];
        }
        
        // Verificar MIME type real
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'png' => ['image/png'],
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'gif' => ['image/gif'],
            'mp4' => ['video/mp4'],
            'avi' => ['video/x-msvideo'],
            'mov' => ['video/quicktime'],
            'mp3' => ['audio/mpeg'],
            'wav' => ['audio/wav', 'audio/x-wav']
        ];
        
        if (isset($allowedMimeTypes[$extension]) && !in_array($mimeType, $allowedMimeTypes[$extension])) {
            return [
                'success' => false,
                'message' => 'Tipo de arquivo não corresponde à extensão'
            ];
        }
        
        return ['success' => true];
    }
    
    /**
     * Gerar thumbnail para imagens
     */
    public function generateThumbnail($documentId, $width = 200, $height = 200) {
        $document = $this->db->fetchOne(
            "SELECT * FROM documents WHERE id = ?",
            [$documentId]
        );
        
        if (!$document || strpos($document['file_type'], 'image/') !== 0) {
            throw new Exception('Documento não é uma imagem');
        }
        
        $sourcePath = $this->uploadPath . $document['file_path'];
        $thumbDir = $this->uploadPath . 'thumbnails/';
        
        if (!file_exists($thumbDir)) {
            mkdir($thumbDir, 0777, true);
        }
        
        $thumbPath = $thumbDir . $documentId . '_thumb.jpg';
        
        // Criar thumbnail baseado no tipo
        $sourceImage = null;
        switch ($document['file_type']) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
        }
        
        if (!$sourceImage) {
            throw new Exception('Não foi possível processar a imagem');
        }
        
        // Obter dimensões originais
        $origWidth = imagesx($sourceImage);
        $origHeight = imagesy($sourceImage);
        
        // Calcular novas dimensões mantendo proporção
        $ratio = min($width / $origWidth, $height / $origHeight);
        $newWidth = round($origWidth * $ratio);
        $newHeight = round($origHeight * $ratio);
        
        // Criar imagem redimensionada
        $thumb = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0,
            $newWidth, $newHeight, $origWidth, $origHeight);
        
        // Salvar thumbnail
        imagejpeg($thumb, $thumbPath, 85);
        
        // Liberar memória
        imagedestroy($sourceImage);
        imagedestroy($thumb);
        
        return str_replace($this->uploadPath, '', $thumbPath);
    }
    
    /**
     * Obter informações de uso de armazenamento
     */
    public function getStorageInfo($companyId = null) {
        $sql = "SELECT 
                COUNT(*) as total_files,
                SUM(file_size) as total_size,
                AVG(file_size) as avg_size
                FROM documents d
                JOIN disputes dis ON d.dispute_id = dis.id";
        
        $params = [];
        if ($companyId) {
            $sql .= " WHERE dis.company_id = ?";
            $params[] = $companyId;
        }
        
        $info = $this->db->fetchOne($sql, $params);
        
        return [
            'total_files' => intval($info['total_files']),
            'total_size' => intval($info['total_size']),
            'total_size_mb' => round($info['total_size'] / 1024 / 1024, 2),
            'average_size_mb' => round($info['avg_size'] / 1024 / 1024, 2)
        ];
    }
}