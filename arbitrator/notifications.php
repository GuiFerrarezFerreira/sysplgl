<?php
require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';

checkUserType(['arbitrator']);

// Lógica para notificações
include 'templates/notifications.php';
?>