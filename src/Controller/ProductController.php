<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/products')]
#[IsGranted('ROLE_USER')]
class ProductController extends AbstractController
{
    #[Route('/', name: 'app_products_index', methods: ['GET'])]
    public function index(ProductRepository $productRepository, Request $request): Response
    {
        $search = $request->query->get('search');
        $category = $request->query->get('category');

        if ($search) {
            $products = $productRepository->search($search);
        } elseif ($category) {
            $products = $productRepository->findByCategory($category);
        } else {
            $products = $productRepository->findAll();
        }

        return $this->render('products/index.html.twig', [
            'products' => $products,
            'search' => $search,
            'category' => $category,
        ]);
    }

    #[Route('/new', name: 'app_products_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $product = new Product();

        if ($request->isMethod('POST')) {
            $product->setName($request->request->get('name'));
            $product->setSku($request->request->get('sku'));
            $product->setDescription($request->request->get('description'));
            $product->setCategory($request->request->get('category', 'product'));
            $product->setPrice($request->request->get('price', '0.00'));
            $product->setCost($request->request->get('cost'));
            $product->setUnit($request->request->get('unit'));
            $product->setIsActive($request->request->get('is_active') === '1');

            $em->persist($product);
            $em->flush();

            $this->addFlash('success', 'Товар успешно создан');

            return $this->redirectToRoute('app_products_index');
        }

        return $this->render('products/new.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}', name: 'app_products_show', methods: ['GET'])]
    public function show(Product $product): Response
    {
        return $this->render('products/show.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_products_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Product $product, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $product->setName($request->request->get('name'));
            $product->setSku($request->request->get('sku'));
            $product->setDescription($request->request->get('description'));
            $product->setCategory($request->request->get('category'));
            $product->setPrice($request->request->get('price'));
            $product->setCost($request->request->get('cost'));
            $product->setUnit($request->request->get('unit'));
            $product->setIsActive($request->request->get('is_active') === '1');
            $product->setUpdatedAt(new \DateTime());

            $em->flush();

            $this->addFlash('success', 'Товар успешно обновлён');

            return $this->redirectToRoute('app_products_show', ['id' => $product->getId()]);
        }

        return $this->render('products/edit.html.twig', [
            'product' => $product,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_products_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Product $product, EntityManagerInterface $em): Response
    {
        $em->remove($product);
        $em->flush();

        $this->addFlash('success', 'Товар успешно удалён');

        return $this->redirectToRoute('app_products_index');
    }
}
