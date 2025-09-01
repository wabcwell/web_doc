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

$title = '查看文档 - ' . htmlspecialchars($document['title'] ?? '未知文档');
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
                                <li class="breadcrumb-item"><a href="view.php?id=<?php echo $crumb['document_id']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a></li>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($document['title'] ?? '未知文档'); ?></li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <a href="edit.php?id=<?php echo $id; ?>" class="btn me-2" style="background-color: #7a8ba3; border-color: #7a8ba3; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#8d9db6'; this.style.borderColor='#8d9db6'" onmouseout="this.style.backgroundColor='#7a8ba3'; this.style.borderColor='#7a8ba3'">
                        <i class="bi bi-pencil"></i> 编辑
                    </a>
                    <a href="/index.php?id=<?php echo $id; ?>" target="_blank" class="btn" style="background-color: #8fa088; border-color: #8fa088; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#a8b5a0'; this.style.borderColor='#a8b5a0'" onmouseout="this.style.backgroundColor='#8fa088'; this.style.borderColor='#8fa088'">
                        <i class="bi bi-eye"></i> 前台查看
                    </a>
                </div>
            </div>

            <!-- 文档信息卡片 -->
            <div class="d-flex flex-row flex-wrap">
                <div class="document-content-area">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">文档内容</h5>
                        </div>
                        <div class="card-body">
                            <div class="document-content">
                                <?php 
                                $content = $document['content'];
                                
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
                        </div>
                    </div>
                </div>
                
                <div class="document-info-area">
                    <!-- 文档信息 -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">文档信息</h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">ID</dt>
                                <dd class="col-sm-8"><?php echo $document['id']; ?></dd>
                                
                                <dt class="col-sm-4">作者</dt>
                                <dd class="col-sm-8"><?php echo htmlspecialchars($document['username'] ?? '未知用户'); ?></dd>
                                
                                <dt class="col-sm-4">可见性</dt>
                                <dd class="col-sm-8">
                                    <?php 
                                    $visibility_status = $document['is_public'] ? '公开' : '私有';
                                    $visibility_class = $document['is_public'] ? 'success' : 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $visibility_class; ?>">
                                        <?php echo $visibility_status; ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-4">状态</dt>
                                <dd class="col-sm-8">
                                    <?php 
                                    switch($document['is_formal']) {
                                        case 0:
                                            $status = '草稿';
                                            $status_class = 'warning';
                                            break;
                                        case 1:
                                            $status = '正式';
                                            $status_class = 'primary';
                                            break;
                                        default:
                                            $status = '未知';
                                            $status_class = 'dark';
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <?php echo $status; ?>
                                    </span>
                                </dd>
                                
                                <dt class="col-sm-4">排序权重</dt>
                                <dd class="col-sm-8"><?php echo $document['sort_order']; ?></dd>
                                
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
                                        <span class="badge bg-info me-1"><?php echo htmlspecialchars(trim($tag) ?? ''); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                </dd>
                                <?php endif; ?>
                            </dl>
                        </div>
                    </div>

                    <!-- 操作按钮 -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">操作</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <a href="edit.php?id=<?php echo $id; ?>" class="btn" style="background-color: #9b87c1; border-color: #9b87c1; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#b19cd9'; this.style.borderColor='#b19cd9'" onmouseout="this.style.backgroundColor='#9b87c1'; this.style.borderColor='#9b87c1'">
                                    <i class="bi bi-pencil"></i> 编辑文档
                                </a>
                                <a href="edit_log.php?id=<?php echo $id; ?>" class="btn" style="background-color: #64b5f6; border-color: #64b5f6; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#90caf9'; this.style.borderColor='#90caf9'" onmouseout="this.style.backgroundColor='#64b5f6'; this.style.borderColor='#64b5f6'">
                                    <i class="bi bi-clock-history"></i> 操作记录
                                </a>
                                <a href="view_his.php?id=<?php echo $id; ?>" class="btn" style="background-color: #ffb74d; border-color: #ffb74d; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#ffcc80'; this.style.borderColor='#ffcc80'" onmouseout="this.style.backgroundColor='#ffb74d'; this.style.borderColor='#ffb74d'">
                                    <i class="bi bi-clock-history"></i> 历史版本
                                </a>
                                <button type="button" class="btn" style="background-color: #ff8a65; border-color: #ff8a65; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#ffab91'; this.style.borderColor='#ffab91'" onmouseout="this.style.backgroundColor='#ff8a65'; this.style.borderColor='#ff8a65'" onclick="confirmDelete(<?php echo $id; ?>, '<?php echo addslashes(htmlspecialchars($document['title'] ?? '未知文档')); ?>')">
                                    <i class="bi bi-trash"></i> 删除文档
                                </button>
                                <a href="index.php" class="btn" style="background-color: #90a4ae; border-color: #90a4ae; color: white; transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='#b0bec5'; this.style.borderColor='#b0bec5'" onmouseout="this.style.backgroundColor='#90a4ae'; this.style.borderColor='#90a4ae'">
                                    <i class="bi bi-arrow-left"></i> 返回列表
                                </a>
                            </div>
                        </div>
                    </div>
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
                    <p class="text-danger mb-0">
                        <i class="bi bi-exclamation-triangle-fill"></i> 
                        此操作不可撤销，文档将被永久删除。
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <a href="#" id="confirmDeleteBtn" class="btn btn-danger">确认删除</a>
                </div>
            </div>
        </div>
    </div>

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
<?php 
// 更新浏览次数
$stmt = $db->prepare("UPDATE documents SET view_count = view_count + 1 WHERE document_id = ?");
$stmt->execute([$id]);
?>