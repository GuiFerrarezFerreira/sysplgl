<?php
/**
 * Arquivo de Configuração Principal do Sistema de Arbitragem
 * 
 * Este arquivo contém todas as configurações necessárias para o funcionamento
 * completo do sistema, incluindo banco de dados, APIs, segurança e integrações.
 */

// Configurações de Ambiente
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: 'development'); // development, staging, production
define('DEBUG_MODE', ENVIRONMENT === 'development');
define('MAINTENANCE_MODE', false);

// Configurações de Timezone e Localização
date_default_timezone_set('America/Sao_Paulo');
setlocale(LC_ALL, 'pt_BR.UTF-8');
define('DEFAULT_LANGUAGE', 'pt-BR');
define('SUPPORTED_LANGUAGES', ['pt-BR', 'en-US', 'es-ES']);

// Configurações de URL e Paths
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/sysplgl');
define('API_URL', BASE_URL . '/api');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

// Paths do Sistema
define('ROOT_PATH', dirname(__FILE__));
define('APP_PATH', ROOT_PATH . '/app');
define('CONTROLLERS_PATH', APP_PATH . '/controllers');
define('MODELS_PATH', APP_PATH . '/models');
define('VIEWS_PATH', APP_PATH . '/views');
define('HELPERS_PATH', APP_PATH . '/helpers');
define('LIBS_PATH', APP_PATH . '/libs');
define('CONFIG_PATH', ROOT_PATH . '/config');
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');
define('CACHE_PATH', ROOT_PATH . '/cache');
define('TEMP_PATH', ROOT_PATH . '/temp');

// Configurações do Banco de Dados
define('DB_CONFIG', [
    'primary' => [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'port' => getenv('DB_PORT') ?: 3306,
        'database' => getenv('DB_NAME') ?: 'arbitragem_db',
        'username' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => 'arb_',
        'strict' => true,
        'engine' => 'InnoDB',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ],
    'read_replica' => [ // Para escalabilidade futura
        'host' => getenv('DB_READ_HOST') ?: 'localhost',
        'port' => getenv('DB_READ_PORT') ?: 3306,
        // ... mesmas configurações
    ]
]);

// Configurações de Segurança
define('SECURITY_CONFIG', [
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'your-super-secret-key-change-in-production',
        'algorithm' => 'HS256',
        'access_token_ttl' => 3600, // 1 hora
        'refresh_token_ttl' => 2592000, // 30 dias
        'issuer' => 'arbitragem-system',
        'audience' => 'arbitragem-users'
    ],
    'encryption' => [
        'method' => 'AES-256-CBC',
        'key' => getenv('ENCRYPTION_KEY') ?: 'your-32-character-encryption-key',
        'iv_length' => 16
    ],
    'password' => [
        'min_length' => 8,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_numbers' => true,
        'require_special_chars' => true,
        'bcrypt_cost' => 12
    ],
    'session' => [
        'name' => 'ARB_SESSION',
        'lifetime' => 7200, // 2 horas
        'path' => '/',
        'domain' => '',
        'secure' => ENVIRONMENT === 'production',
        'httponly' => true,
        'samesite' => 'Lax'
    ],
    'csrf' => [
        'enabled' => true,
        'token_name' => 'csrf_token',
        'header_name' => 'X-CSRF-TOKEN',
        'cookie_name' => 'csrf_cookie',
        'expire' => 7200
    ],
    'cors' => [
        'enabled' => true,
        'allowed_origins' => ['*'], // Configurar domínios específicos em produção
        'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
        'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],
        'max_age' => 86400,
        'credentials' => true
    ]
]);

// Configurações de Upload
define('UPLOAD_CONFIG', [
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'allowed_extensions' => [
        'documents' => ['pdf', 'doc', 'docx', 'odt', 'txt', 'rtf'],
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        'spreadsheets' => ['xls', 'xlsx', 'csv', 'ods'],
        'archives' => ['zip', 'rar', '7z', 'tar', 'gz']
    ],
    'upload_paths' => [
        'processes' => UPLOADS_PATH . '/processes',
        'documents' => UPLOADS_PATH . '/documents',
        'evidence' => UPLOADS_PATH . '/evidence',
        'signatures' => UPLOADS_PATH . '/signatures',
        'temp' => TEMP_PATH . '/uploads'
    ],
    'image_processing' => [
        'quality' => 85,
        'max_width' => 2048,
        'max_height' => 2048,
        'thumbnail_sizes' => [
            'small' => [150, 150],
            'medium' => [300, 300],
            'large' => [600, 600]
        ]
    ]
]);

