<?php

namespace Dhii\Stax\Test;

use org\bovigo\vfs\vfsStream;

/**
 * Tests Loader class
 *
 * @since [*next-version*]
 * @author Dhii Team <development@dhii.co>
 */
class LoaderTest extends \PHPUnit_Framework_TestCase
{
    const VFS_DIR = 'vfs';
    const LOADER_NAMESPACE = 'Dhii\\Stax';
    const LOADER_CLASSNAME = self::LOADER_NAMESPACE . '\\Loader';
    const LOADER_INTERFACE = self::LOADER_NAMESPACE . '\\LoaderInterface';
    const LOADER_NAME_ROOT = 'root';
    
    protected $vfs;
    
    /**
     * @since [*next-version*]
     * @return \Dhii\Stax\Loader
     */
    public function createInstance($paths = array(), $name = null, $parent = null)
    {
        $className = static::LOADER_CLASSNAME;
        if (is_null($name)) {
            $name = static::LOADER_NAME_ROOT;
        }
        $instance = new $className($name);
        /* @var $instance \Dhii\Stax\Loader */
        foreach ($paths as $_path) {
            $instance->addPathToLoad($_path);
        }
        
        if (!is_null($parent)) {
            $instance->setParent($parent);
        }
        
        return $instance;
    }
    
    /**
     * @since [*next-version*]
     * @param type $methods
     * @return \Dhii\Stax\Loader
     */
    public function createMock($methods = array(), $name = 'root')
    {
        $className = static::LOADER_CLASSNAME;
        $builder = $this->getMockBuilder($className);
        $builder->setConstructorArgs([$name]);
        if (!empty($methods)) {
            $builder->setMethods($methods);
        }
        $instance = $builder->getMock();
        
        return $instance;
    }
    
    /**
     * @since [*next-version*]
     * @param type $structure
     * @return \org\bovigo\vfs\vfsStreamDirectory
     */
    public function createVfsDir($structure = array())
    {
        return vfsStream::setup(self::VFS_DIR, null, $structure);
    }
    
    /**
     * @since [*next-version*]
     * @return array An array that represents a dummy file structure.
     *  Files have content that is PHP code, which outputs a string unique to that file.
     */
    public function getVfsStructure()
    {
        $structure = [
            'example'           => [
                '3-my-module'       => [],
                '8-my-other-module'       => [
                    '1-file.php'        => $this->getFileContentFromUid('123'),
                    '2-file.php'        => $this->getFileContentFromUid('234')
                ]
            ],
            '2-a-module'        => [
                '2-file.php'        => $this->getFileContentFromUid('345'),
                '3-file.php'        => $this->getFileContentFromUid('456'),
                '2.5-file.php'      => $this->getFileContentFromUid('678'),
                '2.48-file.php'      => $this->getFileContentFromUid('789'),
                'misc-folder'       => [
                    'just-a-file'       => $this->getFileContentFromUid('567')
                ]
            ]
        ];
        
        return $structure;
    }
    
    /**
     * @since [*next-version*]
     * @return array An array of absolute paths that the loader should load.
     *  Paths are both directories and files.
     */
    public function getPathsToLoad()
    {
        return $this->addRootDir([
            'example/3-my-module/',
            'example/8-my-other-module/',
            '2-a-module/'
        ]);
    }
    
    /**
     * @since [*next-version*]
     * @param string|array $path A filesystem path or array of paths.
     * @return string|array A string, or an array of strings, each of which is
     *  an absoluve path to a file.
     */
    public function addRootDir($path)
    {
        $addRoot = function($path) {
            return implode('/', [$this->vfs ? $this->vfs->url() : '', $path]);
        };
        
        return is_array($path)
                ? array_map($addRoot, $path)
                : $addRoot($path);
    }
    
    /**
     * @since [*next-version*]
     * @param string $uid The UID, or Unique IDentifier, of a file.
     * @return string File content that corresponds to the file identified by
     *  the UID.
     */
    public function getFileContentFromUid($uid)
    {
        return sprintf("<?= '{$uid}';\n");
    }
    
    /**
     * @since [*next-version*]
     */
    public function setUp()
    {
        $structure = $this->getVfsStructure();
        $this->vfs = $this->createVfsDir($structure);
    }
    
    /**
     * Tests whether an proper instance of the Loader can be created.
     * @since [*next-version*]
     */
    public function testCreateInstance()
    {
        $instance = $this->createInstance();
        $this->assertInstanceOf(static::LOADER_INTERFACE, $instance, 'Loader instance must be a valid loader');
    }
    
    /**
     * Tests whether the loader can sort paths by basename.
     * @since [*next-version*]
     */
    public function testSortPathsByBasename()
    {
        $paths = $this->getPathsToLoad();
        $loader = $this->createInstance();
        $loader->sortPathsByBasename($paths);
        $this->assertEquals($this->addRootDir([
            '2-a-module/',
            'example/3-my-module/',
            'example/8-my-other-module/'
        ]), $paths, 'Loader did not sort paths correctly');
    }
    
    /**
     * Tests whether the loader behaves correctly when trying to load a file,
     * but it is a directory.
     * @since [*next-version*]
     */
    public function testLoadFileThatIsDirectory()
    {
        $this->expectException('InvalidArgumentException');
        $this->createInstance()->loadFile($this->addRootDir('example/3-my-module'));
    }
    
    /**
     * Tests whether the loader behaves correctly when trying to load a file
     * that does not exist.
     * @since [*next-version*]
     */
    public function testLoadFileThatDoesNotExist()
    {
        $this->expectException('Exception');
        $this->createInstance()->loadFile($this->addRootDir('non-existent-path'));
    }
    
