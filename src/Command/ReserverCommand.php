<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Repository\AdherentRepository;
use App\Repository\EditionRepository;
use App\Service\ReservationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:reserver', description: 'Réserve une édition (id adhérent + id édition)')]
class ReserverCommand extends Command
{
    public function __construct(
        private AdherentRepository $adherents,
        private EditionRepository $editions,
        private ReservationService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('adherent', InputArgument::REQUIRED, "Id de l'adhérent")
            ->addArgument('edition', InputArgument::REQUIRED, "Id de l'édition");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adherent = $this->adherents->find($input->getArgument('adherent'));
        $edition = $this->editions->find($input->getArgument('edition'));

        if (!$adherent || !$edition) {
            $io->error('Adhérent ou édition introuvable.');

            return Command::FAILURE;
        }

        try {
            $reservation = $this->service->reserver($adherent, $edition);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Réservation enregistrée : position %d dans la file.',
            $this->service->positionDansLaFile($reservation)
        ));

        return Command::SUCCESS;
    }
}
