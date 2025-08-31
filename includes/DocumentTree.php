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
    public function getTree($parent_document_id = 0) {
        if ($parent_document_id == 0) {
            $sql = "SELECT d.*, u.username, d.is_formal 
                    FROM documents d 
                    LEFT JOIN users u ON d.user_id = u.id 
                    WHERE d.parent_id = 0 AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                    ORDER BY d.sort_order ASC, d.document_id ASC";
        } else {
            // 通过document_id找到对应的内部ID
            $stmt = $this->db->prepare("SELECT id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$parent_document_id]);
            $internal_parent_id = $stmt->fetchColumn();
            
            if (!$internal_parent_id) {
                return [];
            }
            
            $sql = "SELECT d.*, u.username, d.is_formal 
                    FROM documents d 
                    LEFT JOIN users u ON d.user_id = u.id 
                    WHERE d.parent_id = " . intval($internal_parent_id) . " AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                    ORDER BY d.sort_order ASC, d.document_id ASC";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($documents as &$doc) {
            $doc['children'] = $this->getTree($doc['document_id']);
        }
        
        return $documents;
    }
    
    /**
     * 获取文档的所有子文档
     */
    public function getChildren($parent_document_id) {
        if ($parent_document_id == 0) {
            $stmt = $this->db->prepare("SELECT d.*, u.username 
                                      FROM documents d 
                                      LEFT JOIN users u ON d.user_id = u.id 
                                      WHERE d.parent_id = 0 AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                      ORDER BY d.sort_order ASC, d.document_id ASC");
            $stmt->execute();
        } else {
            // 通过document_id找到对应的内部ID
            $stmt = $this->db->prepare("SELECT id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$parent_document_id]);
            $internal_parent_id = $stmt->fetchColumn();
            
            if (!$internal_parent_id) {
                return [];
            }
            
            $stmt = $this->db->prepare("SELECT d.*, u.username 
                                      FROM documents d 
                                      LEFT JOIN users u ON d.user_id = u.id 
                                      WHERE d.parent_id = ? AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                      ORDER BY d.sort_order ASC, d.document_id ASC");
            $stmt->execute([$internal_parent_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取文档的同级文档
     */
    public function getSiblings($document_id) {
        // 首先获取当前文档的父内部ID
        $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE document_id = ?");
        $stmt->execute([$document_id]);
        $parent_internal_id = $stmt->fetchColumn();
        
        // 然后获取同父级的所有文档
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.parent_id = ? AND d.document_id != ? AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY d.sort_order ASC, d.document_id ASC");
        $stmt->execute([$parent_internal_id, $document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据document_id获取文档的层级深度
     */
    public function getDepthByDocumentId($document_id) {
        $depth = 0;
        $current_document_id = $document_id;
        
        while ($current_document_id > 0) {
            // 获取当前文档的父文档内部ID
            $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$current_document_id]);
            $parent_internal_id = $stmt->fetchColumn();
            
            if ($parent_internal_id && $parent_internal_id > 0) {
                $depth++;
                // 通过内部ID获取对应的业务ID
                $stmt = $this->db->prepare("SELECT document_id FROM documents WHERE id = ? AND del_status = 0");
                $stmt->execute([$parent_internal_id]);
                $current_document_id = $stmt->fetchColumn();
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
        $current_document_id = $document_id;
        
        while ($current_document_id > 0) {
            $stmt = $this->db->prepare("SELECT document_id, title, parent_id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$current_document_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($document) {
                array_unshift($breadcrumbs, [
                    'document_id' => $document['document_id'],
                    'title' => $document['title']
                ]);
                
                if ($document['parent_id'] > 0) {
                    // 通过内部ID获取对应的业务ID
                    $stmt = $this->db->prepare("SELECT document_id FROM documents WHERE id = ? AND del_status = 0");
                    $stmt->execute([$document['parent_id']]);
                    $current_document_id = $stmt->fetchColumn();
                } else {
                    break;
                }
            } else {
                break;
            }
        }
        
        return $breadcrumbs;
    }
    
    /**
     * 获取所有文档总数（公开且正式）
     */
    public function getTotalDocuments() {
        $stmt = $this->db->query("SELECT COUNT(*) FROM documents WHERE del_status = 0 AND is_public = 1 AND is_formal = 1");
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
                                  WHERE d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY d.updated_at DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取最近14天内创建的文档数量
     */
    public function getRecentCreatedCount($days = 14) {
        $stmt = $this->db->prepare("SELECT COUNT(*) 
                                  FROM documents 
                                  WHERE del_status = 0 
                                  AND is_public = 1 
                                  AND is_formal = 1
                                  AND created_at >= datetime('now', '-' || ? || ' days')");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    /**
     * 获取最近14天内删除的文档数量
     */
    public function getRecentDeletedCount($days = 14) {
        $stmt = $this->db->prepare("SELECT COUNT(*) 
                                  FROM documents 
                                  WHERE del_status = 1
                                  AND deleted_at >= datetime('now', '-' || ? || ' days')");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    /**
     * 获取最近14天内更新操作的次数
     */
    public function getRecentUpdateCount($days = 14) {
        $stmt = $this->db->prepare("SELECT COUNT(*) 
                                  FROM edit_log_new 
                                  WHERE action = 'update'
                                  AND created_at >= datetime('now', '-' || ? || ' days')");
        $stmt->execute([$days]);
        return $stmt->fetchColumn();
    }

    /**
     * 获取最近创建的文档
     */
    public function getRecentlyCreatedDocuments($limit = 10) {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY d.created_at DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取最近删除的文档
     */
    public function getRecentlyDeletedDocuments($limit = 10) {
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.del_status = 1
                                  ORDER BY d.deleted_at DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取历史版本最多的文档
     */
    public function getDocumentsWithMostVersions($limit = 10) {
        $stmt = $this->db->prepare("SELECT d.*, u.username, 
                                  (SELECT COUNT(*) FROM documents_version WHERE document_id = d.document_id) as version_count
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY version_count DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取操作次数最多的文档
     */
    public function getDocumentsWithMostOperations($limit = 10) {
        $stmt = $this->db->prepare("SELECT d.*, u.username, 
                                  (SELECT COUNT(*) FROM edit_log WHERE document_id = d.document_id) as operation_count
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY operation_count DESC 
                                  LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 渲染树形选项
     */
    public function renderTreeOptions($tree, $selected_id = null, $exclude_id = null, $indent = '') {
        foreach ($tree as $doc) {
            if ($doc['document_id'] == $exclude_id) continue;
            
            $selected = ($selected_id == $doc['document_id']) ? ' selected' : '';
            echo "<option value=\"{$doc['document_id']}\"{$selected}>{$indent}" . htmlspecialchars($doc['title']) . "</option>";
            
            if (!empty($doc['children'])) {
                $this->renderTreeOptions($doc['children'], $selected_id, $exclude_id, $indent . '&nbsp;&nbsp;&nbsp;&nbsp;');
            }
        }
    }
    
    /**
     * 渲染树形结构
     */
    public function renderTree($tree, $level = 0) {
        // 限制最大递归深度，防止内存溢出
        if ($level > 50) {
            return;
        }
        
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
                    <a href="edit.php?id=<?php echo $doc['document_id']; ?>" class="text-decoration-none">
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
                        <a href="edit.php?id=<?php echo $doc['document_id']; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <a href="/index.php?id=<?php echo $doc['document_id']; ?>" target="_blank" class="btn btn-outline-success">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="delete.php?id=<?php echo $doc['document_id']; ?>" 
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
    public function getAllDocumentsByHierarchy($limit = null) {
        $sql = "SELECT d.*, u.username
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.del_status = 0
                ORDER BY d.parent_id ASC, d.sort_order ASC, d.document_id ASC";
        
        if ($limit !== null) {
            $sql .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->db->query($sql);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 构建树形结构，使用document_id作为层级基础
        return $this->buildHierarchy($documents);
    }
    
    /**
     * 获取指定文档下最大的排序值
     */
    public function getMaxSortOrder($document_id) {
        if ($document_id == 0) {
            $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM documents WHERE parent_id = 0");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM documents WHERE parent_id = (SELECT id FROM documents WHERE document_id = ?)");
            $stmt->execute([$document_id]);
        }
        return $stmt->fetchColumn() ?: 0;
    }
    
    /**
     * 获取指定文档下最大的子文档排序值
     */
    public function getMaxChildSortOrder($document_id) {
        if ($document_id == 0) {
            $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM documents WHERE parent_id = 0");
            $stmt->execute();
        } else {
            $stmt = $this->db->prepare("SELECT MAX(sort_order) FROM documents WHERE parent_id = (SELECT id FROM documents WHERE document_id = ?)");
            $stmt->execute([$document_id]);
        }
        return $stmt->fetchColumn() ?: 0;
    }
    
    /**
     * 获取文档的父ID（返回业务ID）
     */
    public function getParentId($document_id) {
        $stmt = $this->db->prepare("SELECT d2.document_id 
                                  FROM documents d1 
                                  JOIN documents d2 ON d1.parent_id = d2.id 
                                  WHERE d1.document_id = ?");
        $stmt->execute([$document_id]);
        return $stmt->fetchColumn();
    }
    
    /**
     * 构建层级结构
     */
    private function buildHierarchy($documents, $parent_document_id = 0, $level = 0) {
        static $documentIdMap = null;
        static $childrenMap = null;
        
        // 只在第一次调用时初始化映射
        if ($documentIdMap === null || $childrenMap === null) {
            $documentIdMap = [];
            $childrenMap = [];
            
            // 预处理文档，建立父子关系映射
            foreach ($documents as $doc) {
                $documentIdMap[$doc['document_id']] = $doc['id'];
                
                // 将文档按parent_id分组
                $parentId = $doc['parent_id'];
                if (!isset($childrenMap[$parentId])) {
                    $childrenMap[$parentId] = [];
                }
                $childrenMap[$parentId][] = $doc;
            }
        }
        
        $result = [];
        
        // 递归构建树形结构
        $current_parent_internal_id = $parent_document_id == 0 ? 0 : ($documentIdMap[$parent_document_id] ?? 0);
        if (isset($childrenMap[$current_parent_internal_id])) {
            foreach ($childrenMap[$current_parent_internal_id] as $doc) {
                $doc['level'] = $level;
                $result[] = $doc;
                
                // 递归获取子文档
                $children = $this->buildHierarchy([], $doc['document_id'], $level + 1);
                if (!empty($children)) {
                    $result = array_merge($result, $children);
                }
            }
        }
        
        // 重置静态变量，以便下次调用时重新初始化
        if ($parent_document_id == 0) {
            $documentIdMap = null;
            $childrenMap = null;
        }
        
        return $result;
    }
    
    /**
     * 获取所有文档（扁平化列表，带限制和分页）
     */
    public function getAllDocuments($limit = null, $offset = 0) {
        $sql = "SELECT d.*, u.username 
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.del_status = 0";
        
        if ($limit !== null) {
            $sql .= " LIMIT ? OFFSET ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit, $offset]);
        } else {
            $sql .= " ORDER BY d.sort_order ASC, d.document_id ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}