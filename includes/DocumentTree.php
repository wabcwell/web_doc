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
                    ORDER BY d.sort_order ASC";
        } else {
            // 直接使用document_id作为parent_id查询子文档
            $sql = "SELECT d.*, u.username, d.is_formal 
                    FROM documents d 
                    LEFT JOIN users u ON d.user_id = u.id 
                    WHERE d.parent_id = " . intval($parent_document_id) . " AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                    ORDER BY d.sort_order ASC";
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
                                      ORDER BY d.sort_order ASC");
            $stmt->execute();
        } else {
            // 直接使用document_id作为parent_id查询子文档
            $stmt = $this->db->prepare("SELECT d.*, u.username 
                                      FROM documents d 
                                      LEFT JOIN users u ON d.user_id = u.id 
                                      WHERE d.parent_id = ? AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                      ORDER BY d.sort_order ASC");
            $stmt->execute([$parent_document_id]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取文档的同级文档
     */
    public function getSiblings($document_id) {
        // 首先获取当前文档的父document_id
        $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE document_id = ?");
        $stmt->execute([$document_id]);
        $parent_document_id = $stmt->fetchColumn();
        
        // 然后获取同父级的所有文档
        $stmt = $this->db->prepare("SELECT d.*, u.username 
                                  FROM documents d 
                                  LEFT JOIN users u ON d.user_id = u.id 
                                  WHERE d.parent_id = ? AND d.document_id != ? AND d.del_status = 0 AND d.is_public = 1 AND d.is_formal = 1
                                  ORDER BY d.sort_order ASC");
        $stmt->execute([$parent_document_id, $document_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 根据document_id获取文档的层级深度
     */
    public function getDepthByDocumentId($document_id) {
        $depth = 0;
        $current_document_id = $document_id;
        
        while ($current_document_id > 0) {
            // 获取当前文档的父文档document_id
            $stmt = $this->db->prepare("SELECT parent_id FROM documents WHERE document_id = ? AND del_status = 0");
            $stmt->execute([$current_document_id]);
            $parent_document_id = $stmt->fetchColumn();
            
            if ($parent_document_id && $parent_document_id > 0) {
                $depth++;
                $current_document_id = $parent_document_id;
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
                    $current_document_id = $document['parent_id'];
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
        // 首先获取所有文档，按parent_id和sort_order排序，确保层级关系正确
        $sql = "SELECT d.*, u.username
                FROM documents d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.del_status = 0
                ORDER BY d.parent_id ASC, d.sort_order ASC";
        
        // 确保有一个合理的限制来防止内存溢出
        $effectiveLimit = 100; // 默认限制
        if ($limit !== null && $limit > 0) {
            $effectiveLimit = min(intval($limit), 100); // 最大不超过100
        }
        
        $sql .= " LIMIT " . $effectiveLimit;
        
        $stmt = $this->db->query($sql);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 使用递归方式构建层级结构，确保与前台getTree()方法的排序一致
        return $this->buildHierarchyRecursive($documents);
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
     * 构建层级结构（内存优化版本）
     */
    private function buildHierarchy($documents) {
        // 如果文档数量过多，只处理前100个以避免内存问题
        if (count($documents) > 100) {
            $documents = array_slice($documents, 0, 100);
        }
        
        // 创建内部ID到文档的映射
        $internalIdMap = [];
        foreach ($documents as $doc) {
            $internalIdMap[$doc['id']] = $doc;
        }
        
        // 构建父子关系树
        $tree = [];
        $processedDocs = [];
        
        // 首先处理根节点（parent_id = 0）
        foreach ($documents as $doc) {
            if ($doc['parent_id'] == 0) {
                $doc['level'] = 0;
                $tree[] = $doc;
                $processedDocs[$doc['document_id']] = $doc;
            }
        }
        
        // 然后递归处理子节点
        $this->processChildren($documents, $internalIdMap, $processedDocs, $tree, 0);
        
        return $tree;
    }
    
    /**
     * 递归构建层级结构（与前台getTree()方法排序一致）
     */
    private function buildHierarchyRecursive($documents) {
        $hierarchy = [];
        
        // 按parent_id分组文档，便于快速查找
        $groupedByParent = [];
        foreach ($documents as $doc) {
            $parentId = $doc['parent_id'];
            if (!isset($groupedByParent[$parentId])) {
                $groupedByParent[$parentId] = [];
            }
            $groupedByParent[$parentId][] = $doc;
        }
        
        // 对每个parent_id组的文档按sort_order排序（与前台一致）
        foreach ($groupedByParent as $parentId => &$children) {
            usort($children, function($a, $b) {
                return $a['sort_order'] - $b['sort_order'];
            });
        }
        
        // 递归构建层级结构
        $this->buildTreeRecursive(0, $groupedByParent, $hierarchy, 0);
        
        return $hierarchy;
    }
    
    /**
     * 递归构建树形结构（与前台getTree()方法逻辑一致）
     */
    private function buildTreeRecursive($parentId, $groupedByParent, &$result, $level) {
        if (!isset($groupedByParent[$parentId])) {
            return;
        }
        
        foreach ($groupedByParent[$parentId] as $doc) {
            $doc['level'] = $level;
            $result[] = $doc;
            
            // 递归处理子节点
            $this->buildTreeRecursive($doc['document_id'], $groupedByParent, $result, $level + 1);
        }
    }
    
    /**
     * 递归处理子节点
     */
    private function processChildren($allDocuments, $internalIdMap, &$processedDocs, &$tree, $level) {
        $level++;
        
        foreach ($allDocuments as $doc) {
            // 跳过已处理的文档
            if (isset($processedDocs[$doc['document_id']])) {
                continue;
            }
            
            // 查找父文档
            $parentDoc = null;
            if ($doc['parent_id'] > 0 && isset($internalIdMap[$doc['parent_id']])) {
                $parentDoc = $internalIdMap[$doc['parent_id']];
            }
            
            if ($parentDoc && isset($processedDocs[$parentDoc['document_id']])) {
                // 父文档已处理，可以处理当前文档
                $doc['level'] = $level;
                $tree[] = $doc;
                $processedDocs[$doc['document_id']] = $doc;
            }
        }
        
        // 如果还有未处理的文档，继续递归（处理深层嵌套）
        if (count($processedDocs) < count($allDocuments) && $level < 10) {
            $this->processChildren($allDocuments, $internalIdMap, $processedDocs, $tree, $level);
        }
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
            $sql .= " ORDER BY d.sort_order ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}