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
    case 'jpg':
    case 'png':
        // 图片格式通过前端html2canvas处理，这里返回一个引导页面
        export_image_guide($document, $format);
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

function export_image_guide($document, $format) {
    $title = htmlspecialchars($document['title']);
    $format_upper = strtoupper($format);
    $updated_at = date('Y-m-d H:i', strtotime($document['updated_at']));
    $content = nl2br(htmlspecialchars($document['content']));
    
    $output = '<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导出为 ' . $format_upper . ' 图片 - ' . $title . '</title>
    <link rel="stylesheet" href="assets/css/static/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/static/bootstrap-icons.min.css">
    <style>
        body { 
            padding: 20px; 
            background: #f8f9fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 40px;
        }
        .icon {
            font-size: 48px;
            color: #0d6efd;
            margin-bottom: 20px;
        }
        .btn-export {
            background: #0d6efd;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-export:hover {
            background: #0b5ed7;
        }
        .loading {
            display: none;
            text-align: center;
            color: #6c757d;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0d6efd;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center">
            <i class="bi bi-image icon"></i>
            <h2>导出为 ' . $format_upper . ' 图片</h2>
            <p class="text-muted mb-4">文档：<strong>' . $title . '</strong></p>
            
            <button class="btn-export" onclick="exportImage()">
                <i class="bi bi-download"></i> 开始导出
            </button>
            
            <div class="loading" id="loading">
                <div class="spinner"></div>
                <p>正在生成图片，请稍候...</p>
            </div>
            
            <div class="mt-4">
                <small class="text-muted">
                    <i class="bi bi-info-circle"></i> 
                    图片将通过浏览器自动生成并下载，可能需要几秒钟时间
                </small>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script>
        function exportImage() {
            const loading = document.getElementById("loading");
            const button = document.querySelector(".btn-export");
            
            // 显示加载状态
            button.style.display = "none";
            loading.style.display = "block";
            
            // 获取文档内容
            const title = "' . addslashes($title) . '"; 
            
            // 创建临时内容区域
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = `
                <div style="padding: 40px; background: white; max-width: 800px; margin: 0 auto;">
                    <h1 style="margin-bottom: 20px; color: #333; border-bottom: 2px solid #0d6efd; padding-bottom: 10px;">${title}</h1>
                    <div style="color: #6c757d; font-size: 14px; margin-bottom: 30px;">
                        <p><strong>更新时间：</strong>' . $updated_at . '</p>
                    </div>
                    <div style="line-height: 1.8; color: #333;">
                        ' . $content . '
                    </div>
                </div>
            `;
            
            document.body.appendChild(tempDiv);
            
            html2canvas(tempDiv, {
                backgroundColor: "#f8f9fa",
                scale: 2,
                useCORS: true,
                allowTaint: true,
                width: 800,
                height: tempDiv.scrollHeight
            }).then(canvas => {
                // 创建下载链接
                const link = document.createElement("a");
                link.download = `${title}.' . $format . '`;
                
                if ("' . $format . '" === "jpg") {
                    link.href = canvas.toDataURL("image/jpeg", 0.9);
                } else {
                    link.href = canvas.toDataURL("image/png");
                }
                
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                
                // 清理临时元素
                document.body.removeChild(tempDiv);
                
                // 返回上一页
                setTimeout(() => {
                    window.history.back();
                }, 1000);
                
            }).catch(error => {
                console.error("导出失败:", error);
                alert("图片导出失败，请重试");
                window.history.back();
            });
        }
        
        // 自动触发导出
        setTimeout(exportImage, 500);
    </script>
</body>
</html>';
    
    header('Content-Type: text/html; charset=utf-8');
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
    header('Content-Disposition: attachment; filename="' . $document['title'] . '.pdf"');
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