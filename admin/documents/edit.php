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

// 获取文档和父文档选项
$db = get_db();
$tree = new DocumentTree($db);
$documents = $tree->getAllDocumentsByHierarchy();

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
        
        header('Location: index.php?success=update');
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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../../assets/css/static/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <style>
        /* 解决容器高度限制 */
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
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1>编辑文档</h1>
            
            <form method="post" id="documentForm">
                <div class="d-flex flex-column flex-lg-row" id="responsive-container" style="gap: 15px;">
                    <!-- 左侧：文档标题和内容模块 -->
                    <div class="flex-grow-1">
                        <!-- 文档标题 -->
                        <div class="form-group mb-3">
                            <label for="title">文档标题 *</label>
                            <input type="text" class="form-control" id="title" name="title" required 
                                   value="<?php echo htmlspecialchars($document['title'] ?? ''); ?>" 
                                   placeholder="请输入文档标题">
                        </div>
                        
                        <!-- 文档内容 -->
                        <script id="editor" type="text/plain" style="width:100%;min-height:500px;"><?php echo $document['content'] ?? ''; ?></script>
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
                                        <option value="1" <?php echo $document['is_public'] == 1 ? 'selected' : ''; ?>>公开</option>
                                        <option value="0" <?php echo $document['is_public'] == 0 ? 'selected' : ''; ?>>私有</option>
                                    </select>
                                </div>
                                
                                <!-- 文档状态 -->
                                <div class="form-group mb-3">
                                    <label for="is_formal">文档状态</label>
                                    <select class="form-control" id="is_formal" name="is_formal">
                                        <option value="0" <?php echo $document['is_formal'] == 0 ? 'selected' : ''; ?>>草稿</option>
                                        <option value="1" <?php echo $document['is_formal'] == 1 ? 'selected' : ''; ?>>正式</option>
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
                                               if ($doc['document_id'] == $id) continue; // 排除当前文档
                                        ?>
                                            <option value="<?php echo $doc['document_id']; ?>" 
                                                <?php echo $doc['document_id'] == $document['parent_id'] ? 'selected' : ''; ?>>
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
                                           value="<?php echo htmlspecialchars($document['tags'] ?? ''); ?>"
                                           placeholder="多个标签用逗号分隔">
                                </div>
                                
                                <!-- 排序权重 -->
                                <div class="form-group mb-4">
                                    <label for="sort_order">排序权重</label>
                                    <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                           value="<?php echo htmlspecialchars($document['sort_order'] ?? '0'); ?>" 
                                           min="0" placeholder="数值越大越靠前">
                                </div>
                                
                                <!-- 按钮组 -->
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">保存修改</button>
                                    <a href="index.php" class="btn btn-secondary">取消</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- UEditorPlus脚本 -->
    <script>
    // 必须在加载ueditor.config.js之前设置UEDITOR_HOME_URL
    window.UEDITOR_HOME_URL = '/admin/assets/ueditorplus/';
    </script>
    <script src="../assets/ueditorplus/ueditor.config.js"></script>
    <script src="../assets/ueditorplus/ueditor.all.js"></script>
    <script src="../assets/ueditorplus/lang/zh-cn/zh-cn.js"></script>
    
    <script>
    // 初始化UEditorPlus
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
        serverUrl: '/admin/ueditor_upload.php?document_id=<?php echo $id; ?>'
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