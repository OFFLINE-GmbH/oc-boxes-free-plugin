<?php

namespace OFFLINE\Boxes\Classes\Partial;

use Cms\Contracts\CmsObject;
use October\Rain\Extension\Extendable;

class ExternalPartial extends Extendable implements CmsObject
{
    protected string $content;

    protected string $fileName;

    protected string $filePath;

    protected string $fileBaseName;

    public function __toString()
    {
        return $this->getFilePath();
    }

    public static function load($hostObj, $fileName)
    {
        $partial = new self();

        $partial->fileName = pathinfo($fileName, PATHINFO_FILENAME);
        $partial->filePath = $fileName;

        if (file_exists($fileName)) {
            $partial->content = file_get_contents($fileName);
        }

        return $partial;
    }

    public static function loadCached($hostObj, $fileName)
    {
        return static::load($hostObj, $fileName);
    }

    public function getFilePath($fileName = null)
    {
        return $this->filePath;
    }

    public function getFileName()
    {
        return $this->fileName;
    }

    public function getBaseFileName()
    {
        return $this->fileBaseName;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getTwigContent()
    {
        return $this->content;
    }

    public function getTwigCacheKey()
    {
        return $this->getFilePath();
    }
}
