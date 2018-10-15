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

use AppBundle\Entity\SalesOrder;

/**
 * ProductRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class SalesOrderRepository extends \Doctrine\ORM\EntityRepository
{
    public function findAll()
    {
        return $this->findBy(array(), array('id' => 'DESC'));
    }

    /**
     * This function searches in fields: Id, Kvk, Email, Name
     */
    public function findBySearchQuery($query)
    {
        $q = $this->getEntityManager()
            ->createQuery("SELECT o FROM AppBundle:SalesOrder o WHERE o.orderNr = ?1 ORDER BY o.id DESC")
            ->setParameter(1, $query);

        return $q->getResult();
    }

    public function generateOrderNr(SalesOrder $order)
    {
        $orderNr = $order->getOrderDate()->format("Y") . sprintf('%06d', $order->getId());
        $order->setOrderNr($orderNr);
        return $orderNr;
    }
}
