<?php

namespace Dhii\Stax;

/**
 * @author Dhii Team <development@dhii.co>
 */
class Loader implements LoaderInterface
{
    const NAME_SEPARATOR = '/';
    
    protected $loadedFiles = [];
    protected $pathsToLoad = [];
    protected $parent;
    protected $children;
    protected $name;
    protected $path;
    
    public function __construct($name)
    {
        $this->_setName($name);
    }
    
    public static function create($name, $paths = array(), LoaderInterface $parent = null) {
        $instance = new static($name);
        
        foreach ((array) $paths as $_idx => $_path) {
            $this->addPathToLoad($_path);
        }
        
        if (!is_null($parent)) {
            $this->setParent($parent);
        }
        
        return $instance;
    }
    
    public function addPathToLoad($path)
    {
        if (isset($this->pathsToLoad[$path])) {
            return $this;
        }
        
        $this->pathsToLoad[$path] = 1;
        
        return $this;
    }
    
    public function sanitizePath($path)
    {
        $path = trim($path);
        $path = str_replace('\\/', DIRECTORY_SEPARATOR, $path);
        
        return $path;
    }
    
    public function trailingSlash($path) {
        return rtrim(rtrim($path), '/') . '/';
    }
    
    protected function _floatCompare($a, $b)
    {
        if ($a === $b) {
            return 0;
        }
        return $a < $b
                ? -1
                : 1;
    }
    
    public function sortPathsByBasename(&$paths)
    {
        usort($paths, function($pathA, $pathB) {
            // We're sorting by basename; the path is irrelevant
            $baseA = basename($this->sanitizePath($pathA));
            $baseB = basename($this->sanitizePath($pathB));
            // Order can be controlled by pre-pending a number - optionally fractional
            $numA = floatval($baseA);
            $numB = floatval($baseB);
            // Attempt to sort numerically; if can't, then use natural sorting
            return ($numA === $numB && $numB === 0)
                    ? strnatcasecmp($baseA, $baseB)
                    : $this->_floatCompare($baseA, $baseB);
        });
        
        return $this;
    }
    
    public function loadFiles($files, &$dirs = null)
    {
        $_dirs = [];
        $loaded = [];
        foreach ((array) $files as $_idx => $_path)
        {
            $_path = $this->_beforeLoadFile($_path);
            try {
                $this->loadFile($_path);
            }
            catch (\InvalidArgumentException $e) {
                $_dirs[$_path] = 1;
                continue;
            }
            catch (\Exception $e) {
                $this->_cannotLoadFile($_path, $e);
            }
            $loaded[] = $_path;
        }
        
        if (is_array($dirs)) {
            $dirs = array_merge($dirs, array_keys($_dirs));
        }
        
        return $loaded;
    }
    
    public function _cannotLoadFile($path, $exception = null)
    {
        if ($exception) {
            throw $exception;
        }
        
        return $this;
    }
    
    public function _beforeLoadFile($path)
    {
        return $path;
    }
    
    /**
     * 
     * @param string $path
     * @return mixed
     * @throws Exception If file does not exist or is unreadable.
     */
    public function loadFile($path)
    {
        if (is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Could not load file "%1$s": path is a directory', $path));
        }
        
        if (!is_readable($path)) {
            throw new \Exception(sprintf('Could not load file "%1$s": file is not readable or does not exist', $path));
        }
        
        return include($path);
    }
    
    public function loadDirs($paths)
    {
        $loaded = [];
        foreach ($paths as $_path) {
            array_merge($loaded, array_flip($this->loadDir($_path)));
        }
        
        return array_keys($loaded);
    }
    
    public function loadDir($path)
    {
        if (!is_dir($path)) {
            throw new \InvalidArgumentException(sprintf('Could not load "%1$s": path is not directory', $path));
        }

        $contents = $this->getDirFiles($path);
        return $this->loadFiles($contents);
    }
    
    
    public function getPathsToLoad()
    {
        return array_keys($this->pathsToLoad);
    }
    
    public function getDirFiles($dirPath)
    {
        $dirPath = $this->trailingSlash($dirPath);
        
        if (!is_dir($dirPath)) {
            throw new \InvalidArgumentException(sprintf('Could not get contents of directory "%1$s": not a directory', $dirPath));
        }
        
        if (!($dirHandle = opendir($dirPath))) {
            throw new Exception(sprintf('Could not get contents of directory "%1$s": could not open directory', $dirPath));
        }
        
        $contents = [];
        while(($file = readdir($dirHandle)) !== false) {
            $fileAbsPath = "{$dirPath}{$file}";
            if (in_array($file, ['.', '..']) // Exclude those pseudo directories
                    || !is_file($fileAbsPath)) { // Only files
                continue;
            }
            $contents[$fileAbsPath] = 1;
        }
        
        $contents = array_keys($contents);
        return $contents;
    }
    
