<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/init.php';

// 权限检查
if (!check_login()) {
    header('Location: /admin/login.php');
    exit();
}

$is_admin = check_admin();
$title = '文件管理';

// 初始化分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取筛选参数
$filter_type = $_GET['type'] ?? '';
$filter_user = intval($_GET['user'] ?? 0);
$search_keyword = $_GET['search'] ?? '';

// 获取文件数据
$db = get_db();
$files = get_files($db, $per_page, $offset, $filter_type, $filter_user, $search_keyword);
$total_files = get_files_count($db, $filter_type, $filter_user, $search_keyword);
$total_pages = ceil($total_files / $per_page);

// 获取文件类型列表
$file_types = get_file_types($db);

// 获取用户列表
$users = get_users_for_filter($db);

include '../sidebar.php';

// 辅助函数：获取文件列表
function get_files(PDO $db, int $limit, int $offset, string $type = '', int $user_id = 0, string $search = ''): array {
    $where = [];
    $params = [];
    
    $where[] = "f.del_status = 0";
    
    if ($type && $type !== 'all') {
        $where[] = "f.file_type = ?";
        $params[] = $type;
    }
    
    if ($user_id > 0) {
        $where[] = "f.uploaded_by = ?";
        $params[] = $user_id;
    }
    
    if ($search) {
        $where[] = "(f.description LIKE ? OR f.file_path LIKE ? OR f.file_format LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT f.*, u.username, d.title as document_title
            FROM file_upload f
            LEFT JOIN users u ON f.uploaded_by = u.id
            LEFT JOIN documents d ON f.document_id = d.id
            {$where_sql}
            ORDER BY f.uploaded_at DESC 
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 辅助函数：获取文件总数
function get_files_count(PDO $db, string $type = '', int $user_id = 0, string $search = ''): int {
    $where = [];
    $params = [];
    
    $where[] = "del_status = 0";
    
    if ($type && $type !== 'all') {
        $where[] = "file_type = ?";
        $params[] = $type;
    }
    
    if ($user_id > 0) {
        $where[] = "uploaded_by = ?";
        $params[] = $user_id;
    }
    
    if ($search) {
        $where[] = "(description LIKE ? OR file_path LIKE ? OR file_format LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    $sql = "SELECT COUNT(*) FROM file_upload {$where_sql}";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

// 辅助函数：获取文件类型列表
function get_file_types(PDO $db): array {
    $sql = "SELECT DISTINCT file_type FROM file_upload WHERE del_status = 0 ORDER BY file_type";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// 辅助函数：获取用户列表（用于筛选）
function get_users_for_filter(PDO $db): array {
    $sql = "SELECT id, username FROM users ORDER BY username";
    $stmt = $db->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 辅助函数：格式化文件大小
function format_file_size($bytes): string {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' B';
    }
}

// 辅助函数：获取文件类型图标
function get_file_icon($file_type): string {
    $icons = [
        'image' => 'bi-image-fill',
        'video' => 'bi-camera-video-fill',
        'audio' => 'bi-music-note-beamed',
        'document' => 'bi-file-earmark-text-fill',
        'archive' => 'bi-file-zip-fill',
        'other' => 'bi-file-earmark-fill'
    ];
    return $icons[$file_type] ?? 'bi-file-earmark-fill';
}

// 辅助函数：显示操作按钮
function render_action_buttons(array $file, bool $is_admin): string {
    $file_id = $file['id'];
    $file_name = htmlspecialchars(basename($file['file_path']), ENT_QUOTES, 'UTF-8');
    
    $html = '<div class="btn-group" role="group" style="gap: 2px;">';
    
    // 查看按钮 - 蓝色 (#64b5f6)
    $html .= '<a href="/uploads/' . htmlspecialchars($file['file_path'], ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="查看文件"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#90caf9\'; this.style.borderColor=\'#90caf9\';" ';
    $html .= ' onmouseout="this.style.backgroundColor=\'#64b5f6\'; this.style.borderColor=\'#64b5f6\';">';
    $html .= '<i class="bi bi-eye" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 编辑按钮 - 橙色 (#ffb74d)
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="编辑信息" ';
    $html .= 'onclick="editFile(' . $file_id . ')" ';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffcc80\'; this.style.borderColor=\'#ffcc80\';" ';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ffb74d\'; this.style.borderColor=\'#ffb74d\';">';
    $html .= '<i class="bi bi-pencil" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    // 删除按钮 - 珊瑚色 (#ff8a65)
    $disabled = $is_admin ? '' : 'disabled';
    $disabled_style = $is_admin ? '' : 'opacity: 0.65; cursor: not-allowed;';
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s, border-color 0.2s; ' . $disabled_style . '" data-tooltip="删除文件" ';
    $html .= $disabled . ' onclick="deleteFile(' . $file_id . ', \'' . addslashes($file_name) . '\')" ';
    $html .= ' onmouseover="if(!this.disabled){this.style.backgroundColor=\'#ffab91\'; this.style.borderColor=\'#ffab91\';}" ';
    $html .= ' onmouseout="if(!this.disabled){this.style.backgroundColor=\'#ff8a65\'; this.style.borderColor=\'#ff8a65\';}">';
    $html .= '<i class="bi bi-trash-fill" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

// 辅助函数：获取统计信息
function get_file_stats(PDO $db): array {
    $stats = [];
    
    $stats['total_files'] = $db->query("SELECT COUNT(*) FROM file_upload WHERE del_status = 0")->fetchColumn();
    $stats['total_size'] = $db->query("SELECT COALESCE(SUM(file_size), 0) FROM file_upload WHERE del_status = 0")->fetchColumn();
    
    $type_stats = $db->query("SELECT file_type, COUNT(*) as count, SUM(file_size) as total_size FROM file_upload WHERE del_status = 0 GROUP BY file_type")->fetchAll(PDO::FETCH_ASSOC);
    $stats['type_stats'] = $type_stats;
    
    return $stats;
}

$stats = get_file_stats($db);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - 管理后台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .btn-group .btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .btn-group .btn i {
            font-size: 14px;
        }
        
        /* 纯CSS悬停提示样式 */
        [data-tooltip] {
            position: relative;
        }
        
        [data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 1000;
            margin-bottom: 5px;
            pointer-events: none;
        }
        
        [data-tooltip]:hover::before {
            content: "";
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            border: 4px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            margin-bottom: 1px;
            pointer-events: none;
        }
        
        .file-icon {
            font-size: 1.5rem;
            color: #6c757d;
        }
        
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .stats-card h4 {
            margin: 0;
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .stats-card .display-4 {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-folder2-open"></i> 文件管理</h1>
                <a href="../dashboard.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> 返回仪表板
                </a>
            </div>

            <!-- 统计信息卡片 -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h4>总文件数</h4>
                        <div class="display-4"><?php echo number_format($stats['total_files']); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                        <h4>总大小</h4>
                        <div class="display-4"><?php echo format_file_size($stats['total_size']); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stats-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <h4>文件类型分布</h4>
                        <div class="row">
                            <?php foreach ($stats['type_stats'] as $type): ?>
                                <div class="col-6">
                                    <small><?php echo ucfirst($type['file_type']); ?>: <?php echo $type['count']; ?>个</small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 筛选和搜索区域 -->
            <div class="filter-section">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">文件类型</label>
                        <select name="type" class="form-select">
                            <option value="">全部类型</option>
                            <?php foreach ($file_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">上传者</label>
                        <select name="user" class="form-select">
                            <option value="">全部用户</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">搜索</label>
                        <input type="text" name="search" class="form-control" placeholder="文件名、描述..." 
                               value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-search"></i> 搜索
                            </button>
                            <a href="?" class="btn btn-secondary">重置</a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- 消息提示区域 -->
            <div id="messageContainer">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>成功！</strong> <?php echo $_SESSION['success']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>错误！</strong> <?php echo $_SESSION['error']; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
            </div>

            <!-- 文件列表 -->
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">文件列表</h5>
                        <span class="text-muted">共 <?php echo number_format($total_files); ?> 个文件</span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($files)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">暂无文件</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%" class="text-center">#</th>
                                        <th width="5%" class="text-center">图标</th>
                                        <th width="20%">文件名</th>
                                        <th width="10%">类型</th>
                                        <th width="10%">大小</th>
                                        <th width="15%">关联文档</th>
                                        <th width="15%">上传者</th>
                                        <th width="15%">上传时间</th>
                                        <th width="15%" class="text-center">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $index => $file): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $offset + $index + 1; ?></td>
                                            <td class="text-center">
                                                <i class="bi <?php echo get_file_icon($file['file_type']); ?> file-icon" 
                                                   style="color: <?php echo get_file_color($file['file_type']); ?>"></i>
                                            </td>
                                            <td>
                                                <div class="fw-bold"><?php echo htmlspecialchars(basename($file['file_path'])); ?></div>
                                                <?php if ($file['description']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($file['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo strtoupper($file['file_format']); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo ucfirst($file['file_type']); ?></small>
                                            </td>
                                            <td><?php echo format_file_size($file['file_size']); ?></td>
                                            <td>
                                                <?php if ($file['document_id'] && $file['document_title']): ?>
                                                    <a href="../documents/view.php?id=<?php echo $file['document_id']; ?>" 
                                                       class="text-decoration-none">
                                                        <?php echo htmlspecialchars($file['document_title']); ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <small><?php echo htmlspecialchars($file['username']); ?></small>
                                            </td>
                                            <td>
                                                <small><?php echo date('Y-m-d H:i', strtotime($file['uploaded_at'])); ?></small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo render_action_buttons($file, $is_admin); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 分页 -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="文件列表分页" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>&type=<?php echo $filter_type; ?>&user=<?php echo $filter_user; ?>&search=<?php echo urlencode($search_keyword); ?>">上一页</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>&type=<?php echo $filter_type; ?>&user=<?php echo $filter_user; ?>&search=<?php echo urlencode($search_keyword); ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>&type=<?php echo $filter_type; ?>&user=<?php echo $filter_user; ?>&search=<?php echo urlencode($search_keyword); ?>">下一页</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>

    <!-- 添加辅助函数 -->
    <?php
    function get_file_color($file_type) {
        $colors = [
            'image' => '#28a745',
            'video' => '#dc3545',
            'audio' => '#6f42c1',
            'document' => '#007bff',
            'archive' => '#fd7e14',
            'other' => '#6c757d'
        ];
        return $colors[$file_type] ?? '#6c757d';
    }
    ?>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 编辑文件信息
        function editFile(fileId) {
            // 这里可以添加编辑文件信息的逻辑
            alert('编辑文件功能开发中...');
        }

        // 删除文件
        function deleteFile(fileId, fileName) {
            if (confirm(`确定要删除文件 "${fileName}" 吗？此操作不可恢复！`)) {
                // 这里可以添加删除文件的AJAX请求
                alert('删除文件功能开发中...');
            }
        }

        // 页面加载完成后的处理
        document.addEventListener('DOMContentLoaded', function() {
            // 自动隐藏成功消息
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 3000);
        });
    </script>
</body>
</html>