// Configurações de Email
define('EMAIL_CONFIG', [
    'default' => 'smtp', // smtp, sendmail, mail
    'smtp' => [
        'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'port' => getenv('SMTP_PORT') ?: 587,
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASS') ?: '',
        'encryption' => 'tls', // tls, ssl
        'auth' => true,
        'timeout' => 30
    ],
    'from' => [
        'address' => getenv('MAIL_FROM_ADDRESS') ?: 'noreply@arbitragem.com',
        'name' => getenv('MAIL_FROM_NAME') ?: 'Sistema de Arbitragem'
    ],
    'templates_path' => VIEWS_PATH . '/emails',
    'queue' => [
        'enabled' => true,
        'driver' => 'database', // database, redis
        'table' => 'email_queue'
    ]
]);

// Configurações de SMS
define('SMS_CONFIG', [
    'provider' => getenv('SMS_PROVIDER') ?: 'twilio', // twilio, nexmo, aws_sns
    'twilio' => [
        'account_sid' => getenv('TWILIO_SID') ?: '',
        'auth_token' => getenv('TWILIO_TOKEN') ?: '',
        'from_number' => getenv('TWILIO_NUMBER') ?: ''
    ],
    'templates_path' => VIEWS_PATH . '/sms'
]);

// Configurações de Pagamento
define('PAYMENT_CONFIG', [
    'default_gateway' => 'stripe', // stripe, paypal, mercadopago
    'currency' => 'BRL',
    'stripe' => [
        'public_key' => getenv('STRIPE_PUBLIC_KEY') ?: '',
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'api_version' => '2023-10-16'
    ],
    'mercadopago' => [
        'access_token' => getenv('MP_ACCESS_TOKEN') ?: '',
        'public_key' => getenv('MP_PUBLIC_KEY') ?: '',
        'webhook_secret' => getenv('MP_WEBHOOK_SECRET') ?: ''
    ],
    'tax_rate' => 0.05, // 5% de taxa administrativa
    'installments' => [
        'max' => 12,
        'min_amount' => 100.00,
        'interest_rate' => 0.0199 // 1.99% ao mês
    ]
]);

// Configurações de APIs Externas
define('EXTERNAL_APIS', [
    'google' => [
        'maps_api_key' => getenv('GOOGLE_MAPS_KEY') ?: '',
        'calendar_api_key' => getenv('GOOGLE_CALENDAR_KEY') ?: '',
        'oauth_client_id' => getenv('GOOGLE_CLIENT_ID') ?: '',
        'oauth_client_secret' => getenv('GOOGLE_CLIENT_SECRET') ?: ''
    ],
    'docusign' => [
        'integration_key' => getenv('DOCUSIGN_KEY') ?: '',
        'secret_key' => getenv('DOCUSIGN_SECRET') ?: '',
        'base_url' => 'https://demo.docusign.net/restapi', // Mudar para produção
        'account_id' => getenv('DOCUSIGN_ACCOUNT_ID') ?: ''
    ],
    'zoom' => [
        'api_key' => getenv('ZOOM_API_KEY') ?: '',
        'api_secret' => getenv('ZOOM_API_SECRET') ?: '',
        'webhook_token' => getenv('ZOOM_WEBHOOK_TOKEN') ?: ''
    ],
    'whatsapp' => [
        'api_url' => 'https://api.whatsapp.com',
        'token' => getenv('WHATSAPP_TOKEN') ?: '',
        'phone_number_id' => getenv('WHATSAPP_PHONE_ID') ?: ''
    ]
]);

// Configurações de Cache
define('CACHE_CONFIG', [
    'default' => 'file', // file, redis, memcached
    'prefix' => 'arb_cache_',
    'ttl' => 3600, // 1 hora padrão
    'file' => [
        'path' => CACHE_PATH
    ],
    'redis' => [
        'host' => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port' => getenv('REDIS_PORT') ?: 6379,
        'password' => getenv('REDIS_PASSWORD') ?: null,
        'database' => 0
    ]
]);

// Configurações de Log
define('LOG_CONFIG', [
    'enabled' => true,
    'level' => DEBUG_MODE ? 'debug' : 'error', // debug, info, warning, error, critical
    'handlers' => [
        'file' => [
            'enabled' => true,
            'path' => LOGS_PATH,
            'max_files' => 30,
            'max_size' => 10 * 1024 * 1024 // 10MB
        ],
        'database' => [
            'enabled' => true,
            'table' => 'system_logs',
            'level' => 'warning' // Apenas warnings e errors no BD
        ],
        'email' => [
            'enabled' => ENVIRONMENT === 'production',
            'level' => 'critical',
            'to' => ['admin@arbitragem.com']
        ]
    ]
]);

