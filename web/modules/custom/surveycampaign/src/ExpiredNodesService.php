<?php
namespace Drupal\surveycampaign;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
class ExpiredNodesService
{
    protected $entityTypeManager;
    public function __construct(EntityTypeManagerInterface $entity_type_manager)
    {
        $this->entityTypeManager = $entity_type_manager;
    }
    public function load()
    {
        $storage = $this->entityTypeManager->getStorage('node');
        $query = $storage->getQuery()
        ->condition('created',strtotime('-120 days'), '<')
        ->condition('type', 'job')
        ->condition('status', 1);
        $nids = $query->execute();
        return $storage->loadMultiple($nids);
    }
    
}
