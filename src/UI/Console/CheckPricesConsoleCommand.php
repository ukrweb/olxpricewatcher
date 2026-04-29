<?php

declare(strict_types=1);

namespace App\UI\Console;

use App\Application\PriceTracking\CheckPricesCommand;
use App\Application\PriceTracking\CheckPricesHandler;
use DateMalformedStringException;
use Random\RandomException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:check-prices', description: 'Checks due OLX listings and sends price-change notifications.')]
final class CheckPricesConsoleCommand extends Command
{
    public function __construct(private readonly CheckPricesHandler $handler)
    {
        parent::__construct();
    }

    /**
     * @throws DateMalformedStringException
     * @throws RandomException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $processed = ($this->handler)(new CheckPricesCommand());
        $output->writeln(sprintf('Processed %d listing(s).', $processed));

        return Command::SUCCESS;
    }
}
