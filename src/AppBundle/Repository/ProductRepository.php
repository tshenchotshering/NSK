<?php

/*
 * Nexxus Stock Keeping (online voorraad beheer software)
 * Copyright (C) 2018 Copiatek Scan & Computer Solution BV
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see licenses.
 *
 * Copiatek – info@copiatek.nl – Postbus 547 2501 CM Den Haag
 */

namespace AppBundle\Repository;

use AppBundle\Entity\AOrder;
use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use AppBundle\Entity\ProductAttributeRelation;
use AppBundle\Entity\ProductOrderRelation;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * ProductRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ProductRepository extends \Doctrine\ORM\EntityRepository
{
    public function findAll()
    {
        return $this->findBy(array(), array('id' => 'DESC'));
    }

    public function findMine(User $user)
    {
        if ($user->hasRole("ROLE_LOCAL"))
            return $this->findBy(array("location" => $user->getLocation()), array('id' => 'DESC'));
        else
            return $this->findBy(array(), array('id' => 'DESC'));
    }

    /**
     * This function searches in fields: Id, Sku, Name
     */
    public function findBySearchQuery($query)
    {
        if (is_numeric($query))
        {
            $q = $this->getEntityManager()
                ->createQuery("SELECT c FROM AppBundle:Product c WHERE c.id = ?1 OR c.sku = ?1 ORDER BY c.id DESC")->setParameter(1, $query);
        }
        else
        {
            $q = $this->getEntityManager()
                ->createQuery("SELECT c FROM AppBundle:Product c WHERE c.name LIKE ?2 OR c.sku = ?1 ORDER BY c.id DESC")->setParameter(1, $query)->setParameter(2, '%' . $query . '%');
        }

        return $q->getResult();
    }

    /**
     * This function searches in fields: Sku (of products) and orderNr (of orders)
     */
    public function findByBarcodeSearchQuery($query)
    {
        $result = array();

        foreach ($this->findBySearchQuery($query) as $product)
        {
            /** @var Product $product */
            $result["product-".$product->getId()] = sprintf("Product '%s' with SKU %s, in quantity %s, at location %s",
                $product->getName(), $product->getSku(), $product->getQuantity(), $product->getLocation()->getName());
        }

        foreach ($this->_em->getRepository(\AppBundle\Entity\SalesOrder::class)->findBySearchQuery($query) as $salesOrder)
        {
            /** @var \AppBundle\Entity\SalesOrder $salesOrder */
            $result["salesorder-".$salesOrder->getId()] = sprintf("Sales order with nr %s, dated %s, to customer %s, at location %s",
                $salesOrder->getOrderNr(), $salesOrder->getOrderDate()->format("M j, Y"), $salesOrder->getCustomer()->getName(), $salesOrder->getLocation()->getName());
        }

        foreach ($this->_em->getRepository(\AppBundle\Entity\PurchaseOrder::class)->findBySearchQuery($query) as $purchaseOrder)
        {
            /** @var \AppBundle\Entity\PurchaseOrder $purchaseOrder */
            $result["salesorder-".$purchaseOrder->getId()] = sprintf("Purchase order with nr %s, dated %s, from supplier %s, at location %s",
                $purchaseOrder->getOrderNr(), $purchaseOrder->getOrderDate()->format("M j, Y"), $purchaseOrder->getSupplier()->getName(), $purchaseOrder->getLocation()->getName());
        }

        return $result;
    }

    public function findStock(User $user)
    {
        $products = $this->findMine($user);

        $products = (new ArrayCollection($products))->filter(
            function(Product $product) {
                return $product->getQuantityInStock() > 0;
            });

        return $products;
    }

    public function generateProductAttributeRelations(Product $product)
    {
        // get all possible attributes for this product type
        $allAttributes = $product->getType()->getAttributes();

        // add new attributes to this product
        foreach ($allAttributes as $newAttribute)
        {
            $exists = $product->getAttributeRelations()->exists(function($key, $r) use ($newAttribute) {
                /** @var ProductAttributeRelation $r */
                return $r->getAttribute() == $newAttribute;
            });

            if (!$exists)
            {
                $r = new ProductAttributeRelation();
                $r->setAttribute($newAttribute);
                $r->setProduct($product);
                $this->_em->persist($r);
                $product->addAttributeRelation($r);
            }
        }
    }

    public function generateProductOrderRelation(Product $product, AOrder $order)
    {
        $exists = $product->getOrderRelations()->exists(function($key, $r) use ($order) {
            /** @var ProductOrderRelation $r */
            return $r->getOrder() == $order;
        });

        if (!$exists)
        {
            $r = new ProductOrderRelation();
            $r->setOrder($order);
            $r->setProduct($product);
            $r->setQuantity(1);
            $this->_em->persist($r);
            $product->addOrderRelation($r);
        }
    }
}
