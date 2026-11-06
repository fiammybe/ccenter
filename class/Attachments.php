<?php

/**
 * Uploaded files of messages. They live outside the web root's reach in
 * <uploads>/<module>/<5 digit message id>/<file>; files that are not yet linked to a message wait in
 * <uploads>/<module>/work<session>/.
 */
class mod_ccenter_Attachments
{
    /** Only a plain file name may be used; anything with a path in it is cut down to its last part. */
    public static function safeName($file)
    {
        $file = basename(str_replace(["\0", '\\'], ['', '/'], (string) $file));
        return ($file === '.' || $file === '..') ? '' : $file;
    }

    /** Module directory name (the folder this module is installed in). */
    private static function dirname()
    {
        return basename(dirname(__DIR__));
    }

    /**
     * Path of a file relative to the uploads directory.
     *
     * @param int    $id   message id, 0 for the work folder of the current session
     * @param string $file file name ('' for the folder itself)
     */
    public static function path($id, $file = '')
    {
        $dir = $id ? sprintf('%05d', (int) $id) : 'work' . substr(session_id(), 0, 8);
        $file = self::safeName($file);
        return '/' . self::dirname() . '/' . $dir . ($file !== '' ? '/' . $file : '');
    }

    /** Absolute path on disk. */
    public static function fullPath($id, $file = '')
    {
        return ICMS_UPLOAD_PATH . self::path($id, $file);
    }

    /**
     * Move an uploaded (or work folder) file into the folder of a message.
     *
     * @param string $tmp  source; empty = the file in the work folder of this session
     * @param string $file file name
     * @param int    $id   message id
     * @return bool
     */
    public static function move($tmp, $file, $id = 0)
    {
        $path = self::fullPath($id, $file);
        $dir = dirname($path);
        $base = dirname($dir);
        if (!is_dir($base)) {
            if (!mkdir($base)) {
                return false;
            }
            file_put_contents("$base/.htaccess", "deny from all\n"); // no direct access
        }
        if (!is_dir($dir) && !mkdir($dir)) {
            return false;
        }
        if ($tmp === '' || $tmp === null) {
            $tmp = self::fullPath(0, $file);
        }
        return @rename($tmp, $path) || move_uploaded_file($tmp, $path);
    }

    /** Delete all files of a message and its folder. */
    public static function removeAll($id)
    {
        if (!$id) {
            return;
        }
        $dir = self::fullPath($id);
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $name) {
            if ($name !== '.' && $name !== '..' && is_file("$dir/$name")) {
                unlink("$dir/$name");
            }
        }
        @rmdir($dir);
    }
}
