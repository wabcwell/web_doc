<?php
require_once '../../config.php';
require_once '../../includes/init.php';
require_once '../../includes/auth.php';
require_once '../../includes/DocumentTree.php';

// 检查用户权限
Auth::requireLogin();

// 获取文档ID
$id = $_GET['id'] ?? 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// 获取父文档选项
$db = get_db();
$tree = new DocumentTree($db);
$documents = $tree->getAllDocumentsByHierarchy(100); // 限制返回100个文档以减少内存使用

// 获取当前文档
$stmt = $db->prepare("SELECT * FROM documents WHERE document_id = ?");
$stmt->execute([$id]);
$document = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$document) {
    header('Location: index.php');
    exit;
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
        // 获取当前文档信息用于记录日志和保存版本
        $old_document = $document;
        
        // 初始化变更状态标记
        $changes = [
            'op_title' => 0,
            'op_content' => 0,
            'op_tags' => 0,
            'op_parent' => 0,
            'op_corder' => 0,
            'op_public' => 0,
            'op_formal' => 0
        ];
        
        // 标准化处理函数
        $normalize = function($value) {
            if ($value === null || $value === false) {
                return '';
            }
            // 强制转换为字符串并标准化
            $value = trim((string)$value);
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            $value = preg_replace('/[ \t]+$/m', '', $value);
            $value = preg_replace('/\n{3,}/', "\n\n", $value);
            return $value;
        };
        
        // 检测各字段变更
        $changes['op_title'] = ($normalize($old_document['title']) !== $normalize($title)) ? 1 : 0;
        $changes['op_content'] = ($normalize($old_document['content']) !== $normalize($content)) ? 1 : 0;
        $changes['op_tags'] = ($normalize($old_document['tags']) !== $normalize($tags)) ? 1 : 0;
        $changes['op_parent'] = ($old_document['parent_id'] != $parent_id) ? 1 : 0;
        $changes['op_corder'] = ($old_document['sort_order'] != $sort_order) ? 1 : 0;
        
        // 检测公开状态变更
        if ($old_document['is_public'] != $is_public) {
            $changes['op_public'] = ($is_public == 1) ? 1 : 2;
        }
        
        // 检测正式状态变更
        if ($old_document['is_formal'] != $is_formal) {
            $changes['op_formal'] = ($is_formal == 1) ? 1 : 2;
        }
        
        // 判断是否有任何变更
        $has_changes = array_sum($changes) > 0;
        
        // 生成唯一的update_code
        $update_code = uniqid() . '_' . time();
        
        // 更新文档
        $stmt = $db->prepare("UPDATE documents SET title = ?, content = ?, parent_id = ?, sort_order = ?, tags = ?, is_public = ?, is_formal = ?, updated_at = datetime('now'), update_code = ? WHERE document_id = ?");
        $stmt->execute([$title, $content, $parent_id, $sort_order, $tags, $is_public, $is_formal, $update_code, $id]);
        
        // 记录编辑日志
        log_edit(
            $id,
            $_SESSION['user_id'],
            'update',
            $changes,
            $update_code
        );
        
        // 仅在内容有变更时保存新版本
        if ($has_changes) {
            save_document_version($id, $title, $content, $_SESSION['user_id'], $tags, $update_code);
        }
        
        // 使用POST-REDIRECT-GET模式避免表单重复提交
        header('Location: edit.php?id=' . $id . '&updated=1');
        exit;
    }
}

$title = '编辑文档';
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
            <h1 class="wp-heading-inline"><i class="bi bi-pencil-square"></i> 编辑文档</h1>
            <hr class="wp-header-end">
            
            <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
            <div class="notice notice-success">
                <p>文档已更新！</p>
            </div>
            <?php elseif (isset($_GET['success']) && $_GET['success'] === 'add'): ?>
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
                                           value="<?php echo htmlspecialchars($document['title'] ?? ''); ?>" 
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
                                    <script id="editor" name="content" type="text/plain"><?php echo $document['content'] ?? ''; ?></script>
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
                                        <div class="small text-muted mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span>创建时间</span>
                                                <span><?php echo date('Y-m-d H:i', strtotime($document['created_at'])); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between">
                                                <span>更新时间</span>
                                                <span><?php echo date('Y-m-d H:i', strtotime($document['updated_at'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0">可见性</label>
                                        <select name="is_public" class="form-select form-select-sm" style="width: 120px;">
                                            <option value="1" <?php echo $document['is_public'] == 1 ? 'selected' : ''; ?>>公开</option>
                                            <option value="0" <?php echo $document['is_public'] == 0 ? 'selected' : ''; ?>>私密</option>
                                        </select>
                                    </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                        <label class="form-label mb-0">文档状态</label>
                                        <select name="is_formal" class="form-select form-select-sm" style="width: 120px;">
                                            <option value="0" <?php echo $document['is_formal'] == 0 ? 'selected' : ''; ?>>草稿</option>
                                            <option value="1" <?php echo $document['is_formal'] == 1 ? 'selected' : ''; ?>>正式文档</option>
                                        </select>
                                    </div>
                                    </div>
                                    
                                    <div style="padding: 4px 12px; text-align: right;">
                                        <input type="submit" name="publish" id="publish" class="button button-primary button-large" value="更新" style="background: #0073aa; border-color: #006799; color: #fff; padding: 0 25px; font-size: 14px; height: 32px; border: 1px solid; border-radius: 3px; cursor: pointer; font-weight: 600;">
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
                                                if ($item['document_id'] == $_GET['id']) continue; // 排除当前文档
                                                $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $item['level']);
                                                $prefix = $item['level'] > 0 ? '└─ ' : '';
                                                // $selected现在是parent_id（存储的是document_id），直接与$item['document_id']比较
                                                $selected_attr = ($item['document_id'] == $selected) ? ' selected' : '';
                                                $title = htmlspecialchars($item['title']);
                                                
                                                echo "<option value='{$item['document_id']}'{$selected_attr}>{$indent}{$prefix}{$title}</option>";
                                            }
                                        }
                                        renderOptions($documents, $document['parent_id']);
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
                                           value="<?php echo htmlspecialchars($document['sort_order'] ?? '0'); ?>" 
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
                                           value="<?php echo htmlspecialchars($document['tags'] ?? ''); ?>" 
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
            serverUrl: '../ueditor_upload.php?document_id=<?php echo $id; ?>',
            retainOnlyLabelPasted: false,
            pasteplain: false,
            enableAutoSave: false,
            // 禁用Word图片转存功能，防止Excel表格被转换为图片
            wordImage: {
                enabled: false
            },
            // 禁用所有粘贴过滤和转换功能
            pasteFilter: false,
            enablePasteUpload: false,
            catchRemoteImageEnable: false
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
        
        // 保存快捷键
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('post').submit();
            }
        });

        // 定义beforeunload处理函数，便于移除
        function beforeUnloadHandler(e) {
            var title = document.getElementById('title').value;
            var content = ue.getContent();
            
            var originalTitle = <?php echo json_encode($document['title']); ?>;
            var originalContent = <?php echo json_encode($document['content']); ?>;
            
            if (title !== originalTitle || content !== originalContent) {
                e.preventDefault();
                e.returnValue = '';
            }
        }

        // 页面卸载前提示
        window.addEventListener('beforeunload', beforeUnloadHandler);
    </script>
</body>
</html>