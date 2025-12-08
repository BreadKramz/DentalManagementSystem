<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/products')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'admin_products', methods: ['GET'])]
    public function index(ProductRepository $productRepository): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Access denied. Admin or Staff role required.');
        }

        $products = $this->isGranted('ROLE_ADMIN') ? $productRepository->findAll() : $productRepository->findBy(['createdBy' => $this->getUser()]);

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'admin_products_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Access denied. Admin or Staff role required.');
        }

        $product = new Product();
        $product->setCreatedBy($this->getUser());
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            // Log the create action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('CREATE Product');
            $log->setEntityType('Product');
            $log->setEntityId($product->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('admin_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/products/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_products_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Access denied. Admin or Staff role required.');
        }

        return $this->render('admin/products/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_products_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Access denied. Admin or Staff role required.');
        }

        // Staff can only edit their own products
        if ($this->isGranted('ROLE_STAFF') && $product->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only edit your own products.');
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Log the update action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('UPDATE Product');
            $log->setEntityType('Product');
            $log->setEntityId($product->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            return $this->redirectToRoute('admin_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/products/edit.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_products_delete', methods: ['POST'])]
    public function delete(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            throw $this->createAccessDeniedException('Access denied. Admin or Staff role required.');
        }

        // Staff can only delete their own products
        if ($this->isGranted('ROLE_STAFF') && $product->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only delete your own products.');
        }

        if ($this->isCsrfTokenValid('delete'.$product->getId(), $request->request->get('_token'))) {
            // Log the delete action before removing
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('DELETE Product');
            $log->setEntityType('Product');
            $log->setEntityId($product->getId());
            $entityManager->persist($log);

            $entityManager->remove($product);
            $entityManager->flush();
        }

        return $this->redirectToRoute('admin_products', [], Response::HTTP_SEE_OTHER);
    }
}
