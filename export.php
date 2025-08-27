<?php
require_once 'config.php';
require_once 'includes/init.php';

// 获取数据库连接
$pdo = get_db();

// 获取文档ID和导出格式
$document_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$format = isset($_GET['format']) ? strtolower($_GET['format']) : 'html';

if (!$document_id) {
    die('文档ID不能为空');
}

// 获取文档信息
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$document_id]);
$document = $stmt->fetch();

if (!$document) {
    die('文档不存在');
}

// 使用本地Parsedown
require_once 'Parsedown.php';
$Parsedown = new Parsedown();

// 处理不同格式的导出
switch ($format) {
    case 'html':
        export_html($document, $Parsedown);
        break;
    case 'pdf':
        export_pdf($document, $Parsedown);
        break;
    case 'md':
        export_md($document);
        break;
    default:
        die('不支持的导出格式');
}

function export_html($document, $Parsedown) {
    $html = $Parsedown->text($document['content']);
    
    $output = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($document['title']) . '</title>
    <link rel="stylesheet" href="assets/css/static/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .document-header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #dee2e6; }
        .document-meta { color: #6c757d; font-size: 14px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="document-header">
            <h1>' . htmlspecialchars($document['title']) . '</h1>
            <div class="document-meta">
                <p>更新时间：' . date('Y-m-d H:i', strtotime($document['updated_at'])) . '</p>
            </div>
        </div>
        <div class="document-content">
            ' . $html . '
        </div>
    </div>
</body>
</html>';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $document['title'] . '.html"');
    echo $output;
}

function export_pdf($document, $Parsedown) {
    // 由于PDF导出需要额外的库，这里提供一个简单的HTML转PDF的替代方案
    // 实际项目中可以使用TCPDF或DomPDF等库
    
    $html = $Parsedown->text($document['content']);
    
    $output = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($document['title']) . '</title>
    <style>
        body { 
            font-family: "SimSun", "宋体", serif;
            line-height: 1.6;
            margin: 40px;
            color: #333;
        }
        h1, h2, h3, h4, h5, h6 {
            color: #2c3e50;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        h1 { font-size: 24px; border-bottom: 2px solid #3498db; padding-bottom: 10px; }
        h2 { font-size: 20px; }
        h3 { font-size: 18px; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border-left: 4px solid #3498db;
        }
        code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Consolas", "Monaco", monospace;
        }
        blockquote {
            border-left: 4px solid #bdc3c7;
            padding-left: 20px;
            margin: 20px 0;
            color: #7f8c8d;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        .document-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid #3498db;
        }
        .document-meta {
            color: #7f8c8d;
            font-size: 14px;
            margin-top: 20px;
        }
        @media print {
            body { margin: 20px; }
            .document-header { page-break-after: avoid; }
            h1, h2, h3 { page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="document-header">
            <h1>' . htmlspecialchars($document['title']) . '</h1>
            <div class="document-meta">
                <p>更新时间：' . date('Y-m-d H:i', strtotime($document['updated_at'])) . '</p>
            </div>
        </div>
    <div class="document-content">
        ' . $html . '
    </div>
    <script>
        window.print();
    </script>
</body>
</html>';
    
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $document['title'] . '.pdf.html"');
    echo $output;
}

function export_md($document) {
    $output = "# " . $document['title'] . "\n\n";
    $output .= "**更新时间：** " . date('Y-m-d H:i', strtotime($document['updated_at'])) . "\n\n";
    $output .= "---\n\n";
    $output .= $document['content'];
    
    header('Content-Type: text/markdown; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $document['title'] . '.md"');
    echo $output;
}
?>