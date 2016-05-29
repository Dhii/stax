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
}
