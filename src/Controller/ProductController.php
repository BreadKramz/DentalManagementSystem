<?php

namespace App\Controller;

use App\Entity\Product;
use App\Form\ProductType;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/products')]
final class ProductController extends AbstractController
{
    #[Route('/', name: 'admin_products')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function index(ProductRepository $productRepository): Response
    {
        $products = $this->isGranted('ROLE_ADMIN') ? $productRepository->findAll() : $productRepository->findBy(['createdBy' => $this->getUser()]);

        return $this->render('admin/products/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/new', name: 'admin_products_new')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $product = new Product();
        $product->setCreatedBy($this->getUser());
        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($product);
            $entityManager->flush();

            $this->addFlash('success', 'Product created successfully.');
            return $this->redirectToRoute('admin_products', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/products/new.html.twig', [
            'product' => $product,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_products_show')]
    #[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
    public function show(Product $product): Response
    {
        return $this->render('admin/products/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_products_edit')]
    public function edit(Request $request, Product $product, EntityManagerInterface $entityManager): Response
    {
        // Staff can only edit their own products
        if ($this->isGranted('ROLE_STAFF') && $product->getCreatedBy() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You can only edit your own products.');
        }

        $form = $this->createForm(ProductType::class, $product);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Product updated successfully.');
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
        if ($this->isCsrfTokenValid('delete_product', $request->request->get('_token'))) {
            $entityManager->remove($product);
            $entityManager->flush();

            // Log the delete action
            $log = new \App\Entity\ActivityLog();
            $log->setUser($this->getUser());
            $log->setRole(implode(', ', $this->getUser()->getRoles()));
            $log->setAction('DELETE Product');
            $log->setEntityType('Product');
            $log->setEntityId($product->getId());
            $entityManager->persist($log);
            $entityManager->flush();

            $this->addFlash('success', 'Product deleted successfully.');
        }

        return $this->redirectToRoute('admin_products', [], Response::HTTP_SEE_OTHER);
    }
}
