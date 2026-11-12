<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Service\PenaliteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:declarer', description: 'Déclare un document perdu ou abîmé (perdu|abime + code-barres)')]
class DeclarerCommand extends Command
{
    public function __construct(
        private ExemplaireRepository $exemplaires,
        private PretRepository $prets,
        private PenaliteService $penalites,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('type', InputArgument::REQUIRED, 'perdu ou abime')
            ->addArgument('codeBarres', InputArgument::REQUIRED, "Code-barres de l'exemplaire");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $type = $input->getArgument('type');

        if (!in_array($type, ['perdu', 'abime'], true)) {
            $io->error('Le type doit être « perdu » ou « abime ».');

            return Command::FAILURE;
        }

        $exemplaire = $this->exemplaires->findOneBy(['codeBarres' => $input->getArgument('codeBarres')]);
        $pret = $exemplaire ? $this->prets->findEnCoursPourExemplaire($exemplaire) : null;

        if (!$pret) {
            $io->error('Aucun prêt en cours pour cet exemplaire.');

            return Command::FAILURE;
        }

        try {
            $penalite = $type === 'perdu'
                ? $this->penalites->declarerPerdu($pret)
                : $this->penalites->declarerAbime($pret);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('Document déclaré %s. Pénalité : %s MAD.', $type, $penalite->getMontant()));

        return Command::SUCCESS;
    }
}