// Configurações de Queue/Jobs
define('QUEUE_CONFIG', [
    'default' => 'database', // database, redis, sync
    'connections' => [
        'database' => [
            'table' => 'jobs',
            'failed_table' => 'failed_jobs',
            'retry_after' => 90
        ],
        'redis' => [
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90
        ]
    ]
]);

// Configurações de Relatórios
define('REPORTS_CONFIG', [
    'storage_path' => UPLOADS_PATH . '/reports',
    'temp_path' => TEMP_PATH . '/reports',
    'formats' => ['pdf', 'xlsx', 'csv'],
    'pdf' => [
        'engine' => 'dompdf', // dompdf, tcpdf, wkhtmltopdf
        'paper' => 'A4',
        'orientation' => 'portrait',
        'font' => 'DejaVu Sans'
    ],
    'templates_path' => VIEWS_PATH . '/reports'
]);

// Configurações de Notificações
define('NOTIFICATIONS_CONFIG', [
    'channels' => ['database', 'email', 'sms', 'whatsapp', 'push'],
    'queue' => true,
    'batch_size' => 100,
    'rate_limit' => [
        'email' => 10, // por minuto
        'sms' => 5,
        'whatsapp' => 15
    ]
]);

// Configurações de Busca
define('SEARCH_CONFIG', [
    'engine' => 'database', // database, elasticsearch, algolia
    'min_query_length' => 3,
    'results_per_page' => 20,
    'elasticsearch' => [
        'hosts' => [getenv('ELASTIC_HOST') ?: 'localhost:9200'],
        'index' => 'arbitragem'
    ]
]);

// Configurações de Performance
define('PERFORMANCE_CONFIG', [
    'query_cache' => true,
    'view_cache' => ENVIRONMENT === 'production',
    'asset_versioning' => true,
    'minify_assets' => ENVIRONMENT === 'production',
    'lazy_loading' => true,
    'pagination_limit' => 100
]);

// Configurações de Monitoramento
define('MONITORING_CONFIG', [
    'enabled' => ENVIRONMENT === 'production',
    'sentry' => [
        'dsn' => getenv('SENTRY_DSN') ?: '',
        'environment' => ENVIRONMENT,
        'traces_sample_rate' => 0.1
    ],
    'metrics' => [
        'enabled' => true,
        'driver' => 'prometheus', // prometheus, statsd
        'endpoint' => '/metrics'
    ]
]);

// Configurações de Backup
define('BACKUP_CONFIG', [
    'enabled' => true,
    'schedule' => '0 2 * * *', // 2AM todos os dias
    'retention_days' => 30,
    'destinations' => ['local', 's3'],
    'local_path' => ROOT_PATH . '/backups',
    's3' => [
        'bucket' => getenv('BACKUP_S3_BUCKET') ?: '',
        'region' => getenv('AWS_REGION') ?: 'us-east-1',
        'path' => 'arbitragem-backups'
    ]
]);

// Configurações de Feature Flags
define('FEATURES', [
    'video_conference' => true,
    'ai_document_analysis' => false,
    'blockchain_signatures' => false,
    'mobile_app' => true,
    'advanced_analytics' => true,
    'multi_tenancy' => false,
    'api_v2' => false
]);

// Autoload de Classes
spl_autoload_register(function ($class) {
    $paths = [
        CONTROLLERS_PATH,
        MODELS_PATH,
        LIBS_PATH,
        HELPERS_PATH
    ];
    
    foreach ($paths as $path) {
        $file = $path . '/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Funções Helper Globais
require_once HELPERS_PATH . '/functions.php';

// Validar Configurações Críticas
if (ENVIRONMENT === 'production') {
    $required_env_vars = [
        'JWT_SECRET', 'ENCRYPTION_KEY', 'DB_HOST', 'DB_NAME', 
        'DB_USER', 'DB_PASS', 'SMTP_HOST', 'SMTP_USER'
    ];
    
    foreach ($required_env_vars as $var) {
        if (empty(getenv($var))) {
            throw new Exception("Variável de ambiente obrigatória não definida: $var");
        }
    }
}

// Criar diretórios necessários se não existirem
$required_dirs = [
    UPLOADS_PATH, LOGS_PATH, CACHE_PATH, TEMP_PATH,
    UPLOADS_PATH . '/processes', UPLOADS_PATH . '/documents',
    UPLOADS_PATH . '/evidence', UPLOADS_PATH . '/signatures'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Inicializar sistema de log
if (!DEBUG_MODE) {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/php-errors.log');
}