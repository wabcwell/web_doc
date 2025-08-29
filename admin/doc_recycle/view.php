<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID
$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php?error=未指定文档ID');
    exit;
}

$db = get_db();

// 获取回收站中的文档详情（仅del_status=1的文档）
$stmt = $db->prepare("SELECT d.*, u.username FROM documents d LEFT JOIN users u ON d.user_id = u.id WHERE d.id = ? AND d.del_status = 1");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php?error=文档不存在或不在回收站中');
    exit;
}

// 获取面包屑导航（仅显示已删除的文档路径）
$breadcrumbs = [];
$current_id = $document['parent_id'];
while ($current_id) {
    $stmt = $db->prepare("SELECT id, title FROM documents WHERE id = ? AND del_status = 1");
    $stmt->execute([$current_id]);
    $parent = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($parent) {
        array_unshift($breadcrumbs, $parent);
        $current_id = $db->query("SELECT parent_id FROM documents WHERE id = $current_id AND del_status = 1")->fetchColumn();
    } else {
        break;
    }
}

$title = '回收站文档 - ' . htmlspecialchars($document['title'] ?? '未知文档');
include '../sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .markdown-content {
            line-height: 1.8;
        }
        .markdown-content h1, .markdown-content h2, .markdown-content h3,
        .markdown-content h4, .markdown-content h5, .markdown-content h6 {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .markdown-content pre {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            overflow-x: auto;
        }
        .markdown-content code {
            background-color: #f8f9fa;
            padding: 0.2em 0.4em;
            border-radius: 0.25rem;
            font-size: 0.875em;
        }
        .markdown-content pre code {
            background-color: transparent;
            padding: 0;
            font-size: inherit;
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
                            <li class="breadcrumb-item"><a href="index.php">回收站</a></li>
                            <?php foreach ($breadcrumbs as $crumb): ?>
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $crumb['id']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="index.php" class="btn" style="background-color: #90a4ae; border-color: #90a4ae; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#b0bec5'; this.style.borderColor='#b0bec5'" onmouseout="this.style.backgroundColor='#90a4ae'; this.style.borderColor='#90a4ae'">
                        <i class="bi bi-arrow-left"></i> 返回回收站
                    </a>
                </div>
            </div>

            <!-- 消息提示区域 -->
            <div id="messageContainer">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>成功！</strong> 
                        <?php 
                        switch($_GET['success']) {
                            case 'restore':
                                echo '文档已成功恢复！';
                                break;
                            case 'delete':
                                echo '文档已被永久删除！';
                                break;
                            default:
                                echo '操作成功完成！';
                        }
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>错误！</strong> <?php echo htmlspecialchars($_GET['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- 文档内容区域 -->
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-file-text"></i> 文档内容</h5>
                        </div>
                        <div class="card-body">
                            <div class="markdown-content">
                                <?php 
                                require_once '../../Parsedown.php';
                                $Parsedown = new Parsedown();
                                echo $Parsedown->text($document['content']);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- 文档信息模块 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-info-circle"></i> 文档信息</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">ID</dt>
                                <dd class="col-sm-8"><?php echo $document['id']; ?></dd>
                                
                                <dt class="col-sm-4">作者</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($document['username'] ?? '未知用户'); ?></dd>
                                
                                <dt class="col-sm-4">状态</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-danger">
                                        <i class="bi bi-trash"></i> 已删除
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-4">删除时间</dt>
                                <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['deleted_at'])); ?></dd>
                                
                                <dt class="col-sm-4">创建时间</dt>
                                <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?></dd>
                                
                                <dt class="col-sm-4">更新时间</dt>
                                <dd class="col-sm-8"><?php echo date('Y-m-d H:i', strtotime($document['updated_at'])); ?></dd>
                                
                                <?php if ($document['tags']): ?>
                                <dt class="col-sm-4">标签</dt>
                                <dd class="col-sm-8">
                                    <?php 
                                    $tags = array_map('trim', explode(',', $document['tags']));
                                    foreach ($tags as $tag): 
                                        if (trim($tag)): 
                                    ?>
                                        <span class="badge bg-info me-1"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <!-- 操作模块 -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-gear"></i> 操作</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <!-- 恢复按钮 - 绿色 (#4caf50) -->
                                <button type="button" class="btn" style="background-color: #4caf50; border-color: #4caf50; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#66bb6a'; this.style.borderColor='#66bb6a'" onmouseout="this.style.backgroundColor='#4caf50'; this.style.borderColor='#4caf50'" onclick="showRestoreConfirm(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars(addslashes($document['title'] ?? '未知文档')); ?>')">
                                    <i class="bi bi-arrow-counterclockwise"></i> 恢复文档
                                </button>
                                
                                <!-- 历史版本按钮 - 橙色 (#ffb74d) -->
                                <a href="../documents/view_his.php?id=<?php echo $document['id']; ?>" class="btn" style="background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#ffcc80'; this.style.borderColor='#ffcc80'" onmouseout="this.style.backgroundColor='#ffb74d'; this.style.borderColor='#ffb74d'">
                                    <i class="bi bi-clock-history"></i> 历史版本
                                </a>
                                
                                <!-- 更新历史按钮 - 浅蓝色 (#64b5f6) -->
                                <a href="../documents/edit_log.php?id=<?php echo $document['id']; ?>" class="btn" style="background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#90caf9'; this.style.borderColor='#90caf9'" onmouseout="this.style.backgroundColor='#64b5f6'; this.style.borderColor='#64b5f6'">
                                    <i class="bi bi-list-ul"></i> 更新历史
                                </a>
                                
                                <!-- 永久删除按钮 - 珊瑚色 (#ff8a65) -->
                                <button type="button" class="btn" style="background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#ffab91'; this.style.borderColor='#ffab91'" onmouseout="this.style.backgroundColor='#ff8a65'; this.style.borderColor='#ff8a65'" onclick="showDeleteConfirm(<?php echo $document['id']; ?>, '<?php echo htmlspecialchars(addslashes($document['title'] ?? '未知文档')); ?>')">
                                    <i class="bi bi-trash-fill"></i> 永久删除
                                </button>
                                

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
                    <h5 class="modal-title" id="restoreModalLabel">
                        <i class="bi bi-arrow-counterclockwise"></i> 确认恢复文档
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要恢复文档 <strong id="restoreDocumentTitle" class="text-primary"></strong> 吗？</p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>提示：</strong>文档将从回收站恢复到原文档库，此操作不会影响到子文档。
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> 取消
                    </button>
                    <button type="button" class="btn btn-success" id="confirmRestoreBtn">
                        <i class="bi bi-check-circle"></i> 确认恢复
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 永久删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> 确认永久删除
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要永久删除文档 <strong id="deleteDocumentTitle" class="text-danger"></strong> 吗？</p>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>警告：</strong>此操作不可撤销，文档将被永久删除！
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> 取消
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">
                        <i class="bi bi-trash-fill"></i> 确认删除
                    </button>
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
        // 全局变量
        let currentDocumentId = null;
        let currentDocumentTitle = null;

        // 显示恢复确认弹窗
        function showRestoreConfirm(docId, docTitle) {
            currentDocumentId = docId;
            currentDocumentTitle = docTitle;
            
            document.getElementById('restoreDocumentTitle').textContent = docTitle;
            
            const modal = new bootstrap.Modal(document.getElementById('restoreModal'));
            modal.show();
        }

        // 显示永久删除确认弹窗
        function showDeleteConfirm(docId, docTitle) {
            currentDocumentId = docId;
            currentDocumentTitle = docTitle;
            
            document.getElementById('deleteDocumentTitle').textContent = docTitle;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // 执行恢复操作
        document.getElementById('confirmRestoreBtn').addEventListener('click', function() {
            if (!currentDocumentId) return;

            const button = this;
            const originalText = button.innerHTML;
            
            // 禁用按钮并显示加载状态
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> 处理中...';

            // 发送恢复请求
            fetch('restore.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + currentDocumentId + '&confirm=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 跳转到回收站首页并显示成功消息
                    window.location.href = 'index.php?success=restore';
                } else {
                    showMessage('error', data.message || '恢复失败，请重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', '网络错误，请稍后重试');
            })
            .finally(() => {
                // 关闭模态框
                const modal = bootstrap.Modal.getInstance(document.getElementById('restoreModal'));
                modal.hide();
                
                // 恢复按钮状态
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });

        // 执行永久删除操作
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            if (!currentDocumentId) return;

            const button = this;
            const originalText = button.innerHTML;
            
            // 禁用按钮并显示加载状态
            button.disabled = true;
            button.innerHTML = '<i class="bi bi-hourglass-split"></i> 删除中...';

            // 发送删除请求
            fetch('permdel.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id=' + currentDocumentId + '&confirm=1'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 跳转到回收站首页并显示成功消息
                    window.location.href = 'index.php?success=delete';
                } else {
                    showMessage('error', data.message || '删除失败，请重试');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('error', '网络错误，请稍后重试');
            })
            .finally(() => {
                // 关闭模态框
                const modal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                modal.hide();
                
                // 恢复按钮状态
                button.disabled = false;
                button.innerHTML = originalText;
            });
        });

        // 显示消息提示
        function showMessage(type, message) {
            const container = document.getElementById('messageContainer');
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const iconClass = type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill';
            
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                    <i class="bi ${iconClass}"></i>
                    <strong>${type === 'success' ? '成功！' : '错误！'}</strong> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            
            container.insertAdjacentHTML('afterbegin', alertHtml);
            
            // 3秒后自动消失
            setTimeout(() => {
                const alert = container.querySelector('.alert');
                if (alert) {
                    alert.remove();
                }
            }, 3000);
        }

        // 重置模态框状态
        document.getElementById('restoreModal').addEventListener('hidden.bs.modal', function () {
            currentDocumentId = null;
            currentDocumentTitle = null;
        });

        document.getElementById('deleteModal').addEventListener('hidden.bs.modal', function () {
            currentDocumentId = null;
            currentDocumentTitle = null;
        });
    </script>
</body>
</html>