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
    
    if (!empty($title)) {
        // 生成唯一的update_code
        $update_code = uniqid() . '_' . time();
        
        // 使用预生成的document_id
        $new_document_id = $pre_generated_document_id;
        
        $stmt = $db->prepare("INSERT INTO documents (document_id, title, content, parent_id, sort_order, tags, is_public, is_formal, created_at, updated_at, update_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, datetime('now'), datetime('now'), ?)");
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
    <link rel="stylesheet" href="../../assets/css/static/bootstrap.min.css">
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

                <div class="d-flex flex-column flex-lg-row" id="responsive-container" style="gap: 15px;">
                    <!-- 左侧：文档标题和内容模块 -->
                    <div class="flex-grow-1">
                        <!-- 文档标题 -->
                        <div class="form-group mb-3">
                            <input type="text" class="form-control" id="title" name="title" required 
                                   placeholder="请输入文档标题">
                        </div>
                        
                        <!-- 文档内容 -->
                        <script id="editor" type="text/plain" style="width:100%;min-height:500px;"></script>
                        <textarea name="content" id="content" style="display: none;"></textarea>
                    </div>
                    
                    <!-- 右侧：设置和按钮模块 -->
                    <div class="flex-shrink-0" style="width: 280px; flex: 0 0 280px;">
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

    // 初始化UEditorPlus - 启用自动增高并支持Excel表格粘贴
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
        serverUrl: '/admin/ueditor_upload.php?document_id=<?php echo $pre_generated_document_id; ?>',
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
    
    // 监听粘贴事件，确保Excel表格不被转换为图片
    ue.ready(function() {
        ue.addListener('beforepaste', function(type, data) {
            console.log('Paste event detected:', type, data);
            // 检查粘贴内容是否包含表格标签
            if (data.html && data.html.includes('<table')) {
                console.log('Table content detected, bypassing UEditor processing');
                // 如果是表格内容，完全阻止UEditor处理，使用原生方式插入
                setTimeout(function() {
                    // 使用document.execCommand直接插入HTML，完全绕过UEditor
                    ue.focus();
                    var range = ue.selection.getRange();
                    range.select();
                    document.execCommand('insertHTML', false, data.html);
                    console.log('Table inserted successfully');
                }, 10);
                return false; // 阻止默认粘贴行为
            }
        });
        
        // 监听afterpaste事件，确保表格没有被转换
        ue.addListener('afterpaste', function() {
            setTimeout(function() {
                // 检查编辑器内容中是否有被转换为图片的表格
                var content = ue.getContent();
                if (content.includes('<img') && content.includes('data-table')) {
                    console.log('Detected table converted to image, attempting to restore');
                    // 这里可以添加恢复逻辑，如果需要的话
                }
            }, 100);
        });
        
        // 监听afterpaste事件，为表头添加浅灰色背景
         ue.addListener('afterpaste', function() {
             // 获取UEditor的iframe文档对象
             var iframe = ue.iframe;
             var iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
             
             // 添加调试日志
             console.log('afterpaste事件触发');
             
             // 查找所有表格
             var tables = iframeDoc.querySelectorAll('table');
             console.log('找到表格数量:', tables.length);
             
             tables.forEach(function(table) {
                 // 查找所有表头单元格（th元素）
                 var thCells = table.querySelectorAll('th');
                 console.log('表格中找到th表头数量:', thCells.length);
                 
                 thCells.forEach(function(th) {
                     th.style.backgroundColor = '#f8f9fa';
                     console.log('设置th表头背景色:', th.style.backgroundColor);
                 });
                 
                 // 处理第一行作为表头的情况（如果使用td而不是th）
                 var firstRow = table.querySelector('tr');
                 if (firstRow && thCells.length === 0) {
                     var firstRowCells = firstRow.querySelectorAll('td');
                     console.log('第一行中找到td单元格数量:', firstRowCells.length);
                     
                     firstRowCells.forEach(function(td) {
                         td.style.backgroundColor = '#f8f9fa';
                         console.log('设置第一行td背景色:', td.style.backgroundColor);
                     });
                 }
             });
             
             if (tables.length > 0) {
                 console.log('Applied header background to', tables.length, 'tables');
             }
         });
    });
    </script>
</body>
</html>