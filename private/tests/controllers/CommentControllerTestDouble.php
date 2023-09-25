<?php
include_once($GLOBALS['config']['private_folder'].'/controllers/CommentController.php');

    class CommentControllerTestDouble extends CommentController {
        protected static $inputStream;

        public static function setInputStream($input = 'php://input')
        {
            static::$inputStream = $input;
        }
    
        protected static function getInputStream()
        {
            return static::$inputStream;
        }

    }
?>
