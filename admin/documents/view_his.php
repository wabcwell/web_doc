<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/DocumentTree.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID和版本参数
$id = $_GET['id'] ?? null;
$version_id = $_GET['version'] ?? null;

if (!$id) {
    header('Location: index.php?error=未指定文档ID');
    exit;
}

$db = get_db();

// 检查文档在documents_version表中是否存在
$stmt = $db->prepare("SELECT COUNT(*) FROM documents_version WHERE document_id = ?");
$stmt->execute([$id]);
$doc_exists = $stmt->fetchColumn();

if (!$doc_exists) {
    header('Location: index.php?error=文档不存在或无历史版本');
    exit;
}

// 获取分页参数
$show_all = isset($_GET['show_all']) && $_GET['show_all'] == '1';
$limit = $show_all ? null : 10; // 默认显示10条，查看全部时不限制

// 获取所有历史版本用于计数
$count_stmt = $db->prepare("SELECT COUNT(*) FROM documents_version WHERE document_id = ?");
$count_stmt->execute([$id]);
$total_versions = $count_stmt->fetchColumn();

// 获取分页后的历史版本
$sql = "SELECT dv.*, u.username FROM documents_version dv LEFT JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? ORDER BY dv.version_number DESC";
if ($limit) {
    $sql .= " LIMIT ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$id, $limit]);
} else {
    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);
}
$versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取操作记录
$stmt = $db->prepare("SELECT el.*, u.username FROM edit_log el LEFT JOIN users u ON el.user_id = u.id WHERE el.document_id = ? ORDER BY el.created_at DESC");
$stmt->execute([$id]);
$edit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取当前显示的内容
if ($version_id && is_numeric($version_id)) {
    // 显示指定版本内容
    $stmt = $db->prepare("SELECT dv.*, u.username FROM documents_version dv LEFT JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? AND dv.id = ?");
    $stmt->execute([$id, $version_id]);
    $current_content = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // 显示最新版本内容（从documents_version表获取）
    $stmt = $db->prepare("SELECT dv.*, u.username FROM documents_version dv LEFT JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? ORDER BY dv.version_number DESC LIMIT 1");
    $stmt->execute([$id]);
    $current_content = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 如果documents_version表中没有数据，给出提示
if (!$current_content) {
    die('该文档暂无历史版本数据');
}

$title = '查看历史 - ' . htmlspecialchars($current_content['title'] ?? '未知文档');
include '../sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="../../assets/css/static/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../admin/assets/ueditorplus/third-party/SyntaxHighlighter/shCoreDefault.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .container-fluid {
            padding-left: 0;
            padding-right: 0;
            margin-left: 0;
            margin-right: 0;
        }
        
        /* 左侧文档内容区域 */
        .document-content-area {
            flex: 1;
            padding-right: 0;
        }
        
        /* 右侧文档信息（操作）区域 */
        .document-info-area {
            width: 300px;
            padding-left: 20px;
        }
        
        /* 在小屏幕上调整布局 */
        @media (max-width: 1127px) {
            .document-info-area {
                width: 100%;
                padding-left: 0;
                margin-top: 20px;
            }
            
            .document-content-area {
                padding-right: 0;
            }
        }
        
        .document-content img {
            max-width: 100%;
            height: auto !important;
            display: block;
            margin: 1rem 0;
            object-fit: contain;
        }
        
        .document-content {
            overflow-wrap: break-word;
            word-wrap: break-word;
            max-width: 100%;
        }
        
        .document-content table {
            max-width: 100%;
            overflow-x: auto;
            display: block;
        }
        
        /* 防止图片变形 */
        .document-content img:not([height]) {
            height: auto !important;
        }
        
        /* 确保容器不溢出 */
        .document-content > * {
            max-width: 100%;
        }
        
        /* 为.col-md-8添加margin-right以避免与.col-md-4重叠 */
        @media (min-width: 768px) {
            .col-md-8 {
                margin-right: 30%; /* 使用相对单位而不是绝对像素值 */
            }
            
            /* 使card填满col-md-8的宽度 */
            .col-md-8 .card {
                width: 100%;
                margin-right: 0;
            }
        }
        
        @media (max-width: 768px) {
            .document-info-area {
                position: relative;
                width: auto;
                right: 0;
                margin-top: 20px;
            }
            
            .document-content-area {
                margin-right: 0;
            }
            
            .document-content-area .card {
                width: 100%;
                margin-right: 0;
            }
        }
        
        .version-item.active {
            background-color: #f0f8ff;
            border-left: 3px solid #007bff;
            color: #212529;
        }
        .version-item.active .text-muted,
        .version-item.active small {
            color: #6c757d !important;
        }
        .log-item {
            border-left: 3px solid #ddd;
        }
        .log-item.status-created {
            border-left-color: #28a745;
        }
        .log-item.status-updated {
            border-left-color: #ffc107;
        }
        .log-item.status-deleted {
            border-left-color: #dc3545;
        }
        .log-item.status-rollback {
            border-left-color: #17a2b8;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <!-- 页面标题 -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1><i class="bi bi-clock-history"></i> <?php echo htmlspecialchars($current_content['title'] ?? '未知文档'); ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">文档管理</a></li>
                            <li class="breadcrumb-item active">历史版本</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="view.php?id=<?php echo $id; ?>" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> 返回查看
                    </a>
                </div>
            </div>

            <!-- 文档信息卡片 -->
            <div class="d-flex flex-row flex-wrap">
                <div class="document-content-area">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-file-text"></i> 文档内容</h5>
                            <div>
                                <span class="badge bg-secondary">
                                    版本 <?php echo $current_content['version_number'] ?? '最新'; ?>
                                </span>
                                <?php if ($version_id && is_numeric($version_id)): ?>
                                    <span class="badge bg-info ms-2">
                                        <?php echo date('Y-m-d H:i', strtotime($current_content['created_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-header bg-light">
                            <h4 class="mb-0"><?php echo htmlspecialchars($current_content['title'] ?? '未知文档'); ?></h4>
                        </div>
                        <div class="card-body">
                            <div class="document-content">
                                <?php 
                                $content = $current_content['content'];
                                
                                // 智能判断内容格式：如果是HTML则直接输出，否则使用Markdown解析
                                $trimmed_content = trim($content);
                                $is_html = false;
                                
                                // 检查内容是否以HTML标签开头
                                if (preg_match('/^\s*<[a-zA-Z][^>]*>/', $trimmed_content)) {
                                    $is_html = true;
                                }
                                
                                // 检查是否包含完整的HTML结构
                                if (stripos($trimmed_content, '<html') !== false || 
                                    stripos($trimmed_content, '<body') !== false ||
                                    stripos($trimmed_content, '<div') !== false) {
                                    $is_html = true;
                                }
                                
                                if ($is_html) {
                                    // 直接输出HTML内容
                                    echo $content;
                                } else {
                                    // 使用Markdown解析
                                    require_once '../../Parsedown.php';
                                    $Parsedown = new Parsedown();
                                    echo $Parsedown->text($content);
                                }
                                ?>
                            </div>
                            
                            <?php if (!empty($current_content['tags'])): ?>
                            <hr>
                            <div class="document-tags">
                                <h6 class="mb-2">
                                    <i class="bi bi-tags"></i> 标签
                                </h6>
                                <div>
                                    <?php 
                                    $tags = array_map('trim', explode(',', $current_content['tags']));
                                    $tags = array_filter($tags);
                                    foreach ($tags as $tag): 
                                        if (trim($tag)): ?>
                                        <span class="badge bg-light text-dark border me-1 mb-1">
                                            <i class="bi bi-hash"></i> <?php echo htmlspecialchars(trim($tag)); ?>
                                        </span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="document-info-area">
                    <!-- 历史版本列表 -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-clock-history"></i> 历史版本</h5>
                            <?php if (!$show_all && $total_versions > 10): ?>
                                <a href="?id=<?php echo $id; ?>&show_all=1" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-list"></i> 查看所有
                                </a>
                            <?php elseif ($show_all && $total_versions > 10): ?>
                                <a href="?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-chevron-up"></i> 收起
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php 
                                // 显示分页后的版本记录
                                foreach ($versions as $index => $version): 
                                    $is_latest = $index === 0 && $show_all; // 只有在查看全部时才标记第一条为最新
                                    $is_active = $version_id == $version['id'] || (!$version_id && $index === 0);
                                    $display_index = $show_all ? $index : $index; // 调整索引计算
                                ?>
                                    <a href="?id=<?php echo $id; ?>&version=<?php echo $version['id']; ?><?php echo $show_all ? '&show_all=1' : ''; ?>" 
                                       class="list-group-item list-group-item-action version-item <?php echo $is_active ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between align-items-center">
                                            <h6 class="mb-1">版本 <?php echo $version['version_number']; ?></h6>
                                            <?php if ($is_latest): ?>
                                                <span class="badge bg-success">最新</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex w-100 justify-content-between">
                                            <small class="text-muted"><?php echo date('Y-m-d H:i', strtotime($version['created_at'])); ?></small>
                                            <small class="text-muted"><?php echo htmlspecialchars($version['username']); ?></small>
                                        </div>
                                        
                                        <?php if ($is_active && $index > 0): ?>
                                             <div class="mt-2">
                                                 <button type="button" 
                                                         class="btn btn-sm btn-outline-primary rollback-btn"
                                                         data-version-id="<?php echo $version['id']; ?>"
                                                         data-version-number="<?php echo $version['version_number']; ?>">
                                                     <i class="bi bi-arrow-clockwise"></i> 回滚到此版本
                                                 </button>
                                             </div>
                                         <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                                
                                <?php if (!$show_all && $total_versions > 10): ?>
                                    <div class="list-group-item text-center">
                                        <a href="?id=<?php echo $id; ?>&show_all=1" class="text-decoration-none">
                                            查看全部 <?php echo $total_versions; ?> 个版本
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- 操作记录 -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="bi bi-list-ul"></i> 操作记录</h5>
                            <a href="edit_log.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-list"></i> 查看全部
                            </a>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php 
                                $recent_logs = array_slice($edit_logs, 0, 5);
                                foreach ($recent_logs as $log): 
                                ?>
                                    <div class="list-group-item log-item status-<?php echo htmlspecialchars($log['action']); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <small class="text-muted"><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1 small">
                                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                            <?php 
                                            switch($log['action']) {
                                                case 'create':
                                                    echo '创建了文档';
                                                    break;
                                                case 'update':
                                                    echo '更新了文档';
                                                    break;
                                                case 'delete':
                                                    echo '删除了文档';
                                                    break;
                                                case 'rollback':
                                                    echo '回滚了版本';
                                                    break;
                                                default:
                                                    echo $log['action'];
                                            }
                                            ?>
                                        </p>
                                        <?php if ($log['action'] === 'update'): ?>
                                            <?php
                                            $changes = [];
                                            if ($log['op_title'] == 1) $changes[] = '标题';
                                            if ($log['op_content'] == 1) $changes[] = '内容';
                                            if ($log['op_tags'] == 1) $changes[] = '标签';
                                            if ($log['op_parent'] == 1) $changes[] = '父文档';
                                            if ($log['op_corder'] == 1) $changes[] = '排序';
                                            if ($log['op_public'] == 1) $changes[] = '设为公开';
                                            elseif ($log['op_public'] == 2) $changes[] = '设为私有';
                                            if ($log['op_formal'] == 1) $changes[] = '设为正式';
                                            elseif ($log['op_formal'] == 2) $changes[] = '设为草稿';
                                            
                                            if (!empty($changes)): ?>
                                                <small class="text-muted">
                                                    变更：<?php echo implode('、', $changes); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <?php if (count($edit_logs) > 5): ?>
                                    <div class="list-group-item text-center">
                                        <a href="edit_log.php?id=<?php echo $id; ?>" class="text-decoration-none">
                                            查看全部 <?php echo count($edit_logs); ?> 条记录
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 回滚确认模态框 -->
    <div class="modal fade" id="rollbackModal" tabindex="-1" aria-labelledby="rollbackModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="rollbackModalLabel">确认回滚</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要将文档回滚到版本 <strong id="rollbackVersionNumber"></strong> 吗？</p>
                    <p class="text-warning mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        此操作将创建一个新的版本，当前内容不会丢失。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <form id="rollbackForm" method="post" action="rollback.php" style="display: inline;">
                        <input type="hidden" name="document_id" value="<?php echo $id; ?>">
                        <input type="hidden" name="version_id" id="rollbackVersionId" value="">
                        <button type="submit" class="btn btn-primary">确认回滚</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/js/static/bootstrap.bundle.min.js"></script>
    <script src="../../admin/assets/ueditorplus/third-party/SyntaxHighlighter/shCore.js"></script>
    
    <script>
        // 回滚确认函数
        document.addEventListener('DOMContentLoaded', function() {
            const rollbackBtns = document.querySelectorAll('.rollback-btn');
            const rollbackModal = new bootstrap.Modal(document.getElementById('rollbackModal'));
            
            rollbackBtns.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const versionId = this.getAttribute('data-version-id');
                    const versionNumber = this.getAttribute('data-version-number');
                    
                    document.getElementById('rollbackVersionId').value = versionId;
                    document.getElementById('rollbackVersionNumber').textContent = versionNumber;
                    
                    rollbackModal.show();
                });
            });

            // 初始化SyntaxHighlighter
            if (typeof SyntaxHighlighter !== 'undefined') {
                SyntaxHighlighter.highlight();
            }
        });
    </script>
</body>
</html>