<?php
/**
 * arbitrator/profile.php - Perfil do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];
$arbitratorId = getArbitratorId($userId);

// Processar atualizações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_profile':
            try {
                $db->beginTransaction();
                
                // Atualizar dados do usuário
                $userData = [
                    'name' => $_POST['name'],
                    'phone' => $_POST['phone'],
                    'address' => $_POST['address'],
                    'city' => $_POST['city'],
                    'state' => $_POST['state'],
                    'zip_code' => $_POST['zip_code']
                ];
                
                $db->update('users', $userData, 'id = ?', [$userId]);
                
                // Atualizar dados do árbitro
                $arbitratorData = [
                    'bio' => $_POST['bio'],
                    'experience_years' => $_POST['experience_years'],
                    'hourly_rate' => $_POST['hourly_rate'],
                    'specializations' => json_encode($_POST['specializations'] ?? []),
                    'languages' => json_encode($_POST['languages'] ?? [])
                ];
                
                $db->update('arbitrators', $arbitratorData, 'id = ?', [$arbitratorId]);
                
                $db->commit();
                $_SESSION['success'] = 'Perfil atualizado com sucesso!';
                
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['error'] = 'Erro ao atualizar perfil.';
                logError('Erro ao atualizar perfil: ' . $e->getMessage());
            }
            redirect('profile.php');
            break;
            
        case 'upload_photo':
            if (isset($_FILES['photo'])) {
                $uploadResult = uploadFile($_FILES['photo'], 'profiles/', ['jpg', 'jpeg', 'png'], 5242880);
                
                if ($uploadResult['success']) {
                    $db->update('users', ['photo_url' => $uploadResult['path']], 'id = ?', [$userId]);
                    $_SESSION['success'] = 'Foto atualizada com sucesso!';
                } else {
                    $_SESSION['error'] = $uploadResult['message'];
                }
            }
            redirect('profile.php');
            break;
            
        case 'add_certification':
            try {
                $certificationData = [
                    'arbitrator_id' => $arbitratorId,
                    'training_name' => $_POST['certification_name'],
                    'institution' => $_POST['institution'],
                    'completion_date' => $_POST['completion_date'],
                    'credits' => $_POST['credits'] ?? 0
                ];
                
                // Upload do certificado se fornecido
                if (isset($_FILES['certificate']) && $_FILES['certificate']['size'] > 0) {
                    $uploadResult = uploadFile($_FILES['certificate'], 'certifications/', ['pdf', 'jpg', 'png']);
                    if ($uploadResult['success']) {
                        $certificationData['certificate_path'] = $uploadResult['path'];
                    }
                }
                
                $db->insert('arbitrator_trainings', $certificationData);
                $_SESSION['success'] = 'Certificação adicionada com sucesso!';
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Erro ao adicionar certificação.';
                logError('Erro ao adicionar certificação: ' . $e->getMessage());
            }
            redirect('profile.php');
            break;
    }
}

// Buscar dados do perfil
$profile = $db->fetchOne("
    SELECT 
        a.*,
        u.name, u.email, u.phone, u.cpf_cnpj, u.photo_url,
        u.address, u.city, u.state, u.zip_code,
        COUNT(DISTINCT ac.id) as total_cases,
        COUNT(DISTINCT CASE WHEN ac.status = 'completed' THEN ac.id END) as completed_cases,
        AVG(ar.rating) as avg_rating,
        COUNT(DISTINCT ar.id) as total_reviews
    FROM arbitrators a
    INNER JOIN users u ON a.user_id = u.id
    LEFT JOIN arbitrator_cases ac ON a.id = ac.arbitrator_id
    LEFT JOIN arbitrator_reviews ar ON a.id = ar.arbitrator_id
    WHERE a.id = ?
    GROUP BY a.id
", [$arbitratorId]);

// Decodificar campos JSON
$profile['specializations'] = json_decode($profile['specializations'] ?? '[]', true);
$profile['languages'] = json_decode($profile['languages'] ?? '[]', true);
$profile['certifications'] = json_decode($profile['certifications'] ?? '[]', true);

// Buscar certificações/treinamentos
$trainings = $db->fetchAll("
    SELECT * FROM arbitrator_trainings 
    WHERE arbitrator_id = ? 
    ORDER BY completion_date DESC
", [$arbitratorId]);

// Buscar avaliações recentes
$recentReviews = $db->fetchAll("
    SELECT 
        ar.*,
        d.case_number,
        CASE WHEN ar.is_anonymous = 1 THEN 'Anônimo' ELSE u.name END as reviewer_name
    FROM arbitrator_reviews ar
    INNER JOIN disputes d ON ar.dispute_id = d.id
    LEFT JOIN users u ON ar.reviewer_id = u.id
    WHERE ar.arbitrator_id = ?
    ORDER BY ar.created_at DESC
    LIMIT 5
", [$arbitratorId]);

// Buscar estatísticas de desempenho
$performance = $db->fetchOne("
    SELECT 
        AVG(punctuality) as avg_punctuality,
        AVG(professionalism) as avg_professionalism,
        AVG(communication) as avg_communication,
        AVG(impartiality) as avg_impartiality
    FROM arbitrator_reviews
    WHERE arbitrator_id = ?
", [$arbitratorId]);

// Lista de especializações disponíveis
$availableSpecializations = [
    'property_sale' => 'Compra e Venda',
    'rental' => 'Locação',
    'construction' => 'Construção Civil',
    'condominium' => 'Condomínios',
    'real_estate' => 'Incorporação Imobiliária',
    'property_management' => 'Administração de Imóveis',
    'commercial' => 'Imóveis Comerciais',
    'rural' => 'Imóveis Rurais'
];

// Lista de idiomas
$availableLanguages = [
    'pt' => 'Português',
    'en' => 'Inglês',
    'es' => 'Espanhol',
    'fr' => 'Francês',
    'it' => 'Italiano',
    'de' => 'Alemão',
    'zh' => 'Mandarim',
    'ja' => 'Japonês'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meu Perfil - Arbitrivm</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-header {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: bold;
            color: #2563eb;
            position: relative;
            overflow: hidden;
        }
        
        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .photo-upload {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.7);
            color: white;
            text-align: center;
            padding: 0.5rem;
            cursor: pointer;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .profile-photo:hover .photo-upload {
            opacity: 1;
        }
        
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .stat-box {
            background: rgba(255,255,255,0.1);
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            display: block;
        }
        
        .stat-label {
            font-size: 0.875rem;
            opacity: 0.9;
        }
        
        .content-section {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
        }
        
        .rating-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .stars {
            color: #f59e0b;
        }
        
        .performance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .performance-item {
            text-align: center;
        }
        
        .performance-bar {
            width: 100%;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .performance-fill {
            height: 100%;
            background: #2563eb;
            border-radius: 4px;
            transition: width 0.3s;
        }
        
        .certification-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .certification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        
        .review-item {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        .close-modal:hover {
            color: #374151;
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-container">
            <div class="profile-info">
                <div class="profile-photo">
                    <?php if ($profile['photo_url']): ?>
                        <img src="<?php echo htmlspecialchars($profile['photo_url']); ?>" alt="Foto do perfil">
                    <?php else: ?>
                        <?php echo strtoupper(substr($profile['name'], 0, 1)); ?>
                    <?php endif; ?>
                    <form action="profile.php" method="POST" enctype="multipart/form-data" id="photoForm">
                        <input type="hidden" name="action" value="upload_photo">
                        <label class="photo-upload">
                            <input type="file" name="photo" accept="image/*" style="display: none;" onchange="document.getElementById('photoForm').submit()">
                            <span>Alterar foto</span>
                        </label>
                    </form>
                </div>
                <div>
                    <h1><?php echo htmlspecialchars($profile['name']); ?></h1>
                    <p>Árbitro <?php echo $profile['is_verified'] ? 'Verificado' : 'Em Verificação'; ?></p>
                    <p>Registro: <?php echo htmlspecialchars($profile['registration_number'] ?? 'Pendente'); ?></p>
                    <div class="rating-display">
                        <span class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php if ($i <= round($profile['avg_rating'] ?? 0)): ?>★<?php else: ?>☆<?php endif; ?>
                            <?php endfor; ?>
                        </span>
                        <span><?php echo number_format($profile['avg_rating'] ?? 0, 1); ?> (<?php echo $profile['total_reviews']; ?> avaliações)</span>
                    </div>
                </div>
            </div>
            
            <div class="profile-stats">
                <div class="stat-box">
                    <span class="stat-value"><?php echo $profile['total_cases']; ?></span>
                    <span class="stat-label">Casos Total</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value"><?php echo $profile['completed_cases']; ?></span>
                    <span class="stat-label">Concluídos</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value"><?php echo $profile['experience_years']; ?></span>
                    <span class="stat-label">Anos de Experiência</span>
                </div>
                <div class="stat-box">
                    <span class="stat-value">R$ <?php echo number_format($profile['hourly_rate'], 2, ',', '.'); ?></span>
                    <span class="stat-label">Valor/Hora</span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="profile-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Informações Pessoais -->
        <div class="content-section">
            <div class="section-header">
                <h2>Informações Pessoais</h2>
                <button type="button" class="btn btn-primary" onclick="toggleEdit('personal')">Editar</button>
            </div>
            
            <form action="profile.php" method="POST" id="personalForm" style="display: none;">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Nome Completo</label>
                        <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($profile['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($profile['phone']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>CPF/CNPJ</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['cpf_cnpj']); ?>" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Endereço</label>
                        <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Cidade</label>
                        <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Estado</label>
                        <select name="state" class="form-control">
                            <option value="">Selecione...</option>
                            <?php foreach (getBrazilianStates() as $uf => $state): ?>
                                <option value="<?php echo $uf; ?>" <?php echo $profile['state'] == $uf ? 'selected' : ''; ?>>
                                    <?php echo $state; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text" name="zip_code" class="form-control" value="<?php echo htmlspecialchars($profile['zip_code'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleEdit('personal')">Cancelar</button>
                </div>
            </form>
            
            <div id="personalInfo">
                <div class="form-grid">
                    <div>
                        <strong>Email:</strong> <?php echo htmlspecialchars($profile['email']); ?>
                    </div>
                    <div>
                        <strong>Telefone:</strong> <?php echo htmlspecialchars($profile['phone']); ?>
                    </div>
                    <div>
                        <strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($profile['cpf_cnpj']); ?>
                    </div>
                    <div>
                        <strong>Endereço:</strong> 
                        <?php 
                        $address = [];
                        if ($profile['address']) $address[] = $profile['address'];
                        if ($profile['city']) $address[] = $profile['city'];
                        if ($profile['state']) $address[] = $profile['state'];
                        if ($profile['zip_code']) $address[] = 'CEP ' . $profile['zip_code'];
                        echo htmlspecialchars(implode(', ', $address) ?: 'Não informado');
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informações Profissionais -->
        <div class="content-section">
            <div class="section-header">
                <h2>Informações Profissionais</h2>
                <button type="button" class="btn btn-primary" onclick="toggleEdit('professional')">Editar</button>
            </div>
            
            <form action="profile.php" method="POST" id="professionalForm" style="display: none;">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label>Anos de Experiência</label>
                        <input type="number" name="experience_years" class="form-control" value="<?php echo $profile['experience_years']; ?>" min="0">
                    </div>
                    
                    <div class="form-group">
                        <label>Valor por Hora (R$)</label>
                        <input type="number" name="hourly_rate" class="form-control" value="<?php echo $profile['hourly_rate']; ?>" min="0" step="0.01">
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Biografia / Apresentação</label>
                        <textarea name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($profile['bio'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Especializações</label>
                        <div class="checkbox-group">
                            <?php foreach ($availableSpecializations as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="specializations[]" value="<?php echo $key; ?>" 
                                           <?php echo in_array($key, $profile['specializations'] ?? []) ? 'checked' : ''; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group full-width">
                        <label>Idiomas</label>
                        <div class="checkbox-group">
                            <?php foreach ($availableLanguages as $key => $label): ?>
                                <label>
                                    <input type="checkbox" name="languages[]" value="<?php echo $key; ?>" 
                                           <?php echo in_array($key, $profile['languages'] ?? []) ? 'checked' : ''; ?>>
                                    <?php echo $label; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                    <button type="button" class="btn btn-secondary" onclick="toggleEdit('professional')">Cancelar</button>
                </div>
            </form>
            
            <div id="professionalInfo">
                <div class="form-grid">
                    <div>
                        <strong>Experiência:</strong> <?php echo $profile['experience_years']; ?> anos
                    </div>
                    <div>
                        <strong>Valor/Hora:</strong> R$ <?php echo number_format($profile['hourly_rate'], 2, ',', '.'); ?>
                    </div>
                    <div class="full-width">
                        <strong>Especializações:</strong> 
                        <?php 
                        $specs = array_map(function($s) use ($availableSpecializations) {
                            return $availableSpecializations[$s] ?? $s;
                        }, $profile['specializations'] ?? []);
                        echo htmlspecialchars(implode(', ', $specs) ?: 'Nenhuma');
                        ?>
                    </div>
                    <div class="full-width">
                        <strong>Idiomas:</strong> 
                        <?php 
                        $langs = array_map(function($l) use ($availableLanguages) {
                            return $availableLanguages[$l] ?? $l;
                        }, $profile['languages'] ?? []);
                        echo htmlspecialchars(implode(', ', $langs) ?: 'Português');
                        ?>
                    </div>
                    <?php if ($profile['bio']): ?>
                    <div class="full-width">
                        <strong>Biografia:</strong><br>
                        <?php echo nl2br(htmlspecialchars($profile['bio'])); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Certificações e Treinamentos -->
        <div class="content-section">
            <div class="section-header">
                <h2>Certificações e Treinamentos</h2>
                <button type="button" class="btn btn-primary" onclick="showModal('certificationModal')">Adicionar</button>
            </div>
            
            <div class="certification-list">
                <?php if (empty($trainings)): ?>
                    <p class="text-muted">Nenhuma certificação cadastrada.</p>
                <?php else: ?>
                    <?php foreach ($trainings as $training): ?>
                        <div class="certification-item">
                            <div>
                                <strong><?php echo htmlspecialchars($training['training_name']); ?></strong><br>
                                <span class="text-muted">
                                    <?php echo htmlspecialchars($training['institution']); ?> - 
                                    <?php echo date('m/Y', strtotime($training['completion_date'])); ?>
                                    <?php if ($training['credits'] > 0): ?>
                                        (<?php echo $training['credits']; ?> créditos)
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <?php if ($training['verified']): ?>
                                    <span class="badge badge-success">Verificado</span>
                                <?php else: ?>
                                    <span class="badge badge-warning">Pendente</span>
                                <?php endif; ?>
                                <?php if ($training['certificate_path']): ?>
                                    <a href="<?php echo htmlspecialchars($training['certificate_path']); ?>" target="_blank" class="btn btn-sm btn-secondary">Ver Certificado</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Desempenho -->
        <div class="content-section">
            <div class="section-header">
                <h2>Indicadores de Desempenho</h2>
            </div>
            
            <div class="performance-grid">
                <div class="performance-item">
                    <strong>Pontualidade</strong>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?php echo ($performance['avg_punctuality'] ?? 0) * 20; ?>%"></div>
                    </div>
                    <span><?php echo number_format($performance['avg_punctuality'] ?? 0, 1); ?>/5.0</span>
                </div>
                
                <div class="performance-item">
                    <strong>Profissionalismo</strong>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?php echo ($performance['avg_professionalism'] ?? 0) * 20; ?>%"></div>
                    </div>
                    <span><?php echo number_format($performance['avg_professionalism'] ?? 0, 1); ?>/5.0</span>
                </div>
                
                <div class="performance-item">
                    <strong>Comunicação</strong>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?php echo ($performance['avg_communication'] ?? 0) * 20; ?>%"></div>
                    </div>
                    <span><?php echo number_format($performance['avg_communication'] ?? 0, 1); ?>/5.0</span>
                </div>
                
                <div class="performance-item">
                    <strong>Imparcialidade</strong>
                    <div class="performance-bar">
                        <div class="performance-fill" style="width: <?php echo ($performance['avg_impartiality'] ?? 0) * 20; ?>%"></div>
                    </div>
                    <span><?php echo number_format($performance['avg_impartiality'] ?? 0, 1); ?>/5.0</span>
                </div>
            </div>
        </div>
        
        <!-- Avaliações Recentes -->
        <div class="content-section">
            <div class="section-header">
                <h2>Avaliações Recentes</h2>
                <a href="reviews.php" class="btn btn-secondary">Ver Todas</a>
            </div>
            
            <?php if (empty($recentReviews)): ?>
                <p class="text-muted">Nenhuma avaliação recebida ainda.</p>
            <?php else: ?>
                <?php foreach ($recentReviews as $review): ?>
                    <div class="review-item">
                        <div class="review-header">
                            <div>
                                <strong>Caso <?php echo htmlspecialchars($review['case_number']); ?></strong>
                                <span class="stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $review['rating']): ?>★<?php else: ?>☆<?php endif; ?>
                                    <?php endfor; ?>
                                </span>
                            </div>
                            <span class="text-muted"><?php echo date('d/m/Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        <?php if ($review['review_text']): ?>
                            <p><?php echo htmlspecialchars($review['review_text']); ?></p>
                        <?php endif; ?>
                        <small class="text-muted">Por: <?php echo htmlspecialchars($review['reviewer_name']); ?></small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal de Certificação -->
    <div id="certificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Adicionar Certificação</h3>
                <button type="button" class="close-modal" onclick="hideModal('certificationModal')">&times;</button>
            </div>
            
            <form action="profile.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_certification">
                
                <div class="form-group">
                    <label>Nome do Curso/Certificação *</label>
                    <input type="text" name="certification_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Instituição *</label>
                    <input type="text" name="institution" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Data de Conclusão *</label>
                    <input type="date" name="completion_date" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Créditos/Horas</label>
                    <input type="number" name="credits" class="form-control" min="0">
                </div>
                
                <div class="form-group">
                    <label>Certificado (PDF, JPG, PNG)</label>
                    <input type="file" name="certificate" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Adicionar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('certificationModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
        function toggleEdit(section) {
            const form = document.getElementById(section + 'Form');
            const info = document.getElementById(section + 'Info');
            
            if (form.style.display === 'none') {
                form.style.display = 'block';
                info.style.display = 'none';
            } else {
                form.style.display = 'none';
                info.style.display = 'block';
            }
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>