    public function getDirsFiles($paths)
    {
        $files = [];
        foreach ((array) $paths as $_path) {
            try {
                $this->getDirFiles($_path);
            } catch (\InvalidArgumentException $ex) {
                continue;
            }
            $files[$_path] = 1;
        }
        
        return array_flip($files);
    }
    
    public function getFilesToLoad()
    {
        $files = [];
        foreach ($this->getPathsToLoad() as $_path) {
            if (!file_exists($_path)) {
                throw new Exception(sprintf('Could not determine whether to load path "%1$s": path does not exist'));
            }

            $files = array_merge($files, is_file($_path)
                    ? [$_path => 1]
                    : array_flip($this->getDirFiles($_path)));
        }
        
        $files = array_keys($files);
        $this->sortPathsByBasename($files);
        return $files;
    }
    
    public function load()
    {
        $files = $this->getFilesToLoad();
        $this->loadedFiles = $this->loadFiles($files, $dirs);
        
        return $this;
    }
    
    protected function _beforeLoad(&$paths)
    {
        return $this;
    }
    
    protected function _afterLoad()
    {
        return $this;
    }
    
    public function getLoadedFiles()
    {
        return $this->loadedFiles;
    }

    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @return LoaderInterface
     */
    public function getParent()
    {
        return $this->parent;
    }

    /**
     * Adds a loader to this loader's children.
     * 
     * This will not automatically set this loader as the child loader's parent.
     * 
     * @since [*next-version*]
     * @param \Dhii\Stax\LoaderInterface $loader The loader to add to this loader's children.
     * @return \Dhii\Stax\Loader This instance.
     * @throws \OutOfRangeException If child with the loader's name already exists.
     */
    public function addChild(LoaderInterface $loader)
    {
        $name = $loader->getName();
        if (isset($this->children[$name])) {
            throw new \OutOfRangeException(sprintf('Cannot add child "%1$s": loader "%2$s" already has a child with that name', $name, $this->getTreePathString()));
        }

        $this->_setChild($loader);
        return $this;
    }
    
    /**
     * Adds a loader to this loader's children.
     * 
     * No checks performed. Will replace any current loader with same name.
     * 
     * @since [*next-version*]
     * @param \Dhii\Stax\LoaderInterface $loader The loader to add to this loader's children.
     * @return \Dhii\Stax\Loader This instance.
     */
    protected function _setChild(LoaderInterface $loader)
    {
        $name = $loader->getName();
        $this->children[$name] = $loader;
        return $this;
    }
    
    /**
     * Get a child by name relative to this loader.
     * 
     * @since [*next-version*]
     * @param string $path Name or path to child to get, relative to this loader.
     * @return LoaderInterface
     */
    public function getChild($path)
    {
        $path = $this->getTreePathFromName($path);
        $name = array_shift($path);
        $child = $this->_getChild($name);
        return !count($path)
                ? $child
                : $child->getChild($path);
    }
    
    /**
     * Get an immediate child by its name.
     * 
     * Name will get sanitized.
     * 
     * @since [*next-version*]
     * @param string $name Name of child to get.
     * @return LoaderInterface
     * @throws \OutOfRangeException If no such child.
     */
    protected function _getChild($name)
    {
        $origName = $name;
        $name = $this->sanitizeName($name);
        if (!isset($this->children[$name])) {
            throw new \OutOfRangeException(sprintf('Could not get child "%1$s" from loader "%2$s"', $origName, $this->getTreePathString()));
        }
        
        return $this->children[$name];
    }
    
    /**
     * Sanitizes a name of a loader.
     * 
     * A loader name may contain only alpha-numeric characters, the dash, and
     * the underscore
     * 
     * @since [*next-version*]
     * @param string $name A loader name.
     * @return string A loader name that does not contain illegal characters.
     */
    public function sanitizeName($name)
    {
        return preg_replace('![^\w\d_-]!', '', $name);
    }
    
    /**
     * Standardize a tree path of any positive length to an array representation.
     * 
     * @since [*next-version*]
     * @param string|array $name A loader name or string representation of a loader path.
     *  If array, it will be returned without modification.
     * @return array An array, each element of which is a name of a loader.
     *  The elements' sequence represents the loaders that have to be accessed
     *  sequentially to get to the last loader.
     */
    public function getTreePathFromName($name)
    {
        if (is_array($name)) {
            return $name;
        }
        return explode(static::NAME_SEPARATOR, $name);
    }
    
