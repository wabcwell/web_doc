/**
 * 配置文件
 */

(function () {
    window.UEDITOR_CONFIG = {
        serverUrl: "../upload.php",
        toolbars: [
            ['fullscreen', 'source', '|', 'undo', 'redo', '|',
             'bold', 'italic', 'underline', 'strikethrough', '|', 'forecolor', 'backcolor', '|',
             'insertorderedlist', 'insertunorderedlist', '|',
             'justifyleft', 'justifycenter', 'justifyright', 'justifyjustify', '|',
             'link', 'unlink', 'anchor', '|', 'imagenone', 'imageleft', 'imageright', 'imagecenter', '|',
             'simpleupload', 'insertimage', 'emotion', '|',
             'inserttable', 'deletetable', '|',
             'print', 'preview', 'searchreplace']
        ],
        initialFrameHeight: 500,
        autoHeightEnabled: false,
        elementPathEnabled: false,
        wordCount: true,
        maximumWords: 10000,
        autoFloatEnabled: false,
        zIndex: 1000
    };
})();