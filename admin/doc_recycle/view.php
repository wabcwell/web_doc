<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/DocumentTree.php';
require_once '../../Parsedown.php';

// 检查用户是否已登录
if (!check_login()) {
    header('Location: /admin/login.php');
    exit();
}

// 检查是否为管理员
$is_admin = check_admin();

// 获取文档ID
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($document_id <= 0) {
    $_SESSION['error'] = '无效的文档ID';
    header('Location: index.php');
    exit();
}

// 获取数据库连接
$db = get_db();
$documentTree = new DocumentTree($db);
$parsedown = new Parsedown();

// 获取已删除的文档信息
$stmt = $db->prepare("SELECT d.*, u.username 
                     FROM documents d 
                     LEFT JOIN users u ON d.user_id = u.id 
                     WHERE d.id = ? AND d.del_status = 1");
$stmt->execute([$document_id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    $_SESSION['error'] = '文档不存在或未被删除';
    header('Location: index.php');
    exit();
}

// 获取文档的版本历史
$stmt = $db->prepare("SELECT dv.*, u.username 
                     FROM documents_version dv 
                     LEFT JOIN users u ON dv.created_by = u.id 
                     WHERE dv.document_id = ? 
                     ORDER BY dv.version_number DESC");
$stmt->execute([$document_id]);
$versions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取文档的编辑日志
$stmt = $db->prepare("SELECT el.*, u.username 
                     FROM edit_log el 
                     LEFT JOIN users u ON el.user_id = u.id 
                     WHERE el.document_id = ? 
                     ORDER BY el.created_at DESC");
$stmt->execute([$document_id]);
$edit_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取文档的层级路径
$breadcrumbs = [];
$current_id = $document['parent_id'];
while ($current_id > 0) {
    $stmt = $db->prepare("SELECT id, title, parent_id FROM documents WHERE id = ? AND del_status = 0");
    $stmt->execute([$current_id]);
    $parent_doc = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($parent_doc) {
        array_unshift($breadcrumbs, $parent_doc);
        $current_id = $parent_doc['parent_id'];
    } else {
        break;
    }
}

$title = '查看已删除文档: ' . htmlspecialchars($document['title']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title); ?> - 管理后台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="admin-container">
        <?php require_once '../sidebar.php'; ?>
        <div class="main-content">

<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-file-text"></i> 查看已删除文档
                    </h5>
                    <div>
                        <a href="index.php" class="btn btn-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> 返回回收站
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">文档内容</h6>
                                </div>
                                <div class="card-body">
                                    <div class="document-path mb-3">
                                        <nav aria-label="breadcrumb">
                                            <ol class="breadcrumb">
                                                <li class="breadcrumb-item"><a href="index.php">回收站</a></li>
                                                <?php foreach ($breadcrumbs as $crumb): ?>
                                                    <li class="breadcrumb-item">
                                                        <a href="../../documents/view.php?id=<?php echo $crumb['id']; ?>" target="_blank">
                                                            <?php echo htmlspecialchars($crumb['title']); ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                                <li class="breadcrumb-item active"><?php echo htmlspecialchars($document['title']); ?></li>
                                            </ol>
                                        </nav>
                                    </div>
                                    
                                    <div class="document-content">
                                        <?php echo $parsedown->text($document['content']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">文档信息</h6>
                                </div>
                                <div class="card-body">
                                    <dl class="row mb-0">
                                        <dt class="col-sm-4">标题:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($document['title']); ?></dd>
                                        
                                        <dt class="col-sm-4">状态:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-danger">已删除</span>
                                        </dd>
                                        
                                        <dt class="col-sm-4">公开:</dt>
                                        <dd class="col-sm-8">
                                            <span class="badge bg-<?php echo $document['is_public'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $document['is_public'] ? '是' : '否'; ?>
                                            </span>
                                        </dd>
                                        
                                        <dt class="col-sm-4">创建者:</dt>
                                        <dd class="col-sm-8"><?php echo htmlspecialchars($document['username'] ?? '未知用户'); ?></dd>
                                        
                                        <dt class="col-sm-4">创建时间:</dt>
                                        <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?></dd>
                                        
                                        <dt class="col-sm-4">更新时间:</dt>
                                        <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['updated_at'])); ?></dd>
                                        
                                        <dt class="col-sm-4">删除时间:</dt>
                                        <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['deleted_at'])); ?></dd>
                                    </dl>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h6 class="mb-0">操作</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <button type="button" 
                                                class="btn btn-success" 
                                                onclick="showRestoreModal(<?php echo $document['id']; ?>, '<?php echo addslashes($document['title']); ?>', this)">
                                            <i class="bi bi-arrow-counterclockwise"></i> 恢复文档
                                        </button>
                                        <button type="button" 
                                                class="btn btn-danger" 
                                                onclick="showPermanentDeleteModal(<?php echo $document['id']; ?>, '<?php echo addslashes($document['title']); ?>', this)"
                                                <?php echo $is_admin ? '' : 'disabled'; ?>>
                                            <i class="bi bi-trash-fill"></i> 永久删除
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($versions)): ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="mb-0">版本历史</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>版本</th>
                                                <th>更新者</th>
                                                <th>更新时间</th>
                                                <th>操作</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($versions as $version): ?>
                                                <tr>
                                                    <td>v<?php echo $version['version_number']; ?></td>
                                                    <td><?php echo htmlspecialchars($version['username']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($version['created_at'])); ?></td>
                                                    <td>
                                                        <a href="../../documents/view_his.php?id=<?php echo $document['id']; ?>&version=<?php echo $version['version_number']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" 
                                                           target="_blank">
                                                            查看
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($edit_logs)): ?>
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">编辑日志</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>操作</th>
                                                <th>用户</th>
                                                <th>时间</th>
                                                <th>详情</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($edit_logs as $log): ?>
                                                <tr>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            switch($log['action']) {
                                                                case 'create': echo 'success'; break;
                                                                case 'update': echo 'warning'; break;
                                                                case 'delete': echo 'danger'; break;
                                                                case 'restore': echo 'info'; break;
                                                                default: echo 'secondary';
                                                            }
                                                        ?>">
                                                        <?php echo htmlspecialchars($log['action']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                                    <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                                    <td>
                                                        <?php if ($log['old_title'] != $log['new_title']): ?>
                                                            标题: <?php echo htmlspecialchars($log['old_title']); ?> → <?php echo htmlspecialchars($log['new_title']); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
    
    <!-- 恢复确认模态框 -->
    <div class="modal fade" id="restoreModal" tabindex="-1" aria-labelledby="restoreModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreModalLabel">确认恢复文档</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要恢复文档 <strong id="restoreDocumentTitle"></strong> 吗？</p>
                    <p class="text-info mb-0">
                        <i class="bi bi-info-circle-fill"></i> 
                        文档将从回收站恢复到原文档库，此操作不会涉及到子文档。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" id="confirmRestoreBtn" class="btn btn-success">
                        <i class="bi bi-arrow-counterclockwise"></i> 确认恢复
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 永久删除确认模态框 -->
    <div class="modal fade" id="permanentDeleteModal" tabindex="-1" aria-labelledby="permanentDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger" id="permanentDeleteModalLabel">确认永久删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要永久删除文档 <strong id="deleteDocumentTitle" class="text-danger"></strong> 吗？</p>
                    <p class="text-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        此操作不可撤销！文档将被永久删除，所有版本历史和编辑记录也将一并删除。
                    </p>
                    <div class="mt-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="confirmDeleteCheck">
                            <label class="form-check-label text-danger" for="confirmDeleteCheck">
                                我已了解此操作的风险，确认永久删除
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <button type="button" id="confirmPermanentDeleteBtn" class="btn btn-danger" disabled>
                        <i class="bi bi-trash-fill"></i> 永久删除
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/prism.min.js"></script>
    <script>
        // 全局变量存储当前操作信息
        let currentDocumentId = null;
        let currentDocumentTitle = null;
        let currentButtonElement = null;

        // 恢复模态框相关
        const restoreModal = new bootstrap.Modal(document.getElementById('restoreModal'));
        document.getElementById('confirmRestoreBtn').addEventListener('click', function() {
            if (currentDocumentId && currentButtonElement) {
                performRestore(currentDocumentId, currentDocumentTitle, currentButtonElement);
                restoreModal.hide();
            }
        });

        // 永久删除模态框相关
        const permanentDeleteModal = new bootstrap.Modal(document.getElementById('permanentDeleteModal'));
        const confirmDeleteCheck = document.getElementById('confirmDeleteCheck');
        const confirmPermanentDeleteBtn = document.getElementById('confirmPermanentDeleteBtn');

        confirmDeleteCheck.addEventListener('change', function() {
            confirmPermanentDeleteBtn.disabled = !this.checked;
        });

        document.getElementById('confirmPermanentDeleteBtn').addEventListener('click', function() {
            if (currentDocumentId && currentButtonElement) {
                performPermanentDelete(currentDocumentId, currentDocumentTitle, currentButtonElement);
                permanentDeleteModal.hide();
            }
        });

        function showRestoreModal(documentId, documentTitle, buttonElement) {
            currentDocumentId = documentId;
            currentDocumentTitle = documentTitle;
            currentButtonElement = buttonElement;
            
            document.getElementById('restoreDocumentTitle').textContent = documentTitle;
            restoreModal.show();
        }

        function showPermanentDeleteModal(documentId, documentTitle, buttonElement) {
            currentDocumentId = documentId;
            currentDocumentTitle = documentTitle;
            currentButtonElement = buttonElement;
            
            document.getElementById('deleteDocumentTitle').textContent = documentTitle;
            confirmDeleteCheck.checked = false;
            confirmPermanentDeleteBtn.disabled = true;
            permanentDeleteModal.show();
        }

        function performRestore(documentId, documentTitle, buttonElement) {
            // 显示加载状态
            const originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="bi bi-hourglass-split"></i> 恢复中...';
            buttonElement.disabled = true;
            
            // 发送AJAX请求
            fetch('restore.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + documentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示成功消息
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>恢复成功！</strong> 文档 "${documentTitle}" 已恢复到原文档库。
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                    
                    // 2秒后跳转到回收站首页
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    // 显示错误消息
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>恢复失败！</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                    
                    // 恢复按钮状态
                    buttonElement.innerHTML = originalText;
                    buttonElement.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>恢复失败！</strong> 网络错误，请稍后重试。
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                
                // 恢复按钮状态
                buttonElement.innerHTML = originalText;
                buttonElement.disabled = false;
            });
        }

        function performPermanentDelete(documentId, documentTitle, buttonElement) {
            // 显示加载状态
            const originalText = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="bi bi-hourglass-split"></i> 删除中...';
            buttonElement.disabled = true;
            
            // 发送AJAX请求
            fetch('permdel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + documentId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 显示成功消息
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>删除成功！</strong> 文档 "${documentTitle}" 已永久删除。
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                    
                    // 2秒后跳转到回收站首页
                    setTimeout(() => {
                        window.location.href = 'index.php';
                    }, 2000);
                } else {
                    // 显示错误消息
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                    alertDiv.innerHTML = `
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>删除失败！</strong> ${data.message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                    
                    // 恢复按钮状态
                    buttonElement.innerHTML = originalText;
                    buttonElement.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-danger alert-dismissible fade show';
                alertDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>删除失败！</strong> 网络错误，请稍后重试。
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                document.querySelector('.main-content .container-fluid').insertBefore(alertDiv, document.querySelector('.card'));
                
                // 恢复按钮状态
                buttonElement.innerHTML = originalText;
                buttonElement.disabled = false;
            });
        }
    </script>
</body>
</html>