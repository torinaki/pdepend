<?php
class TestClass
{
    public function testMethod()
    {
        $event = new OrderManagerEvent();
        $event->setHash($hash);
        $event->setQuantity($quantity);

        /** @var OrderManagerEvent $event */
        $event = $this->dispatcher->dispatch(OrderManagerEvent::PRE_UPDATE_ITEM, $event);
        if ($event->hasErrors()) {
            $this->addErrors($event->getErrors());
            throw new AddToCartException('Error while updating product in cart');
        }

        /** @var $item \Op\CartBundle\Entity\OrderItem */
        $item = $this->getOrder()->findItemByHash($hash);
        if (!$item) {
            return;
        }
        if ($item->isBundledVariant()) {
            //we don't allow update qty on bundled item
            if ($quantity > 0) {
                return;
            }
            $this->removeItem($item);
            //TODO: move it to method get option by key
            /** @var $option OrderOption */
            foreach ($item->getOptions() as $option) {
                if ('OPBC' != $option->getName()) {
                    continue;
                }
                $variants = array();
                $parent = $this->getOrder()->findBundleParentByChildId($option->getValue());
                $variants[] = $parent->getVariantId();
                $leftItems = $this->getOrder()->findBundleByParent($parent);

                if (count($leftItems) <= 0 && $parent == null) {
                    continue;
                }
                foreach ($leftItems as $bundleItem) {
                    $variants[] = $bundleItem->getVariantId();
                }
                asort($variants);
                $glue = implode('-', $variants);
                //Before update we should make sure that there is no item with this key
                $possibleParent = $this->getOrder()->findBundleParentByChildId($glue);
                //if we have no bundle like that, then go ahead and update new key
                if ($possibleParent == null) {
                    //check if there is only parent item left, then remove its OPBP
                    if (count($leftItems) == 0) {
                        $parent->removeOption('OPBP');
                    } else {
                        $parent->setOption('OPBP', $glue);
                        foreach ($leftItems as $bundleItem) {
                            $bundleItem->setOption('OPBC', $glue);
                        }
                    }
                } else {
                    //Update old bundle quantity and remove current
                    $this->oldBundleQuantity[$possibleParent->getHash()] = $parent->getQuantity();
                    $this->removeItem($parent);
                    foreach ($leftItems as $bundleItem) {
                        $this->removeItem($bundleItem);
                    }
                    $this->save();
                    //Here we need to update possible parent with new quantity in oldBundleQuantity we
                    //save quantity so updateItem will pick up this value and incerement current
                    //quantity
                    $this->updateItem($possibleParent->getHash(), $possibleParent->getQuantity());
                }
            }
            return;
        }
        if (isset($this->oldBundleQuantity[$hash])) {
            $quantity += $this->oldBundleQuantity[$hash];
        }
        $item->setQuantity($quantity);
        $bundles = $this->getOrder()->findBundleByParent($item);
        foreach ($bundles as $bundle) {
            $bundle->setQuantity($quantity);
        }
        $this->removeZeroQtyItems();
        $this->save();

        $event = new OrderManagerEvent();
        $event->setOrderItem($item);

        $this->dispatcher->dispatch(OrderManagerEvent::AFTER_UPDATE_ITEM, $event);
    }
}