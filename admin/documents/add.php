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
// 限制返回文档数量，避免内存溢出
$documents = $tree->getAllDocumentsByHierarchy(1000);

// 获取URL参数用于自动填充
$parent_id_param = $_GET['parent_id'] ?? 0;
$sort_order_param = $_GET['sort_order'] ?? 0;

// 调试信息：记录请求详情（可选，生产环境可移除）
// $request_info = [
//     'method' => $_SERVER['REQUEST_METHOD'],
//     'uri' => $_SERVER['REQUEST_URI'],
//     'time' => date('Y-m-d H:i:s')
// ];
// error_log('add.php accessed: ' . json_encode($request_info));

// 进入页面时获取document_id并标记为已分配
// =============================================================================
// 防重复机制：防止document_id重复分配的核心逻辑
// =============================================================================
// 步骤1：获取当前时间戳，用于时间间隔判断
$current_time = time(); // 当前Unix时间戳（秒）
$last_gen_time = $_SESSION['last_document_gen_time'] ?? 0; // 上次生成ID的时间，默认为0（新会话）

// 步骤2：判断是否需要生成新的document_id
// 条件1：必须是GET请求（正常页面访问）
// 条件2：不能是AJAX请求（防止异步请求重复生成）
// 条件3：距离上次生成必须超过3秒（防止快速刷新/预加载）
if ($_SERVER['REQUEST_METHOD'] === 'GET' && 
    empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    ($current_time - $last_gen_time > 3)) {
    
    // 步骤3：生成新的document_id并标记为已分配
    $pre_generated_document_id = get_next_available_document_id(); // 获取下一个可用ID
    mark_document_id_allocated($pre_generated_document_id, $_SESSION['user_id'] ?? 1); // 标记为已分配
    
    // 步骤4：将生成的ID保存到会话，供后续使用
    $_SESSION['pre_generated_document_id'] = $pre_generated_document_id;
    $_SESSION['last_document_gen_time'] = $current_time; // 记录本次生成时间
    $_SESSION['gen_token'] = uniqid('doc_', true); // 生成唯一令牌（可选，用于调试）
    
// 步骤5：会话中已有预生成的ID，直接复用
} elseif (isset($_SESSION['pre_generated_document_id'])) {
    $pre_generated_document_id = $_SESSION['pre_generated_document_id']; // 复用会话中保存的ID
    
// 步骤6：回退方案（会话失效或特殊情况）
} else {
    $pre_generated_document_id = get_next_available_document_id(); // 获取新的ID
    mark_document_id_allocated($pre_generated_document_id, $_SESSION['user_id'] ?? 1); // 标记为已分配
    $_SESSION['pre_generated_document_id'] = $pre_generated_document_id; // 保存到会话
    $_SESSION['last_document_gen_time'] = $current_time; // 记录生成时间
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $parent_id = $_POST['parent_id'] ?? 0;
    $sort_order = $_POST['sort_order'] ?? 0;
    $tags = $_POST['tags'] ?? '';
    $is_public = isset($_POST['is_public']) ? intval($_POST['is_public']) : 1;
    $is_formal = isset($_POST['is_formal']) ? intval($_POST['is_formal']) : 0;
    
    // 验证parent_id的有效性
    if ($parent_id != 0) {
        $stmt_check = $db->prepare("SELECT id FROM documents WHERE document_id = ?");
        $stmt_check->execute([$parent_id]);
        if (!$stmt_check->fetch()) {
            // 如果parent_id不存在，则设置为0（顶级文档）
            $parent_id = 0;
        } else {
            // 将业务ID转换为内部ID
            $stmt_convert = $db->prepare("SELECT id FROM documents WHERE document_id = ?");
            $stmt_convert->execute([$parent_id]);
            $parent_internal_id = $stmt_convert->fetchColumn();
            $parent_id = $parent_internal_id ?: 0;
        }
    }
    // 确保parent_id不为NULL，顶级文档的parent_id应为0
    if ($parent_id === null) {
        $parent_id = 0;
    }
    
    if (!empty($title)) {
        // 生成唯一的update_code
        $update_code = uniqid() . '_' . time();
        
        // 使用预生成的document_id
        $new_document_id = $pre_generated_document_id;
        
        $stmt = $db->prepare("INSERT INTO documents (document_id, title, content, parent_id, sort_order, tags, is_public, is_formal, created_at, updated_at, update_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)");
        $stmt->execute([$new_document_id, $title, $content, $parent_id, $sort_order, $tags, $is_public, $is_formal, $update_code]);
        
        $document_id = $new_document_id;
        
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
        
        // 将document_id_apportion中的ID标记为已使用
        mark_document_id_used($new_document_id, $_SESSION['user_id'] ?? 1);
        
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
        
        /* 解决容器高度限制 */
        .card-body {
            height: auto !important;
            min-height: 600px;
        }
        
        /* 确保编辑器容器可以自动增高 */
        .form-group:has(#editor) {
            height: auto !important;
        }
        
        /* 移除可能的高度限制 */
        .card {
            height: auto !important;
        }
        
        /* 移除容器宽度限制，支持无限拉伸 */
        .container-fluid {
            max-width: none;
            width: 100%;
        }
        
        /* 确保编辑器容器能自动增高 */
        #editor {
            height: auto !important;
            min-height: 500px;
        }
        
        /* 精确修复元素路径间距问题 */
        .edui-default .edui-editor-bottombar {
            height: 24px !important;
            line-height: 24px !important;
            padding: 0 5px !important;
            margin: 0 !important;
            border-top: 1px solid #d4d4d4 !important;
            box-sizing: border-box !important;
        }
        
        .edui-default .edui-editor {
            border-radius: 0.375rem !important;
            overflow: hidden !important;
        }
        
        .edui-default .edui-editor-bottomContainer {
            height: 24px !important;
            margin: 0 !important;
            padding: 0 !important;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1><i class="bi bi-file-earmark-plus"></i> 添加新文档</h1>
            
            <form method="post" id="documentForm">

                <div class="d-flex flex-column flex-lg-row document-add-container" id="responsive-container">
                    <!-- 左侧：文档标题和内容模块 -->
                    <div class="flex-grow-1">
                        <!-- 文档标题 -->
                        <div class="form-group mb-3">
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="请输入文档标题">
                        </div>
                        
                        <!-- 文档内容 -->
                        <script id="editor" type="text/plain"></script>
                        <textarea name="content" id="content" class="document-add-content"></textarea>
                    </div>
                    
                    <!-- 右侧：设置和按钮模块 -->
                    <div class="flex-shrink-0 document-add-sidebar">
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
                                                $selected = ($parent_id_param !== null && $doc['document_id'] == $parent_id_param) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $doc['document_id']; ?>" <?php echo $selected; ?>>
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
                                    <button type="submit" class="btn btn-success">添加文档</button>
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
    /* 响应式断点：1200px */
    @media screen and (max-width: 1200px) {
        #responsive-container {
            flex-direction: column !important;
        }
        #responsive-container .flex-shrink-0 {
            width: 100% !important;
            flex: none !important;
            min-width: 100% !important;
            max-width: 100% !important;
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
    // 设置UEditor服务器URL - 上传时动态获取document_id
    window.UEDITOR_CONFIG = window.UEDITOR_CONFIG || {};
    window.UEDITOR_CONFIG.serverUrl = '/admin/ueditor_upload.php?document_id=<?php echo $pre_generated_document_id; ?>';
    
    // 简化的高度调整
    function autoHeight() {
        var editor = UE.getEditor('editor');
        editor.ready(function() {
            // 启用UEditor内置自动增高
            editor.setOpt('autoHeightEnabled', true);
        });
    }

    // 初始化UEditorPlus - 启用自动增高
    const ue = UE.getEditor('editor', {
        autoHeightEnabled: true,
        initialFrameHeight: 500,
        minFrameHeight: 500,
        maxFrameHeight: 2000,
        elementPathEnabled: true,   // 显示底部元素路径
        wordCount: true,            // 显示字数统计
        maximumWords: 10000,
        autoFloatEnabled: false,
        minFrameHeight: 500,
        maxFrameHeight: 1200,
        serverUrl: '/admin/ueditor_upload.php?document_id=<?php echo $pre_generated_document_id; ?>'
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
    
    // 启用自动增高功能
    autoHeight();
    </script>
</body>
</html>