<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace ActionscriptComposer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use DOMDocument;
use Composer\Script\ScriptEvents;
use Composer\EventDispatcher\EventSubscriberInterface;

/**
 * Description of Plugin
 *
 * @author Jeroen
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     *
     * @var Composer
     */
    static protected $composer;
    
    /**
     *
     * @var \DomElement
     */
    protected static $compilerNode;
    
    public function activate(Composer $composer, IOInterface $io)
    {
        self::$composer = $composer;
    }
    
    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::POST_AUTOLOAD_DUMP => array(
                array('postAutoloadDump', 0)
            ),
        );
    }
    
    public function postAutoloadDump(Event $event)
    {
        $composer = self::$composer;
        $config = $composer->getConfig();
        $localRepo = $composer->getRepositoryManager()->getLocalRepository();
        $packages = $localRepo->getCanonicalPackages();
        $basePath = $config->get('vendor-dir');
        
        $srcPaths = array();
        $libPaths = array();
        
        foreach ($packages as $package)
        {
            self::getPackage($package, $basePath, $srcPaths, $libPaths);
        }
        
        self::buildXml($composer, $srcPaths, $libPaths);
    }
    
    
    protected static function getPackage($package, $basePath, &$srcPaths, &$libPaths)
    {
        $config = $package->getExtra();
        $basePath = $basePath."/".$package->getName()."/";
        
        if(isset($config["as-source-path"]))
        {
            self::addPaths($config["as-source-path"], $basePath, $srcPaths);
        }
        
        if(isset($config["as-lib"]))
        {
            self::addPaths($config["as-lib"], $basePath, $srcPaths);
        }
    }
    
    protected static function addPaths($paths, $basePath, &$result)
    {
        
        if(!is_array($paths))
        {
            $paths = Array($paths);
        }
        
        foreach($paths as $path)
        {
            $path = $basePath.$path;
            $path = str_replace("/", DIRECTORY_SEPARATOR, $path);
            $result[] = $path;   
        }
        
    }
    
    protected static function buildXml(Composer $composer, $srcPaths, $libPaths)
    {
        if(sizeof($srcPaths) == 0 && sizeof($libPaths)==0)
        {
            return;
        }
        $config = $composer->getPackage()->getExtra();
        $file = isset($config["as-buildxml-path"])?$config["as-buildxml-path"]:"buildpaths.xml";
        $destination = $composer->getConfig()->get('vendor-dir').DIRECTORY_SEPARATOR.$file;
        
        $xml = self::createCompilerXml();
        $targetNode = self::$compilerNode;
        
        if(sizeof($srcPaths) > 0)
        {
            self::createPathElementsNode($xml, $targetNode, "source-path", $srcPaths);
        }
        
        if(sizeof($libPaths) > 0)
        {
            self::createPathElementsNode($xml, $targetNode, "library-path", $libPaths);
        }
        
        $xml->formatOutput = true;
        $xml->save($destination);
    }
    
    protected static function createCompilerXml()
    {
        $xml = new DOMDocument("1.0");

        $root = $xml->createElement("flex-config");
        $xml->appendChild($root);
        self::$compilerNode = $xml->createElement("compiler");
        $root->appendChild(self::$compilerNode);

        return $xml;
    }
    
    protected static function createPathElementsNode(DOMDocument $xml, \DomElement $targetNode, $type, $paths)
    {
        $sourcepath = $xml->createElement($type);
        $sourcepath->setAttribute("append", "true");
        
        foreach($paths as $path)
        {
            self::createPathElement($xml, $sourcepath, $path);
        }
        
        $targetNode->appendChild($sourcepath);
    }
    
    protected static function createPathElement(DOMDocument $xml, \DomElement $targetNode, $path)
    {
        $pathnode = $xml->createElement("path-elements");
        $pathtext = $xml->createTextNode($path);
        $pathnode->appendChild($pathtext);
        $targetNode->appendChild($pathnode);
    }
}