    /**
     * Tests whether the loader correctly loads a PHP file.
     * @since [*next-version*]
     */
    public function testLoadFileSuccess()
    {
        $this->expectOutputString('123');
        $this->createInstance()->loadFile($this->addRootDir('example/8-my-other-module/1-file.php'));
    }
    
    /**
     * Tests whether the loader correctly loads an array of files.
     * @since [*next-version*]
     */
    public function testLoadFiles()
    {
        $this->expectOutputString('123234');
        $this->createInstance()->loadFiles($this->addRootDir([
            'example/8-my-other-module/1-file.php',
            'example/8-my-other-module/2-file.php'
        ]));
    }
    
    /**
     * Tests whether the loader correctly retrieves a list of files from
     * a directory.
     * @since [*next-version*]
     */
    public function testGetDirFiles()
    {
        $files = $this->createInstance()->getDirFiles($this->addRootDir('2-a-module'));
        $this->assertEquals($this->addRootDir([
            '2-a-module/2-file.php',
            '2-a-module/3-file.php',
            '2-a-module/2.5-file.php',
            '2-a-module/2.48-file.php'
        ]), $files, 'Files of directory listed incorrectly');
    }
    
    /**
     * Tests whether the loader correctly loads all files from a directory.
     * @since [*next-version*]
     */
    public function testLoadDir()
    {
        $this->expectOutputString('345456678789');
        $this->createInstance()->loadDir($this->addRootDir('2-a-module'));
    }
    
    /**
     * Tests whether the loader correctly loads all files from many directories.
     * @since [*next-version*]
     */
    public function testLoadDirs()
    {
        $this->expectOutputString('123234345456678789');
        $this->createInstance()->loadDirs($this->addRootDir([
            'example/8-my-other-module/',
            '2-a-module/'
        ]));
    }
    
    /**
     * Tests whether pathds to load can be correctly retrieved after being set
     * for a loader.
     * @since [*next-version*]
     */
    public function testAddGetPathsToLoad()
    {
        $paths = $this->getPathsToLoad();
        $loader = $this->createInstance($paths);
        
        $this->assertEquals($paths, $loader->getPathsToLoad(), 'Loader did not register given paths');
    }
    
    /**
     * Tests whether a loader can correctly retrieve all files to load from
     * multiple paths, and in correct order.
     * @since [*next-version*]
     */
    public function testGetFilesToLoad()
    {
        $instance = $this->createMock(['getPathsToLoad']);
        $instance->method('getPathsToLoad')->willReturn($this->getPathsToLoad());
        $files = $instance->getFilesToLoad();
        $this->assertEquals($this->addRootDir([
            'example/8-my-other-module/1-file.php',
            '2-a-module/2-file.php',
            'example/8-my-other-module/2-file.php',
            '2-a-module/2.48-file.php',
            '2-a-module/2.5-file.php',
            '2-a-module/3-file.php'
        ]), $files, 'Files to be loaded are incorrect');
    }
    
    /**
     * Tests whether a loader can correctly load all files discovered at
     * multiple paths, and in correct order.
     * @since [*next-version*]
     */
    public function testLoad()
    {
        $instance = $this->createMock(['getFilesToLoad']); // All methods except specified will run as usual
        $instance->method('getFilesToLoad')->willReturn($this->addRootDir([
            'example/8-my-other-module/1-file.php',
            '2-a-module/2-file.php',
            'example/8-my-other-module/2-file.php',
            '2-a-module/3-file.php'
        ]));
        $this->expectOutputString('123345234456');
        $instance->load();
    }
    
    /**
     * Tests whether the name retrieved from a loader instance is same as that given to it when created.
     * @since [*next-version*]
     */
    public function testGetName()
    {
        $instance = $this->createInstance([], static::LOADER_NAME_ROOT);
        $this->assertSame(static::LOADER_NAME_ROOT, $instance->getName(), 'Loader name is not same as what it was instantiated with');
    }
    
    /**
     * Tests whether name sanitization removes all illegal characters, and nothing else.
     * @since [*next-version*]
     */
    public function testSanitizeName()
    {
        $instance = $this->createInstance();
        $name = ' loader /*^ @!#  $(*^ name  ';
        $name = $instance->sanitizeName($name);
        $this->assertSame('loadername', $name);
    }
    
    /**
     * Tests whether parent gets set correctly, and this relationship is reflected
     * in the parent's children.
     * @since [*next-version*]
     */
    public function testSetParentGetChildren()
    {
        $root = $this->createInstance();
        $child1 = $this->createInstance([], 'child1')->setParent($root);
        $child2 = $this->createInstance([], 'child2')->setParent($root);
        $this->assertSame($root, $child1->getParent(), 'The first child\'s parent is wrong');
        $this->assertSame($root, $child2->getParent(), 'The second child\'s parent is wrong');
        $this->assertContains($child1, $root->getChildren(), 'The first child is not among the root laoder\'s children');
        $this->assertContains($child2, $root->getChildren(), 'The second child is not among the root laoder\'s children');
        $this->assertEquals(2, count($root->getChildren()), 'Root loader does not contain only two children');
    }
    
    /**
     * Tests whether children get added correctly, and a relationship is formed
     * and reflected as the parent's children.
     * @since [*next-version*]
     */
    public function testAddChildrenGetChildren()
    {
        $root = $this->createInstance();
        $child1 = $this->createInstance([], 'child1');
        $child2 = $this->createInstance([], 'child2');
        $root->addChild($child1)
                ->addChild($child2);
        $this->assertContains($child1, $root->getChildren(), 'The first child is not among the root laoder\'s children');
        $this->assertContains($child2, $root->getChildren(), 'The second child is not among the root laoder\'s children');
        $this->assertEquals(2, count($root->getChildren()), 'Root loader does not contain only two children');
    }
}
