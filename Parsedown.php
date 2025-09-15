<?php

class Parsedown
{
    function text($text)
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Headers
        $text = preg_replace('/^### (.*$)/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^## (.*$)/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^# (.*$)/m', '<h1>$1</h1>', $text);
        
        // Bold
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.*?)__/', '<strong>$1</strong>', $text);
        
        // Italic
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.*?)_/', '<em>$1</em>', $text);
        
        // Code
        $text = preg_replace('/`(.*?)`/', '<code>$1</code>', $text);
        
        // Links
        $text = preg_replace('/\[([^\]]+)\]\(([^\)]+)\)/', '<a href="$2">$1</a>', $text);
        
        // Images
        $text = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img alt="$1" src="$2">', $text);
        
        // Line breaks
        $text = nl2br($text);
        
        // Remove extra <br> tags around headers
        $text = preg_replace('/(<\/h[1-6]>)(<br \/>)/', '$1', $text);
        
        return $text;
    }
}