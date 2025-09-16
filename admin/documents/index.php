<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/DocumentTree.php';

Auth::requireLogin();

// 渲染文档操作按钮的函数
function render_document_action_buttons(array $doc, int $next_child_sort): string {
    $doc_id = $doc['document_id'];
    $doc_title = htmlspecialchars($doc['title'], ENT_QUOTES, 'UTF-8');
    
    $html = '<div class="btn-group" role="group" style="gap: 2px;">';
    
    // 查看按钮 - 蓝灰色 (#90a4ae)
    $html .= '<a href="view.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #90a4ae; border-color: #90a4ae; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="查看"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#b0bec5\'; this.style.borderColor=\'#b0bec5\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#90a4ae\'; this.style.borderColor=\'#90a4ae\';">';
    $html .= '<i class="bi bi-eye" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 版本历史按钮 - 橙色 (#ffb74d)
    $html .= '<a href="view_his.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="版本历史"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffcc80\'; this.style.borderColor=\'#ffcc80\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ffb74d\'; this.style.borderColor=\'#ffb74d\';">';
    $html .= '<i class="bi bi-clock-history" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 更新记录按钮 - 浅蓝色 (#64b5f6)
    $html .= '<a href="edit_log.php?id=' . $doc_id . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="更新记录"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#90caf9\'; this.style.borderColor=\'#90caf9\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#64b5f6\'; this.style.borderColor=\'#64b5f6\';">';
    $html .= '<i class="bi bi-list-check" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 添加下级文档按钮 - 绿色 (#4caf50)
    $html .= '<a href="add.php?parent_id=' . $doc_id . '&sort_order=' . $next_child_sort . '" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #4caf50; border-color: #4caf50; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="添加下级"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#66bb6a\'; this.style.borderColor=\'#66bb6a\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#4caf50\'; this.style.borderColor=\'#4caf50\';">';
    $html .= '<i class="bi bi-plus" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</a>';
    
    // 删除按钮 - 珊瑚色 (#ff8a65)
    $html .= '<button type="button" class="btn btn-sm d-flex align-items-center justify-content-center" style="width: 30px; height: 30px; padding: 0; background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s, border-color 0.2s;" data-tooltip="删除"';
    $html .= ' onclick="confirmDelete(' . $doc_id . ', \'' . $doc_title . '\')"';
    $html .= ' onmouseover="this.style.backgroundColor=\'#ffab91\'; this.style.borderColor=\'#ffab91\';"';
    $html .= ' onmouseout="this.style.backgroundColor=\'#ff8a65\'; this.style.borderColor=\'#ff8a65\';">';
    $html .= '<i class="bi bi-trash" style="font-size: 14px; margin: 0 auto;"></i>';
    $html .= '</button>';
    
    $html .= '</div>';
    
    return $html;
}

$documentTree = new DocumentTree();
$documents = $documentTree->getAllDocumentsByHierarchy(100); // 使用专门的后台层级获取方法

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
    <link rel="stylesheet" href="../assets/css/static/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        .btn-group .btn {
            width: 30px;
            height: 30px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative; /* 为CSS提示提供定位上下文 */
        }
        .btn-group .btn i {
            font-size: 14px;
        }
        
        /* CSS悬停提示样式 */
        .btn[data-tooltip] {
            position: relative;
        }
        
        .btn[data-tooltip]:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 5px;
            padding: 4px 8px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            font-size: 12px;
            white-space: nowrap;
            border-radius: 3px;
            z-index: 1000;
            pointer-events: none;
        }
        
        .btn[data-tooltip]:hover::before {
            content: '';
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            margin-bottom: 1px;
            border: 4px solid transparent;
            border-top-color: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            pointer-events: none;
        }
        
        /* 修复操作列的样式 */
        .table td:last-child {
            position: relative;
        }
        
        /* 优化层级显示 */
        .table td:nth-child(2) {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .table td:nth-child(2) a {
            font-weight: 500;
            color: #495057;
            transition: color 0.15s ease-in-out;
        }
        
        .table td:nth-child(2) a:hover {
            color: #0056b3;
            text-decoration: underline !important;
        }
        
        /* 层级连接线样式 */
        .table td:nth-child(2) span[style*="font-family: monospace"] {
            color: #6c757d;
            font-size: 14px;
            line-height: 1;
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
                                        <th style="width: 80px;">排序</th>
                                        <th style="width: 100px;">作者</th>
                                        <th style="width: 140px;">更新时间</th>
                                        <th style="width: 80px;">可见性</th>
                                        <th style="width: 80px;">状态</th>
                                        <th style="width: 200px; text-align: center;">操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($documents as $doc): 

                                        
                                        // 计算同级文档的下一个排序值
                                        $next_sort_order = $doc['sort_order'] + 1;
                                        
                                        // 计算下级文档的下一个排序值
                                        $max_child_sort = $documentTree->getMaxChildSortOrder($doc['document_id']);
                                        $next_child_sort = $max_child_sort + 1;
                                    ?>
                                    <tr>
                                        <td><?php echo $doc['document_id']; ?></td>
                                        <td>
                                            <div style="display: flex; align-items: center;">
                                                <?php 
                                                $level = isset($doc['level']) ? $doc['level'] : 0;
                                                if ($level > 0): 
                                                    // 生成层级缩进和连接线
                                                    $indent = '';
                                                    for ($i = 0; $i < $level; $i++) {
                                                        if ($i == $level - 1) {
                                                            $indent .= '└─ ';
                                                        } else {
                                                            $indent .= '│&nbsp;&nbsp;';
                                                        }
                                                    }
                                                    echo '<span style="margin-right: 5px; font-family: monospace;">' . $indent . '</span>';
                                                endif; 
                                                ?>
                                                <a href="edit.php?id=<?php echo $doc['document_id']; ?>" class="text-decoration-none">
                                                    <?php echo htmlspecialchars($doc['title']); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td style="text-align: center;"><?php echo $doc['sort_order']; ?></td>
                                        <td><?php echo htmlspecialchars($doc['username'] ?? ''); ?></td>
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
                                            $is_formal = isset($doc['is_formal']) ? $doc['is_formal'] : 1; // 默认正式文档
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
                                        <td>
                                            <?php echo render_document_action_buttons($doc, $next_child_sort); ?>
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

    <script src="../assets/js/static/bootstrap.bundle.min.js"></script>
    <script>
        // 删除确认函数
        function confirmDelete(id, title) {
            document.getElementById('deleteDocumentTitle').textContent = title;
            document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
            
            // 显示模态框
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }


    </script>
</body>
</html>