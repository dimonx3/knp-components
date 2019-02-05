<?php

namespace Knp\Component\Pager\Event\Subscriber\Paginate\Doctrine\ODM\MongoDB;

use Doctrine\ODM\MongoDB\Query\Query;
use Knp\Component\Pager\Event\ItemsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QuerySubscriber implements EventSubscriberInterface
{
    public function items(ItemsEvent $event)
    {
        if ($event->target instanceof Query) {
            // items
            $type = $event->target->getType();
            if ($type !== Query::TYPE_FIND) {
                throw new \UnexpectedValueException('ODM query must be a FIND type query');
            }
            static $reflectionProperty;
            if (is_null($reflectionProperty)) {
                $reflectionClass = new \ReflectionClass('Doctrine\ODM\MongoDB\Query\Query');
                $reflectionProperty = $reflectionClass->getProperty('query');
                $reflectionProperty->setAccessible(true);
            }
            $queryOptions = $reflectionProperty->getValue($event->target);

            $resultQuery = clone $event->target;

            // set the count from the cursor
            $reflectionProperty->setValue($resultQuery, array_merge($queryOptions, ['type' => Query::TYPE_COUNT]));
            $event->count = $resultQuery->execute();

            $reflectionProperty->setValue($resultQuery, array_merge($queryOptions, [
                'limit' => $event->getLimit(),
                'skip' => $event->getOffset()
            ]));
            $cursor = $resultQuery->execute();

            $event->items = array();
            // iterator_to_array for GridFS results in 1 item
            foreach ($cursor as $item) {
                $event->items[] = $item;
            }
            $event->stopPropagation();
        }
    }

    public static function getSubscribedEvents()
    {
        return array(
            'knp_pager.items' => array('items', 0)
        );
    }
}
