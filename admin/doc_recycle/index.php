<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/init.php';

// 权限检查
if (!check_login()) {
    header('Location: /admin/login.php');
    exit();
}

$is_admin = check_admin();
$title = '文档回收站';

// 初始化分页参数
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取回收站数据
$db = get_db();
$documents = get_recycle_documents($db, $per_page, $offset);
$total_documents = get_recycle_count($db);
$total_pages = ceil($total_documents / $per_page);

include '../sidebar.php';

// 辅助函数：获取回收站文档列表
function get_recycle_documents(PDO $db, int $limit, int $offset): array {
    $sql = "SELECT d.*, u.username, 
                   (SELECT title FROM documents WHERE id = d.parent_id) as parent_title
            FROM documents d 
            LEFT JOIN users u ON d.user_id = u.id 
            WHERE d.del_status = 1 
            ORDER BY d.deleted_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 辅助函数：获取回收站文档总数
function get_recycle_count(PDO $db): int {
    return $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 1")->fetchColumn();
}

// 辅助函数：显示操作按钮
function render_action_buttons(array $doc, bool $is_admin): string {
    $doc_id = $doc['id'];
    $doc_title = htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8');
    
    $html = '<div class="btn-group" role="group" style="gap: 2px;">';
    
    // 历史版本按钮 - 橙色 (#ffb74d)
    $html .= '<a href="../documents/view_his.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s, border-color 0.2s;" title="历史版本" data-bs-toggle="tooltip" data-bs-placement="top"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffcc80\'; this.style.borderColor=\'#ffcc80\';" ';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ffb74d\'; this.style.borderColor=\'#ffb74d\';">';
    $html .= '<i class="bi bi-clock-history" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 更新历史按钮 - 浅蓝色 (#64b5f6)
    $html .= '<a href="../documents/edit_log.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s, border-color 0.2s;" title="更新历史" data-bs-toggle="tooltip" data-bs-placement="top"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#90caf9\'; this.style.borderColor=\'#90caf9\';" ';
    $html .= ' onmouseout="this.style.backgroundColor=\'#64b5f6\'; this.style.borderColor=\'#64b5f6\';">';
    $html .= '<i class="bi bi-list-ul" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 恢复按钮 - 绿色 (#4caf50)
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #4caf50; border-color: #4caf50; color: white; transition: background-color 0.2s, border-color 0.2s;" title="恢复文档" data-bs-toggle="tooltip" data-bs-placement="top" ';
    $html .= 'onclick="showRestoreConfirm(' . $doc_id . ', \'' . $doc_title . '\')" ';
    $html .= ' onmouseover="this.style.backgroundColor=\'#66bb6a\'; this.style.borderColor=\'#66bb6a\';" ';
    $html .= ' onmouseout="this.style.backgroundColor=\'#4caf50\'; this.style.borderColor=\'#4caf50\';">';
    $html .= '<i class="bi bi-arrow-counterclockwise" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    // 永久删除按钮 - 珊瑚色 (#ff8a65)
    $disabled = $is_admin ? '' : 'disabled';
    $disabled_style = $is_admin ? '' : 'opacity: 0.65; cursor: not-allowed;';
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s, border-color 0.2s; ' . $disabled_style . '" title="永久删除" data-bs-toggle="tooltip" data-bs-placement="top" ';
    $html .= $disabled . ' onclick="showDeleteConfirm(' . $doc_id . ', \'' . $doc_title . '\')" ';
    $html .= ' onmouseover="if(!this.disabled){this.style.backgroundColor=\'#ffab91\'; this.style.borderColor=\'#ffab91\';}" ';
    $html .= ' onmouseout="if(!this.disabled){this.style.backgroundColor=\'#ff8a65\'; this.style.borderColor=\'#ff8a65\';}">';
    $html .= '<i class="bi bi-trash-fill" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

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
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-recycle"></i> 文档回收站</h1>
            </div>

            <!-- 消息提示区域 -->
            <div id="messageContainer">
                <?php 
                // 处理URL参数中的成功消息
                $success_msg = '';
                if (isset($_GET['success']) && !isset($_SESSION['handled_url_success'])) {
                    switch($_GET['success']) {
                        case 'restore':
                            $success_msg = '文档已成功恢复到原文档库';
                            $_SESSION['handled_url_success'] = true; // 标记已处理
                            break;
                        case 'delete':
                            $success_msg = '文档已永久删除';
                            $_SESSION['handled_url_success'] = true; // 标记已处理
                            break;
                    }
                }
                ?>

                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>成功！</strong> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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
                
                <?php 
                // 清理URL成功处理标记
                if (isset($_SESSION['handled_url_success'])) {
                    unset($_SESSION['handled_url_success']);
                }
                ?>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> 已删除文档列表</h5>
                    <span class="badge bg-secondary">共 <?php echo $total_documents; ?> 个文档</span>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox text-muted" style="font-size: 4rem;"></i>
                            <h4 class="text-muted mt-3">回收站是空的</h4>
                            <p class="text-muted">当前没有已删除的文档</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col" style="width: 60px;">ID</th>
                                        <th scope="col">文档标题</th>
                                        <th scope="col">父文档</th>
                                        <th scope="col">删除者</th>
                                        <th scope="col">删除时间</th>
                                        <th scope="col" style="width: 200px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><strong><?php echo $doc['id']; ?></strong></td>
                                            <td>
                                                <a href="view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none">
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($doc['title']); ?></div>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($doc['parent_title']): ?>
                                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($doc['parent_title']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="text-primary"><?php echo htmlspecialchars($doc['username'] ?? '未知用户'); ?></span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> 
                                                    <?php echo date('Y-m-d H:i', strtotime($doc['deleted_at'])); ?>
                                                </small>
                                            </td>
                                            <td class="text-center">
                                                <?php echo render_action_buttons($doc, $is_admin); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- 分页 -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="回收站分页" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                                <i class="bi bi-chevron-left"></i> 上一页
                                            </a>
                                        </li>
                                    <?php endif; ?>

                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                                下一页 <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
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
    <script>
        // 全局变量
        let currentDocumentId = null;
        let currentDocumentTitle = null;

        // 初始化工具提示
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

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
                    // 直接跳转，不再显示重复提示
                    window.location.href = '?success=restore&title=' + encodeURIComponent(currentDocumentTitle);
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
                    // 直接跳转，不再显示重复提示
                    window.location.href = '?success=delete&title=' + encodeURIComponent(currentDocumentTitle);
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