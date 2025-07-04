<?php
/**
 * Arbitrivm - Cadastro de Árbitros
 * register-arbitrator.php
 */

require_once 'config.php';

// Se já estiver logado como árbitro, redirecionar
if (isLoggedIn() && getCurrentUser()['role'] === 'arbitrator') {
    redirect('index.php');
    exit;
}

$errors = [];
$step = $_POST['step'] ?? 1;

// Processar formulário se for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'register') {
        // Validar CSRF
        if (!validateCSRF($_POST['csrf_token'] ?? '')) {
            $errors[] = 'Token de segurança inválido.';
        } else {
            // Coletar e validar dados
            $arbitratorData = [
                // Dados pessoais
                'first_name' => sanitizeInput($_POST['firstName'] ?? ''),
                'last_name' => sanitizeInput($_POST['lastName'] ?? ''),
                'cpf' => preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? ''),
                'email' => sanitizeInput($_POST['email'] ?? ''),
                'phone' => sanitizeInput($_POST['phone'] ?? ''),
                'birth_date' => $_POST['birthDate'] ?? '',
                'gender' => $_POST['gender'] ?? '',
                'address' => sanitizeInput($_POST['address'] ?? ''),
                'city' => sanitizeInput($_POST['city'] ?? ''),
                'state' => $_POST['state'] ?? '',
                'zip_code' => preg_replace('/[^0-9]/', '', $_POST['zipCode'] ?? ''),
                
                // Qualificações
                'education_level' => $_POST['educationLevel'] ?? '',
                'course_name' => sanitizeInput($_POST['courseName'] ?? ''),
                'institution' => sanitizeInput($_POST['institution'] ?? ''),
                'graduation_year' => intval($_POST['graduationYear'] ?? 0),
                'registration_type' => $_POST['registrationType'] ?? '',
                'registration_number' => sanitizeInput($_POST['registrationNumber'] ?? ''),
                'experience_years' => intval($_POST['experienceYears'] ?? 0),
                'specializations' => $_POST['specializations'] ?? [],
                'previous_cases' => intval($_POST['previousCases'] ?? 0),
                'experience_summary' => sanitizeInput($_POST['experienceSummary'] ?? ''),
                
                // Honorários e disponibilidade
                'hourly_rate' => floatval($_POST['hourlyRate'] ?? 0),
                'weekly_availability' => $_POST['weeklyAvailability'] ?? '',
                'communication_preferences' => $_POST['communication'] ?? [],
                
                // Senha
                'password' => $_POST['password'] ?? '',
                'password_confirm' => $_POST['passwordConfirm'] ?? ''
            ];
            
            // Validações
            if (empty($arbitratorData['first_name']) || empty($arbitratorData['last_name'])) {
                $errors[] = 'Nome completo é obrigatório.';
            }
            
            if (!filter_var($arbitratorData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email inválido.';
            }
            
            if (!validateCPF($arbitratorData['cpf'])) {
                $errors[] = 'CPF inválido.';
            }
            
            if ($arbitratorData['password'] !== $arbitratorData['password_confirm']) {
                $errors[] = 'As senhas não coincidem.';
            }
            
            if (strlen($arbitratorData['password']) < PASSWORD_MIN_LENGTH) {
                $errors[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
            }
            
            if ($arbitratorData['experience_years'] < 0) {
                $errors[] = 'Anos de experiência inválido.';
            }
            
            if (empty($arbitratorData['specializations'])) {
                $errors[] = 'Selecione pelo menos uma área de especialização.';
            }
            
            // Se não houver erros, processar o cadastro
            if (empty($errors)) {
                $db = new Database();
                $auth = new Auth();
                
                try {
                    // Verificar se email já existe
                    if ($db->getUserByEmail($arbitratorData['email'])) {
                        $errors[] = 'Este email já está cadastrado.';
                    } else {
                        // Iniciar transação
                        $db->getConnection()->beginTransaction();
                        
                        // Criar usuário
                        $userData = [
                            'email' => $arbitratorData['email'],
                            'password' => $arbitratorData['password'],
                            'first_name' => $arbitratorData['first_name'],
                            'last_name' => $arbitratorData['last_name'],
                            'cpf' => $arbitratorData['cpf'],
                            'phone' => $arbitratorData['phone'],
                            'role' => 'arbitrator',
                            'arbitrator_data' => [
                                'registration_number' => generateArbitratorRegistration(),
                                'specializations' => json_encode($arbitratorData['specializations']),
                                'bio' => $arbitratorData['experience_summary'],
                                'experience_years' => $arbitratorData['experience_years'],
                                'hourly_rate' => $arbitratorData['hourly_rate'],
                                'education_level' => $arbitratorData['education_level'],
                                'course_name' => $arbitratorData['course_name'],
                                'institution' => $arbitratorData['institution'],
                                'graduation_year' => $arbitratorData['graduation_year'],
                                'professional_registration' => $arbitratorData['registration_type'] . ' ' . $arbitratorData['registration_number'],
                                'previous_cases' => $arbitratorData['previous_cases'],
                                'weekly_availability' => $arbitratorData['weekly_availability'],
                                'communication_preferences' => json_encode($arbitratorData['communication_preferences']),
                                'documents_verified' => 0, // Será verificado manualmente
                                'is_available' => 1
                            ]
                        ];
                        
                        // Registrar usuário e árbitro
                        $result = $auth->register($userData);
                        
                        if ($result['success']) {
                            $userId = $result['user_id'];
                            
                            // Processar upload de documentos
                            $fileHandler = new FileHandler();
                            $documentsUploaded = true;
                            
                            // Lista de documentos obrigatórios
                            $requiredDocs = [
                                'docIdentity' => 'Documento de Identidade',
                                'docCPF' => 'CPF',
                                'docAddress' => 'Comprovante de Endereço',
                                'docDiploma' => 'Diploma'
                            ];
                            
                            foreach ($requiredDocs as $fieldName => $docType) {
                                if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                                    try {
                                        // Criar diretório específico para documentos de árbitros
                                        $uploadDir = UPLOAD_PATH . 'arbitrators/' . $userId . '/';
                                        if (!file_exists($uploadDir)) {
                                            mkdir($uploadDir, 0777, true);
                                        }
                                        
                                        // Upload do arquivo
                                        $fileResult = $fileHandler->uploadFile(
                                            $_FILES[$fieldName],
                                            null, // Sem dispute_id
                                            $userId,
                                            'arbitrator_document',
                                            $docType
                                        );
                                        
                                        // Salvar referência do documento
                                        $db->insert('arbitrator_documents', [
                                            'arbitrator_id' => $db->fetchOne(
                                                "SELECT id FROM arbitrators WHERE user_id = ?", 
                                                [$userId]
                                            )['id'],
                                            'document_type' => $docType,
                                            'file_path' => $fileResult['file_path'],
                                            'file_name' => $fileResult['file_name'],
                                            'verified' => 0,
                                            'created_at' => date('Y-m-d H:i:s')
                                        ]);
                                        
                                    } catch (Exception $e) {
                                        $documentsUploaded = false;
                                        logError('Erro no upload de documento: ' . $e->getMessage());
                                    }
                                } else {
                                    $documentsUploaded = false;
                                }
                            }
                            
                            // Processar documentos opcionais
                            $optionalDocs = [
                                'docProfessional' => 'Registro Profissional',
                                'docCertificates' => 'Certificados',
                                'docCV' => 'Currículo'
                            ];
                            
                            foreach ($optionalDocs as $fieldName => $docType) {
                                if (isset($_FILES[$fieldName]) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
                                    // Processar upload opcional...
                                }
                            }
                            
                            // Commit da transação
                            $db->getConnection()->commit();
                            
                            // Enviar email de boas-vindas
                            sendArbitratorWelcomeEmail($arbitratorData['email'], $arbitratorData['first_name']);
                            
                            // Notificar administradores
                            notifyAdminsNewArbitrator($userId);
                            
                            // Redirecionar para página de sucesso
                            $_SESSION['success'] = 'Cadastro realizado com sucesso! Seus documentos serão analisados e você receberá um email de confirmação.';
                            redirect('arbitrator-success.php');
                            exit;
                            
                        } else {
                            $db->getConnection()->rollBack();
                            $errors[] = $result['message'];
                        }
                    }
                    
                } catch (Exception $e) {
                    $db->getConnection()->rollBack();
                    $errors[] = 'Erro ao processar cadastro. Por favor, tente novamente.';
                    logError('Erro no cadastro de árbitro: ' . $e->getMessage());
                }
            }
        }
    }
}

