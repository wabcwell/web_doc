<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/DocumentTree.php';

// 检查用户权限
Auth::requireLogin();

// 获取父文档选项
$db = get_db();
$tree = new DocumentTree($db);
$documents = $tree->getAllDocuments();

// 获取URL参数用于自动填充
$parent_id_param = $_GET['parent_id'] ?? 0;
$sort_order_param = $_GET['sort_order'] ?? 0;

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $parent_id = $_POST['parent_id'] ?? 0;
    $sort_order = $_POST['sort_order'] ?? 0;
    $tags = $_POST['tags'] ?? '';
    $is_public = isset($_POST['is_public']) ? intval($_POST['is_public']) : 1;
    $is_formal = isset($_POST['is_formal']) ? intval($_POST['is_formal']) : 0;
    
    if (!empty($title)) {
        // 生成唯一的update_code
        $update_code = uniqid() . '_' . time();
        
        $stmt = $db->prepare("INSERT INTO documents (title, content, parent_id, sort_order, tags, is_public, is_formal, created_at, updated_at, update_code) VALUES (?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), ?)");
        $stmt->execute([$title, $content, $parent_id, $sort_order, $tags, $is_public, $is_formal, $update_code]);
        
        $document_id = $db->lastInsertId();
        
        // 记录创建日志（创建操作不需要记录变更状态）
        log_edit(
            $document_id,
            $_SESSION['user_id'],
            'create',
            [],
            $update_code
        );
        
        // 保存初始版本
        save_document_version($document_id, $title, $content, $_SESSION['user_id'], $tags, $update_code);
        
        header('Location: index.php?success=add');
        exit;
    }
}

$title = '添加文档';
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
    <!-- UEditorPlus样式 -->
    <link rel="stylesheet" href="../assets/ueditorplus/themes/default/css/ueditor.css">
    <style>
        #editor-container {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
        }
        .edui-default .edui-editor {
            border-radius: 0.375rem;
        }
        .edui-default .edui-editor-toolbarbox {
            border-radius: 0.375rem 0.375rem 0 0;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1>添加新文档</h1>
            
            <form method="post" id="documentForm">
                <div class="d-flex flex-column flex-lg-row" style="gap: 15px;">
                    <!-- 左侧：文档标题和内容模块 -->
                    <div class="flex-grow-1">
                        <div class="card">
                            <div class="card-body">
                                <!-- 文档标题 -->
                                <div class="form-group mb-3">
                                    <label for="title">文档标题 *</label>
                                    <input type="text" class="form-control" id="title" name="title" required 
                                           placeholder="请输入文档标题">
                                </div>
                                
                                <!-- 文档内容 -->
                                <div class="form-group">
                                    <script id="editor" type="text/plain" style="width:100%;height:500px;"></script>
                                    <textarea name="content" id="content" style="display: none;"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 右侧：设置和按钮模块 -->
                    <div class="flex-shrink-0" style="width: 280px;">
                        <div class="card">
                            <div class="card-body">
                                <!-- 公开性选项 -->
                                <div class="form-group mb-3">
                                    <label for="is_public">可见性</label>
                                    <select class="form-control" id="is_public" name="is_public">
                                        <option value="1" selected>公开</option>
                                        <option value="0">私有</option>
                                    </select>
                                </div>
                                
                                <!-- 文档状态 -->
                                <div class="form-group mb-3">
                                    <label for="is_formal">文档状态</label>
                                    <select class="form-control" id="is_formal" name="is_formal">
                                        <option value="0" selected>草稿</option>
                                        <option value="1">正式</option>
                                    </select>
                                </div>
                                
                                <!-- 父文档选择器 -->
                                <div class="form-group mb-3">
                                    <label for="parent_id">父文档</label>
                                    <select class="form-control" id="parent_id" name="parent_id">
                                        <option value="0">无父文档（顶级文档）</option>
                                        <?php 
                                        if (!empty($documents)) {
                                            foreach ($documents as $doc): 
                                                $selected = ($parent_id_param !== null && $doc['id'] == $parent_id_param) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $doc['id']; ?>" <?php echo $selected; ?>>
                                                <?php echo str_repeat('&nbsp;&nbsp;', $doc['level']) . htmlspecialchars($doc['title']); ?>
                                            </option>
                                        <?php 
                                            endforeach; 
                                        }
                                        ?>
                                    </select>
                                </div>
                                
                                <!-- 标签输入框 -->
                                <div class="form-group mb-3">
                                    <label for="tags">标签</label>
                                    <input type="text" class="form-control" id="tags" name="tags" 
                                           placeholder="多个标签用逗号分隔">
                                </div>
                                
                                <!-- 排序权重 -->
                                <div class="form-group mb-4">
                                    <label for="sort_order">排序权重</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                           value="<?php echo htmlspecialchars($sort_order_param); ?>" min="0" placeholder="数值越大越靠前">
                                </div>
                                
                                <!-- 按钮组 -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">添加文档</button>
                                    <a href="index.php" class="btn btn-secondary">取消</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
    @media (max-width: 800px) {
        .flex-lg-row {
            flex-direction: column !important;
        }
        .flex-shrink-0 {
            width: 100% !important;
            margin-top: 15px;
        }
    }
    </style>

    <!-- UEditorPlus脚本 -->
    <script>
    // 必须在加载ueditor.config.js之前设置UEDITOR_HOME_URL
    window.UEDITOR_HOME_URL = '/admin/assets/ueditorplus/';
    </script>
    <script src="../assets/ueditorplus/ueditor.config.js"></script>
    <script src="../assets/ueditorplus/ueditor.all.js"></script>
    <script src="../assets/ueditorplus/lang/zh-cn/zh-cn.js"></script>
    
    <script>
    // 初始化UEditorPlus - 使用ueditor.config.js中的配置
    const ue = UE.getEditor('editor', {
        initialFrameHeight: 500,
        autoHeightEnabled: false,
        elementPathEnabled: false,
        wordCount: true,
        maximumWords: 10000,
        autoFloatEnabled: false
    });

    // 表单提交处理
    document.getElementById('documentForm').addEventListener('submit', function(e) {
        document.getElementById('content').value = ue.getContent();
    });

    // 保存快捷键
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('documentForm').submit();
        }
    });
    </script>
</body>
</html>