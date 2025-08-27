<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/init.php';

// 检查用户是否已登录
if (!check_login()) {
    header('Location: /admin/login.php');
    exit();
}

// 检查是否为管理员
$is_admin = check_admin();

$title = '文档回收站';

// 获取数据库连接
$db = get_db();

// 文档恢复功能已移至 restore.php，通过AJAX调用实现

// 永久删除功能已移至 permdel.php，通过AJAX调用实现

// 获取回收站中的文档 - 按删除时间降序排序
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// 获取数据库连接
$db = get_db();

// 获取回收站文档总数
$stmt = $db->query("SELECT COUNT(*) FROM documents WHERE del_status = 1");
$total_documents = $stmt->fetchColumn();
$total_pages = ceil($total_documents / $per_page);

// 获取回收站中的文档，包含父文档名称
$stmt = $db->prepare("SELECT d.*, u.username, 
                     (SELECT title FROM documents WHERE id = d.parent_id) as parent_title
                     FROM documents d 
                     LEFT JOIN users u ON d.user_id = u.id 
                     WHERE d.del_status = 1 
                     ORDER BY d.deleted_at DESC 
                     LIMIT ? OFFSET ?");
$stmt->execute([$per_page, $offset]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../sidebar.php';
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?> - 管理后台</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>文档回收站</h1>
            </div>
            
            <!-- 成功提示消息 -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <strong>操作成功！</strong> <?php echo $_SESSION['success']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>

            <!-- 错误提示消息 -->
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>操作失败！</strong> <?php echo $_SESSION['error']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">已删除文档列表</h5>
                    <span class="badge bg-secondary">共 <?php echo $total_documents; ?> 个文档</span>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">回收站是空的</p>
                            <p class="text-muted">没有已删除的文档</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>文档标题</th>
                                        <th>父文档</th>
                                        <th>删除者</th>
                                        <th>删除时间</th>
                                        <th style="width: 180px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><?php echo $doc['id']; ?></td>
                                            <td>
                                                <a href="view.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?php if ($doc['parent_title']): ?>
                                                    <span class="text-muted"><?php echo htmlspecialchars($doc['parent_title']); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($doc['username'] ?? '未知用户'); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($doc['deleted_at'])); ?></td>
                                            <td style="width: 240px;">
                                                <div style="display: flex; gap: 2px; align-items: center; justify-content: center; margin: 0 auto;">
                                                    <a href="../documents/view_his.php?id=<?php echo $doc['id']; ?>" 
                                                       class="btn btn-sm d-flex align-items-center justify-content-center" 
                                                       style="width: 30px; height: 30px; padding: 0; background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s;" 
                                                       title="版本历史"
                                                       onmouseover="this.style.backgroundColor='#ffcc80'; this.style.borderColor='#ffcc80';" 
                                                       onmouseout="this.style.backgroundColor='#ffb74d'; this.style.borderColor='#ffb74d';">
                                                        <i class="bi bi-clock-history" style="font-size: 14px; margin: 0 auto;"></i>
                                                    </a>
                                                    <a href="../documents/edit_log.php?id=<?php echo $doc['id']; ?>" 
                                                       class="btn btn-sm d-flex align-items-center justify-content-center" 
                                                       style="width: 30px; height: 30px; padding: 0; background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s;" 
                                                       title="查看更新记录"
                                                       onmouseover="this.style.backgroundColor='#90caf9'; this.style.borderColor='#90caf9';" 
                                                       onmouseout="this.style.backgroundColor='#64b5f6'; this.style.borderColor='#64b5f6';">
                                                        <i class="bi bi-list-ul" style="font-size: 14px; margin: 0 auto;"></i>
                                                    </a>
                                                    <button type="button" 
                                                class="btn btn-sm d-flex align-items-center justify-content-center" 
                                                style="width: 30px; height: 30px; padding: 0; background-color: #90a4ae; border-color: #90a4ae; color: white; transition: background-color 0.2s;" 
                                                title="恢复文档"
                                                onclick="showRestoreModal(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['title']); ?>', this)"
                                                onmouseover="this.style.backgroundColor='#b0bec5'; this.style.borderColor='#b0bec5';" 
                                                onmouseout="this.style.backgroundColor='#90a4ae'; this.style.borderColor='#90a4ae';">
                                            <i class="bi bi-arrow-counterclockwise" style="font-size: 14px; margin: 0 auto;"></i>
                                        </button>
                                                    <button type="button" 
                                                            class="btn btn-sm d-flex align-items-center justify-content-center" 
                                                            style="width: 30px; height: 30px; padding: 0; background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s;" 
                                                            title="永久删除"
                                                            onclick="showPermanentDeleteModal(<?php echo $doc['id']; ?>, '<?php echo addslashes($doc['title']); ?>', this)"
                                                            <?php echo $is_admin ? '' : 'disabled'; ?>
                                                            onmouseover="this.style.backgroundColor='#ffab91'; this.style.borderColor='#ffab91';" 
                                                            onmouseout="this.style.backgroundColor='#ff8a65'; this.style.borderColor='#ff8a65';">
                                                        <i class="bi bi-trash-fill" style="font-size: 14px; margin: 0 auto;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="分页">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">上一页</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">下一页</a>
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
    <script>
        // 调试：确保页面加载完成
        document.addEventListener('DOMContentLoaded', function() {
            console.log('回收站页面已加载完成');
            console.log('Bootstrap版本:', bootstrap.Modal.VERSION);
        });
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
            const originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="bi bi-hourglass-split" style="font-size: 14px;"></i>';
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
                        
                        // 淡出效果后移除行
                        const row = buttonElement.closest('tr');
                        row.style.transition = 'opacity 0.5s';
                        row.style.opacity = '0';
                        
                        setTimeout(() => {
                            row.remove();
                            
                            // 检查是否还有文档
                            const remainingRows = document.querySelectorAll('tbody tr').length;
                            if (remainingRows === 0) {
                                // 如果没有文档了，刷新页面
                                window.location.href = 'index.php';
                            } else {
                                // 更新计数
                                const countBadge = document.querySelector('.badge.bg-secondary');
                                const currentCount = parseInt(countBadge.textContent.match(/\d+/)[0]);
                                countBadge.textContent = `共 ${currentCount - 1} 个文档`;
                            }
                        }, 500);
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
                        buttonElement.innerHTML = originalHTML;
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
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.disabled = false;
                });
        }

        function performPermanentDelete(documentId, documentTitle, buttonElement) {
            // 显示加载状态
            const originalHTML = buttonElement.innerHTML;
            buttonElement.innerHTML = '<i class="bi bi-hourglass-split" style="font-size: 14px;"></i>';
            buttonElement.disabled = true;
            buttonElement.closest('a').style.pointerEvents = 'none';
            
            // 禁用同一行的其他按钮
            const row = buttonElement.closest('tr');
            const buttons = row.querySelectorAll('button, a');
            buttons.forEach(btn => {
                if (btn !== buttonElement) btn.style.pointerEvents = 'none';
            });
            
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
                    
                    // 移除已删除的行
                    const row = buttonElement.closest('tr');
                    row.style.transition = 'opacity 0.5s';
                    row.style.opacity = '0';
                    setTimeout(() => {
                        row.remove();
                        
                        // 检查是否还有文档
                        const tbody = document.querySelector('tbody');
                        if (!tbody || tbody.children.length === 0) {
                            location.reload();
                        }
                    }, 500);
                    
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
                    buttonElement.innerHTML = originalHTML;
                    buttonElement.disabled = false;
                    buttons.forEach(btn => {
                        btn.style.pointerEvents = '';
                    });
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
                buttonElement.innerHTML = originalHTML;
                buttonElement.disabled = false;
                buttons.forEach(btn => {
                    btn.style.pointerEvents = '';
                });
            });
        }
    </script>
</body>
</html>