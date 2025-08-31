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
$yearmonth = $_GET['yearmonth'] ?? '';
$search_keyword = $_GET['search'] ?? '';

// 将年月转换为日期范围
$start_date = '';
$end_date = '';

if ($yearmonth) {
    $start_date = $yearmonth . '-01';
    $end_date = date('Y-m-t', strtotime($yearmonth . '-01'));
}

// 获取排序参数
$sort = $_GET['sort'] ?? 'date';
$order = $_GET['order'] ?? 'desc';

// 验证排序参数
$allowed_sorts = ['name', 'type', 'size', 'user', 'date'];
$allowed_orders = ['asc', 'desc'];
$sort = in_array($sort, $allowed_sorts) ? $sort : 'date';
$order = in_array($order, $allowed_orders) ? $order : 'desc';

// 获取文件数据
$db = get_db();
$files = get_files($db, $per_page, $offset, $filter_type, $start_date, $end_date, $search_keyword, $sort, $order);
$total_files = get_files_count($db, $filter_type, $start_date, $end_date, $search_keyword);
$total_pages = ceil($total_files / $per_page);

// 获取文件类型列表
$file_types = get_file_types($db);



include '../sidebar.php';

// 辅助函数：获取文件列表
function get_files(PDO $db, int $limit, int $offset, string $type = '', string $start_date = '', string $end_date = '', string $search = '', string $sort = 'date', string $order = 'desc'): array {
    $where = [];
    $params = [];
    
    $where[] = "f.del_status = 0";
    
    if ($type && $type !== 'all') {
        $where[] = "f.file_type = ?";
        $params[] = $type;
    }
    
    if ($start_date) {
        $where[] = "DATE(f.uploaded_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "DATE(f.uploaded_at) <= ?";
        $params[] = $end_date;
    }
    
    if ($search) {
        $where[] = "(f.description LIKE ? OR f.file_path LIKE ? OR f.file_format LIKE ?)";
        $search_param = "%{$search}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $params[] = $search_param;
    }
    
    $where_sql = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // 定义排序字段映射
    $sort_columns = [
        'name' => 'f.file_path',
        'type' => 'f.file_type',
        'size' => 'f.file_size',
        'user' => 'u.username',
        'date' => 'f.uploaded_at'
    ];
    
    $sort_column = $sort_columns[$sort] ?? 'f.uploaded_at';
    $sort_order = ($order === 'asc') ? 'ASC' : 'DESC';
    
    $sql = "SELECT f.*, u.username, d.title as document_title
            FROM file_upload f
            LEFT JOIN users u ON f.uploaded_by = u.id
            LEFT JOIN documents d ON f.document_id = d.document_id
            {$where_sql}
            ORDER BY {$sort_column} {$sort_order}
            LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 辅助函数：获取文件总数
function get_files_count(PDO $db, string $type = '', string $start_date = '', string $end_date = '', string $search = ''): int {
    $where = [];
    $params = [];
    
    $where[] = "del_status = 0";
    
    if ($type && $type !== 'all') {
        $where[] = "file_type = ?";
        $params[] = $type;
    }
    
    if ($start_date) {
        $where[] = "DATE(uploaded_at) >= ?";
        $params[] = $start_date;
    }
    
    if ($end_date) {
        $where[] = "DATE(uploaded_at) <= ?";
        $params[] = $end_date;
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

// 辅助函数：获取文件类型的中文名称
function get_file_type_chinese($file_type): string {
    $type_map = [
        'image' => '图片',
        'video' => '视频',
        'audio' => '音频',
        'document' => '文档',
        'archive' => '压缩包',
        'other' => '其他'
    ];
    return $type_map[$file_type] ?? $file_type;
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
    $file_url = ltrim($file['file_path'], '/'); // 移除开头的斜杠避免重复
    $html .= '<a href="/' . htmlspecialchars($file_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="查看文件"';
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
    $html .= $disabled . ' onclick="event.preventDefault(); event.stopPropagation(); deleteFile(' . $file_id . ', \'' . addslashes($file_name) . '\'); return false;" ';
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
            padding: 10px 0px 0px 0px;
            margin-bottom: 20px;
        }

        /* 排序指示器样式 */
        .sortable-header a {
            color: inherit;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .sortable-header a:hover {
            color: #0d6efd;
            text-decoration: none;
        }
        .sortable-header th {
            white-space: nowrap;
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
                                    <small><?php echo get_file_type_chinese($type['file_type']); ?>: <?php echo $type['count']; ?>个</small>
                                </div>
                            <?php endforeach; ?>
                          </div>
                      </div>
                </div>
            </div>

            <!-- 筛选和搜索区域 -->
            <div class="filter-section" style="margin-bottom: 5px;">
                <style>
                    .filter-section .form-control-sm,
                    .filter-section .form-select-sm {
                        height: 31px !important;
                        min-height: 31px !important;
                        line-height: 1.25 !important;
                    }
                    .filter-section .btn-sm {
                        height: 31px !important;
                        min-height: 31px !important;
                        line-height: 1.25 !important;
                        padding-top: 0.25rem !important;
                        padding-bottom: 0.25rem !important;
                    }
                </style>
                <form method="GET" class="row g-1 align-items-center">
                    <div class="col-md-2">
                        <select name="type" class="form-select form-select-sm">
                            <option value="">全部文件类型</option>
                            <?php foreach ($file_types as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $filter_type === $type ? 'selected' : ''; ?>>
                                    <?php echo get_file_type_chinese($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select name="yearmonth" class="form-select form-select-sm">
                            <option value="">全部时间</option>
                            <?php
                            $current_year = date('Y');
                            $current_month = date('m');
                            for ($year = $current_year; $year >= $current_year - 2; $year--):
                                for ($month = ($year == $current_year ? $current_month : 12); $month >= 1; $month--):
                                    $ym = sprintf('%04d-%02d', $year, $month);
                                    $display = sprintf('%d年%d月', $year, $month);
                            ?>
                                <option value="<?php echo $ym; ?>" <?php echo ($ym === $yearmonth) ? 'selected' : ''; ?>>
                                <?php echo $display; ?>
                            </option>
                            <?php
                                endfor;
                            endfor;
                            ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="文件名、描述..." 
                               value="<?php echo htmlspecialchars($search_keyword); ?>">
                    </div>
                    <div class="col-md-3">
                        <div>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-search"></i> 搜索
                            </button>
                            <a href="?" class="btn btn-secondary btn-sm">重置</a>
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
                        <div class="d-flex align-items-center">
                            <div id="batchActions" class="me-3" style="display: none;">
                                <button type="button" class="btn btn-danger btn-sm" onclick="batchDelete()">
                                    <i class="bi bi-trash"></i> 批量删除
                                </button>
                                <button type="button" class="btn btn-primary btn-sm ms-1" onclick="batchDownload()">
                                    <i class="bi bi-download"></i> 批量下载
                                </button>
                            </div>
                            <span class="text-muted">共 <?php echo number_format($total_files); ?> 个文件</span>
                        </div>
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
                                        <th width="3%" class="text-center">
                                            <input type="checkbox" id="selectAll" class="form-check-input">
                                        </th>
                                        <th width="5%" class="text-center">图标</th>
                                        <th width="22%" class="sortable-header">
                                            <a href="?<?php 
                                        $sort_params = [];
                                        if ($filter_type !== '') $sort_params['type'] = $filter_type;
                                        if ($yearmonth !== '') $sort_params['yearmonth'] = $yearmonth;
                                        if ($search_keyword !== '') $sort_params['search'] = $search_keyword;
                                        $sort_params['sort'] = 'name';
                                        $sort_params['order'] = ($_GET['sort'] ?? '') === 'name' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc';
                                        echo http_build_query($sort_params);
                                        ?>">
                                                文件名
                                                <?php if (($_GET['sort'] ?? '') === 'name'): ?>
                                                    <i class="bi bi-chevron-<?php echo ($_GET['order'] ?? 'asc') === 'asc' ? 'up' : 'down'; ?> small"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th width="10%" class="sortable-header">
                                            <a href="?<?php 
                                        $sort_params = [];
                                        if ($filter_type !== '') $sort_params['type'] = $filter_type;
                                        if ($yearmonth !== '') $sort_params['yearmonth'] = $yearmonth;
                                        if ($search_keyword !== '') $sort_params['search'] = $search_keyword;
                                        $sort_params['sort'] = 'type';
                                        $sort_params['order'] = ($_GET['sort'] ?? '') === 'type' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc';
                                        echo http_build_query($sort_params);
                                        ?>">
                                                类型
                                                <?php if (($_GET['sort'] ?? '') === 'type'): ?>
                                                    <i class="bi bi-chevron-<?php echo ($_GET['order'] ?? 'asc') === 'asc' ? 'up' : 'down'; ?> small"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th width="10%" class="sortable-header">
                                            <a href="?<?php 
                                        $sort_params = [];
                                        if ($filter_type !== '') $sort_params['type'] = $filter_type;
                                        if ($yearmonth !== '') $sort_params['yearmonth'] = $yearmonth;
                                        if ($search_keyword !== '') $sort_params['search'] = $search_keyword;
                                        $sort_params['sort'] = 'size';
                                        $sort_params['order'] = ($_GET['sort'] ?? '') === 'size' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc';
                                        echo http_build_query($sort_params);
                                        ?>">
                                                大小
                                                <?php if (($_GET['sort'] ?? '') === 'size'): ?>
                                                    <i class="bi bi-chevron-<?php echo ($_GET['order'] ?? 'asc') === 'asc' ? 'up' : 'down'; ?> small"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th width="15%">关联文档</th>
                                        <th width="15%" class="sortable-header">
                                            <a href="?<?php 
                                        $sort_params = [];
                                        if ($filter_type !== '') $sort_params['type'] = $filter_type;
                                        if ($yearmonth !== '') $sort_params['yearmonth'] = $yearmonth;
                                        if ($search_keyword !== '') $sort_params['search'] = $search_keyword;
                                        $sort_params['sort'] = 'user';
                                        $sort_params['order'] = ($_GET['sort'] ?? '') === 'user' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc';
                                        echo http_build_query($sort_params);
                                        ?>">
                                                上传者
                                                <?php if (($_GET['sort'] ?? '') === 'user'): ?>
                                                    <i class="bi bi-chevron-<?php echo ($_GET['order'] ?? 'asc') === 'asc' ? 'up' : 'down'; ?> small"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th width="15%" class="sortable-header">
                                            <a href="?<?php 
                                        $sort_params = [];
                                        if ($filter_type !== '') $sort_params['type'] = $filter_type;
                                        if ($yearmonth !== '') $sort_params['yearmonth'] = $yearmonth;
                                        if ($search_keyword !== '') $sort_params['search'] = $search_keyword;
                                        $sort_params['sort'] = 'date';
                                        $sort_params['order'] = ($_GET['sort'] ?? '') === 'date' && ($_GET['order'] ?? '') === 'asc' ? 'desc' : 'asc';
                                        echo http_build_query($sort_params);
                                        ?>">
                                                上传时间
                                                <?php if (($_GET['sort'] ?? '') === 'date'): ?>
                                                    <i class="bi bi-chevron-<?php echo ($_GET['order'] ?? 'asc') === 'asc' ? 'up' : 'down'; ?> small"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-arrow-down-up small text-muted"></i>
                                                <?php endif; ?>
                                            </a>
                                        </th>
                                        <th width="12%" class="text-center">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($files as $index => $file): ?>
                                        <tr id="file-<?php echo $file['id']; ?>" class="file-row">
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input file-checkbox" value="<?php echo $file['id']; ?>">
                                            </td>
                                            <td class="text-center">
                                                <?php if ($file['file_type'] === 'image'): ?>
                                                    <?php
                                                    $image_url = ltrim($file['file_path'], '/'); // 移除开头的斜杠避免重复
                                                    ?>
                                                    <img src="<?php echo htmlspecialchars('/' . $image_url); ?>" 
                                                         alt="<?php echo htmlspecialchars(basename($file['file_path'])); ?>"
                                                         class="img-thumbnail img-fluid"
                                                         style="width: 40px; height: 40px; object-fit: cover; cursor: pointer;"
                                                         data-bs-toggle="modal" 
                                                         data-bs-target="#imageModal"
                                                         data-image-src="<?php echo htmlspecialchars('/' . $image_url); ?>"
                                                         data-image-name="<?php echo htmlspecialchars(basename($file['file_path'])); ?>">
                                                <?php else: ?>
                                                    <i class="bi <?php echo get_file_icon($file['file_type']); ?> file-icon" 
                                                       style="color: <?php echo get_file_color($file['file_type']); ?>"></i>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="fw-bold">
                                                    <?php if (!empty($file['alias'])): ?>
                                                        <?php echo htmlspecialchars($file['alias']); ?>
                                                        <br>
                                                        <small class="text-muted">(存储名: <?php echo htmlspecialchars(basename($file['file_path'])); ?>)</small>
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars(basename($file['file_path'])); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($file['description']): ?>
                                                    <small class="text-muted"><?php echo htmlspecialchars($file['description']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?php echo strtoupper($file['file_format']); ?></span>
                                                <br>
                                                <small class="text-muted"><?php echo get_file_type_chinese($file['file_type']); ?></small>
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
                        <?php 
                        // 构建查询参数 - 保留所有当前筛选条件
                        $query_params = [];
                        
                        // 保留必要的筛选参数
                        if (!empty($filter_type)) {
                            $query_params['type'] = $filter_type;
                        }
                        if (!empty($yearmonth)) {
                            $query_params['yearmonth'] = $yearmonth;
                        }
                        if (!empty($search_keyword)) {
                            $query_params['search'] = $search_keyword;
                        }
                        if (!empty($sort)) {
                            $query_params['sort'] = $sort;
                        }
                        if (!empty($order)) {
                            $query_params['order'] = $order;
                        }
                        
                        // 构建基础查询字符串
                        $base_query = http_build_query($query_params);
                        $query_prefix = $base_query ? '?' . $base_query . '&' : '?';
                        ?>
                        
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $query_prefix; ?>page=<?php echo $page - 1; ?>">上一页</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $query_prefix; ?>page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?php echo $query_prefix; ?>page=<?php echo $page + 1; ?>">下一页</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
</div>
        </div>
    </div>

    <!-- 图片预览模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">图片预览</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="" class="img-fluid" style="max-height: 70vh;">
                </div>
                <div class="modal-footer">
                    <a id="downloadImage" href="" class="btn btn-primary" download>
                        <i class="bi bi-download"></i> 下载图片
                    </a>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                </div>
            </div>
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
        console.log('点击删除按钮，fileId:', fileId, 'fileName:', fileName);
        console.log('当前时间:', new Date().toLocaleTimeString());
        
        // 添加调试：检查是否重复触发
        if (window.lastDeleteClick && (Date.now() - window.lastDeleteClick < 1000)) {
            console.log('检测到重复点击，忽略');
            return;
        }
        window.lastDeleteClick = Date.now();
        
        if (confirm(`确定要删除文件 "${fileName}" 吗？此操作不可恢复！`)) {
            console.log('用户第一次确认');
            if (confirm(`再次确认：真的要永久删除 "${fileName}" 吗？`)) {
                console.log('用户二次确认，开始删除');
                // 使用AJAX异步删除，保持页面位置
                fetch('delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: `id=${fileId}`
                })
                .then(response => response.json())
                .then(data => {
                    console.log('AJAX响应:', data);
                    if (data.success) {
                        // 成功删除，隐藏文件行
                        const fileRow = document.getElementById(`file-${fileId}`);
                        if (fileRow) {
                            fileRow.style.transition = 'opacity 0.3s ease';
                            fileRow.style.opacity = '0';
                            setTimeout(() => {
                                fileRow.remove();
                                updateFileCount();
                            }, 300);
                        }
                        
                        // 显示成功消息
                        showMessage('文件删除成功', 'success');
                        
                        // 如果当前页没有文件了，重新加载页面
                        const remainingFiles = document.querySelectorAll('.file-row').length;
                        if (remainingFiles === 0) {
                            // 延迟重新加载，让用户看到删除动画
                            setTimeout(() => {
                                location.reload();
                            }, 500);
                        }
                    } else {
                        showMessage(data.error || '删除失败', 'danger');
                    }
                })
                .catch(error => {
                    console.error('AJAX错误:', error);
                    showMessage('删除文件时发生错误', 'danger');
                });
            } else {
                console.log('用户取消二次确认');
            }
            }
        }

        // 显示消息提示
        function showMessage(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(alertDiv);
            
            // 3秒后自动关闭
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }

        // 更新文件计数
        function updateFileCount() {
            const totalFiles = document.querySelectorAll('.file-row').length;
            const totalElement = document.querySelector('.text-muted');
            if (totalElement) {
                const match = totalElement.textContent.match(/共 (\d+) 个文件/);
                if (match) {
                    const oldTotal = parseInt(match[1]);
                    const newTotal = oldTotal - 1;
                    totalElement.textContent = totalElement.textContent.replace(/共 \d+ 个文件/, `共 ${newTotal} 个文件`);
                }
            }
        }

        // 批量选择功能
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const fileCheckboxes = document.querySelectorAll('.file-checkbox');
            const batchActions = document.getElementById('batchActions');

            // 全选/取消全选
            selectAllCheckbox.addEventListener('change', function() {
                fileCheckboxes.forEach(checkbox => {
                    checkbox.checked = selectAllCheckbox.checked;
                });
                updateBatchActions();
            });

            // 单个选择框变化
            fileCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const checkedCount = document.querySelectorAll('.file-checkbox:checked').length;
                    selectAllCheckbox.checked = checkedCount === fileCheckboxes.length;
                    updateBatchActions();
                });
            });

            // 更新批量操作按钮显示
            function updateBatchActions() {
                const checkedCount = document.querySelectorAll('.file-checkbox:checked').length;
                if (checkedCount > 0) {
                    batchActions.style.display = 'block';
                } else {
                    batchActions.style.display = 'none';
                }
            }
        });

        // 批量删除
        function batchDelete() {
            const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selectedFiles.length === 0) {
                alert('请选择要删除的文件');
                return;
            }

            // 防止重复触发
            if (window.lastBatchDelete && (Date.now() - window.lastBatchDelete < 2000)) {
                console.log('检测到重复批量删除，忽略');
                return;
            }
            window.lastBatchDelete = Date.now();

            if (confirm(`确定要删除选中的 ${selectedFiles.length} 个文件吗？此操作不可恢复！`)) {
                if (confirm(`再次确认：真的要永久删除这 ${selectedFiles.length} 个文件吗？`)) {
                let deletedCount = 0;
                const totalCount = selectedFiles.length;
                
                function deleteNextFile() {
                    if (deletedCount < totalCount) {
                        const fileId = selectedFiles[deletedCount];
                        
                        fetch('delete.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: `id=${fileId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // 成功删除，隐藏文件行
                                const fileRow = document.getElementById(`file-${fileId}`);
                                if (fileRow) {
                                    fileRow.style.transition = 'opacity 0.3s ease';
                                    fileRow.style.opacity = '0';
                                    setTimeout(() => {
                                        fileRow.remove();
                                        updateFileCount();
                                    }, 300);
                                }
                                
                                deletedCount++;
                                
                                if (deletedCount === totalCount) {
                                    // 所有文件删除完成
                                    showMessage(`成功删除 ${totalCount} 个文件`, 'success');
                                    
                                    // 如果当前页没有文件了，重新加载页面
                                    const remainingFiles = document.querySelectorAll('.file-row').length;
                                    if (remainingFiles === 0) {
                                        setTimeout(() => {
                                            location.reload();
                                        }, 500);
                                    }
                                    
                                    // 重置全选框和批量操作按钮
                                    document.getElementById('selectAll').checked = false;
                                    document.getElementById('batchActions').style.display = 'none';
                                } else {
                                    // 继续删除下一个文件
                                    deleteNextFile();
                                }
                            } else {
                                showMessage(`删除文件 ${fileId} 失败: ${data.error}`, 'danger');
                                deletedCount++;
                                deleteNextFile();
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            showMessage(`删除文件 ${fileId} 时发生错误`, 'danger');
                            deletedCount++;
                            deleteNextFile();
                        });
                    }
                }
                
                // 开始批量删除
                deleteNextFile();
            } else {
                console.log('用户取消批量删除二次确认');
            }
        }
    }

        // 批量下载
        function batchDownload() {
            const selectedFiles = Array.from(document.querySelectorAll('.file-checkbox:checked'))
                .map(cb => cb.value);
            
            if (selectedFiles.length === 0) {
                alert('请选择要下载的文件');
                return;
            }

            // 这里可以添加批量下载的逻辑
            alert(`正在准备下载 ${selectedFiles.length} 个文件...\n文件ID: ${selectedFiles.join(', ')}`);
        }

        // 图片预览功能
        document.addEventListener('DOMContentLoaded', function() {
            const imageModal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');
            const downloadImage = document.getElementById('downloadImage');
            const imageModalLabel = document.getElementById('imageModalLabel');

            imageModal.addEventListener('show.bs.modal', function(event) {
                const thumbnail = event.relatedTarget;
                const imageSrc = thumbnail.getAttribute('data-image-src');
                const imageName = thumbnail.getAttribute('data-image-name');
                
                modalImage.src = imageSrc;
                modalImage.alt = imageName;
                downloadImage.href = imageSrc;
                imageModalLabel.textContent = imageName;
            });
        });

        // 自动隐藏成功消息
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 3000);
    </script>
</body>
</html>