// Funções auxiliares
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) {
        return false;
    }
    
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    
    for ($t = 9; $t < 11; $t++) {
        for ($d = 0, $c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) {
            return false;
        }
    }
    
    return true;
}

function generateArbitratorRegistration() {
    // Gerar número de registro único
    $prefix = 'ARB';
    $year = date('Y');
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $prefix . $year . $random;
}

function sendArbitratorWelcomeEmail($email, $name) {
    $subject = "Bem-vindo ao Arbitrivm - Cadastro de Árbitro";
    $message = "
        <h2>Olá, $name!</h2>
        <p>Seu cadastro como árbitro foi recebido com sucesso.</p>
        <p>Nosso time analisará seus documentos e qualificações em até 5 dias úteis.</p>
        <p>Você receberá um email assim que seu cadastro for aprovado.</p>
        <p>Enquanto isso, que tal conhecer mais sobre o Arbitrivm?</p>
        <p><a href='" . BASE_URL . "/about-arbitrators'>Saiba mais sobre ser um árbitro</a></p>
    ";
    
    Utils::sendEmail($email, $subject, $message);
}

function notifyAdminsNewArbitrator($userId) {
    $db = new Database();
    
    // Buscar administradores
    $admins = $db->fetchAll("SELECT id, email FROM users WHERE role = 'admin'");
    
    foreach ($admins as $admin) {
        $db->createNotification(
            $admin['id'],
            'new_arbitrator',
            'Novo Árbitro Cadastrado',
            'Um novo árbitro se cadastrou e aguarda aprovação de documentos.',
            ['arbitrator_user_id' => $userId]
        );
    }
}

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de Árbitro - Arbitrivm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/arbitrator-register.css">
</head>
<body>
    <!-- O HTML seria o mesmo do artifact anterior, mas processado pelo PHP -->
    
    <?php if (!empty($errors)): ?>
    <div class="error-container">
        <h3>Erros encontrados:</h3>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <h1>Seja um Árbitro no Arbitrivm</h1>
            <p>Ajude a resolver disputas imobiliárias de forma justa e eficiente</p>
        </div>
    </div>

    <div class="container">
        <!-- Progress Bar -->
        <div class="progress-bar">
            <div class="progress-step active" id="step1"></div>
            <div class="progress-step" id="step2"></div>
            <div class="progress-step" id="step3"></div>
            <div class="progress-step" id="step4"></div>
        </div>

        <form id="arbitratorForm" class="form-card">
            <!-- Step 1: Informações Pessoais -->
            <div class="form-step" id="step-1">
                <div class="form-header">
                    <h2>Informações Pessoais</h2>
                </div>
                <div class="form-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nome Completo <span class="required">*</span></label>
                            <input type="text" class="form-control" name="fullName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CPF <span class="required">*</span></label>
                            <input type="text" class="form-control" name="cpf" maxlength="14" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Telefone <span class="required">*</span></label>
                            <input type="tel" class="form-control" name="phone" maxlength="15" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Data de Nascimento <span class="required">*</span></label>
                            <input type="date" class="form-control" name="birthDate" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Gênero</label>
                            <select class="form-control form-select" name="gender">
                                <option value="">Selecione...</option>
                                <option value="M">Masculino</option>
                                <option value="F">Feminino</option>
                                <option value="O">Outro</option>
                                <option value="N">Prefiro não informar</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Endereço Completo <span class="required">*</span></label>
                        <input type="text" class="form-control" name="address" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Cidade <span class="required">*</span></label>
                            <input type="text" class="form-control" name="city" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado <span class="required">*</span></label>
                            <select class="form-control form-select" name="state" required>
                                <option value="">Selecione...</option>
                                <option value="AC">Acre</option>
                                <option value="AL">Alagoas</option>
                                <option value="AP">Amapá</option>
                                <option value="AM">Amazonas</option>
                                <option value="BA">Bahia</option>
                                <option value="CE">Ceará</option>
                                <option value="DF">Distrito Federal</option>
                                <option value="ES">Espírito Santo</option>
                                <option value="GO">Goiás</option>
                                <option value="MA">Maranhão</option>
                                <option value="MT">Mato Grosso</option>
                                <option value="MS">Mato Grosso do Sul</option>
                                <option value="MG">Minas Gerais</option>
                                <option value="PA">Pará</option>
                                <option value="PB">Paraíba</option>
                                <option value="PR">Paraná</option>
                                <option value="PE">Pernambuco</option>
                                <option value="PI">Piauí</option>
                                <option value="RJ">Rio de Janeiro</option>
                                <option value="RN">Rio Grande do Norte</option>
                                <option value="RS">Rio Grande do Sul</option>
                                <option value="RO">Rondônia</option>
                                <option value="RR">Roraima</option>
                                <option value="SC">Santa Catarina</option>
                                <option value="SP">São Paulo</option>
                                <option value="SE">Sergipe</option>
                                <option value="TO">Tocantins</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">CEP <span class="required">*</span></label>
                            <input type="text" class="form-control" name="zipCode" maxlength="9" required>
                        </div>
                    </div>

                    <div class="form-footer">
                        <a href="login.php" class="btn btn-secondary">Já tenho conta</a>
                        <button type="button" class="btn btn-primary" onclick="nextStep(2)">
                            Próximo
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Qualificações Profissionais -->
            <div class="form-step" id="step-2" style="display: none;">
                <div class="form-header">
                    <h2>Qualificações Profissionais</h2>
                </div>
                <div class="form-body">
                    <div class="info-box">
                        <strong>Importante:</strong> Para ser um árbitro no Arbitrivm, você deve ter formação jurídica ou experiência comprovada em mediação/arbitragem.
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Formação Acadêmica</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Nível de Formação <span class="required">*</span></label>
                            <select class="form-control form-select" name="educationLevel" required>
                                <option value="">Selecione...</option>
                                <option value="graduation">Graduação</option>
                                <option value="specialization">Especialização</option>
                                <option value="masters">Mestrado</option>
                                <option value="doctorate">Doutorado</option>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Curso <span class="required">*</span></label>
                                <input type="text" class="form-control" name="courseName" placeholder="Ex: Direito" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Instituição <span class="required">*</span></label>
                                <input type="text" class="form-control" name="institution" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Ano de Conclusão <span class="required">*</span></label>
                                <input type="number" class="form-control" name="graduationYear" min="1950" max="2024" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Registro Profissional</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tipo de Registro</label>
                                <select class="form-control form-select" name="registrationType">
                                    <option value="">Selecione...</option>
                                    <option value="OAB">OAB - Ordem dos Advogados do Brasil</option>
                                    <option value="CRA">CRA - Conselho Regional de Administração</option>
                                    <option value="CRECI">CRECI - Conselho Regional de Corretores de Imóveis</option>
                                    <option value="CAU">CAU - Conselho de Arquitetura e Urbanismo</option>
                                    <option value="CREA">CREA - Conselho Regional de Engenharia</option>
                                    <option value="OTHER">Outro</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Número do Registro</label>
                                <input type="text" class="form-control" name="registrationNumber">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Experiência em Arbitragem</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Anos de Experiência <span class="required">*</span></label>
                            <input type="number" class="form-control" name="experienceYears" min="0" max="50" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Áreas de Especialização <span class="required">*</span></label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec1" name="specializations[]" value="rental">
                                    <label for="spec1">Locação Residencial</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec2" name="specializations[]" value="commercial">
                                    <label for="spec2">Locação Comercial</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec3" name="specializations[]" value="sale">
                                    <label for="spec3">Compra e Venda</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec4" name="specializations[]" value="condo">
                                    <label for="spec4">Questões Condominiais</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec5" name="specializations[]" value="construction">
                                    <label for="spec5">Construção Civil</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="spec6" name="specializations[]" value="rural">
                                    <label for="spec6">Imóveis Rurais</label>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Casos Arbitrados (aproximadamente)</label>
                            <input type="number" class="form-control" name="previousCases" min="0" placeholder="0">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Resumo da Experiência <span class="required">*</span></label>
                            <textarea class="form-control" name="experienceSummary" rows="4" required 
                                      placeholder="Descreva brevemente sua experiência em arbitragem e mediação..."></textarea>
                        </div>
                    </div>

                    <div class="form-footer">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(1)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(3)">
                            Próximo
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Documentação -->
            <div class="form-step" id="step-3" style="display: none;">
                <div class="form-header">
                    <h2>Documentação</h2>
                </div>
                <div class="form-body">
                    <div class="info-box">
                        <strong>Documentos Necessários:</strong> Por favor, faça upload dos documentos solicitados em formato PDF, JPG ou PNG (máx. 5MB cada).
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Documentos Obrigatórios</h3>
                        
                        <div class="form-group">
                            <label class="form-label">RG ou CNH <span class="required">*</span></label>
                            <div class="file-upload" onclick="document.getElementById('docIdentity').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar documento de identidade</p>
                            </div>
                            <input type="file" id="docIdentity" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">CPF <span class="required">*</span></label>
                            <div class="file-upload" onclick="document.getElementById('docCPF').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar CPF</p>
                            </div>
                            <input type="file" id="docCPF" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Comprovante de Endereço <span class="required">*</span></label>
                            <div class="file-upload" onclick="document.getElementById('docAddress').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar comprovante (últimos 3 meses)</p>
                            </div>
                            <input type="file" id="docAddress" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Diploma ou Certificado <span class="required">*</span></label>
                            <div class="file-upload" onclick="document.getElementById('docDiploma').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar diploma de graduação</p>
                            </div>
                            <input type="file" id="docDiploma" accept=".pdf,.jpg,.jpeg,.png" style="display: none;" required>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Documentos Opcionais</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Registro Profissional (OAB, CRA, etc)</label>
                            <div class="file-upload" onclick="document.getElementById('docProfessional').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar registro profissional</p>
                            </div>
                            <input type="file" id="docProfessional" accept=".pdf,.jpg,.jpeg,.png" style="display: none;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Certificados de Cursos de Arbitragem</label>
                            <div class="file-upload" onclick="document.getElementById('docCertificates').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar certificados (pode enviar múltiplos)</p>
                            </div>
                            <input type="file" id="docCertificates" accept=".pdf,.jpg,.jpeg,.png" multiple style="display: none;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Currículo Profissional</label>
                            <div class="file-upload" onclick="document.getElementById('docCV').click()">
                                <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                </svg>
                                <p class="file-upload-text">Clique para enviar seu CV</p>
                            </div>
                            <input type="file" id="docCV" accept=".pdf,.doc,.docx" style="display: none;">
                        </div>
                    </div>

                    <div class="file-list" id="fileList">
                        <!-- Arquivos selecionados aparecerão aqui -->
                    </div>

                    <div class="form-footer">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(2)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </button>
                        <button type="button" class="btn btn-primary" onclick="nextStep(4)">
                            Próximo
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Termos e Finalização -->
            <div class="form-step" id="step-4" style="display: none;">
                <div class="form-header">
                    <h2>Termos e Condições</h2>
                </div>
                <div class="form-body">
                    <div class="form-section">
                        <h3 class="section-title">Honorários e Disponibilidade</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Valor por Hora (R$) <span class="required">*</span></label>
                                <input type="number" class="form-control" name="hourlyRate" min="50" step="10" required 
                                       placeholder="Ex: 200">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Disponibilidade Semanal <span class="required">*</span></label>
                                <select class="form-control form-select" name="weeklyAvailability" required>
                                    <option value="">Selecione...</option>
                                    <option value="5">Até 5 horas</option>
                                    <option value="10">5 a 10 horas</option>
                                    <option value="20">10 a 20 horas</option>
                                    <option value="40">20 a 40 horas</option>
                                    <option value="40+">Mais de 40 horas</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Preferência de Comunicação <span class="required">*</span></label>
                            <div class="checkbox-group">
                                <div class="checkbox-item">
                                    <input type="checkbox" id="comm1" name="communication[]" value="email" checked>
                                    <label for="comm1">Email</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="comm2" name="communication[]" value="whatsapp">
                                    <label for="comm2">WhatsApp</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="comm3" name="communication[]" value="phone">
                                    <label for="comm3">Telefone</label>
                                </div>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="comm4" name="communication[]" value="video">
                                    <label for="comm4">Videoconferência</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Termos de Adesão</h3>
                        
                        <div class="terms-box">
                            <h4>TERMO DE ADESÃO AO SISTEMA ARBITRIVM</h4>
                            <p>Ao se cadastrar como árbitro no Sistema Arbitrivm, você concorda com os seguintes termos:</p>
                            
                            <h5>1. Compromisso de Imparcialidade</h5>
                            <p>O árbitro se compromete a atuar com total imparcialidade, independência e neutralidade em todos os casos que lhe forem designados.</p>
                            
                            <h5>2. Confidencialidade</h5>
                            <p>Todas as informações relacionadas aos processos arbitrais são estritamente confidenciais e não devem ser divulgadas a terceiros.</p>
                            
                            <h5>3. Disponibilidade</h5>
                            <p>O árbitro deve manter sua disponibilidade atualizada no sistema e responder às solicitações dentro de 48 horas.</p>
                            
                            <h5>4. Honorários</h5>
                            <p>Os honorários serão calculados com base no valor/hora cadastrado e na complexidade do caso. O Arbitrivm retém 20% como taxa de administração.</p>
                            
                            <h5>5. Qualificação Contínua</h5>
                            <p>O árbitro se compromete a manter-se atualizado e participar de treinamentos oferecidos pela plataforma.</p>
                            
                            <h5>6. Código de Ética</h5>
                            <p>O árbitro deve seguir o código de ética profissional e as normas estabelecidas pelo Arbitrivm.</p>
                        </div>

                        <div class="checkbox-item" style="margin-top: 1rem;">
                            <input type="checkbox" id="acceptTerms" name="acceptTerms" required>
                            <label for="acceptTerms">
                                Li e aceito os <strong>Termos de Adesão</strong> e o <strong>Código de Ética</strong> do Arbitrivm
                            </label>
                        </div>

                        <div class="checkbox-item" style="margin-top: 0.5rem;">
                            <input type="checkbox" id="acceptPrivacy" name="acceptPrivacy" required>
                            <label for="acceptPrivacy">
                                Concordo com a <strong>Política de Privacidade</strong> e o tratamento dos meus dados pessoais
                            </label>
                        </div>

                        <div class="checkbox-item" style="margin-top: 0.5rem;">
                            <input type="checkbox" id="acceptNewsletter" name="acceptNewsletter">
                            <label for="acceptNewsletter">
                                Desejo receber comunicações sobre novos casos e atualizações do sistema
                            </label>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">Criar Senha de Acesso</h3>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Senha <span class="required">*</span></label>
                                <input type="password" class="form-control" name="password" required minlength="8">
                                <small class="text-muted">Mínimo 8 caracteres, com letras e números</small>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirmar Senha <span class="required">*</span></label>
                                <input type="password" class="form-control" name="passwordConfirm" required>
                            </div>
                        </div>
                    </div>

                    <div id="errorMessage" class="error-message" style="display: none;"></div>

                    <div class="form-footer">
                        <button type="button" class="btn btn-secondary" onclick="previousStep(3)">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                            Voltar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Finalizar Cadastro
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
    
    <script>
        // JavaScript do formulário com token CSRF
        const csrfToken = '<?php echo $csrfToken; ?>';
    </script>
    <script>
        let currentStep = 1;
        const totalSteps = 4;
        let uploadedFiles = {};

        // Navegação entre steps
        function showStep(step) {
            // Esconder todos os steps
            document.querySelectorAll('.form-step').forEach(s => {
                s.style.display = 'none';
            });
            
            // Mostrar step atual
            document.getElementById(`step-${step}`).style.display = 'block';
            
            // Atualizar progress bar
            for (let i = 1; i <= totalSteps; i++) {
                const progressStep = document.getElementById(`step${i}`);
                if (i < step) {
                    progressStep.classList.add('completed');
                    progressStep.classList.remove('active');
                } else if (i === step) {
                    progressStep.classList.add('active');
                    progressStep.classList.remove('completed');
                } else {
                    progressStep.classList.remove('active', 'completed');
                }
            }
            
            // Scroll to top
            window.scrollTo(0, 0);
        }

        function nextStep(step) {
            // Validar step atual antes de avançar
            if (validateCurrentStep()) {
                currentStep = step;
                showStep(step);
            }
        }

        function previousStep(step) {
            currentStep = step;
            showStep(step);
        }

        // Validação
        function validateCurrentStep() {
            const currentForm = document.querySelector(`#step-${currentStep} :invalid`);
            if (currentForm) {
                currentForm.reportValidity();
                return false;
            }
            return true;
        }

        // Máscaras
        document.querySelector('input[name="cpf"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
        });

        document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        document.querySelector('input[name="zipCode"]').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Upload de arquivos
        const fileInputs = document.querySelectorAll('input[type="file"]');
        fileInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    // Validar tamanho (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('O arquivo não pode ser maior que 5MB');
                        e.target.value = '';
                        return;
                    }
                    
                    uploadedFiles[e.target.id] = file;
                    updateFileList();
                    
                    // Atualizar visual do upload
                    const uploadDiv = e.target.previousElementSibling;
                    uploadDiv.style.backgroundColor = '#d1fae5';
                    uploadDiv.querySelector('.file-upload-text').textContent = file.name;
                }
            });
        });

        function updateFileList() {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            Object.entries(uploadedFiles).forEach(([inputId, file]) => {
                if (file) {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    fileItem.innerHTML = `
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span>${file.name}</span>
                            <span style="color: var(--gray); font-size: 0.75rem;">(${(file.size / 1024 / 1024).toFixed(2)} MB)</span>
                        </div>
                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;" 
                                onclick="removeFile('${inputId}')">Remover</button>
                    `;
                    fileList.appendChild(fileItem);
                }
            });
        }

        function removeFile(inputId) {
            delete uploadedFiles[inputId];
            document.getElementById(inputId).value = '';
            const uploadDiv = document.getElementById(inputId).previousElementSibling;
            uploadDiv.style.backgroundColor = 'var(--light)';
            uploadDiv.querySelector('.file-upload-text').textContent = uploadDiv.querySelector('.file-upload-text').textContent.replace(uploadedFiles[inputId]?.name || '', 'Clique para enviar');
            updateFileList();
        }

        // Submit do formulário
        document.getElementById('arbitratorForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const errorMsg = document.getElementById('errorMessage');
            
            // Validar senhas
            const password = document.querySelector('input[name="password"]').value;
            const passwordConfirm = document.querySelector('input[name="passwordConfirm"]').value;
            
            if (password !== passwordConfirm) {
                errorMsg.textContent = 'As senhas não coincidem';
                errorMsg.style.display = 'block';
                return;
            }
            
            // Validar documentos obrigatórios
            const requiredDocs = ['docIdentity', 'docCPF', 'docAddress', 'docDiploma'];
            for (const docId of requiredDocs) {
                if (!uploadedFiles[docId]) {
                    errorMsg.textContent = 'Por favor, envie todos os documentos obrigatórios';
                    errorMsg.style.display = 'block';
                    return;
                }
            }
            
            // Simular envio
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span>Processando...';
            
            // Aqui você faria o envio real para o servidor
            // const formData = new FormData(this);
            // Object.entries(uploadedFiles).forEach(([key, file]) => {
            //     formData.append(key, file);
            // });
            
            setTimeout(() => {
                // Simular sucesso
                alert('Cadastro realizado com sucesso! Você receberá um email de confirmação em breve.');
                window.location.href = 'login.php';
            }, 2000);
        });

        // Inicializar
        showStep(1);
    </script>    
</body>
</html>