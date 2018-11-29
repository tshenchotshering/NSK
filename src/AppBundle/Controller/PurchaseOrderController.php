<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Entity\Supplier;
use AppBundle\Entity\PurchaseOrder;
use AppBundle\Form\IndexSearchForm;
use AppBundle\Form\PurchaseOrderForm;
use Symfony\Component\Form\FormError;

/**
 * @Route("/purchaseorder")
 */
class PurchaseOrderController extends Controller
{
    use PdfControllerTrait;

    /**
     * @Route("/", name="purchaseorder_index")
     */
    public function indexAction(Request $request)
    {
        $repo = $this->getDoctrine()->getRepository(PurchaseOrder::class);

        $orders = array();

        $container = new \AppBundle\Helper\IndexSearchContainer();
        $container->user = $this->getUser();
        $container->className = PurchaseOrder::class;

        $form = $this->createForm(IndexSearchForm::class, $container);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $container->isSearchable())
        {
            $orders = $repo->findBySearchQuery($container);
        }
        else
        {
            $orders = $repo->findMine($this->getUser());
        }

        $paginator = $this->get('knp_paginator');
        $ordersPage = $paginator->paginate($orders, $request->query->getInt('page', 1), 10);

        return $this->render('AppBundle:PurchaseOrder:index.html.twig', array(
            'orders' => $ordersPage,
            'form' => $form->createView()
            ));
    }

    /**
     * @Route("/edit/{id}/{success}", name="purchaseorder_edit")
     */
    public function editAction(Request $request, $id = 0, $success = null)
    {
        $em = $this->getDoctrine()->getManager();

        /** @var \AppBundle\Repository\PurchaseOrderRepository */
        $repo = $em->getRepository('AppBundle:PurchaseOrder');

        if ($id == 0)
        {
            $order = new PurchaseOrder();
            $order->setOrderDate(new \DateTime());
        }
        else
        {
            /** @var PurchaseOrder */
            $order = $repo->find($id);
        }

        $form = $this->createForm(PurchaseOrderForm::class, $order, array('user' => $this->getUser()));

        $form->handleRequest($request);

        if (!$order->getLocation())
        {
            $order->setLocation($this->get('security.token_storage')->getToken()->getUser()->getLocation());
        }

        if ($form->isSubmitted())
        {
            if ($form->get('newOrExistingSupplier')->getData() == 'existing')
            {
                if (!$order->getSupplier())
                {
                    $form->get('supplier')->addError(new FormError('Please select existing supplier'));
                }
            }
            else
            {
                /** @var Supplier */
                $newSupplier = $form->get('newSupplier')->getData();
                $newSupplier->addPurchaseOrder($order);
                $newSupplier->setLocation($order->getLocation());
                $em->persist($newSupplier);
                $order->setSupplier($newSupplier);
            }

            if ($form->isValid())
            {
                $em->persist($order);

                try {
                    $em->flush();
                }
                catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
                    $form->get('orderNr')->addError(new FormError('Already exists'));
                    $success = false;
                }

                if ($success !== false)
                {
                    if (!$order->getOrderNr())
                    {
                        $order->setOrderNr($repo->generateOrderNr($order));
                        $em->flush();
                    }

                    return $this->redirectToRoute("purchaseorder_edit", array('id' => $order->getId(), 'success' => true));
                }
            }
            else
            {
                $success = false;
            }
        }

        return $this->render('AppBundle:PurchaseOrder:edit.html.twig', array(
                'order' => $order,
                'form' => $form->createView(),
                'success' => $success,
            ));

    }

    /**
     * @Route("/delete/{id}", name="purchaseorder_delete")
     */
    public function deleteAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('AppBundle:PurchaseOrder')->find($id);
        $em->remove($order);
        $em->flush();

        return $this->redirectToRoute('purchaseorder_index');
    }

    /**
     * @Route("/print/{id}", name="purchaseorder_print")
     */
    public function printAction($id)
    {
        $em = $this->getDoctrine()->getManager();
        $order = $em->getRepository('AppBundle:PurchaseOrder')->find($id);

        $html = $this->render('AppBundle:PurchaseOrder:print.html.twig', array('order' => $order));
        $mPdfConfiguration = ['', 'A4' ,'','',10,10,10,10,0,0,'P'];

        return $this->getPdfResponse("Nexxus purchase order", $html, $mPdfConfiguration);
    }
}
