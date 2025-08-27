<?php
require_once 'config.php';

// 获取搜索关键词
$query = isset($_GET['q']) ? trim($_GET['q']) : '';
$results = [];

if ($query) {
    // 搜索文档
    $stmt = $pdo->prepare("SELECT * FROM documents WHERE title LIKE ? OR content LIKE ? ORDER BY updated_at DESC");
    $search_term = '%' . $query . '%';
    $stmt->execute([$search_term, $search_term]);
    $results = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>搜索 - <?php echo htmlspecialchars($query ?: '文档搜索'); ?> - 文档系统</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css">
    <style>
        .search-highlight {
            background-color: yellow;
            padding: 2px 4px;
            border-radius: 3px;
        }
        .search-result {
            margin-bottom: 20px;
            padding: 15px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
        }
        .search-result h4 {
            margin-bottom: 10px;
        }
        .search-result .category {
            color: #6c757d;
            font-size: 14px;
        }
        .search-result .date {
            color: #6c757d;
            font-size: 12px;
        }
        .search-result .content {
            margin-top: 10px;
            font-size: 14px;
            color: #333;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">文档系统</a>
            <div class="navbar-nav">
                <a class="nav-link" href="index.php">首页</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>文档搜索</h2>
                
                <form action="search.php" method="get" class="mb-4">
                    <div class="input-group">
                        <input type="text" class="form-control" name="q" placeholder="输入关键词搜索..." value="<?php echo htmlspecialchars($query); ?>">
                        <button class="btn btn-primary" type="submit">搜索</button>
                    </div>
                </form>

                <?php if ($query): ?>
                    <h4>搜索结果 "<?php echo htmlspecialchars($query); ?>"</h4>
                    
                    <?php if (empty($results)): ?>
                        <div class="alert alert-info">没有找到相关文档</div>
                    <?php else: ?>
                        <p>找到 <?php echo count($results); ?> 篇相关文档</p>
                        
                        <?php
                        // 使用本地Parsedown
                        require_once 'Parsedown.php';
                        $Parsedown = new Parsedown();
                        $Parsedown->setSafeMode(true);
                        
                        foreach ($results as $result):
                            // 高亮显示关键词
                            $title = $result['title'];
                            $content = $result['content'];
                            
                            // 截取内容片段
                            $content_length = 200;
                            $pos = stripos($content, $query);
                            if ($pos !== false) {
                                $start = max(0, $pos - 50);
                                $content = substr($content, $start, $content_length);
                                $content = htmlspecialchars($content);
                                $content = str_ireplace($query, '<span class="search-highlight">' . htmlspecialchars($query) . '</span>', $content);
                            } else {
                                $content = htmlspecialchars(substr($content, 0, $content_length));
                            }
                            
                            $title = str_ireplace($query, '<span class="search-highlight">' . htmlspecialchars($query) . '</span>', htmlspecialchars($title));
                        ?>
                            <div class="search-result">
                                <h4><a href="index.php?id=<?php echo $result['id']; ?>"><?php echo $title; ?></a></h4>
                                <div class="date">更新时间：<?php echo date('Y-m-d H:i', strtotime($result['updated_at'])); ?></div>
                                <div class="content">
                                    <?php echo $Parsedown->text($content . '...'); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5>搜索提示</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li>• 支持关键词搜索</li>
                            <li>• 搜索标题和内容</li>
                            <li>• 结果按更新时间排序</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>