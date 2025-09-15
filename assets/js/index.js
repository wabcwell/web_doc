// 代码高亮转换功能
function convertAndHighlightCode() {
    console.log('Starting code highlighting conversion...');
    
    // 查找所有使用SyntaxHighlighter格式的pre标签
    const pres = document.querySelectorAll('pre[class*="brush:"]');
    console.log('Found', pres.length, 'code blocks to convert');
    
    if (pres.length === 0) {
        console.log('No SyntaxHighlighter format found, checking for other formats...');
        return;
    }
    
    pres.forEach((pre, index) => {
        console.log('Processing block', index + 1, 'class:', pre.className);
        
        const className = pre.className;
        const match = className.match(/brush:\s*(\w+)/);
        if (match) {
            const language = match[1].toLowerCase();
            console.log('Converting to language:', language);
            
            // 获取原始内容 - 优先使用textContent避免HTML实体问题
            let content = pre.textContent || pre.innerText || '';
            
            // 清理内容：替换所有类型的空格和HTML实体
            content = content
                .replace(/&nbsp;/g, ' ')
                .replace(/\u00A0/g, ' ')
                .replace(/&amp;/g, '&')
                .replace(/&lt;/g, '<')
                .replace(/&gt;/g, '>')
                .replace(/&quot;/g, '"')
                .replace(/&#39;/g, "'")
                .trim();
            
            console.log('Content length after cleanup:', content.length);
            
            // 处理代码高亮
            pre.className = `language-${language}`;
            
            // 确保有code标签
            if (!pre.querySelector('code')) {
                const code = document.createElement('code');
                code.className = `language-${language}`;
                code.textContent = content;
                pre.innerHTML = '';
                pre.appendChild(code);
            }
        }
    });
    
    // 初始化SyntaxHighlighter
    setTimeout(() => {
        if (typeof SyntaxHighlighter !== 'undefined') {
            console.log('SyntaxHighlighter loaded, highlighting...');
            SyntaxHighlighter.highlight();
            console.log('Highlighting completed');
        } else {
            console.error('SyntaxHighlighter not loaded');
        }
    }, 100);
}

// 添加复制按钮功能
function addCopyButtons() {
    // 查找所有代码块
    const codeBlocks = document.querySelectorAll('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre');
    
    codeBlocks.forEach(block => {
        // 避免重复添加
        if (block.parentNode.classList.contains('code-block-wrapper')) {
            return;
        }
        
        // 创建包装器
        const wrapper = document.createElement('div');
        wrapper.className = 'code-block-wrapper';
        
        // 创建复制按钮
        const copyButton = document.createElement('button');
        copyButton.className = 'copy-button';
        copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
        copyButton.title = '复制代码';
        
        // 插入包装器
        block.parentNode.insertBefore(wrapper, block);
        wrapper.appendChild(block);
        wrapper.appendChild(copyButton);
        
        // 添加点击事件
        copyButton.addEventListener('click', function() {
            let codeText = '';
            
            // 根据不同类型获取代码内容
            if (block.classList.contains('syntaxhighlighter')) {
                // SyntaxHighlighter
                const lines = block.querySelectorAll('.line');
                codeText = Array.from(lines).map(line => line.textContent).join('\n');
            } else if (block.querySelector('code')) {
                // 处理代码高亮
                codeText = block.querySelector('code').textContent;
            } else {
                // 普通pre标签
                codeText = block.textContent;
            }
            
            // 复制到剪贴板
            navigator.clipboard.writeText(codeText.trim()).then(() => {
                // 显示成功状态
                copyButton.innerHTML = '<i class="bi bi-check"></i> 已复制';
                copyButton.classList.add('copied');
                
                // 2秒后恢复原状
                setTimeout(() => {
                    copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
                    copyButton.classList.remove('copied');
                }, 2000);
            }).catch(err => {
                console.error('复制失败:', err);
                // 备用复制方法
                const textArea = document.createElement('textarea');
                textArea.value = codeText.trim();
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                copyButton.innerHTML = '<i class="bi bi-check"></i> 已复制';
                copyButton.classList.add('copied');
                setTimeout(() => {
                    copyButton.innerHTML = '<i class="bi bi-clipboard"></i> 复制';
                    copyButton.classList.remove('copied');
                }, 2000);
            });
        });
    });
}

// 侧边栏切换功能
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.querySelector('.main-content');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    
    sidebar.classList.toggle('collapsed');
    mainContent.classList.toggle('expanded');
    toggleBtn.classList.toggle('collapsed');
    
    if (sidebar.classList.contains('collapsed')) {
        toggleIcon.className = 'bi bi-chevron-right';
        toggleBtn.title = '展开目录';
    } else {
        toggleIcon.className = 'bi bi-chevron-left';
        toggleBtn.title = '收起目录';
    }
    
    // 保存状态到本地存储
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
}

