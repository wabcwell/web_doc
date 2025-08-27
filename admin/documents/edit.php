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
$documents = $tree->getAllDocuments();

// 获取当前文档
$stmt = $db->prepare("SELECT * FROM documents WHERE id = ?");
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
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    
    if (!empty($title)) {
        $stmt = $db->prepare("UPDATE documents SET title = ?, content = ?, parent_id = ?, sort_order = ?, tags = ?, is_public = ?, updated_at = datetime('now') WHERE id = ?");
        $stmt->execute([$title, $content, $parent_id, $sort_order, $tags, $is_public, $id]);
        
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
</head>
<body>
    <div class="main-content">
        <div class="container-fluid">
            <h1>编辑文档</h1>
            
            <form method="post" id="documentForm">
                <!-- 合并后的文档标题和设置 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">基本信息</h5>
                    </div>
                    <div class="card-body">
                        <!-- 标题和公开文档 -->
                        <div class="title-row">
                            <div class="form-group title-field">
                                <label for="title">文档标题 *</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       value="<?php echo htmlspecialchars($document['title'] ?? ''); ?>">
                            </div>
                            
                            <div class="form-group public-checkbox">
                                <div class="checkbox-group">
                                    <input type="checkbox" id="is_public" name="is_public" value="1" 
                                           <?php echo $document['is_public'] ? 'checked' : ''; ?>>
                                    <label for="is_public">公开文档</label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 设置项 -->
                        <div class="settings-row">
                            <div class="form-group parent-field">
                                <label for="parent_id">父文档</label>
                                <select class="form-control" id="parent_id" name="parent_id">
                                    <option value="0">无父文档（顶级文档）</option>
                                    <?php 
                                    if (!empty($documents)) {
                                        foreach ($documents as $doc): 
                                    ?>
                                        <option value="<?php echo $doc['id']; ?>" 
                                                <?php echo $doc['id'] == $document['parent_id'] ? 'selected' : ''; ?>>
                                            <?php echo str_repeat('&nbsp;&nbsp;', $doc['level']) . htmlspecialchars($doc['title']); ?>
                                        </option>
                                    <?php 
                                        endforeach; 
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group tags-field">
                                <label for="tags">标签</label>
                                <input type="text" class="form-control" id="tags" name="tags" 
                                       value="<?php echo htmlspecialchars($document['tags'] ?? ''); ?>" 
                                       placeholder="多个标签用逗号分隔">
                            </div>
                            
                            <div class="form-group sort-field">
                                <label for="sort_order">排序权重</label>
                                <input type="number" class="form-control" id="sort_order" name="sort_order" 
                                       value="<?php echo htmlspecialchars($document['sort_order'] ?? 0); ?>" min="0" 
                                       placeholder="数值越大越靠前">
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 文档内容 -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">文档内容</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <div id="editor"></div>
                            <textarea name="content" id="content" style="display: none;"><?php echo htmlspecialchars($document['content'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="index.php" class="btn btn-secondary">取消</a>
                    <button type="submit" class="btn btn-primary">更新文档</button>
                </div>
            </form>
        </div>
    </div>

    <link rel="stylesheet" href="https://uicdn.toast.com/editor/latest/toastui-editor.min.css" />
    <script src="https://uicdn.toast.com/editor/latest/toastui-editor-all.min.js"></script>
    <script>
    // 初始化编辑器
    const editor = new toastui.Editor({
        el: document.querySelector('#editor'),
        height: '500px',
        initialEditType: 'markdown',
        previewStyle: 'vertical',
        language: 'zh-CN',
        placeholder: '请输入文档内容...',
        initialValue: document.getElementById('content').value,
        hooks: {
            addImageBlobHook: function(blob, callback) {
                const formData = new FormData();
                formData.append('image', blob);
                
                fetch('../upload.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        callback(result.url, 'alt text');
                    } else {
                        alert('图片上传失败：' + result.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('图片上传失败');
                });
            }
        }
    });

    // 表单提交处理
    document.getElementById('documentForm').addEventListener('submit', function(e) {
        document.getElementById('content').value = editor.getMarkdown();
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