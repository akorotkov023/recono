<?php


namespace App\Service;


use Symfony\Component\Finder\Finder;

class FinderFiles
{
    public function __construct(protected string $in){}

    public function getFiles($fileName): array|null
    {
        $res = null;
        $finder = new Finder();
        $finder->files()->in($this->in);
        if ($finder->hasResults()) {
            $finder->files()->name($fileName.'*');
            foreach ($finder as $file) {
                $res[] = $file->getFilename();
            }
        }
        return $res;
    }

    public function getPath($fileName): string|null
    {
        $res = null;
        $finder = new Finder();
        $finder->files()->in($this->in);
        if ($finder->hasResults()) {
            $finder->files()->name($fileName);
            foreach ($finder as $file) {
                $res = $file->getRealPath();
            }
        }
        return $res;
    }
}