// 文档搜索功能
function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    const documentItems = document.querySelectorAll('.document-item');
    
    searchInput.addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        
        documentItems.forEach(item => {
            const title = item.textContent.toLowerCase();
            const parentNode = item.closest('.document-node');
            
            if (title.includes(searchTerm)) {
                item.style.display = 'block';
                if (parentNode) {
                    parentNode.style.display = 'block';
                }
            } else {
                item.style.display = searchTerm ? 'none' : 'block';
            }
        });
    });
}

// 文档树展开/收起功能
function setupDocumentTree() {
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('document-toggle') || e.target.closest('.document-toggle')) {
            const toggle = e.target.classList.contains('document-toggle') ? e.target : e.target.closest('.document-toggle');
            const targetId = toggle.getAttribute('data-toggle');
            const children = document.getElementById('children-' + targetId);
            
            if (children) {
                children.classList.toggle('collapsed');
                toggle.classList.toggle('collapsed');
                toggle.classList.toggle('expanded');
                
                // 保存展开状态
                const isCollapsed = children.classList.contains('collapsed');
                const expandedItems = JSON.parse(localStorage.getItem('expandedItems') || '{}');
                if (isCollapsed) {
                    delete expandedItems[targetId];
                } else {
                    expandedItems[targetId] = true;
                }
                localStorage.setItem('expandedItems', JSON.stringify(expandedItems));
            }
        }
    });
}

// 初始化函数
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded fired');
    
    // 初始化侧边栏切换
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
        
        // 恢复侧边栏状态
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            toggleSidebar();
        }
    }
    
    // 初始化搜索
    setupSearch();
    
    // 初始化文档树
    setupDocumentTree();
    
    // 恢复文档树展开状态
    const expandedItems = JSON.parse(localStorage.getItem('expandedItems') || '{}');
    Object.keys(expandedItems).forEach(id => {
        const toggle = document.querySelector(`[data-toggle="${id}"]`);
        const children = document.getElementById('children-' + id);
        if (toggle && children) {
            children.classList.remove('collapsed');
            toggle.classList.remove('collapsed');
            toggle.classList.add('expanded');
        }
    });
    
    // 延迟执行代码高亮转换，确保所有内容都已加载
    setTimeout(convertAndHighlightCode, 500);
    
    // 添加复制按钮
    setTimeout(addCopyButtons, 1000);
});

// 页面加载完成后执行
window.addEventListener('load', function() {
    console.log('window.load fired');
    setTimeout(convertAndHighlightCode, 1000);
});

// 监听内容变化，为新增代码块添加复制按钮
const observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
        if (mutation.type === 'childList') {
            let hasCodeBlocks = false;
            mutation.addedNodes.forEach(function(node) {
                if (node.nodeType === 1) { // 元素节点
                    if (node.matches && (node.matches('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre') || 
                        node.querySelector && node.querySelector('.syntaxhighlighter, pre[class*="language-"], .markdown-content pre'))) {
                        hasCodeBlocks = true;
                    }
                }
            });
            
            if (hasCodeBlocks) {
                setTimeout(addCopyButtons, 500);
            }
        }
    });
});

// 开始监听
observer.observe(document.body, {
    childList: true,
    subtree: true
});

// 导出供全局使用的函数
window.refreshCodeHighlighting = function() {
    console.log('Manual refresh triggered');
    convertAndHighlightCode();
};

window.addCopyButtons = addCopyButtons;