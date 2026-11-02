<?php

namespace App\Controller;

use App\Form\SearchToolsType;
use App\Form\TwoRegistryToolsType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/*
 * Сверяет два файла и показывает расхождения
 */
class TwoRegistryController extends AbstractController
{
    #[Route('/two/registry', name: 'app_two_registry')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(TwoRegistryToolsType::class, []);
        $form->handleRequest($request);

        $onlyInFile1 = [];
        $onlyInFile2 = [];
        $reg = null;
        $base = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile1 = $form['file1']->getData();
            $uploadedFile2 = $form['file2']->getData();

            if ($uploadedFile1 && $uploadedFile2) {

                $reg = $uploadedFile1->getClientOriginalName();
                $base = $uploadedFile2->getClientOriginalName();

                $ext1 = strtolower($uploadedFile1->getClientOriginalExtension());
                $ext2 = strtolower($uploadedFile2->getClientOriginalExtension());

                if ($ext1 !== 'csv' || $ext2 !== 'csv') {
                    return $this->redirectToRoute('app_two_registry');
                }

                $file1 = file($uploadedFile1->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $file2 = file($uploadedFile2->getPathname(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

                $file1 = array_map('trim', $file1);
                $file2 = array_map('trim', $file2);

                $file1 = array_unique($file1);
                $file2 = array_unique($file2);

                $onlyInFile1 = array_diff($file1, $file2);
                $onlyInFile2 = array_diff($file2, $file1);
            }
        }

        return $this->render('two_registry/index.html.twig', [
            'upload' => $form->createView(),
            'onlyInFile1' => $onlyInFile1,
            'onlyInFile2' => $onlyInFile2,
            'reg' => $reg,
            'base' => $base,
        ]);
    }
}
