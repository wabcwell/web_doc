// 导出功能实现
document.addEventListener('DOMContentLoaded', function() {
    // 代码高亮相关
    if (typeof SyntaxHighlighter !== 'undefined') {
        SyntaxHighlighter.highlight();
    }

    // 导出菜单相关函数
    window.showExportMenu = function() {
        const menu = document.getElementById('exportMenu');
        if (menu) {
            menu.style.display = 'block';
            updateMenuPosition();
        }
    };

    window.hideExportMenu = function() {
        const menu = document.getElementById('exportMenu');
        if (menu) {
            menu.style.display = 'none';
        }
    };

    function updateMenuPosition() {
        const menu = document.getElementById('exportMenu');
        const button = document.querySelector('[onclick="showExportMenu()"]');
        
        if (menu && button) {
            const rect = button.getBoundingClientRect();
            menu.style.top = (rect.bottom + window.scrollY) + 'px';
            menu.style.left = (rect.left + window.scrollX) + 'px';
        }
    }

    // 点击其他地方关闭导出菜单
    function closeExportMenuOnClickOutside(event) {
        const menu = document.getElementById('exportMenu');
        const button = document.querySelector('[onclick="showExportMenu()"]');
        
        if (menu && menu.style.display === 'block' && 
            !menu.contains(event.target) && 
            button !== event.target) {
            hideExportMenu();
        }
    }

    // 键盘导航支持
    function handleKeydown(event) {
        if (event.key === 'Escape') {
            hideExportMenu();
        }
    }

    // 绑定事件监听器
    document.addEventListener('click', closeExportMenuOnClickOutside);
    document.addEventListener('keydown', handleKeydown);

    // 导出为PDF
    window.exportToPDF = function() {
        const content = document.querySelector('.content-body');
        if (!content) return;

        const filename = getDocumentTitle() + '.pdf';
        
        // 使用html2pdf生成PDF
        html2pdf().from(content).set({
            margin: 10,
            filename: filename,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, useCORS: true },
            jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
        }).save();
        
        hideExportMenu();
    };

    // 导出为图片
    window.exportAsImage = function(type) {
        const content = document.querySelector('.content-body');
        if (!content) return;

        const filename = getDocumentTitle() + '.' + type;
        
        html2canvas(content, {
            useCORS: true,
            scale: 2
        }).then(canvas => {
            const link = document.createElement('a');
            link.download = filename;
            link.href = canvas.toDataURL('image/' + type);
            link.click();
        });
        
        hideExportMenu();
    };

    // 导出为HTML
    window.exportToHTML = function() {
        const content = document.querySelector('.content-body');
        if (!content) return;

        const title = getDocumentTitle();
        const htmlContent = `
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>${title}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
        .markdown-content { max-width: 800px; margin: 0 auto; padding: 20px; }
        pre { background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        code { background-color: #f8f9fa; padding: 2px 4px; border-radius: 3px; }
        blockquote { border-left: 4px solid #007bff; padding-left: 16px; color: #6c757d; }
        table { border-collapse: collapse; width: 100%; margin: 16px 0; }
        th, td { border: 1px solid #dee2e6; padding: 8px 12px; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="markdown-content">
        ${content.innerHTML}
    </div>
</body>
</html>`;

        downloadFile(title + '.html', 'text/html', htmlContent);
        hideExportMenu();
    };

    // 导出为Markdown
    window.exportToMarkdown = function() {
        const content = document.querySelector('.content-body');
        if (!content) return;

        const title = getDocumentTitle();
        const markdownContent = htmlToMarkdown(content);
        downloadFile(title + '.md', 'text/markdown', markdownContent);
        hideExportMenu();
    };

    // HTML转Markdown
    function htmlToMarkdown(element) {
        let markdown = '';
        
        function parseNode(node) {
            if (node.nodeType === Node.TEXT_NODE) {
                return node.textContent;
            }
            
            if (node.nodeType !== Node.ELEMENT_NODE) {
                return '';
            }
            
            const tagName = node.tagName.toLowerCase();
            let content = '';
            
            // 处理子节点
            for (let i = 0; i < node.childNodes.length; i++) {
                content += parseNode(node.childNodes[i]);
            }
            
            // 根据标签类型转换
            switch (tagName) {
                case 'h1': return '# ' + content + '\n\n';
                case 'h2': return '## ' + content + '\n\n';
                case 'h3': return '### ' + content + '\n\n';
                case 'h4': return '#### ' + content + '\n\n';
                case 'h5': return '##### ' + content + '\n\n';
                case 'h6': return '###### ' + content + '\n\n';
                case 'p': return content + '\n\n';
                case 'br': return '\n';
                case 'strong': case 'b': return '**' + content + '**';
                case 'em': case 'i': return '*' + content + '*';
                case 'code': return '`' + content + '`';
                case 'pre': return '```\n' + content + '\n```\n\n';
                case 'blockquote': return '> ' + content.replace(/\n/g, '\n> ') + '\n\n';
                case 'ul': return content + '\n';
                case 'ol': return content + '\n';
                case 'li': 
                    const parent = node.parentElement;
                    if (parent && parent.tagName.toLowerCase() === 'ol') {
                        // 简化处理，实际应该计算正确的序号
                        return '1. ' + content + '\n';
                    } else {
                        return '- ' + content + '\n';
                    }
                case 'a': 
                    const href = node.getAttribute('href');
                    return '[' + content + '](' + href + ')';
                case 'img':
                    const src = node.getAttribute('src');
                    const alt = node.getAttribute('alt') || '';
                    return '![' + alt + '](' + src + ')';
                case 'table':
                    return convertTable(node) + '\n';
                default: return content;
            }
        }
        
        // 转换表格
        function convertTable(table) {
            let result = '';
            const rows = table.querySelectorAll('tr');
            
            if (rows.length === 0) return '';
            
            // 处理表头
            const headers = rows[0].querySelectorAll('th, td');
            let headerRow = '|';
            let separatorRow = '|';
            
            headers.forEach(header => {
                headerRow += ' ' + (header.textContent || '') + ' |';
                separatorRow += ' --- |';
            });
            
            result += headerRow + '\n' + separatorRow + '\n';
            
            // 处理数据行
            for (let i = 1; i < rows.length; i++) {
                const cells = rows[i].querySelectorAll('td');
                let row = '|';
                cells.forEach(cell => {
                    row += ' ' + (cell.textContent || '') + ' |';
                });
                result += row + '\n';
            }
            
            return result;
        }
        
        // 遍历所有子节点
        for (let i = 0; i < element.childNodes.length; i++) {
            markdown += parseNode(element.childNodes[i]);
        }

        // 清理多余的空行
        markdown = markdown.replace(/\n{3,}/g, '\n\n');

        return markdown.trim();
    }

    // 获取文档标题
    function getDocumentTitle() {
        const titleElement = document.querySelector('.document-meta .document-title');
        if (titleElement) {
            return sanitizeFilename(titleElement.textContent || 'document');
        }
        
        // 尝试从meta信息获取标题
        const metaTitle = document.querySelector('meta[name="document-title"]');
        if (metaTitle) {
            return sanitizeFilename(metaTitle.getAttribute('content') || 'document');
        }
        
        // 默认标题
        return 'document';
    }

    // 清理文件名
    function sanitizeFilename(filename) {
        return filename.replace(/[<>:"/\\|?*\x00-\x1F]/g, '_').trim();
    }

    function downloadFile(filename, mimeType, content) {
        const blob = new Blob([content], {type: mimeType});
        const link = document.createElement('a');
        link.download = filename;
        link.href = URL.createObjectURL(blob);
        link.click();
        URL.revokeObjectURL(link.href);
    }
});