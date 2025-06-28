<?php
/**
 * Arbitrivm - Classe de Utilidades
 */

class Utils {
    
    /**
     * Enviar email
     */
    public static function sendEmail($to, $subject, $body, $attachments = []) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_FROM_EMAIL . '>',
            'Reply-To: ' . SMTP_FROM_EMAIL,
            'X-Mailer: PHP/' . phpversion()
        ];
        
        // Template HTML básico
        $htmlBody = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #2563eb; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background-color: #f9fafb; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
                a { color: #2563eb; text-decoration: none; }
                .button { display: inline-block; padding: 10px 20px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>Arbitrivm</h1>
                </div>
                <div class="content">
                    ' . $body . '
                </div>
                <div class="footer">
                    <p>© ' . date('Y') . ' Arbitrivm - Sistema de Arbitragem Imobiliária</p>
                    <p>Este é um email automático, não responda.</p>
                </div>
            </div>
        </body>
        </html>';
        
        // Enviar email (usando mail() nativo do PHP)
        $result = mail($to, $subject, $htmlBody, implode("\r\n", $headers));
        
        if (!$result) {
            logError("Falha ao enviar email para: $to", ['subject' => $subject]);
        }
        
        return $result;
    }
    
    /**
     * Formatar CPF
     */
    public static function formatCPF($cpf) {
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        if (strlen($cpf) != 11) return $cpf;
        
        return substr($cpf, 0, 3) . '.' . 
               substr($cpf, 3, 3) . '.' . 
               substr($cpf, 6, 3) . '-' . 
               substr($cpf, 9, 2);
    }
    
    /**
     * Formatar CNPJ
     */
    public static function formatCNPJ($cnpj) {
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        if (strlen($cnpj) != 14) return $cnpj;
        
        return substr($cnpj, 0, 2) . '.' . 
               substr($cnpj, 2, 3) . '.' . 
               substr($cnpj, 5, 3) . '/' . 
               substr($cnpj, 8, 4) . '-' . 
               substr($cnpj, 12, 2);
    }
    
    /**
     * Formatar telefone
     */
    public static function formatPhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) == 11) {
            return '(' . substr($phone, 0, 2) . ') ' . 
                   substr($phone, 2, 5) . '-' . 
                   substr($phone, 7, 4);
        } elseif (strlen($phone) == 10) {
            return '(' . substr($phone, 0, 2) . ') ' . 
                   substr($phone, 2, 4) . '-' . 
                   substr($phone, 6, 4);
        }
        
        return $phone;
    }
    
    /**
     * Gerar senha aleatória
     */
    public static function generatePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Calcular idade a partir da data de nascimento
     */
    public static function calculateAge($birthDate) {
        $birthDate = new DateTime($birthDate);
        $today = new DateTime();
        $age = $today->diff($birthDate);
        return $age->y;
    }
    
    /**
     * Formatar data relativa (ex: "há 2 horas")
     */
    public static function timeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'agora mesmo';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return "há $minutes " . ($minutes == 1 ? 'minuto' : 'minutos');
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return "há $hours " . ($hours == 1 ? 'hora' : 'horas');
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return "há $days " . ($days == 1 ? 'dia' : 'dias');
        } elseif ($diff < 2592000) {
            $weeks = floor($diff / 604800);
            return "há $weeks " . ($weeks == 1 ? 'semana' : 'semanas');
        } elseif ($diff < 31536000) {
            $months = floor($diff / 2592000);
            return "há $months " . ($months == 1 ? 'mês' : 'meses');
        } else {
            $years = floor($diff / 31536000);
            return "há $years " . ($years == 1 ? 'ano' : 'anos');
        }
    }
    
    /**
     * Limpar string para URL amigável
     */
    public static function slugify($text) {
        // Substituir acentos
        $text = iconv('UTF-8', 'ASCII//TRANSLIT', $text);
        
        // Converter para lowercase
        $text = strtolower($text);
        
        // Remover caracteres não alfanuméricos
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        
        // Remover hífens múltiplos
        $text = preg_replace('/-+/', '-', $text);
        
        // Remover hífens do início e fim
        return trim($text, '-');
    }
    
    /**
     * Validar email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validar URL
     */
    public static function isValidURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Obter IP do cliente
     */
    public static function getClientIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = explode(',', $ip)[0];
                }
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    /**
     * Truncar texto
     */
    public static function truncate($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Converter bytes para formato legível
     */
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Gerar cor aleatória (hexadecimal)
     */
    public static function randomColor() {
        return '#' . str_pad(dechex(mt_rand(0, 0xFFFFFF)), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Validar força da senha
     */
    public static function passwordStrength($password) {
        $strength = 0;
        
        if (strlen($password) >= 8) $strength++;
        if (strlen($password) >= 12) $strength++;
        if (preg_match('/[a-z]/', $password)) $strength++;
        if (preg_match('/[A-Z]/', $password)) $strength++;
        if (preg_match('/[0-9]/', $password)) $strength++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $strength++;
        
        $levels = ['Muito fraca', 'Fraca', 'Razoável', 'Boa', 'Forte', 'Muito forte'];
        
        return [
            'score' => $strength,
            'level' => $levels[$strength] ?? $levels[0],
            'percentage' => round(($strength / 6) * 100)
        ];
    }
    
    /**
     * Criar notificação push (placeholder)
     */
    public static function sendPushNotification($userId, $title, $message, $data = []) {
        // Aqui você implementaria a integração com serviço de push
        // Por enquanto, apenas log
        logError("Push notification seria enviada", [
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'data' => $data
        ]);
        
        return true;
    }
    
    /**
     * Exportar dados para CSV
     */
    public static function exportToCSV($data, $filename = 'export.csv') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // BOM para UTF-8
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]), ';');
        }
        
        // Dados
        foreach ($data as $row) {
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
}