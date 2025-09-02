<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/DocumentTree.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?error=未指定文档ID');
    exit;
}

$db = get_db();
$tree = new DocumentTree($db);

// 获取文档详情
$stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.document_id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在');
    exit;
}

// 获取面包屑导航
$breadcrumbs = $tree->getBreadcrumbs($id);

// 获取操作记录（分页）
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取总记录数
$stmt = $db->prepare("SELECT COUNT(*) FROM edit_log WHERE document_id = ?");
$stmt->execute([$id]);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// 获取操作记录
$stmt = $db->prepare("SELECT el.*, u.username FROM edit_log el LEFT JOIN users u ON el.user_id = u.id WHERE el.document_id = ? ORDER BY el.created_at DESC LIMIT ? OFFSET ?");
$stmt->execute([$id, $per_page, $offset]);
$edit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$title = '操作记录 - ' . htmlspecialchars($document['title'] ?? '未知文档');
include '../sidebar.php';

/**
 * 获取变更描述的辅助函数
 */
function get_change_description($log) {
    if ($log['action'] !== 'update') {
        return '';
    }
    
    $changes = [];
    
    // 标题变更
    if ($log['op_title'] == 1) {
        $changes[] = '标题';
    }
    
    // 内容变更
    if ($log['op_content'] == 1) {
        $changes[] = '内容';
    }
    
    // 标签变更
    if ($log['op_tags'] == 1) {
        $changes[] = '标签';
    }
    
    // 父文档变更
    if ($log['op_parent'] == 1) {
        $changes[] = '父文档';
    }
    
    // 排序变更
    if ($log['op_corder'] == 1) {
        $changes[] = '排序权重';
    }
    
    // 公开状态变更
    if ($log['op_public'] == 1) {
        $changes[] = '设为公开';
    } elseif ($log['op_public'] == 2) {
        $changes[] = '设为私有';
    }
    
    // 正式状态变更
    if ($log['op_formal'] == 1) {
        $changes[] = '设为正式文档';
    } elseif ($log['op_formal'] == 2) {
        $changes[] = '设为草稿';
    }
    
    if (empty($changes)) {
        return '';
    }
    
    return '变更了：' . implode('、', $changes);
}

/**
 * 获取操作图标
 */
function get_action_icon($action) {
    switch($action) {
        case 'create':
            return 'bi-plus-circle';
        case 'update':
            return 'bi-pencil';
        case 'delete':
            return 'bi-trash';
        case 'restore':
            return 'bi-arrow-counterclockwise';
        case 'rollback':
            return 'bi-arrow-clockwise';
        default:
            return 'bi-info-circle';
    }
}

/**
 * 获取操作颜色
 */
function get_action_color($action) {
    switch($action) {
        case 'create':
            return 'success';
        case 'update':
            return 'warning';
        case 'delete':
            return 'danger';
        case 'restore':
            return 'primary';
        case 'rollback':
            return 'info';
        default:
            return 'secondary';
    }
}

/**
 * 获取操作中文描述
 */
function get_action_text($action) {
    switch($action) {
        case 'create':
            return '创建了文档';
        case 'update':
            return '更新了文档';
        case 'delete':
            return '删除了文档';
        case 'restore':
            return '恢复了文档';
        case 'rollback':
            return '回滚了版本';
        default:
            return $action;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .log-item {
            border-left: 3px solid #ddd;
            transition: all 0.3s ease;
        }
        .log-item:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .log-item.status-create {
            border-left-color: #28a745;
        }
        .log-item.status-update {
            border-left-color: #ffc107;
        }
        .log-item.status-delete {
            border-left-color: #dc3545;
        }
        .log-item.status-rollback {
            border-left-color: #17a2b8;
        }
        .change-detail {
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 8px 12px;
            margin-top: 8px;
            font-size: 0.875rem;
            color: #6c757d;
        }
        .timeline {
            position: relative;
        }
        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-left: 50px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 13px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid;
        }
        .timeline-item.status-create::before {
            border-color: #28a745;
        }
        .timeline-item.status-update::before {
            border-color: #ffc107;
        }
        .timeline-item.status-delete::before {
            border-color: #dc3545;
        }
        .timeline-item.status-rollback::before {
            border-color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- 页面标题 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="bi bi-list-ul"></i> <?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">文档管理</a></li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $crumb['document_id']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active">操作记录</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> 返回查看
                    </a>
                    <a href="view_his.php?id=<?php echo $id; ?>" class="btn btn-secondary ms-2">
                        <i class="bi bi-clock-history"></i> 历史版本
                    </a>
                </div>
            </div>

            <!-- 统计信息 -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $total_records; ?></h5>
                            <p class="card-text text-muted">总操作数</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo count(array_filter($edit_logs, fn($log) => $log['action'] === 'create')); ?></h5>
                            <p class="card-text text-muted">创建</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo count(array_filter($edit_logs, fn($log) => $log['action'] === 'update')); ?></h5>
                            <p class="card-text text-muted">更新</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo count(array_filter($edit_logs, fn($log) => $log['action'] === 'delete')); ?></h5>
                            <p class="card-text text-muted">删除</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo count(array_filter($edit_logs, fn($log) => $log['action'] === 'restore')); ?></h5>
                            <p class="card-text text-muted">恢复</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo count(array_filter($edit_logs, fn($log) => $log['action'] === 'rollback')); ?></h5>
                            <p class="card-text text-muted">回滚</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 操作记录时间线 -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">操作记录时间线</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($edit_logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-1 text-muted"></i>
                            <p class="text-muted mt-3">暂无操作记录</p>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($edit_logs as $log): ?>
                                <div class="timeline-item status-<?php echo htmlspecialchars($log['action']); ?>">
                                    <div class="card log-item status-<?php echo htmlspecialchars($log['action']); ?>">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <i class="bi <?php echo get_action_icon($log['action']); ?> text-<?php echo get_action_color($log['action']); ?>"></i>
                                                        <?php echo get_action_text($log['action']); ?>
                                                    </h6>
                                                    <p class="mb-1">
                                                        <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                                        <small class="text-muted">· <?php echo date('Y年m月d日 H:i', strtotime($log['created_at'])); ?></small>
                                                    </p>
                                                </div>
                                                <span class="badge bg-<?php echo get_action_color($log['action']); ?>">
                                                    <?php echo strtoupper($log['action']); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($log['action'] === 'update'): ?>
                                                <?php $change_desc = get_change_description($log); ?>
                                                <?php if (!empty($change_desc)): ?>
                                                    <div class="change-detail">
                                                        <i class="bi bi-info-circle"></i>
                                                        <?php echo $change_desc; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="change-detail">
                                                        <i class="bi bi-info-circle"></i>
                                                        进行了更新操作（未检测到具体变更）
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="分页导航" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page - 1; ?>">上一页</a>
                                    </li>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $id; ?>&page=<?php echo $page + 1; ?>">下一页</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>