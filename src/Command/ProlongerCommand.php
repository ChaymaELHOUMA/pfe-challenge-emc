<?php

namespace App\Command;

use App\Exception\RegleMetierException;
use App\Repository\ExemplaireRepository;
use App\Repository\PretRepository;
use App\Service\ProlongationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:prolonger', description: "Prolonge le prêt en cours d'un exemplaire (code-barres)")]
class ProlongerCommand extends Command
{
    public function __construct(
        private ExemplaireRepository $exemplaires,
        private PretRepository $prets,
        private ProlongationService $service,
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
        $pret = $exemplaire ? $this->prets->findEnCoursPourExemplaire($exemplaire) : null;

        if (!$pret) {
            $io->error('Aucun prêt en cours pour cet exemplaire.');

            return Command::FAILURE;
        }

        try {
            $this->service->prolonger($pret);
        } catch (RegleMetierException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf(
            'Prêt prolongé (%d prolongation(s)), à rendre le %s.',
            $pret->getNbProlongations(),
            $pret->getDateEcheance()->format('d/m/Y')
        ));

        return Command::SUCCESS;
    }
}