    /**
     * Sanitizes all names in a loader tree path.
     * 
     * @since [*next-version*]
     * @param string|array $path A string or array representation of a loader tree path.
     * @param boolean $isPreserveType Determines whether or not the type of the
     *  path parameter will be preserved in the return value.
     * @return string|array A loader tree path that does not contain names with illegal characters.
     * @throws \InvalidArgumentExceptionn If one of the names in path became empty after sanitization.
     */
    public function sanitizeTreePath($path, $isPreserveType = true)
    {
        $originalPath = $path;
        $isArray = is_array($originalPath);
        $separator = self::NAME_SEPARATOR;
        
        if (!$isArray) {
            $path = $this->getTreePathFromName((string) $path);
        }
        
        $originalPathString = implode($separator, $originalPath);
        foreach ($path as $_idx => $_name) {
            $name = $this->sanitizeName($_name);
            if (!strlen($name)) {
                throw new \InvalidArgumentException(sprintf('Could not sanitize path "%1$s": name "%2$s" is an empty string when sanitized', $originalPathString));
            }
            $path[$_idx] = $name;
        }
        
        return !$isArray && $isPreserveType
                ? implode($separator, $path)
                : $path;
    }

    /**
     * Removes a loader from this loader's children.
     * 
     * This automatically unsets this loader from being the child loader's parent.
     * 
     * @since [*next-version*]
     * @param string $name The name of the child loader to remove.
     * @return \Dhii\Stax\Loader This instance.
     * @throws \OutOfBoundsException If no child with specified name found.
     */
    public function removeChild($name)
    {
        if (!isset($this->children[$name])) {
            throw new \OutOfBoundsException(sprintf('Could not remove child "%1$s": no such child in loader "%2$s"', $name, $this->getTreePathString()));
        }

        $child = $this->children[$name];
        if ($child instanceof LoaderInterface) {
            $child->unsParent();
        }

        unset($this->children[$name]);
        return $this;
    }

    /**
     * Sets this loader's parent loader.
     * 
     * This automatically adds this loader to the parent's children.
     * 
     * @since [*next-version*]
     * @param \Dhii\Stax\LoaderInterface $loader The loader to set as the parent of this loader.
     * @return \Dhii\Stax\Loader This instance.
     */
    public function setParent(LoaderInterface $loader)
    {
        $loader->addChild($this);
        $this->_setParent($loader);
        return $this;
    }
    
    /**
     * Sets a loader as this loader's parent.
     * 
     * No checks performed. Nothing else is set anywhere.
     * 
     * @since [*next-version*]
     * @param \Dhii\Stax\LoaderInterface $loader The loader to set as this loader's parent.
     * @return \Dhii\Stax\Loader This instance.
     */
    protected function _setParent(LoaderInterface $loader)
    {
        $this->parent = $loader;
        return $this;
    }
    
    /**
     * Unsets this loader's parent loader.
     * 
     * This clears loader path cache.
     * This does not automatically remove this loader from the parent's children.
     * 
     * @since [*next-version*]
     * @return \Dhii\Stax\Loader This instance.
     */
    public function unsParent()
    {
        $this->parent = null;
        $this->path = null;
        return $this;
    }

    /**
     * Get this loader's name.
     * 
     * Assumed to be valid.
     * 
     * @since [*next-version*]
     * @return string This loader's name.
     */
    public function getName()
    {
        return $this->name;
    }
    
    /**
     * Sets this loader's name.
     * 
     * Name gets automatically sanitized.
     * 
     * @since [*next-version*]
     * @param string $name The name to set for this loader.
     * @return \Dhii\Stax\Loader This instance.
     * @throws \InvalidArgumentException If result of name sanitization was an empty string.
     */
    protected function _setName($name)
    {
        $origName = $name;
        $name = $this->sanitizeName($name);
        if (!strlen($name)) {
            throw new \InvalidArgumentException(sprintf('Could not set name "%1$s" for loader: name must not be empty after sanitization', $origName));
        }
        
        $this->name = $name;
        return $this;
    }

    /**
     * Gets the tree path to this loader.
     * 
     * Contains at least this loader's name.
     * 
     * @since [*next-version*]
     * @return array An array, the elements and sequence of which represents
     *  the path of names that lead down the loader tree to this loader.
     */
    public function getTreePath()
    {
        if (is_null($this->path)) {
            $this->path = array($this->getName());
            if ($parent = $this->getParent()) {
                // Add the parent's path array to the beginning
                array_splice($this->path, 0, 0, $parent->getTreePath());
            }
            $this->path = array_keys($this->path);
        }
        
        return $this->path;
    }
    
    /**
     * Get the string representation to the loader tree path to this loader.
     * 
     * @since [*next-version*]
     * @return string A string where all names in this loader's tree path are
     *  concatenated using the name separator.
     */
    public function getTreePathString()
    {
        return implode(static::NAME_SEPARATOR, $this->getTreePath());
    }
}
