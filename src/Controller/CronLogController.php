<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CronLogController extends AbstractController
{
    #[Route('/cron/logs', name: 'app_cron_logs')]
    #[IsGranted('ROLE_ADMIN')]
    public function logs(
        #[Autowire('%kernel.project_dir%')] string $projectDir
    ): Response
    {
        $dir = $projectDir . '/var/cron';
        $files = [];

        if (is_dir($dir)) {
            foreach (glob($dir . '/*.log') ?: [] as $file) {
                $files[] = [
                    'name' => basename($file),
                    'mtime' => filemtime($file) ?: 0,
                    'lines' => $this->tail($file, 50),
                ];
            }
        }

        usort($files, static fn (array $a, array $b) => $a['name'] <=> $b['name']);

        return $this->render('cron/logs.html.twig', [
            'files' => $files,
            'dir' => $dir,
        ]);
    }

    /**
     * Последние $lines строк файла без загрузки всего файла в память.
     *
     * @return list<string>
     */
    private function tail(string $file, int $lines, int $chunk = 65536): array
    {
        $size = @filesize($file);
        if ($size === false || $size === 0) {
            return [];
        }

        $offset = max(0, $size - $chunk);
        $data = @file_get_contents($file, false, null, $offset);
        if ($data === false) {
            return [];
        }

        $dataLines = explode("\n", rtrim($data, "\n"));

        if ($offset > 0) {
            array_shift($dataLines);
        }

        return array_values(array_slice($dataLines, -$lines));
    }
}
