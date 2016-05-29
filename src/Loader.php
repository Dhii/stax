<?php

namespace Dhii\Stax;

/**
 * @author Dhii Team <development@dhii.co>
 */
class Loader implements LoaderInterface
{
    
    protected $loadedFiles = [];
    protected $pathsToLoad = [];
    
    public function __construct($pathsToLoad = array())
    {
        foreach ((array) $pathsToLoad as $_idx => $_path) {
            $this->addPathToLoad($_path);
        }
    }
    
    public function addPathToLoad($path)
    {
//        $path = $this->_beforeAddPathToLoad($path);
        if (isset($this->pathsToLoad[$path])) {
            return $this;
        }
        
        $this->pathsToLoad[$path] = 1;
//        $this->_afterAddPathToLoad($path);
        
        return $this;
    }
    
//    protected function _beforeAddExprToLoad($path)
//    {
//        $path = $this->_sanitizePath($path);
//        return $path;
//    }
    
//    protected function _afterAddExprToLoad($path)
//    {
//        return $this;
//    }
    
    public function sanitizePath($path)
    {
        $path = trim($path);
        $path = str_replace('\\/', DIRECTORY_SEPARATOR, $path);
//        $path = rtrim($path, DIRECTORY_SEPARATOR);
        
        return $path;
    }
    
    public function trailingSlash($path) {
        return rtrim(rtrim($path), '/') . '/';
    }
    
    public function sortPathsByBasename(&$paths)
    {
        usort($paths, function($pathA, $pathB) {
            $baseA = basename($this->sanitizePath($pathA));
            $baseB = basename($this->sanitizePath($pathB));
            return strnatcmp($baseA, $baseB);
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
}
