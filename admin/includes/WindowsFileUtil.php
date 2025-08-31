<?php
class WindowsFileUtil {
    
    /**
     * 安全删除文件（Windows优化）
     * @param string $file_path 文件路径
     * @return bool 是否删除成功
     */
    public static function safeDelete($file_path) {
        if (empty($file_path)) {
            return false;
        }
        
        // 规范化路径
        $file_path = self::normalizePath($file_path);
        
        // 检查文件是否存在
        if (!file_exists($file_path)) {
            return false;
        }
        
        // 检查是否为文件
        if (!is_file($file_path)) {
            return false;
        }
        
        // 尝试删除文件
        if (@unlink($file_path)) {
            return true;
        }
        
        // 如果删除失败，尝试解决权限问题
        return self::forceDelete($file_path);
    }
    
    /**
     * 强制删除文件（处理权限问题）
     */
    public static function forceDelete($file_path) {
        if (!file_exists($file_path)) {
            return false;
        }
        
        // Windows系统：尝试修改文件属性
        if (PHP_OS_FAMILY === 'Windows') {
            // 移除只读属性
            $attributes = self::getFileAttributes($file_path);
            if ($attributes !== false) {
                $new_attributes = $attributes & ~0x00000001; // 移除只读位
                self::setFileAttributes($file_path, $new_attributes);
            }
        }
        
        // 尝试修改文件权限
        @chmod($file_path, 0777);
        
        // 再次尝试删除
        return @unlink($file_path);
    }
    
    /**
     * 规范化路径（处理Windows路径分隔符）
     */
    public static function normalizePath($path) {
        if (empty($path)) {
            return '';
        }
        
        // 替换路径分隔符
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        
        // 确保使用绝对路径
        if (!self::isAbsolutePath($path)) {
            $base_dir = realpath(dirname(__FILE__) . '/../../');
            $path = $base_dir . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        }
        
        return $path;
    }
    
    /**
     * 判断是否为绝对路径
     */
    public static function isAbsolutePath($path) {
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows: C:\path 或 \server\path
            $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
            $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
            return (substr($path, 1, 2) === ":\\" || substr($path, 0, 2) === "\\\\");
        } else {
            // Linux/Unix
            return strpos($path, '/') === 0;
        }
    }
    
    /**
     * 获取文件属性（Windows专用）
     */
    private static function getFileAttributes($file_path) {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }
        
        $escaped_path = escapeshellarg($file_path);
        $output = [];
        $return_var = 0;
        
        exec("attrib $escaped_path", $output, $return_var);
        
        if ($return_var === 0 && !empty($output)) {
            // 解析attrib输出
            $attributes = 0;
            $line = $output[0];
            
            if (strpos($line, 'R') !== false) $attributes |= 0x00000001;
            if (strpos($line, 'H') !== false) $attributes |= 0x00000002;
            if (strpos($line, 'S') !== false) $attributes |= 0x00000004;
            if (strpos($line, 'A') !== false) $attributes |= 0x00000020;
            
            return $attributes;
        }
        
        return false;
    }
    
    /**
     * 设置文件属性（Windows专用）
     */
    private static function setFileAttributes($file_path, $attributes) {
        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }
        
        $escaped_path = escapeshellarg($file_path);
        $attrib_cmd = '';
        
        // 根据属性值构建attrib命令
        if ($attributes & 0x00000001) $attrib_cmd .= '+R'; else $attrib_cmd .= '-R';
        if ($attributes & 0x00000002) $attrib_cmd .= '+H'; else $attrib_cmd .= '-H';
        if ($attributes & 0x00000004) $attrib_cmd .= '+S'; else $attrib_cmd .= '-S';
        if ($attributes & 0x00000020) $attrib_cmd .= '+A'; else $attrib_cmd .= '-A';
        
        $cmd = "attrib $attrib_cmd $escaped_path";
        $output = [];
        $return_var = 0;
        
        exec($cmd, $output, $return_var);
        
        return $return_var === 0;
    }
    
    /**
     * 删除目录及其内容
     */
    public static function deleteDirectory($dir_path) {
        if (!is_dir($dir_path)) {
            return false;
        }
        
        $dir_path = self::normalizePath($dir_path);
        
        // 先删除目录内的所有文件
        $files = array_diff(scandir($dir_path), ['.', '..']);
        foreach ($files as $file) {
            $file_path = $dir_path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($file_path)) {
                self::deleteDirectory($file_path);
            } else {
                self::safeDelete($file_path);
            }
        }
        
        // 删除空目录
        return @rmdir($dir_path);
    }
}
?>