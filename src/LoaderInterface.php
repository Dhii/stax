<?php

namespace Dhii\Stax;

/**
 * @author Dhii Team <development@dhii.co>
 */
interface LoaderInterface {
    
    public function addPathToLoad($path);
    public function getPathsToLoad();
    public function load();
    public function getLoadedFiles();
    public function getName();
    public function setParent(LoaderInterface $loader);
    public function getParent();
    public function unsParent();
    public function getChildren();
    public function getChild($name);
    public function addChild(LoaderInterface $loader);
    public function removeChild($name);
    
    public static function create($name, $paths = array(), LoaderInterface $parent = null);
}
