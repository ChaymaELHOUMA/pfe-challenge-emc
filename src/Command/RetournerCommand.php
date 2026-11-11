<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Service\RetourService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:retourner', description: "Enregistre le retour d'un document (code-barres)")]
class RetournerCommand extends Command
{
    public function __construct(
        private ExemplaireRepository $exemplaires,
        private PretRepository $prets,
        private RetourService $service,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('codeBarres', InputArgument::REQUIRED, "Code-barres de l'exemplaire");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $exemplaire = $this->exemplaires->findOneBy(['codeBarres' => $input->getArgument('codeBarres')]);
        if (!$exemplaire) {
            $io->error('Exemplaire introuvable.');
            return Command::FAILURE;
        }

        $pret = $this->prets->findEnCoursPourExemplaire($exemplaire);
        if (!$pret) {
            $io->error('Aucun prêt en cours pour cet exemplaire.');
            return Command::FAILURE;
        }

        try {
            $penalite = $this->service->retourner($pret);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }

        $io->success('Retour enregistré.');
        if ($pret->getExemplaire()->getStatut() === 'reserve') {
            $io->note('Document mis de côté pour le premier de la file de réservation.');
        }
        if ($penalite !== null) {
            $io->warning(sprintf('Pénalité de retard : %s MAD.', $penalite->getMontant()));
        }

        return Command::SUCCESS;
    }
}
