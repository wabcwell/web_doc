<?php
require_once '../../includes/init.php';
require_once '../../includes/DocumentTree.php';

// 检查是否登录
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// 渲染文档操作按钮的函数
function render_document_action_buttons(array $doc, int $next_child_sort): string {
    $doc_id = $doc['document_id'];
    $doc_title = htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8');
    
    $html = '<div class="btn-group" role="group">';
    
    // 编辑按钮 - 橙色 (#ffb74d)
    $html .= '<a href="edit.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" data-tooltip="编辑"';
    $html .= ' style="background-color: #ffb74d; border-color: #ffb74d; color: white; width: 32px; height: 32px; padding: 0;"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffcc80\'; this.style.borderColor=\'#ffcc80\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ffb74d\'; this.style.borderColor=\'#ffb74d\';">';
    $html .= '<i class="bi bi-pencil" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 查看按钮 - 浅蓝色 (#64b5f6)
    $html .= '<a href="/index.php?id=' . $doc_id . '" target="_blank" class="btn btn-sm d-flex align-items-center justify-content-center" data-tooltip="查看"';
    $html .= ' style="background-color: #64b5f6; border-color: #64b5f6; color: white; width: 32px; height: 32px; padding: 0;"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#90caf9\'; this.style.borderColor=\'#90caf9\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#64b5f6\'; this.style.borderColor=\'#64b5f6\';">';
    $html .= '<i class="bi bi-eye" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 添加下级文档按钮 - 绿色 (#4caf50)
    $html .= '<a href="add.php?parent_id=' . $doc_id . '&sort_order=' . $next_child_sort . '" class="btn btn-sm d-flex align-items-center justify-content-center" data-tooltip="添加下级"';
    $html .= ' style="background-color: #4caf50; border-color: #4caf50; color: white; width: 32px; height: 32px; padding: 0;"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#66bb6a\'; this.style.borderColor=\'#66bb6a\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#4caf50\'; this.style.borderColor=\'#4caf50\';">';
    $html .= '<i class="bi bi-plus" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 删除按钮 - 珊瑚色 (#ff8a65)
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" data-tooltip="删除"';
    $html .= ' style="background-color: #ff8a65; border-color: #ff8a65; color: white; width: 32px; height: 32px; padding: 0;"';
    $html .= ' onclick="confirmDelete(\'' . $doc_title . '\', \'delete.php?id=' . $doc_id . '\')"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffab91\'; this.style.borderColor=\'#ffab91\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ff8a65\'; this.style.borderColor=\'#ff8a65\';">';
    $html .= '<i class="bi bi-trash" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

// 创建DocumentTree实例
$documentTree = new DocumentTree();
// 获取所有文档，使用简化优化
$documents = $documentTree->getAllDocumentsByHierarchySimple();

// 获取参数用于添加按钮
$parent_id = isset($_GET['parent_id']) ? intval($_GET['parent_id']) : 0;
$sort_order = isset($_GET['sort_order']) ? intval($_GET['sort_order']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

$title = '文档管理';
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
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        .btn-group .btn {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
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
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1><i class="bi bi-file-text"></i> 文档管理</h1>
                <a href="add.php" class="btn btn-secondary">
                    <i class="bi bi-plus-circle"></i> 添加文档
                </a>
            </div>
            
            <!-- 成功提示消息 -->
            <?php if (isset($_GET['success'])): ?>
                <?php if ($_GET['success'] === 'update'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <strong>更新成功！</strong> 文档已成功更新。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
                <?php elseif ($_GET['success'] === 'add'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-plus-circle-fill"></i>
                    <strong>添加成功！</strong> 文档已成功创建。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
                <?php elseif ($_GET['success'] === 'delete'): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-trash-fill"></i>
                    <strong>删除成功！</strong> 文档已删除，子文档已自动调整层级。
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="关闭"></button>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-text"></i> 文档列表</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-file-earmark-text text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">暂无文档，开始创建你的第一篇文档吧！</p>
                            <a href="add.php" class="btn btn-secondary">
                                <i class="bi bi-plus-circle"></i> 创建文档
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>标题</th>
                                        <th>作者</th>
                                        <th>更新时间</th>
                                        <th>可见性</th>
                                        <th>状态</th>
                                        <th style="width: 200px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): 
                                        // 使用预计算的下级排序值
                                        $next_child_sort = ($doc['max_child_sort'] ?? 0) + 1;
                                    ?>
                                    <tr>
                                        <td><?php echo $doc['document_id']; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <?php if (isset($doc['level']) && $doc['level'] > 0): ?>
                                                    <span style="margin-right: 5px;">
                                                        <?php echo str_repeat('│&nbsp;&nbsp;', $doc['level']); ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if (isset($doc['level']) && $doc['level'] > 0): ?>
                                                    └─
                                                <?php endif; ?>
                                                <a href="edit.php?id=<?php echo $doc['document_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($doc['username']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($doc['updated_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $visibility_status = $doc['is_public'] ? '公开' : '私有';
                                            $visibility_class = $doc['is_public'] ? 'success' : 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $visibility_class; ?>">
                                                <?php echo $visibility_status; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            $is_formal = isset($doc['is_formal']) ? $doc['is_formal'] : 1;
                                            switch($is_formal) {
                                                case 0:
                                                    $formal_status = '草稿';
                                                    $formal_class = 'warning';
                                                    break;
                                                case 1:
                                                    $formal_status = '正式';
                                                    $formal_class = 'primary';
                                                    break;
                                                default:
                                                    $formal_status = '正式';
                                                    $formal_class = 'primary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $formal_class; ?>">
                                                <?php echo $formal_status; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center">
                                                <?php echo render_document_action_buttons($doc, $next_child_sort); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 删除确认模态框 -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">确认删除</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="关闭"></button>
                </div>
                <div class="modal-body">
                    <p>您确定要删除文档 <strong id="deleteDocumentTitle"></strong> 吗？</p>
                    <p class="text-info mb-0">
                        <i class="bi bi-info-circle-fill"></i> 
                        文档将被移动到回收站，您可以在回收站中恢复或永久删除。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">确认删除</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 删除确认
        function confirmDelete(title, url) {
            document.getElementById('deleteDocumentTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = url;
            new bootstrap.Modal(document.getElementById('deleteModal')).show();
        }
    </script>
</body>
</html>