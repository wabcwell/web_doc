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
$documents = $tree->getAllDocumentsByHierarchy();

// 获取URL参数用于自动填充
$parent_id_param = $_GET['parent_id'] ?? 0;
$sort_order_param = $_GET['sort_order'] ?? 0;

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
    
    if (!empty($title)) {
        // 生成唯一的update_code
        $update_code = uniqid() . '_' . time();
        
        // 使用预生成的document_id
        $document_id = $pre_generated_document_id;
        
        $stmt = $db->prepare("INSERT INTO documents (document_id, title, content, parent_id, sort_order, tags, is_public, is_formal, created_at, updated_at, update_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), ?)");
        $stmt->execute([$document_id, $title, $content, $parent_id, $sort_order, $tags, $is_public, $is_formal, $update_code]);
        
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
        mark_document_id_used($document_id, $_SESSION['user_id'] ?? 1);
        
        // 跳转到编辑页面并显示添加完成提示
        header('Location: edit.php?id=' . $document_id . '&success=add');
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
    
    <!-- WordPress风格的资源加载 -->
    <link rel="stylesheet" href="../../assets/css/static/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    
    <!-- WordPress后台经典样式 -->
    <style>
        /* WordPress后台基础样式 */
        body {
            background: #f0f0f1;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        
        .wrap {
            margin: 10px 20px 0 2px;
        }
        
        .wp-header-end {
            height: 0;
            margin: 0;
            border: 0;
            padding: 0;
        }
        
        /* 卡片样式 */
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            margin-bottom: 20px;
            padding: 0;
            border-radius: 0;
        }
        
        .card-header {
            background: #fff;
            border-bottom: 1px solid #ccd0d4;
            padding: 8px 12px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .card-body {
            padding: 12px;
        }
        
        /* 表单样式 */
        .form-label {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 13px;
        }
        
        .form-control, .form-select {
            border: 1px solid #8c8f94;
            border-radius: 3px;
            font-size: 14px;
            padding: 3px 8px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #007cba;
            box-shadow: 0 0 0 1px #007cba;
            outline: 2px solid transparent;
        }
        
        /* 按钮样式 */
        .btn {
            border-radius: 3px;
            font-size: 13px;
            padding: 0 10px 1px;
            line-height: 2.15384615;
            min-height: 30px;
        }
        
        .btn-primary {
            background: #007cba;
            border-color: #007cba;
            color: #fff;
        }
        
        .btn-primary:hover {
            background: #006ba1;
            border-color: #006ba1;
        }
        
        /* 编辑器容器 */
        .editor-container {
            position: relative;
            border: 1px solid #8c8f94;
            border-radius: 3px;
            background: #fff;
            width: 100%;
        }
        
        /* WordPress风格的编辑器 */
        #editor {
            width: 100%;
            min-height: 400px;
            border: none;
            outline: none;
        }

        /* 编辑器容器样式 */
        .edui-editor {
            border: 1px solid #ccd0d4 !important;
            border-radius: 4px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
            width: 100% !important;
            max-width: none !important;
        }
        
        /* 响应式布局 */
        @media screen and (max-width: 782px) {
            .wrap {
                margin: 0 12px 0 0;
            }
            
            .card {
                margin-left: 0;
                margin-right: 0;
            }
        }
        
        /* 确保编辑器可以无限拉伸 */
        .edui-editor-iframeholder {
            width: 100% !important;
        }
        
        #editor {
            width: 100% !important;
        }
        
        /* 侧边栏样式 */
        #postbox-container-1 {
            min-width: 255px;
            max-width: 360px;
        }
        
        /* 清除浮动 */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }
        
        /* 警告样式 */
        .notice {
            margin: 5px 15px 2px;
            padding: 1px 12px;
            background: #fff;
            border-left: 4px solid #72aee6;
            box-shadow: 0 1px 1px 0 rgba(0,0,0,.1);
        }
        
        .notice-success {
            border-left-color: #00a32a;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="wrap">
            <h1 class="wp-heading-inline">添加文档</h1>
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
            <div class="notice notice-success">
                <p>文档添加完成！现在您可以继续编辑文档内容。</p>
            </div>
            <?php endif; ?>
            
            <form method="post" action="" id="post">
                <div class="row">
                    <!-- 主要内容区域 -->
                    <div class="col-lg-8">
                        <div id="post-body-content">
                            <!-- 标题区域 -->
                            <div id="titlediv">
                                <div id="titlewrap">
                                    <input type="text" name="title" size="30" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                           id="title" 
                                           spellcheck="true" 
                                           autocomplete="off" 
                                           placeholder="在此输入标题" 
                                           class="form-control form-control-lg mb-3">
                                </div>
                            </div>
                            
                            <!-- 编辑器区域 -->
                            <div id="postdivrich" class="postarea wp-editor-expand">
                                <div class="editor-toolbar">
                                    <script id="editor" name="content" type="text/plain">
                                        <?php echo htmlspecialchars($_POST['content'] ?? ''); ?>
                                    </script>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 侧边栏 -->
                    <div class="col-lg-4">
                        <div id="postbox-container-1">
                            <!-- 发布模块 -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">发布</h3>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <label class="form-label mb-0">状态</label>
                                            <select name="is_public" class="form-select form-select-sm" style="width: 120px;">
                                                <option value="1" <?php echo ($_POST['is_public'] ?? 1) == 1 ? 'selected' : ''; ?>>公开</option>
                                                <option value="0" <?php echo ($_POST['is_public'] ?? 1) == 0 ? 'selected' : ''; ?>>私密</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <label class="form-label mb-0">文档类型</label>
                                            <select name="is_formal" class="form-select form-select-sm" style="width: 120px;">
                                                <option value="0" <?php echo ($_POST['is_formal'] ?? 0) == 0 ? 'selected' : ''; ?>>草稿</option>
                                                <option value="1" <?php echo ($_POST['is_formal'] ?? 0) == 1 ? 'selected' : ''; ?>>正式文档</option>
                                            </select>
                                        </div>
                                    </div>
                                    
                                    <div style="padding: 4px 12px; text-align: right;">
                                        <input type="submit" name="publish" id="publish" class="button button-primary button-large" value="发布" style="background: #0073aa; border-color: #006799; color: #fff; padding: 0 25px; font-size: 14px; height: 32px; border: 1px solid; border-radius: 3px; cursor: pointer; font-weight: 600;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 父文档模块 -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">父文档</h3>
                                </div>
                                <div class="card-body">
                                    <select name="parent_id" class="form-select">
                                        <option value="0">无父文档</option>
                                        <?php 
                                        function renderOptions($items, $selected = 0) {
                                            foreach ($items as $item) {
                                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $item['level']);
                                                $prefix = $item['level'] > 0 ? '└─ ' : '';
                                                $selected_attr = ($item['document_id'] == $selected) ? ' selected' : '';
                                                $title = htmlspecialchars($item['title']);
                                                
                                                echo "<option value='{$item['document_id']}'{$selected_attr}>{$indent}{$prefix}{$title}</option>";
                                            }
                                        }
                                        renderOptions($documents, $_POST['parent_id'] ?? 0);
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- 排序模块 -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">排序权重</h3>
                                </div>
                                <div class="card-body">
                                    <input type="number" name="sort_order" 
                                           value="<?php echo $_POST['sort_order'] ?? 0; ?>" 
                                           class="form-control" 
                                           min="0" 
                                           max="999" 
                                           placeholder="0-999，数值越小越靠前">
                                </div>
                            </div>
                            
                            <!-- 标签模块 -->
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h3 class="h5 mb-0">标签</h3>
                                </div>
                                <div class="card-body">
                                    <input type="text" name="tags" 
                                           value="<?php echo htmlspecialchars($_POST['tags'] ?? ''); ?>" 
                                           class="form-control" 
                                           placeholder="用逗号分隔多个标签">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- WordPress风格的UEditor配置 -->
    <script type="text/javascript" src="../assets/ueditorplus/ueditor.config.js"></script>
    <script type="text/javascript" src="../assets/ueditorplus/ueditor.all.js"></script>
    <script type="text/javascript" src="../assets/ueditorplus/lang/zh-cn/zh-cn.js"></script>
    
    <script>
        // 设置UEditor路径
        window.UEDITOR_HOME_URL = '../assets/ueditorplus/';
        
        // WordPress风格的UEditor初始化
        var ue = UE.getEditor('editor', {
            autoHeightEnabled: true,
            initialFrameHeight: 400,
            minFrameHeight: 400,
            maxFrameHeight: 690,
            wordCount: true,
            maximumWords: 100000,
            serverUrl: '../ueditor_upload.php'
        });

        // WordPress风格的自动保存
        var autosaveLast = '';
        var autosavePeriodical;
        
        function checkForChanges() {
            var title = document.getElementById('title').value;
            var content = ue.getContent();
            
            if (title + content !== autosaveLast) {
                autosaveLast = title + content;
                console.log('内容已变更，准备自动保存...');
            }
        }

        // 监听编辑器准备就绪
        ue.ready(function() {
            console.log('WordPress风格编辑器已加载');
            
            // 启动自动检查
            autosavePeriodical = setInterval(checkForChanges, 30000);
            
            // 监听内容变化
            ue.addListener('contentChange', function() {
                console.log('内容发生变化');
            });
        });



        // 表单提交前清理
        document.getElementById('post').addEventListener('submit', function() {
            clearInterval(autosavePeriodical);
            // 移除beforeunload事件，防止表单提交时触发离开提示
            window.removeEventListener('beforeunload', beforeUnloadHandler);
        });

        // 定义beforeunload处理函数，便于移除
        function beforeUnloadHandler(e) {
            var title = document.getElementById('title').value;
            var content = ue.getContent();
            
            if (title || content) {
                e.preventDefault();
                e.returnValue = '';
            }
        }

        // 页面卸载前提示
        window.addEventListener('beforeunload', beforeUnloadHandler);
    </script>
</body>
</html>