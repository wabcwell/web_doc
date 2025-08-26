<?php

require_once __DIR__ . '/init.php';

class DocumentTree {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: get_db();
    }
    
    /**
     * 获取文档树形结构
     */
    public function getTree($parent_id = null) {
        $sql = "SELECT d.*, u.username 
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.parent_id " . ($parent_id === null ? "IS NULL" : "= " . intval($parent_id)) . " 
                ORDER BY d.sort_order ASC, d.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as &$doc) {
            $doc['children'] = $this->getTree($doc['id']);
        }
        
        return $documents;
    }
    
    /**
     * 获取文档的所有子文档
     */
    public function getChildren($parent_id) {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.parent_id = ? 
                                  ORDER BY d.sort_order ASC, d.id ASC");
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取文档的同级文档
     */
    public function getSiblings($document_id) {
        // 获取当前文档的父ID
        $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        $parent_id = $stmt->fetchColumn();
        
        $sql = "SELECT d.*, u.username 
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.parent_id " . ($parent_id === null ? "IS NULL" : "= " . intval($parent_id)) . " 
                AND d.id != ? 
                ORDER BY d.sort_order ASC, d.id ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取文档的层级深度
     */
    public function getDepth($document_id) {
        $depth = 0;
        $current_id = $document_id;
        
        while ($current_id) {
            $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE id = ?");
            $stmt->execute([$current_id]);
            $parent_id = $stmt->fetchColumn();
            
            if ($parent_id) {
                $depth++;
                $current_id = $parent_id;
            } else {
                break;
            }
        }
        
        return $depth;
    }
    
    /**
     * 获取文档的面包屑路径
     */
    public function getBreadcrumbs($document_id) {
        $breadcrumbs = [];
        $current_id = $document_id;
        
        while ($current_id) {
            $stmt = $this->db->prepare("SELECT id, title, parent_id FROM documents WHERE id = ?");
            $stmt->execute([$current_id]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($doc) {
                array_unshift($breadcrumbs, [
                    'id' => $doc['id'],
                    'title' => $doc['title']
                ]);
                $current_id = $doc['parent_id'];
            } else {
                break;
            }
        }
        
        // 移除最后一个（当前文档）
        array_pop($breadcrumbs);
        
        return $breadcrumbs;
    }
    
    /**
     * 获取所有文档总数
     */
    public function getTotalDocuments() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM documents");
        return $stmt->fetchColumn();
    }
    
    /**
     * 获取所有用户总数
     */
    public function getTotalUsers() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM users");
        return $stmt->fetchColumn();
    }
    
    /**
     * 获取最近更新的文档
     */
    public function getRecentDocuments($limit = 5) {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  ORDER BY d.updated_at DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 渲染树形选项
     */
    public function renderTreeOptions($tree, $selected_id = null, $exclude_id = null, $indent = '') {
        foreach ($tree as $doc) {
            if ($doc['id'] == $exclude_id) continue;
            
            $selected = ($selected_id == $doc['id']) ? ' selected' : '';
            echo "<option value=\"{$doc['id']}\"{$selected}>{$indent}" . htmlspecialchars($doc['title']) . "</option>";
            
            if (!empty($doc['children'])) {
                $this->renderTreeOptions($doc['children'], $selected_id, $exclude_id, $indent . '&nbsp;&nbsp;&nbsp;&nbsp;');
            }
        }
    }
    
    /**
     * 渲染树形结构
     */
    public function renderTree($tree, $level = 0) {
        foreach ($tree as $doc) {
            $indent = str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $level);
            $depth_class = 'tree-indent-' . $level;
            ?>
            <tr>
                <td class="<?php echo $depth_class; ?>">
                    <?php echo $indent; ?>
                    <?php if (!empty($doc['children'])): ?>
                        <i class="bi bi-folder-fill text-warning"></i>
                    <?php else: ?>
                        <i class="bi bi-file-text text-info"></i>
                    <?php endif; ?>
                    <a href="edit.php?id=<?php echo $doc['id']; ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($doc['title']); ?>
                    </a>
                </td>
                <td><?php echo htmlspecialchars($doc['username'] ?? '未知用户'); ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($doc['created_at'])); ?></td>
                <td><?php echo date('Y-m-d H:i', strtotime($doc['updated_at'])); ?></td>
                <td>
                    <span class="badge bg-<?php echo $doc['is_public'] ? 'success' : 'secondary'; ?>">
                        <?php echo $doc['is_public'] ? '公开' : '私有'; ?>
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="edit.php?id=<?php echo $doc['id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="../../document.php?id=<?php echo $doc['id']; ?>" target="_blank" class="btn btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="delete.php?id=<?php echo $doc['id']; ?>" 
                           class="btn btn-outline-danger" 
                           onclick="return confirm('确定要删除这个文档吗？')">
                            <i class="bi bi-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php
            if (!empty($doc['children'])) {
                $this->renderTree($doc['children'], $level + 1);
            }
        }
    }
    
    /**
     * 获取所有文档（扁平化列表）
     */
    public function getAllDocuments() {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  ORDER BY d.sort_order ASC, d.id ASC");
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 为每个文档添加level字段
        foreach ($documents as &$doc) {
            $doc['level'] = $this->getDepth($doc['id']);
        }
        
        return $documents;
    }
}