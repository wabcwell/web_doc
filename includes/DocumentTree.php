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
    public function getTree($parent_id = 0) {
        $sql = "SELECT d.*, u.username, d.is_formal 
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.parent_id = " . intval($parent_id) . " AND d.del_status = 0
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
                                  WHERE d.parent_id = ? AND d.del_status = 0
                                  ORDER BY d.sort_order ASC, d.id ASC");
        $stmt->execute([$parent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取文档的同级文档
     */
    public function getSiblings($document_id) {
        // 获取当前文档的父ID
        $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE id = ? AND del_status = 0");
        $stmt->execute([$document_id]);
        $parent_id = $stmt->fetchColumn();
        
        $sql = "SELECT d.*, u.username 
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.parent_id = " . intval($parent_id) . " AND d.del_status = 0
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
        
        while ($current_id > 0) {
            $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE id = ? AND del_status = 0");
            $stmt->execute([$current_id]);
            $parent_id = $stmt->fetchColumn();
            
            if ($parent_id > 0) {
                $depth++;
                $current_id = $parent_id;
            } else {
                break;
            }
        }
        
        return $depth;
    }
    
    /**
     * 获取文档的面包屑导航
     */
    public function getBreadcrumbs($document_id) {
        $breadcrumbs = [];
        $current_id = $document_id;
        
        while ($current_id > 0) {
            $stmt = $this->db->prepare("SELECT id, title, parent_id FROM documents WHERE id = ? AND del_status = 0");
            $stmt->execute([$current_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($document) {
                array_unshift($breadcrumbs, $document);
                $current_id = $document['parent_id'];
            } else {
                break;
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * 获取所有文档总数
     */
    public function getTotalDocuments() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0");
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
    public function getRecentDocuments($limit = 10) {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.del_status = 0
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
                    <?php 
                    $visibility_status = $doc['is_public'] ? '公开' : '私有';
                    $visibility_class = $doc['is_public'] ? 'success' : 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $visibility_class; ?>">
                        <?php echo $visibility_status; ?>
                    </span>
                </td>
                <td>
                    <?php 
                    switch($doc['is_formal']) {
                        case 0:
                            $status = '草稿';
                            $status_class = 'warning';
                            break;
                        case 1:
                            $status = '正式';
                            $status_class = 'primary';
                            break;
                        default:
                            $status = '未知';
                            $status_class = 'dark';
                    }
                    ?>
                    <span class="badge bg-<?php echo $status_class; ?>">
                        <?php echo $status; ?>
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
     * 获取所有文档（按层级关系排序，用于管理后台）
     */
    public function getAllDocumentsByHierarchy() {
        $stmt = $this->db->query("SELECT d.*, u.username
                                FROM documents d 
                                LEFT JOIN users u ON d.user_id = u.id 
                                WHERE d.del_status = 0
                                ORDER BY d.parent_id ASC, d.sort_order ASC, d.id ASC");
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 构建树形结构
        return $this->buildHierarchy($documents);
    }
    
    /**
     * 获取文档的最大排序值
     */
    public function getMaxSortOrder($parent_id = 0) {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM documents WHERE parent_id = ? AND del_status = 0");
        $stmt->execute([$parent_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * 获取文档的下级文档最大排序值
     */
    public function getMaxChildSortOrder($parent_id) {
        $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM documents WHERE parent_id = ?");
        $stmt->execute([$parent_id]);
        return $stmt->fetchColumn() ?? -1;
    }
    
    /**
     * 获取文档的父ID
     */
    public function getParentId($document_id) {
        $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE id = ?");
        $stmt->execute([$document_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * 构建层级结构
     */
    private function buildHierarchy($documents, $parent_id = 0, $level = 0) {
        $result = [];
        foreach ($documents as $doc) {
            if ($doc['parent_id'] == $parent_id) {
                $doc['level'] = $level;
                $result[] = $doc;
                
                // 递归获取子文档
                $children = $this->buildHierarchy($documents, $doc['id'], $level + 1);
                $result = array_merge($result, $children);
            }
        }
        return $result;
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