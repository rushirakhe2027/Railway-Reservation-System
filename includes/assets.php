<?php
// Asset version for cache busting
define('ASSET_VERSION', '1.0.0');

// Base URLs
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST']);
define('ASSETS_URL', BASE_URL . '/assets');

// CDN URLs with local fallbacks
$ASSETS = [
    'css' => [
        'bootstrap' => [
            'cdn' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
            'local' => '/assets/css/bootstrap.min.css'
        ],
        'toastr' => [
            'cdn' => 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css',
            'local' => '/assets/css/toastr.min.css'
        ]
    ],
    'js' => [
        'bootstrap' => [
            'cdn' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
            'local' => '/assets/js/bootstrap.bundle.min.js'
        ],
        'toastr' => [
            'cdn' => 'https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js',
            'local' => '/assets/js/toastr.min.js'
        ]
    ]
];

// Function to load CSS files
function loadCSS($name) {
    global $ASSETS;
    if (!isset($ASSETS['css'][$name])) return '';
    
    $asset = $ASSETS['css'][$name];
    return "<link rel='stylesheet' href='{$asset['cdn']}' 
            onerror=\"this.onerror=null;this.href='{$asset['local']}?v=" . ASSET_VERSION . "'\">";
}

// Function to load JavaScript files
function loadJS($name, $defer = true) {
    global $ASSETS;
    if (!isset($ASSETS['js'][$name])) return '';
    
    $asset = $ASSETS['js'][$name];
    $defer_attr = $defer ? 'defer' : '';
    return "<script src='{$asset['cdn']}' 
            onerror=\"this.onerror=null;this.src='{$asset['local']}?v=" . ASSET_VERSION . "'\" 
            {$defer_attr}></script>";
}

// Common CSS for all pages
function commonCSS() {
    $css = "
    <style>
        :root {
            --primary-color: #2c3e50;
            --accent-color: #e74c3c;
            --secondary-color: #3498db;
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --dark-bg: #2c3e50;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
        }
        
        .toast-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            padding: 15px 25px;
            border-radius: 4px;
            color: white;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }
        
        .btn-primary {
            background: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background: var(--accent-color);
            border-color: var(--accent-color);
        }
    </style>";
    
    return $css . loadCSS('bootstrap');
}

// Common JavaScript for all pages
function commonJS() {
    return loadJS('bootstrap', true);
}

// Toast notification JavaScript
function toastJS($message = null, $type = 'info') {
    if (!$message) return '';
    
    $colors = [
        'success' => '#2ecc71',
        'error' => '#e74c3c',
        'warning' => '#f1c40f',
        'info' => '#3498db'
    ];
    
    $color = $colors[$type] ?? $colors['info'];
    
    return "
    <div class='toast-message' style='background: {$color};' id='toast'>
        {$message}
    </div>
    <script>
        setTimeout(() => {
            const toast = document.getElementById('toast');
            if (toast) {
                toast.style.animation = 'slideOut 0.3s ease-in forwards';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    </script>";
}
?> 