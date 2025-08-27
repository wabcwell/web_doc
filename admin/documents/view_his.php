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
$tree = new DocumentTree($db);

// 获取文档详情
$stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在');
    exit;
}

// 获取面包屑导航
$breadcrumbs = $tree->getBreadcrumbs($id);

// 获取所有历史版本
$stmt = $db->prepare("SELECT dv.*, u.username FROM documents_version dv LEFT JOIN users u ON dv.created_by = u.id WHERE dv.document_id = ? ORDER BY dv.version_number DESC");
$stmt->execute([$id]);
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
    // 显示最新版本内容
    $current_content = $document;
}

$title = '查看历史 - ' . htmlspecialchars($document['title'] ?? '未知文档');
include '../sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
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
                    <h1><?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="index.php">文档管理</a></li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                            <?php endforeach; ?>
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
            <div class="row">
                <!-- 左侧：文档内容 -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">文档内容</h5>
                            <div>
                                <span class="badge bg-secondary">
                                    版本 <?php echo $current_content['version_number'] ?? '最新'; ?>
                                </span>
                                <?php if ($current_content['id'] != $document['id']): ?>
                                    <span class="badge bg-info ms-2">
                                        <?php echo date('Y-m-d H:i', strtotime($current_content['created_at'])); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="markdown-content">
                                <?php 
                                require_once '../../Parsedown.php';
                                $Parsedown = new Parsedown();
                                echo $Parsedown->text($current_content['content']);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 右侧：历史版本和操作记录 -->
                <div class="col-md-4">
                    <!-- 历史版本列表 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">历史版本</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <a href="?id=<?php echo $id; ?>" 
                                   class="list-group-item list-group-item-action version-item <?php echo !$version_id ? 'active' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">最新版本</h6>
                                        <small><?php echo date('m-d H:i', strtotime($document['updated_at'])); ?></small>
                                    </div>
                                    <p class="mb-1 small">当前版本</p>
                                </a>
                                
                                <?php foreach ($versions as $version): ?>
                                    <a href="?id=<?php echo $id; ?>&version=<?php echo $version['id']; ?>" 
                                       class="list-group-item list-group-item-action version-item <?php echo $version_id == $version['id'] ? 'active' : ''; ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">版本 <?php echo $version['version_number']; ?></h6>
                                            <small><?php echo date('m-d H:i', strtotime($version['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1 small">作者: <?php echo htmlspecialchars($version['username']); ?></p>
                                        
                                        <?php if ($version_id == $version['id'] && $version['version_number'] < count($versions)): ?>
                                             <div class="mt-2">
                                                 <button type="button" 
                                                         class="btn btn-sm btn-outline-primary rollback-btn"
                                                         data-version-id="<?php echo $version['id']; ?>"
                                                         data-version-number="<?php echo $version['version_number']; ?>">
                                                     <i class="bi bi-arrow-clockwise"></i> 回滚到此版本
                                                 </button>
                                             </div>
                                         <?php elseif (!$version_id && $version['version_number'] == count($versions) - 1): ?>
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
                            </div>
                        </div>
                    </div>

                    <!-- 操作记录 -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">操作记录</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($edit_logs as $log): ?>
                                    <div class="list-group-item log-item status-<?php echo htmlspecialchars($log['action']); ?>">
                                        <div class="d-flex w-100 justify-content-between">
                                            <small class="text-muted"><?php echo date('m-d H:i', strtotime($log['created_at'])); ?></small>
                                        </div>
                                        <p class="mb-1 small">
                                            <strong><?php echo htmlspecialchars($log['username']); ?></strong>
                                            <?php 
                                            switch($log['action']) {
                                                case 'created':
                                                    echo '创建了文档';
                                                    break;
                                                case 'updated':
                                                    echo '更新了文档';
                                                    break;
                                                case 'deleted':
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
                                    </div>
                                <?php endforeach; ?>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-sql.min.js"></script>
    
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
        });
    </script>
</body>
</html>