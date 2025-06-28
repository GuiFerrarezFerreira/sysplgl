<?php
/**
 * Arbitrivm - Classe de Response para API
 */

class Response {
    
    /**
     * Enviar resposta de sucesso
     */
    public function success($data = null, $message = 'Sucesso', $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
    
    /**
     * Enviar resposta de erro
     */
    public function error($message = 'Erro', $statusCode = 400, $errors = null) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'message' => $message,
            'errors' => $errors
        ]);
        exit;
    }
    
    /**
     * Enviar resposta com paginação
     */
    public function paginated($data, $total, $page, $perPage, $message = 'Sucesso') {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => intval($total),
                'per_page' => intval($perPage),
                'current_page' => intval($page),
                'total_pages' => ceil($total / $perPage),
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $total)
            ]
        ]);
        exit;
    }
    
    /**
     * Enviar arquivo para download
     */
    public function download($filePath, $fileName, $mimeType = null) {
        if (!file_exists($filePath)) {
            $this->error('Arquivo não encontrado', 404);
        }
        
        // Detectar MIME type se não fornecido
        if (!$mimeType) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
        }
        
        // Headers para download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: public');
        
        // Enviar arquivo
        readfile($filePath);
        exit;
    }
    
    /**
     * Resposta vazia (No Content)
     */
    public function noContent() {
        http_response_code(204);
        exit;
    }
    
    /**
     * Resposta de redirecionamento
     */
    public function redirect($url, $permanent = false) {
        http_response_code($permanent ? 301 : 302);
        header('Location: ' . $url);
        exit;
    }
}