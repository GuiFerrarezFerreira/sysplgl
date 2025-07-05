<?php
/**
 * arbitrator/documents.php - Documentos do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];
$arbitratorId = getArbitratorId($userId);

// Processar upload de documento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'upload_document':
            if (isset($_FILES['document']) && $_FILES['document']['error'] === 0) {
                $documentType = $_POST['document_type'] ?? '';
                $title = $_POST['title'] ?? $_FILES['document']['name'];
                
                // Validar tipo de documento
                $allowedTypes = ['diploma', 'certificado', 'carteira_profissional', 'comprovante_experiencia', 'outro'];
                if (!in_array($documentType, $allowedTypes)) {
                    $_SESSION['error'] = 'Tipo de documento inválido.';
                    redirect('documents.php');
                }
                
                // Upload do arquivo
                $uploadResult = uploadFile($_FILES['document'], 'arbitrator_docs/' . $arbitratorId . '/', ['pdf', 'jpg', 'jpeg', 'png'], 10485760); // 10MB
                
                if ($uploadResult['success']) {
                    try {
                        $db->insert('arbitrator_documents', [
                            'arbitrator_id' => $arbitratorId,
                            'document_type' => $documentType,
                            'title' => $title,
                            'file_path' => $uploadResult['path'],
                            'file_size' => $uploadResult['size'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $_SESSION['success'] = 'Documento enviado com sucesso! Aguarde a verificação.';
                        
                    } catch (Exception $e) {
                        // Remover arquivo em caso de erro
                        unlink($uploadResult['path']);
                        $_SESSION['error'] = 'Erro ao registrar documento.';
                        logError('Erro ao registrar documento: ' . $e->getMessage());
                    }
                } else {
                    $_SESSION['error'] = $uploadResult['message'];
                }
            } else {
                $_SESSION['error'] = 'Nenhum arquivo selecionado.';
            }
            redirect('documents.php');
            break;
            
        case 'delete_document':
            $documentId = $_POST['document_id'] ?? 0;
            
            try {
                // Verificar propriedade do documento
                $document = $db->fetchOne(
                    "SELECT * FROM arbitrator_documents WHERE id = ? AND arbitrator_id = ?",
                    [$documentId, $arbitratorId]
                );
                
                if (!$document) {
                    throw new Exception('Documento não encontrado.');
                }
                
                if ($document['verified']) {
                    throw new Exception('Documentos verificados não podem ser excluídos.');
                }
                
                // Remover arquivo
                if (file_exists($document['file_path'])) {
                    unlink($document['file_path']);
                }
                
                // Remover registro
                $db->delete('arbitrator_documents', 'id = ?', [$documentId]);
                
                $_SESSION['success'] = 'Documento removido com sucesso.';
                
            } catch (Exception $e) {
                $_SESSION['error'] = $e->getMessage();
            }
            redirect('documents.php');
            break;
    }
}

// Buscar documentos do árbitro
$documents = $db->fetchAll("
    SELECT 
        ad.*,
        u.name as verified_by_name
    FROM arbitrator_documents ad
    LEFT JOIN users u ON ad.verified_by = u.id
    WHERE ad.arbitrator_id = ?
    ORDER BY ad.created_at DESC
", [$arbitratorId]);

// Organizar por tipo
$documentsByType = [];
foreach ($documents as $doc) {
    $documentsByType[$doc['document_type']][] = $doc;
}

// Tipos de documentos
$documentTypes = [
    'diploma' => [
        'label' => 'Diplomas e Formação',
        'icon' => '🎓',
        'description' => 'Diplomas de graduação, pós-graduação e especialização'
    ],
    'certificado' => [
        'label' => 'Certificados',
        'icon' => '📜',
        'description' => 'Certificados de cursos, treinamentos e workshops'
    ],
    'carteira_profissional' => [
        'label' => 'Registro Profissional',
        'icon' => '💼',
        'description' => 'Carteiras de órgãos de classe e conselhos profissionais'
    ],
    'comprovante_experiencia' => [
        'label' => 'Experiência Profissional',
        'icon' => '📊',
        'description' => 'Comprovantes de experiência e atuação profissional'
    ],
    'outro' => [
        'label' => 'Outros Documentos',
        'icon' => '📄',
        'description' => 'Outros documentos relevantes'
    ]
];

// Estatísticas
$stats = [
    'total' => count($documents),
    'verified' => count(array_filter($documents, function($d) { return $d['verified']; })),
    'pending' => count(array_filter($documents, function($d) { return !$d['verified'] && !$d['rejection_reason']; })),
    'rejected' => count(array_filter($documents, function($d) { return $d['rejection_reason']; }))
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentos - Arbitrivm</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .documents-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .documents-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .stat-card.verified .stat-value {
            color: #10b981;
        }
        
        .stat-card.pending .stat-value {
            color: #f59e0b;
        }
        
        .stat-card.rejected .stat-value {
            color: #ef4444;
        }
        
        .upload-section {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .upload-area {
            border: 2px dashed #e5e7eb;
            border-radius: 0.5rem;
            padding: 3rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: #2563eb;
            background: #f0f9ff;
        }
        
        .upload-area.dragover {
            border-color: #2563eb;
            background: #dbeafe;
        }
        
        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .documents-grid {
            display: grid;
            gap: 2rem;
        }
        
        .document-category {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .category-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .category-icon {
            font-size: 2rem;
        }
        
        .category-info h3 {
            margin: 0;
            color: #111827;
        }
        
        .category-info p {
            margin: 0.25rem 0 0;
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .documents-list {
            display: grid;
            gap: 1rem;
        }
        
        .document-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .document-item:hover {
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .document-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            border-radius: 0.5rem;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .document-meta {
            font-size: 0.75rem;
            color: #6b7280;
            display: flex;
            gap: 1rem;
        }
        
        .document-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-verified {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .document-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
        }
        
        .empty-category {
            text-align: center;
            padding: 2rem;
            color: #9ca3af;
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
        
        .rejection-notice {
            background: #fee2e2;
            border: 1px solid #fecaca;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 0.5rem;
        }
        
        .rejection-notice strong {
            color: #991b1b;
        }
        
        @media (max-width: 768px) {
            .documents-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .document-item {
                flex-direction: column;
                text-align: center;
            }
            
            .document-icon {
                margin: 0 0 1rem 0;
            }
            
            .document-actions {
                margin: 1rem 0 0 0;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="documents-container">
        <div class="documents-header">
            <h1>Meus Documentos</h1>
            <button class="btn btn-primary" onclick="showUploadModal()">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/>
                </svg>
                Adicionar Documento
            </button>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total de Documentos</div>
            </div>
            <div class="stat-card verified">
                <div class="stat-value"><?php echo $stats['verified']; ?></div>
                <div class="stat-label">Verificados</div>
            </div>
            <div class="stat-card pending">
                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                <div class="stat-label">Pendentes</div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-value"><?php echo $stats['rejected']; ?></div>
                <div class="stat-label">Rejeitados</div>
            </div>
        </div>
        
        <!-- Upload Rápido -->
        <div class="upload-section">
            <h2>Upload Rápido</h2>
            <div class="upload-area" id="dropZone" onclick="document.getElementById('quickUpload').click()">
                <div class="upload-icon">📤</div>
                <p><strong>Arraste arquivos aqui ou clique para selecionar</strong></p>
                <p class="text-muted">Formatos aceitos: PDF, JPG, PNG (máx. 10MB)</p>
                <input type="file" id="quickUpload" style="display: none;" accept=".pdf,.jpg,.jpeg,.png" onchange="handleQuickUpload(this)">
            </div>
        </div>
        
        <!-- Documentos por Categoria -->
        <div class="documents-grid">
            <?php foreach ($documentTypes as $type => $typeInfo): ?>
                <div class="document-category">
                    <div class="category-header">
                        <div class="category-icon"><?php echo $typeInfo['icon']; ?></div>
                        <div class="category-info">
                            <h3><?php echo $typeInfo['label']; ?></h3>
                            <p><?php echo $typeInfo['description']; ?></p>
                        </div>
                    </div>
                    
                    <div class="documents-list">
                        <?php if (empty($documentsByType[$type])): ?>
                            <div class="empty-category">
                                <p>Nenhum documento nesta categoria.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($documentsByType[$type] as $doc): ?>
                                <div class="document-item">
                                    <div class="document-icon">
                                        <?php echo getFileIcon($doc['file_path']); ?>
                                    </div>
                                    
                                    <div class="document-info">
                                        <div class="document-title">
                                            <?php echo htmlspecialchars($doc['title']); ?>
                                        </div>
                                        <div class="document-meta">
                                            <span><?php echo formatFileSize($doc['file_size']); ?></span>
                                            <span><?php echo date('d/m/Y', strtotime($doc['created_at'])); ?></span>
                                        </div>
                                        
                                        <?php if ($doc['rejection_reason']): ?>
                                            <div class="rejection-notice">
                                                <strong>Motivo da rejeição:</strong> <?php echo htmlspecialchars($doc['rejection_reason']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-status">
                                        <?php if ($doc['verified']): ?>
                                            <span class="status-badge status-verified">Verificado</span>
                                            <?php if ($doc['verified_by_name']): ?>
                                                <small class="text-muted">por <?php echo htmlspecialchars($doc['verified_by_name']); ?></small>
                                            <?php endif; ?>
                                        <?php elseif ($doc['rejection_reason']): ?>
                                            <span class="status-badge status-rejected">Rejeitado</span>
                                        <?php else: ?>
                                            <span class="status-badge status-pending">Pendente</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="document-actions">
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-sm btn-secondary">
                                            Ver
                                        </a>
                                        <?php if (!$doc['verified']): ?>
                                            <form action="documents.php" method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="delete_document">
                                                <input type="hidden" name="document_id" value="<?php echo $doc['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Tem certeza que deseja excluir este documento?')">
                                                    Excluir
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Modal de Upload -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Adicionar Documento</h3>
                <button type="button" class="close-modal" onclick="hideModal('uploadModal')">&times;</button>
            </div>
            
            <form action="documents.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_document">
                
                <div class="form-group">
                    <label>Tipo de Documento *</label>
                    <select name="document_type" class="form-control" required>
                        <option value="">Selecione...</option>
                        <?php foreach ($documentTypes as $type => $info): ?>
                            <option value="<?php echo $type; ?>"><?php echo $info['label']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Título do Documento *</label>
                    <input type="text" name="title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Arquivo *</label>
                    <input type="file" name="document" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    <small class="text-muted">Formatos aceitos: PDF, JPG, PNG. Tamanho máximo: 10MB</small>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Enviar Documento</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('uploadModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
        // Drag and drop
        const dropZone = document.getElementById('dropZone');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight(e) {
            dropZone.classList.add('dragover');
        }
        
        function unhighlight(e) {
            dropZone.classList.remove('dragover');
        }
        
        dropZone.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const files = e.dataTransfer.files;
            handleFiles(files);
        }
        
        function handleFiles(files) {
            if (files.length > 0) {
                const file = files[0];
                if (validateFile(file)) {
                    showUploadModal();
                    // Preencher o campo de arquivo no modal
                    const fileInput = document.querySelector('#uploadModal input[type="file"]');
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;
                }
            }
        }
        
        function validateFile(file) {
            const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
            const maxSize = 10 * 1024 * 1024; // 10MB
            
            if (!allowedTypes.includes(file.type)) {
                alert('Formato de arquivo não permitido. Use PDF, JPG ou PNG.');
                return false;
            }
            
            if (file.size > maxSize) {
                alert('Arquivo muito grande. O tamanho máximo é 10MB.');
                return false;
            }
            
            return true;
        }
        
        function handleQuickUpload(input) {
            if (input.files.length > 0) {
                const file = input.files[0];
                if (validateFile(file)) {
                    showUploadModal();
                    // Preencher o campo de arquivo no modal
                    const modalFileInput = document.querySelector('#uploadModal input[type="file"]');
                    modalFileInput.files = input.files;
                }
            }
        }
        
        function showUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
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
        
        // Funções auxiliares PHP convertidas para JavaScript
        function getFileIcon(filePath) {
            const extension = filePath.split('.').pop().toLowerCase();
            const icons = {
                'pdf': '📄',
                'jpg': '🖼️',
                'jpeg': '🖼️',
                'png': '🖼️'
            };
            return icons[extension] || '📎';
        }
    </script>
</body>
</html>

<?php
// Funções auxiliares
function getFileIcon($filePath) {
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📄',
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️'
    ];
    return $icons[$extension] ?? '📎';
}

function formatFileSize($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    elseif ($bytes < 1048576) return round($bytes / 1024, 2) . ' KB';
    else return round($bytes / 1048576, 2) . ' MB';
}
?>