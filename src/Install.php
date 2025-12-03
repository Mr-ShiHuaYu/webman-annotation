<?php
namespace X2nx\WebmanAnnotation;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array (
        'config/plugin/x2nx/webman-annotation' => 'config/plugin/x2nx/webman-annotation',
        'config/container.php' => 'config/container.php',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path().'/'.substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            $sourcePath = __DIR__ . '/' . $source;
            $destPath = base_path().'/'.$dest;
            if (is_dir($sourcePath)) {
                copy_dir($sourcePath, $destPath);
            } elseif (is_file($sourcePath)) {
                if (!is_dir(dirname($destPath))) {
                    mkdir(dirname($destPath), 0777, true);
                }
                copy($sourcePath, $destPath);
            }
            echo "Create $dest\n";
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path()."/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest\n";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }
    
}