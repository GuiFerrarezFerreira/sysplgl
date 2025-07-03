<?php
/**
 * Arbitrivm - Logout
 */

require_once 'config.php';

// Processar logout
$auth = new Auth();
$auth->logout();

// Redirecionar para login com mensagem
redirect('login.php?logout=1');
exit;