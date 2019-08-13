<?php

declare(strict_types=1);

namespace Odiseo\SyliusMailchimpPlugin\Command;

use Odiseo\SyliusMailchimpPlugin\Handler\OrderRegisterHandlerInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Sylius\Component\Core\OrderPaymentStates;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class SyncOrdersCommand extends BaseSyncCommand
{
    /**
     * @var EntityRepository
     */
    protected $orderRepository;

    /**
     * @var OrderRegisterHandlerInterface
     */
    protected $orderRegisterHandler;

    /**
     * @param EntityRepository $orderRepository
     * @param OrderRegisterHandlerInterface $orderRegisterHandler
     */
    public function __construct(
        EntityRepository $orderRepository,
        OrderRegisterHandlerInterface $orderRegisterHandler
    ) {
        parent::__construct();

        $this->orderRepository = $orderRepository;
        $this->orderRegisterHandler = $orderRegisterHandler;
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('odiseo:mailchimp:sync-orders')
            ->setDescription('Synchronize the orders to Mailchimp.')
            ->addOption('create-only', 'c', InputOption::VALUE_NONE, 'With this option the existing carts will be not updated.')
            ->addArgument('created-since', null, 'With this option only the carts createdn since the given date will be updated')
        ;

    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->io->title('Synchronizing the orders to Mailchimp');

        $this->registerOrders($input);
    }

    /**
     * @param InputInterface $input
     */
    protected function registerOrders(InputInterface $input)
    {
        $createOnly = $input->getOption('create-only');
        $createdSince = $input->getArgument('created-since');
        $queryBuilder = $this->orderRepository->createQueryBuilder('o')
            ->andWhere('o.paymentState = :paymentState')
            ->setParameter('paymentState', OrderPaymentStates::STATE_PAID);

        if($createdSince != null){
            $createdSince = (new \DateTime($createdSince))->format('Y-m-d H:i:s');
            $queryBuilder->andWhere('o.createdAt >= :createdSince')
                ->setParameter('createdSince', $createdSince);
        }

        $orders = $queryBuilder
            ->getQuery()
            ->getResult()
        ;

        $this->io->text('Connecting ' . count($orders) . ' carts.');
        $this->io->progressStart(count($orders));

        /** @var OrderInterface $order */
        foreach ($orders as $order) {
            try {
                $response = $this->orderRegisterHandler->register($order, $createOnly);

                if (!isset($response['id']) && $response !== false) {
                    $this->showError($response);
                }
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());
            }

            $this->io->progressAdvance(1);
        }

        $this->io->progressFinish();
        $this->io->success('The orders has been synchronized successfully.');
    }
}
