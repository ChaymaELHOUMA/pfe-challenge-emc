<?php

namespace App\Command;

use App\Service\MiseDeCoteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reservations:expirer', description: 'Expire les mises de côté dont le délai est dépassé')]
class ExpirerMisesDeCoteCommand extends Command
{
    public function __construct(private MiseDeCoteService $service)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $nb = $this->service->expirerDepassees();
        $io->success(sprintf('%d mise(s) de côté expirée(s).', $nb));

        return Command::SUCCESS;
    }
}
